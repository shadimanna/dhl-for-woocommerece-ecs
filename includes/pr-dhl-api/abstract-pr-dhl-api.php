<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class PR_DHL_API {

	protected $dhl_label = null;
	protected $dhl_finder = null;

	protected $country_code;

	// abstract public function set_dhl_auth( $client_id, $client_secret );

	public function is_dhl_ecs_asia( ) {
		return false;
	}

	public function get_dhl_label( $args ) {
		return $this->dhl_label->get_dhl_label( $args );
	}

	public function delete_dhl_label( $label_url ) {
		return $this->dhl_label->delete_dhl_label( $label_url );
	}

	public function get_parcel_location( $args ) {
		if ( $this->dhl_finder ) {
			return $this->dhl_finder->get_parcel_location( $args );
		} else {
			throw new Exception( __('Parcel Finder not available', 'dhl-for-woocommerce') );
		}
	}

	abstract public function get_dhl_products_international();

	abstract public function get_dhl_products_domestic();

	public function get_dhl_content_indicator( ) {
		return array();
	}

	public function dhl_test_connection( $client_id, $client_secret ) {
		return $this->dhl_label->dhl_test_connection( $client_id, $client_secret );
	}

	public function dhl_validate_field( $key, $value ) {
		return $this->dhl_label->dhl_validate_field( $key, $value );
	}

	public function dhl_reset_connection( ) {
		return;
	}

	public function get_dhl_duties() {
		$duties = array(
					'DDU' => __('Delivery Duty Unpaid', 'dhl-for-woocommerce'),
					'DDP' => __('Delivery Duty Paid', 'dhl-for-woocommerce')
					);
		return $duties;
	}

	public function get_dhl_visual_age() {
		return array();	
	}
}
