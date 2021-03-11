<?php

namespace PR\DHL\REST_API\DHL_eCS_US;

use Exception;
use PR\DHL\REST_API\API_Client;
use PR\DHL\REST_API\Interfaces\API_Auth_Interface;
use PR\DHL\REST_API\Interfaces\API_Driver_Interface;
use PR\DHL\Utils\Args_Parser;
use stdClass;

/**
 * The API client for DHL eCS.
 *
 * @since [*next-version*]
 */
class Client extends API_Client {

	/**
	 * The api auth.
	 *
	 * @since [*next-version*]
	 *
	 * @var API_Auth_Interface
	 */
	protected $auth;

	/**
	 * The pickup id.
	 *
	 * @since [*next-version*]
	 *
	 * @var String
	 */
	protected $pickup_id;

	/**
	 * The default weight unit of measure.
	 *
	 * @since [*next-version*]
	 *
	 * @var array
	 */
	protected $weight_uom;

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 *
	 * @param string $contact_name The contact name to use for creating orders.
	 */
	public function __construct( $pickup_id, $base_url, API_Driver_Interface $driver, API_Auth_Interface $auth = null ) {
		parent::__construct( $base_url, $driver, $auth );

		$this->auth 		= $auth;
		$this->pickup_id 	= $pickup_id;
		$this->weight_uom 	= get_option('woocommerce_weight_unit');
	}

	/**
	 * Create shipping label
	 *
	 * @since [*next-version*]
	 *
	 * @param int $order_id The order id.
	 *
	 */
	public function create_label( Item_Info $item_info ){

		$route 		= $this->create_label_route( $item_info->shipment['label_format'] );
		$data 		= $this->item_info_to_request_data( $item_info );
		
		//error_log( 'creat_label' );
		//error_log( print_r( $data, true ) );

		$response 			= $this->post($route, $data, $this->header_request() );

		//error_log( 'after post' );
		//error_log( print_r( $response, true ) );

		if ( $response->status === 200 ) {
			
			$decoded_response = json_decode(json_encode($response->body), true );
			return $this->get_label_content( $decoded_response );

		}

		throw new Exception(
			sprintf(
				__( 'Failed to create label: %s', 'pr-shipping-dhl' ),
				$this->generate_error_details( $response )
			)
		);
	}

	/**
	 * Retrieves the label for a DHL, by its barcode.
	 *
	 * @param string $item_barcode The barcode of the item whose label to retrieve.
	 *
	 * @return string The raw PDF data for the item's label.
	 *
	 * @throws Exception
	 */
	public function get_label( $package_id ){

		$route 		= $this->get_label_route();
		$data 		= array( 'packageId' => $package_id );

		$response 			= $this->get($route, $data, $this->header_request( false ) );
		//error_log( 'Client get_label' );
		//error_log( print_r( $response, true ) );
		if ( $response->status === 200 ) {

			$decoded_response 	= json_decode( $response->body, true );
			return $this->get_label_content( $decoded_response );

		}

		throw new Exception(
			sprintf(
				__( 'Failed to create label: %s', 'pr-shipping-dhl' ),
				$this->generate_error_details( $response )
			)
		);
	}

	public function get_label_content( $response ){

		if( !isset( $response['labels'] ) ){
			throw new Exception( __( 'Label contents are not exist!', 'pr-shipping-dhl' ) );
		}

		foreach( $response['labels'] as $label ){
			if( !isset( $label['labelData'] ) ){
				throw new Exception( __( 'Label data is not exist!', 'pr-shipping-dhl' ) );
			}

			if( !isset( $label['packageId'] ) ){
				throw new Exception( __( 'Package ID is not exist!', 'pr-shipping-dhl' ) );
			}

			if( !isset( $label['dhlPackageId'] ) ){
				throw new Exception( __( 'DHL Package ID is not exist!', 'pr-shipping-dhl' ) );
			}

			return $label;
		}

	}

	public function generate_error_details( $response ){

		$error_exception 	= '';
		$error_details 		= '';

		if( isset( $response->body->title ) ){
			$error_exception .= $response->body->title . '<br />';
		}

		if( isset( $response->body->type ) ){
			$error_exception .= '<span>' . esc_html__( 'type', 'pr-dhl-woocommerce' ) . ' : ' . $response->body->type . '</span><br />';
		}
		
		if( isset( $response->body->invalidParams ) && is_array( $response->body->invalidParams ) ){

			foreach( $response->body->invalidParams as $key => $data ){
					
				$detail_string = '';
				$decoded_error = json_decode(json_encode($data), true );

				foreach( $decoded_error as $detail_key => $detail ){

					$detail_string .= '<strong>' . $detail_key . '</strong> : ' . $detail . '<br />';
					
				}

				$error_details .= '<span class="details">' . $detail_string . '</span>';
			}

		}
		
		if( !empty( $error_details ) ){
			$error_exception .= '<br />';
			$error_exception .= '<strong>' . __('Error details:', 'pr-dhl-woocommerce') . '</strong><br />';
			$error_exception .= $error_details;

		}
		
		return $error_exception;
	}

	/**
     * Get message version.
     *
     * @return string The version of the message.
     */
    protected function get_package_id( $prefix, $id ){
        if ( empty( $prefix ) ) {
            $shipment_parts = array( sprintf('%07d', $id ), time() );
        } else {
            $shipment_parts = array( $prefix, sprintf('%07d', $id ), time() );
        }

        return implode('-', $shipment_parts);
    }

	/**
	 * Transforms an item info object into a request data array.
	 *
	 * @param Item_Info $item_info The item info object to transform.
	 *
	 * @return array The request data for the given item info object.
	 */
	protected function item_info_to_request_data( Item_Info $item_info ) {

		$package_id = $this->get_package_id( $item_info->shipment['prefix'], $item_info->shipment['order_id'] );

		$request_data = array(
			'pickup' 				=> $this->pickup_id,
			'distributionCenter'	=> $item_info->shipment['distribution_center'],
			'orderedProductId' 		=> $item_info->shipment['product_code'],
			'consigneeAddress' 		=> $item_info->consignee,
			'returnAddress' 		=> $item_info->return,
			'packageDetail' 		=> array(
				'packageId' 	=> $package_id,
				'packageDescription' => $item_info->shipment['description'],
				'weight' 		=> array(
					'value' 		=> $item_info->shipment['weight'],
					'unitOfMeasure'	=> $item_info->shipment['weightUom'],
				),
				'billingReference1' => $item_info->shipment['billing_ref'],
				'shippingCost' 			=> array(
					'currency' 		=> $item_info->shipment['currency'],
					'dutiesPaid'	=> $item_info->shipment['duties']
				),
			),
		);

		if( $item_info->isCrossBorder ){

			$contents 			= $item_info->contents;
			$shipment_contents 	= array();

			foreach( $contents as $content ){

				$shipment_content = array(
					'skuNumber' 			=> $content['sku'],
					'itemDescription'		=> $content['description'],
					'itemValue' 			=> $content['value'],
					'packagedQuantity' 		=> $content['qty'],
					'countryOfOrigin' 		=> $content['origin'],
					'currency' 				=> $item_info->shipment['currency'],
				);

				if( !empty( $content['hs_code'] ) ){
					$shipment_content['hsCode'] = $content['hs_code'];
				}

				$shipment_contents[] = $shipment_content;

			}

			$request_data['customsDetails'] = $shipment_contents;
			$request_data['packageDetail']['contentCategory'] = $item_info->shipment['content_category'];

		}

		return Args_Parser::unset_empty_values( $request_data );
	}

	/**
	 * Create manifest
	 *
	 * @since [*next-version*]
	 *
	 * @param int $order_id The order id.
	 *
	 */
	public function create_manifest( $package_ids ){

		$route 		= $this->create_manifest_route();
		$data 		= array(
			'pickup' 		=> $this->pickup_id,
			'manifests' 	=> array(
				array( 
					'packageIds' => $package_ids
				)
			)
		);

		$response 			= $this->post($route, $data, $this->header_request() );
		
		if ( $response->status === 200 ) {
			
			if( isset( $response->body->requestId ) ){

				update_option( 'pr_dhl_ecs_us_manifest', $response->body->requestId );
				return $response->body->requestId;

			}else{
				throw new Exception( __( 'DHL Manifest Request ID is not exist!', 'pr-shipping-dhl' ) );
			}

		}

		throw new Exception(
			sprintf(
				__( 'Failed to create manifest: %s', 'pr-shipping-dhl' ),
				$this->generate_error_details( $response )
			)
		);
	}

	public function download_manifest(){

		if( !$this->get_manifest() || empty( $this->get_manifest() ) ){
			throw new Exception( __( 'Manifest request id is empty!', 'pr-shipping-dhl' ) );
		}
		error_log( 'check manifest request id');
		error_log( $this->get_manifest() );
		$route 		= $this->get_manifest_route( $this->get_manifest() );
		//$route = $this->get_manifest_route( '492bb5c3-3689-4148-bbad-2b6544d79364' );
		//https://api-sandbox.dhlecs.com/shipping/v4/manifest/5351244/6e6d89d0-2507-4262-8de9-c1bf3aa9ce01
		//https://api-sandbox.dhlecs.com/shipping/v4/manifest/5351244/5ada27f6-d920-4254-a631-cedc2e437fd5
		$response 			= $this->get($route, array(), $this->header_request() );
		
		if ( $response->status === 200 ) {
			
			$decoded_response = json_decode(json_encode($response->body), true );
			return $this->get_manifest_content( $decoded_response );

		}

		throw new Exception(
			sprintf(
				__( 'Failed to create manifest: %s', 'pr-shipping-dhl' ),
				$this->generate_error_details( $response )
			)
		);
	}

	public function get_manifest_content( $response ){

		if( !isset( $response['status'] ) ){
			throw new Exception( __( 'Status is not exist!', 'pr-shipping-dhl' ) );
		}

		if( $response['status'] != 'COMPLETED' ){
			throw new Exception( sprintf( __( 'Status is : %s', 'pr-shipping-dhl' ), $response['status'] ) );
		}

		if( !isset( $response['manifests'] ) ){
			throw new Exception( __( 'Manifest contents are not exist!', 'pr-shipping-dhl' ) );
		}

		foreach( $response['manifests'] as $manifest ){
			if( !isset( $manifest['manifestData'] ) ){
				throw new Exception( __( 'Manifest data is not exist!', 'pr-shipping-dhl' ) );
			}

			if( !isset( $manifest['manifestId'] ) ){
				throw new Exception( __( 'Manifest ID is not exist!', 'pr-shipping-dhl' ) );
			}

			if( !isset( $manifest['format'] ) ){
				throw new Exception( __( 'Manifest format is not exist!', 'pr-shipping-dhl' ) );
			}

		}

		return $response['manifests'];

	}

	public function header_request( $content_type = true ){

		$headers 	= array(
			'Accept' 		=> 'application/json',
		);

		if( $content_type == true ){
			$headers['Content-Type'] = 'application/json';
		}
		return $headers;
	}

	/**
	 * Add manifest.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $item_barcode The barcode of the item to add.
	 * @param string $wc_order The ID of the WooCommerce order.
	 */
	public function get_manifest(){

		return get_option( 'pr_dhl_ecs_us_manifest' );
	}

	/**
	 * Resets the current shipping label.
	 *
	 * @since [*next-version*]
	 */
	public function reset_current_shipping_label(){

		update_option( 'pr_dhl_ecs_us_label', $this->get_default_label_info() );

	}

	/**
	 * Prepares an API route with the customer namespace and EKP.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $route The route to prepare.
	 *
	 * @return string
	 */
	protected function create_label_route( $format ) {
		return sprintf( 'shipping/v4/label?format=%s', $format );
	}

	/**
	 * Prepares an API route with the package id and pickup id.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $route The route to prepare.
	 *
	 * @return string
	 */
	protected function get_label_route() {
		return sprintf( 'shipping/v4/label/%s', $this->pickup_id );
	}

	/**
	 * Prepares a manifest API route.
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 */
	protected function create_manifest_route() {
		return 'shipping/v4/manifest';
	}

	/**
	 * Prepares a manifest API route.
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 */
	public function get_manifest_route( $request_id ) {
		return sprintf( $this->create_manifest_route() . '/%s/%s', $this->pickup_id, $request_id );
	}

}
