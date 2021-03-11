<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class PR_DHL_API_Factory {

	public static function init() {
		// Load abstract classes
		include_once( 'abstract-pr-dhl-api-rest.php' );
		include_once( 'abstract-pr-dhl-api-soap.php' );
		include_once( 'abstract-pr-dhl-api.php' );

		// Load interfaces
		include_once( 'interface-pr-dhl-api-label.php' );
	}

	public static function make_dhl( $country_code ) {
		static $cache = array();

		// If object exists in cache, simply return it
		if ( array_key_exists( $country_code, $cache ) ) {
			return $cache[ $country_code ];
		}

		PR_DHL_API_Factory::init();

		$dhl_obj = null;

		try {
			switch ($country_code) {
				case 'US':
				case 'GU':
				case 'AS':
				case 'PR':
				case 'UM':
				case 'VI':
				case 'CA':
                    $dhl_obj = new PR_DHL_API_eCS_US( $country_code);
					break;
				case 'SG':
				case 'HK':
				case 'TH':
				case 'CN':
				case 'MY':
				case 'VN':
				case 'AU':
				case 'IN':
					$dhl_obj = new PR_DHL_API_eCS_Asia( $country_code );
					break;
				default:
					throw new Exception( __('The DHL plugin is not supported in your store\'s "Base Location"', 'pr-shipping-dhl') );
			}
		} catch (Exception $e) {
			throw $e;
		}

		// Cache the object to optimize later invocations of the factory
		$cache[ $country_code ] = $dhl_obj;

		return $dhl_obj;
	}
}
