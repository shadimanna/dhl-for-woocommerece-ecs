<?php

namespace PR\DHL\REST_API\DHL_eCS_US;

use Exception;
use PR\DHL\Utils\Args_Parser;

/**
 * A class that represents a Deutsche Post item, which corresponds to a WooCommerce order.
 *
 * @since [*next-version*]
 */
class Item_Info {

	/**
	 * The order id
	 * 
	 * @since [*next-version*]
	 * 
	 * @var int
	 */
	public $order_id;

	/**
	 * The array of shipment information.
	 *
	 * @since [*next-version*]
	 *
	 * @var array
	 */
	public $shipment;

	/**
	 * The array of consignee information sub-arrays.
	 *
	 * @since [*next-version*]
	 *
	 * @var array[]
	 */
	public $consignee;

	/**
	 * The array of return information sub-arrays.
	 *
	 * @since [*next-version*]
	 *
	 * @var array[]
	 */
	public $return;

	/**
	 * The array of content item information sub-arrays.
	 *
	 * @since [*next-version*]
	 *
	 * @var array[]
	 */
	public $contents;

	/**
	 * The units of measurement used for weights in the input args.
	 *
	 * @since [*next-version*]
	 *
	 * @var string
	 */
	protected $weightUom;

	/**
	 * Is the shipment cross-border or domestic
	 *
	 * @since [*next-version*]
	 *
	 * @var boolean
	 */
	public $isCrossBorder;

	/**
	 * Constructor.
	 *
	 * @since [*next-version*]
	 *
	 * @param array $args The arguments to parse.
	 * @param string $weightUom The units of measurement used for weights in the input args.
	 *
	 * @throws Exception If some data in $args did not pass validation.
	 */
	public function __construct( $args, $uom, $isCrossBorder ) {
		//$this->parse_args( $args );
		$this->weightUom 	= $uom;
		$this->isCrossBorder 	= $isCrossBorder;
		$this->parse_args( $args, $uom );
	}

	/**
	 * Parses the arguments and sets the instance's properties.
	 *
	 * @since [*next-version*]
	 *
	 * @param array $args The arguments to parse.
	 *
	 * @throws Exception If some data in $args did not pass validation.
	 */
	protected function parse_args( $args ) {
		$settings = $args[ 'dhl_settings' ];
		$recipient_info = $args[ 'shipping_address' ] + $settings;
		$shipping_info = $args[ 'order_details' ] + $settings;
		$items_info = $args['items'];
		
		$this->shipment 		= Args_Parser::parse_args( $shipping_info, $this->get_shipment_info_schema() );
		$this->consignee 		= Args_Parser::parse_args( $recipient_info, $this->get_recipient_info_schema() );
		$this->return 			= Args_Parser::parse_args( $settings, $this->get_return_info_schema() );
		$this->contents 		= array();

		foreach ( $items_info as $item_info ) {
			$this->contents[] = Args_Parser::parse_args( $item_info, $this->get_content_item_info_schema() );
		}
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for base item info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_shipment_info_schema() {

		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;

		return array(
			'order_id'      => array(
				'error'  => __( 'Shipment "Order ID" is empty!', 'pr-shipping-dhl' ),
			),
			'prefix' 		=> array(
				'default' => 'DHL'
			),
			'pickup_id' 		=> array(
				'error'  => __( 'Shipment "Pickup ID" is empty!', 'pr-shipping-dhl' ),
			),
			'distribution_center' => array(
				'error'  => __( 'Shipment "Distribution Center" is empty!', 'pr-shipping-dhl' ),
			),
			'dhl_product' => array(
				'rename' 	=> 'product_code',
			),
			'description' 	=> array(
			    'default'   => '',
				'validate' => function( $value ) {

					if( empty( $value ) ) {
						throw new Exception( __( 'Shipment "Description" is empty!', 'pr-shipping-dhl' ) );
					}
				},
			),
			'billing_ref' => array(
			    'default'   => '',
			),
			'weight'     => array(
                'error'    => __( 'Order "Weight" is empty!', 'pr-shipping-dhl' ),
                'validate' => function( $weight ) use ($self) {
                    if ( ! is_numeric( $weight ) || $weight <= 0 ) {
                        throw new Exception( __( 'The order "Weight" must be a positive number', 'pr-shipping-dhl' ) );
                    }
                },
				'sanitize' => function ( $weight ) use ($self) {
                    
					return floatval( $weight );
				}
			),
			'weightUom'  => array(
				'sanitize' => function ( $uom ) use ($self) {
					
					return strtoupper( $uom );
				}
			),
			'currency' => array(
				'error' => __( 'Shop "Currency" is empty!', 'pr-shipping-dhl' ),
			),
			'duties' => array(
				'default' 	=> '',
				'validate' => function( $value ) {

					if( empty( $value ) && $this->isCrossBorder == true ) {
						throw new Exception( __( 'Shipment "Duties" is empty!', 'pr-shipping-dhl' ) );
					}
				},
				'sanitize' 	=> function( $value ) {

					return ($value=='DDP') ? true : false;

				}
			),
			'dangerous_goods' => array(
				'rename' 	=> 'content_category',
				'default' 	=> '',
			),
			'label_format' => array(
				'default' 	=> '',
				'validate'	=> function( $format ){
					
					if( $format != 'ZPL' && $format != 'PNG' ){
						throw new Exception( __( 'Label format is not available.', 'pr-shipping-dhl' ) );
					}
				}
			)
		);
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for parsing order recipient info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_recipient_info_schema() {

		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;
		
		return array(
			'name'      => array(
				'error'  => __( 'Recipient "Name" is empty!', 'pr-shipping-dhl' ),
				'sanitize' => function( $name ) use ($self) {

					return $self->string_length_sanitization( $name, 30 );
				}
			),
			'company' 	=> array(
				'rename' 	=> 'companyName',
				'default' 	=> '',
				'sanitize' => function( $company ) use ($self) {

					return $self->string_length_sanitization( $company, 30 );
				},
			),
			'address_1' => array(
				'rename' => 'address1',
				'error' => __( 'Recipient "Address 1" is empty!', 'pr-shipping-dhl' ),
				'sanitize' => function( $address ) use ($self) {

					return $self->string_length_sanitization( $address, 50 );
				},
			),
			'address_2' => array(
				'rename' => 'address2',
				'default' => '',
			),
			'city'      => array(
				'error' => __( 'Recipient "City" is empty!', 'pr-shipping-dhl' ),
				'sanitize' => function( $address ) use ($self) {

					return $self->string_length_sanitization( $address, 30 );
				},
			),
			'state'     => array(
				'default' => '',
				'validate' => function( $value ) {

                    if( empty( $value ) && !$this->isCrossBorder ) {
                        throw new Exception( __( 'Recipient "state" is empty!', 'pr-shipping-dhl' ) );
                    }
                },
			),
			'country'   => array(
				'error' => __( 'Recipient "Country" is empty!', 'pr-shipping-dhl' ),
			),
			'postcode'  => array(
				'rename' => 'postalCode',
				'error' => __( 'Recipient "Postcode" is empty!', 'pr-shipping-dhl' ),
			),
			'email'     => array(
				'default' => '',
				'validate' => function( $value ) {

                    if( empty( $value ) && $this->isCrossBorder ) {
                        throw new Exception( __( 'Recipient "email" is empty!', 'pr-shipping-dhl' ) );
                    }
                },
			),
			'phone'     => array(
				'default' => '',
				'sanitize' => function( $phone ) use ($self) {

					return $self->string_length_sanitization( $phone, 15 );
				},
				'validate' => function( $value ) {

                    if( empty( $value ) && !$this->isCrossBorder ) {
                        throw new Exception( __( 'Recipient "phone" is empty!', 'pr-shipping-dhl' ) );
                    }
                },
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for parsing order pickup shipment info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_return_info_schema() {

		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;

		return array(
			'dhl_contact_name'      => array(
				'rename' => 'name',
				'error'  => __( 'Base "Account Name" in settings is empty.', 'pr-shipping-dhl' ),
				'sanitize' => function( $name ) use ($self) {

					return $self->string_length_sanitization( $name, 30 );
				}
			),
			'dhl_company_name'      => array(
				'rename' => 'companyName',
				'sanitize' => function( $name ) use ($self) {

					return $self->string_length_sanitization( $name, 30 );
				}
			),
			'dhl_return_address_1' => array(
				'rename' => 'address1',
				'error' => __( 'Return "Address 1" is empty!', 'pr-shipping-dhl' ),
				'sanitize' => function( $address ) use ($self) {

					return $self->string_length_sanitization( $address, 50 );
				},
			),
			'dhl_return_address_2' => array(
				'rename' => 'address2',
				'default' => '',
			),
			'dhl_return_city'      => array(
				'rename' => 'city',
				'error' => __( 'Return address "City" is empty!', 'pr-shipping-dhl' ),
				'sanitize' => function( $city ) use ($self) {

					return $self->string_length_sanitization( $city, 30 );
				},
			),
			'dhl_return_state'     => array(
				'rename' => 'state',
				'default' => '',
			),
			'dhl_return_country'   => array(
				'rename' => 'country',
				'error' => __( 'Return address "Country" is empty!', 'pr-shipping-dhl' ),
			),
			'dhl_return_postcode'  => array(
				'rename' => 'postalCode',
				'error' => __( 'Return address "Postcode" is empty!', 'pr-shipping-dhl' ),
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with {@link Args_Parser} for parsing order content item info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_content_item_info_schema()
	{
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually
		$self = $this;

		return array(
			'item_export' => array(
				'rename' => 'description',
				'default' => '',
				'sanitize' => function( $description ) use ($self) {

					return $self->string_length_sanitization( $description, 50 );
				},
				'validate' => function( $value ) {

                    if( empty( $value ) && $this->isCrossBorder ) {
                        throw new Exception( __( 'Item "description" is empty!', 'pr-shipping-dhl' ) );
                    }
                },
			),
			'origin'      => array(
				'default' => PR_DHL()->get_base_country(),
			),
			'hs_code'     => array(
				'default'  => '',
				'validate' => function( $hs_code ) {
					$length = is_string( $hs_code ) ? strlen( $hs_code ) : 0;

					if (empty($length)) {
						return;
					}

					if ( $length < 6 || $length > 20 ) {
						throw new Exception(
							__( 'Item HS Code must be between 6 and 20 characters long', 'pr-shipping-dhl' )
						);
					}
				},
			),
			'qty'         => array(
				'validate' => function( $qty ) {

					if( !is_numeric( $qty ) || $qty < 1 ){

						throw new Exception(
							__( 'Item quantity must be more than 1', 'pr-shipping-dhl' )
						);

					}
				},
			),
			'item_value'       => array(
				'rename' => 'value',
				'default' => 0,
				'sanitize' => function( $value ) use ($self) {

					return $self->float_round_sanitization( $value, 2 );
				}
			),
			'sku'         => array(
				'default' => '',
				'sanitize' => function( $value ) use ($self) {

					return $self->string_length_sanitization( $value, 20 );
				}
			),
		);
	}

	/**
	 * Converts a given weight into grams, if necessary.
	 *
	 * @since [*next-version*]
	 *
	 * @param float $weight The weight amount.
	 * @param string $uom The unit of measurement of the $weight parameter..
	 *
	 * @return float The potentially converted weight.
	 */
	protected function maybe_convert_to_grams( $weight, $uom ) {
		$weight = floatval( $weight );

		switch ( $uom ) {
			case 'kg':
				return $weight * 1000;

			case 'lb':
				return $weight / 2.2;

			case 'oz':
				return $weight / 35.274;
		}

		return $weight;
	}

	protected function float_round_sanitization( $float, $numcomma ) {

		$float = floatval( $float );

		return round( $float, $numcomma);
	}

	protected function string_length_sanitization( $string, $max ) {

		$max = intval( $max );

		if( strlen( $string ) <= $max ){

			return $string;
		}

		return substr( $string, 0, ( $max-1 ));
	}

}
