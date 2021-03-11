<?php

use PR\DHL\REST_API\DHL_eCS_US\Auth;
use PR\DHL\REST_API\DHL_eCS_US\Client;
use PR\DHL\REST_API\DHL_eCS_US\Item_Info;
use PR\DHL\REST_API\Drivers\JSON_API_Driver;
use PR\DHL\REST_API\Drivers\Logging_Driver;
use PR\DHL\REST_API\Drivers\WP_API_Driver;
use PR\DHL\REST_API\Interfaces\API_Auth_Interface;
use PR\DHL\REST_API\Interfaces\API_Driver_Interface;

// Exit if accessed directly or class already exists
if ( ! defined( 'ABSPATH' ) || class_exists( 'PR_DHL_API_eCS_US', false ) ) {
	return;
}

class PR_DHL_API_eCS_US extends PR_DHL_API {
	/**
	 * The URL to the API.
	 *
	 * @since [*next-version*]
	 */
	const API_URL_PRODUCTION = 'https://api.dhlecs.com/';

	/**
	 * The URL to the sandbox API.
	 *
	 * @since [*next-version*]
	 */
	const API_URL_SANDBOX = 'https://api-sandbox.dhlecs.com/';

	/**
	 * The transient name where the API access token is stored.
	 *
	 * @since [*next-version*]
	 */
	const ACCESS_TOKEN_TRANSIENT = 'pr_dhl_ecs_us_access_token';

	/**
	 * The API driver instance.
	 *
	 * @since [*next-version*]
	 *
	 * @var API_Driver_Interface
	 */
	public $api_driver;
	/**
	 * The API authorization instance.
	 *
	 * @since [*next-version*]
	 *
	 * @var Auth
	 */
	public $api_auth;
	/**
	 * The API client instance.
	 *
	 * @since [*next-version*]
	 *
	 * @var Client
	 */
	public $api_client;

	/**
	 * Constructor.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $country_code The country code.
	 *
	 * @throws Exception If an error occurred while creating the API driver, auth or client.
	 */
	public function __construct( $country_code ) {
		$this->country_code = $country_code;

		try {
			$this->api_driver = $this->create_api_driver();
			$this->api_auth = $this->create_api_auth();
			$this->api_client = $this->create_api_client();
		} catch ( Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Initializes the API client instance.
	 *
	 * @since [*next-version*]
	 *
	 * @return Client
	 *
	 * @throws Exception If failed to create the API client.
	 */
	protected function create_api_client() {
		// Create the API client, using this instance's driver and auth objects
		return new Client(
			$this->get_pickup_id(),
			$this->get_api_url(),
			$this->api_driver,
			$this->api_auth
		);
	}

	/**
	 * Initializes the API driver instance.
	 *
	 * @since [*next-version*]
	 *
	 * @return API_Driver_Interface
	 *
	 * @throws Exception If failed to create the API driver.
	 */
	protected function create_api_driver() {
		// Use a standard WordPress-driven API driver to send requests using WordPress' functions
		$driver = new WP_API_Driver();

		// This will log requests given to the original driver and log responses returned from it
		$driver = new Logging_Driver( PR_DHL(), $driver );

		// This will prepare requests given to the previous driver for JSON content
		// and parse responses returned from it as JSON.
		$driver = new JSON_API_Driver( $driver );

		//, decorated using the JSON driver decorator class
		return $driver;
	}

	/**
	 * Initializes the API auth instance.
	 *
	 * @since [*next-version*]
	 *
	 * @return API_Auth_Interface
	 *
	 * @throws Exception If failed to create the API auth.
	 */
	protected function create_api_auth() {
		// Get the saved DHL customer API credentials
		list( $client_id, $client_secret ) = $this->get_api_creds();
		
		// Create the auth object using this instance's API driver and URL
		return new Auth(
			$this->api_driver,
			$this->get_api_url(),
			$client_id,
			$client_secret,
			static::ACCESS_TOKEN_TRANSIENT
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function is_dhl_ecs_us() {
		return true;
	}

	/**
	 * Retrieves the API URL.
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 *
	 * @throws Exception If failed to determine if using the sandbox API or not.
	 */
	public function get_api_url() {
		$is_sandbox = $this->get_setting( 'dhl_sandbox' );
		$is_sandbox = filter_var($is_sandbox, FILTER_VALIDATE_BOOLEAN);
		$api_url = ( $is_sandbox ) ? static::API_URL_SANDBOX : static::API_URL_PRODUCTION;

		return $api_url;
	}

	/**
	 * Retrieves the API credentials.
	 *
	 * @since [*next-version*]
	 *
	 * @return array The client ID and client secret.
	 *
	 * @throws Exception If failed to retrieve the API credentials.
	 */
	public function get_api_creds() {
		return array(
			$this->get_setting( 'dhl_api_key' ),
			$this->get_setting( 'dhl_api_secret' ),
		);
	}

	/**
	 * Retrieves the DHL Pickup Account ID
	 *
	 * @since [*next-version*]
	 *
	 * @return string
	 *
	 * @throws Exception If failed to retrieve the EKP from the settings.
	 */
	public function get_pickup_id() {
		return $this->get_setting( 'dhl_pickup_id' );
	}

	/**
	 * Retrieves a single setting.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $key     The key of the setting to retrieve.
	 * @param string $default The value to return if the setting is not saved.
	 *
	 * @return mixed The setting value.
	 */
	public function get_setting( $key, $default = '' ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Retrieves all of the Deutsche Post settings.
	 *
	 * @since [*next-version*]
	 *
	 * @return array An associative array of the settings keys mapping to their values.
	 */
	public function get_settings() {
		return get_option( 'woocommerce_pr_dhl_ecs_us_settings', array() );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function dhl_test_connection( $client_id, $client_secret ) {
		try {
			// Test the given ID and secret
			$token = $this->api_auth->test_connection( $client_id, $client_secret );
			// Save the token if successful
			$this->api_auth->save_token( $token );
			
			return $token;
		} catch ( Exception $e ) {
			$this->api_auth->save_token( null );
			throw $e;
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function dhl_reset_connection() {
		return $this->api_auth->revoke();
	}

	public function get_dhl_duties() {
        $duties = array(
            'DDU' => __('Duties Consignee Paid', 'pr-shipping-dhl'),
            'DDP' => __('Duties Shipper Paid', 'pr-shipping-dhl')
        );
        return $duties;
	}
	
	public function get_dhl_content_indicator() {

		return array(
			'01' => __('Lithium Metal / Alloy Batteries', 'pr-shipping-dhl' ),
			'04' => __('Lithium-ion or Lithium Polymer Batteries', 'pr-shipping-dhl' ),
			'40' => __('Limited quantities', 'pr-shipping-dhl' ),
		);
		/*
		return array(
			'01' => __('Primary Contained in Equipment', 'pr-shipping-dhl' ),
			'02' => __('Primary Packed with Equipment', 'pr-shipping-dhl' ),
			'03' => __('Primary Stand-Alone', 'pr-shipping-dhl' ),
			'04' => __('Secondary Contained in Equipment', 'pr-shipping-dhl' ),
			'05' => __('Secondary Packed with Equipment', 'pr-shipping-dhl' ),
			'06' => __('Secondary Stand-Alone', 'pr-shipping-dhl' ),
			'08' => __('ORM-D', 'pr-shipping-dhl' ),
			'09' => __('Small Quantity Provision', 'pr-shipping-dhl' ),
		);
		*/
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function get_dhl_products_international() {
		return array(
			'PLT' => __( 'DHL Parcel International Direct', 'pr-shipping-dhl' ),
			'PLY' => __( 'DHL Parcel International Standard', 'pr-shipping-dhl' ),
			'PKY' => __( 'DHL Packet International', 'pr-shipping-dhl' )
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function get_dhl_products_domestic() {
		return array(
			'EXP' => __( 'DHL Parcel Expedited', 'pr-shipping-dhl' ),
			'MAX' => __( 'DHL Parcel Expedited Max', 'pr-shipping-dhl' ),
			'GND' => __( 'DHL Parcel Ground', 'pr-shipping-dhl' ),
			'BEX' => __( 'DHL BPM Expedited', 'pr-shipping-dhl' ),
			'BGN' => __( 'DHL BPM Ground', 'pr-shipping-dhl' ),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function get_dhl_label( $args ) {

		$order_id = isset( $args[ 'order_details' ][ 'order_id' ] )
			? $args[ 'order_details' ][ 'order_id' ]
			: null;

		$uom 				= get_option( 'woocommerce_weight_unit' );
		$is_cross_border 	= PR_DHL()->is_crossborder_shipment( $args['shipping_address']['country'] );
		try {
			$item_info = new Item_Info( $args, $uom, $is_cross_border );
		} catch (Exception $e) {
			throw $e;
		}

		// Create the shipping label
		$label_format 		= $args['dhl_settings']['label_format'];
		$label_response 	= $this->api_client->create_label( $item_info );
		$label_data 		= ( $label_format == 'ZPL' )? $label_response['labelData'] : base64_decode( $label_response['labelData'] );

		$item_file_info 	= $this->save_dhl_label_file( 'item', $label_response['packageId'], $label_data );

		// Save it in the order
		update_post_meta( $order_id, 'pr_dhl_ecsus_dhl_package_id', $label_response['dhlPackageId'] );
		update_post_meta( $order_id, 'pr_dhl_ecsus_package_id', $label_response['packageId'] );
		
		//$this->save_dhl_label_file( 'item', $item_barcode, $label_pdf_data );
		
		// For domestic add "trackingId" otherwise international is "packageId"
		$tracking_id = isset( $label_response['trackingId'] ) ? $label_response['trackingId'] : $label_response['packageId'];

		return array(
			'label_path' 			=> $item_file_info->path,
			'label_url' 			=> $item_file_info->url,
			'tracking_number' 		=> $tracking_id,
			'dhl_package_id' 		=> $label_response['dhlPackageId']
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function delete_dhl_label( $label_info ) {
		if ( ! isset( $label_info['label_path'] ) ) {
			throw new Exception( __( 'DHL Label has no path!', 'pr-shipping-dhl' ) );
		}

		$label_path = $label_info['label_path'];

		if ( file_exists( $label_path ) ) {
			$res = unlink( $label_path );

			if ( ! $res ) {
				throw new Exception( __( 'DHL Label could not be deleted!', 'pr-shipping-dhl' ) );
			}
		}
	}

	public function create_dhl_manifest( $package_ids ){

		$request_id = $this->api_client->create_manifest( $package_ids );

		return $request_id;
	}

	public function download_dhl_manifest(){

		$manifests = $this->api_client->download_manifest();
		
		$file_infos = array();

		foreach( $manifests as $manifest ){
			$data 							= base64_decode( $manifest['manifestData'] );
			$item_file_info 				= $this->save_dhl_label_file( 'manifest', $manifest['manifestId'], $data );
			$file_infos[ $manifest['manifestId'] ] 	= $item_file_info;
		}

		return $file_infos;
	}

	/**
	 * Retrieves the filename for DHL item label files.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $barcode The DHL item barcode.
	 * @param string $format The file format.
	 *
	 * @return string
	 */
	public function get_dhl_item_label_file_name( $barcode, $format = 'pdf' ) {
		return sprintf('dhl-label-%s.%s', $barcode, $format);
	}

	/**
	 * Retrieves the filename for DHL manifest label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $manifest_id The DHL manifest ID.
	 * @param string $format The file format.
	 *
	 * @return string
	 */
	public function get_dhl_manifest_label_file_name( $manifest_id, $format = 'pdf' ) {
		return sprintf('dhl-manifest-%s.%s', $manifest_id, $format);
	}

	/**
	 * Retrieves the file info for a DHL item label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $barcode The DHL item barcode.
	 * @param string $format The file format.
	 *
	 * @return object An object containing the file "path" and "url" strings.
	 */
	public function get_dhl_item_label_file_info( $barcode, $format = 'pdf' ) {
		$file_name = $this->get_dhl_item_label_file_name($barcode, $format);

		return (object) array(
			'path' => PR_DHL()->get_dhl_label_folder_dir() . $file_name,
			'url' => PR_DHL()->get_dhl_label_folder_url() . $file_name,
		);
	}

	/**
	 * Retrieves the file info for DHL manifest label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $order_id The DHL order ID.
	 * @param string $format The file format.
	 *
	 * @return object An object containing the file "path" and "url" strings.
	 */
	public function get_dhl_manifest_label_file_info( $manifest_id, $format = 'pdf') {
		$file_name = $this->get_dhl_manifest_label_file_name( $manifest_id, $format);

		return (object) array(
			'path' => PR_DHL()->get_dhl_label_folder_dir() . $file_name,
			'url' => PR_DHL()->get_dhl_label_folder_url() . $file_name,
		);
	}

	/**
	 * Retrieves the file info for any DHL label file, based on type.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $type The label type: "item" or "order".
	 * @param string $key The key: barcode for type "item", and order ID for type "order".
	 *
	 * @return object An object containing the file "path" and "url" strings.
	 */
	public function get_dhl_label_file_info( $type, $key ) {

		if( $type == 'manifest' ){
			return $this->get_dhl_manifest_label_file_info( $key, 'pdf' );
		}

		$label_format = strtolower( $this->get_setting( 'dhl_label_format' ) );
		// Return info for "item" type
		return $this->get_dhl_item_label_file_info( $key, $label_format );
	}

	/**
	 * Saves an item label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $type The label type: "item", or "manifest".
	 * @param string $key The key: barcode for type "item", and order ID for type "manifest".
	 * @param string $data The label file data.
	 *
	 * @return object The info for the saved label file, containing the "path" and "url".
	 *
	 * @throws Exception If failed to save the label file.
	 */
	public function save_dhl_label_file( $type, $key, $data ) {
		// Get the file info based on type
		$file_info = $this->get_dhl_label_file_info( $type, $key );

		if ( validate_file( $file_info->path ) > 0 ) {
			throw new Exception( __( 'Invalid file path!', 'pr-shipping-dhl' ) );
		}

		$file_ret = file_put_contents( $file_info->path, $data );

		if ( empty( $file_ret ) ) {
			throw new Exception( __( 'DHL label file cannot be saved!', 'pr-shipping-dhl' ) );
		}

		return $file_info;
	}

	/**
	 * Deletes an AWB label file.
	 *
	 * @since [*next-version*]
	 *
	 * @param string $type The label type: "item", "awb" or "order".
	 * @param string $key The key: barcode for type "item", AWB for type "awb" and order ID for type "order".
	 *
	 * @throws Exception If the file could not be deleted.
	 */
	public function delete_dhl_label_file( $type, $key )
	{
		// Get the file info based on type
		$file_info = $this->get_dhl_label_file_info( $type, $key );

		// Do nothing if file does not exist
		if ( ! file_exists( $file_info->path ) ) {
			return;
		}

		// Attempt to delete the file
		$res = unlink( $file_info->path );

		// Throw error if the file could not be deleted
		if (!$res) {
			throw new Exception(__('DHL AWB Label could not be deleted!', 'pr-shipping-dhl'));
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since [*next-version*]
	 */
	public function dhl_validate_field( $key, $value ) {
	}

	/**
	 * Finalizes and creates the current Deutsche Post order.
	 *
	 * @since [*next-version*]
	 *
	 * @return string The ID of the created DHL order.
	 *
	 * @throws Exception If an error occurred while and the API failed to create the order.
	 */
	public function create_order()
	{
		// Create the DHL order
		$response = $this->api_client->create_order();

		$this->get_settings();

		// Get the current DHL order - the one that was just submitted
		$order = $this->api_client->get_order($response->orderId);
		$order_items = $order['items'];

		// Get the tracking note type setting
		$tracking_note_type = $this->get_setting('dhl_tracking_note', 'customer');
		$tracking_note_type = ($tracking_note_type == 'yes') ? '' : 'customer';

		// Go through the shipments retrieved from the API and save the AWB of the shipment to
		// each DHL item's associated WooCommerce order in post meta. This will make sure that each
		// WooCommerce order has a reference to the its DHL shipment AWB.
		// At the same time, we will be collecting the AWBs to merge the label PDFs later on, as well
		// as adding order notes for the AWB to each WC order.
		$awbs = array();
		foreach ($response->shipments as $shipment) {
			foreach ($shipment->items as $item) {
				if ( ! isset( $order_items[ $item->barcode ] ) ) {
					continue;
				}

				// Get the WC order for this DHL item
				$item_wc_order_id = $order_items[ $item->barcode ];
				$item_wc_order = wc_get_order( $item_wc_order_id );

				// Save the AWB to the WC order
				update_post_meta( $item_wc_order_id, 'pr_dhl_dp_awb', $shipment->awb );

				// An an order note for the AWB
				$item_awb_note = __('Shipment AWB: ', 'pr-shipping-dhl') . $shipment->awb;
				$item_wc_order->add_order_note( $item_awb_note, $tracking_note_type, true );

				// Save the AWB in the list.
				$awbs[] = $shipment->awb;

				// Save the DHL order ID in the WC order meta
				update_post_meta( $item_wc_order_id, 'pr_dhl_ecs_asia_order', $response->orderId );
			}
		}

		// Generate the merged AWB label file
		$this->create_dhl_order_label_file( $response->orderId );

		return $response->orderId;
	}
}
