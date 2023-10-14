<?php

if (!defined('ABSPATH'))
{
	exit; // Exit if accessed directly
}


/**
 * DHL Shipping Method.
 *
 * @package  PR_DHL_Method
 * @category Shipping Method
 * @author   Shadi Manna
 */

if (!class_exists('PR_DHL_WC_Method_eCS_Asia')) :

	class PR_DHL_WC_Method_eCS_Asia extends WC_Shipping_Method
	{

		/**
		 * Init and hook in the integration.
		 */
		public function __construct($instance_id = 0)
		{
			$this->id = 'pr_dhl_ecs_asia';
			$this->instance_id = absint($instance_id);
			$this->method_title = __('DHL eCS Asia', 'dhl-for-woocommerce');
			$this->method_description = sprintf(
				__(
					'To start creating DHL eCommerce shipping labels and provide Tracking Numbers to your customers, please fill in your credentials as shown in your contract provided by DHL.
			<br />
			Not yet a customer? Please get a quote %shere%s or if you need support on how to set up this plugin please click %shere%s.',
					'dhl-for-woocommerce'
				),
				'<a href="https://www.logistics.dhl/global-en/home/our-divisions/ecommerce/integration/contact-ecommerce-integration-get-a-quote.html?cid=referrer_3pv-signup_woocommerce_ecommerce-integration&SFL=v_signup-woocommerce" target="_blank">',
				'</a>',
				'<a href="https://www.logistics.dhl/global-en/home/our-divisions/ecommerce/integration/integration-channels/third-party-solutions/woocommerce.html?cid=referrer_docu_woocommerce_ecommerce-integration&SFL=v_woocommerce" target="_blank">',
				'</a>'
			);

			$this->init();
		}

		/**
		 * init function.
		 */
		private function init()
		{
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
		}

		/**
		 * Get message
		 * @return string Error
		 */
		private function get_message($message, $type = 'notice notice-error is-dismissible')
		{

			ob_start();
?>
			<div class="<?php echo $type ?>">
				<p><?php echo $message ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Initialize integration settings form fields.
		 *
		 * @return void
		 */
		public function init_form_fields()
		{

			$log_path = PR_DHL()->get_log_url();

			$select_dhl_product = array('0' => __('- Select DHL Product -', 'dhl-for-woocommerce'));

			$select_dhl_desc_default = array(
				'product_cat' => __('Product Categories', 'dhl-for-woocommerce'),
				'product_tag' => __('Product Tags', 'dhl-for-woocommerce'),
				'product_name' => __('Product Name', 'dhl-for-woocommerce'),
				'product_export' => __('Product Export Description', 'dhl-for-woocommerce')
			);

			try
			{

				$dhl_obj = PR_DHL()->get_dhl_factory();
				$select_dhl_product_int = $dhl_obj->get_dhl_products_international();
				$select_dhl_product_dom = $dhl_obj->get_dhl_products_domestic();
				$select_dhl_duties = $dhl_obj->get_dhl_duties();

				$select_dhl_tax_id_types = array(
					'' => __(' ', 'dhl-for-woocommerce'),
					'none' => __('-- No Shipper Tax ID --', 'dhl-for-woocommerce')
				);
				$select_dhl_tax_id_types += $dhl_obj->get_dhl_tax_id_types();
			}
			catch (Exception $e)
			{
				PR_DHL()->log_msg(__('DHL Products not displaying - ', 'dhl-for-woocommerce') . $e->getMessage());
			}

			$weight_units = get_option('woocommerce_weight_unit');

			$this->form_fields = array(
				'dhl_pickup_dist'     => array(
					'title'           => __('Account', 'dhl-for-woocommerce'),
					'type'            => 'title',
					'description'     => __('Please enter your account information below:', 'dhl-for-woocommerce'),
					'class'			  => '',
				),
				'dhl_pickup_id' => array(
					'title'             => __('Pickup Account ID', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The "Pickup Account" id will be provided by your local DHL sales organization and tells us where to pick up your shipments.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => '',
					'placeholder'		=> '0000500000'
				),
				'dhl_soldto_id' => array(
					'title'             => __('Soldto Account ID', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The "Soldto Account" id will be provided by your local DHL sales organization and tells us where to pick up your shipments.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => '',
					'placeholder'		=> '0000500000'
				),
				'dhl_label_title'     => array(
					'title'           => __('Label', 'dhl-for-woocommerce'),
					'type'            => 'title',
					'description'     => __('Please configure your label parameters underneath.', 'dhl-for-woocommerce'),
					'class'			  => '',
				),
				'dhl_default_product_int' => array(
					'title'             => __('International Default Service', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Please select your default DHL eCommerce shipping service for cross-border shippments that you want to offer to your customers (you can always change this within each individual order afterwards).', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => $select_dhl_product_int,
					'class'				=> 'wc-enhanced-select'
				),
				'dhl_default_product_dom' => array(
					'title'             => __('Domestic Default Service', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Please select your default DHL eCommerce shipping service for domestic shippments that you want to offer to your customers (you can always change this within each individual order afterwards)', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => $select_dhl_product_dom,
					'class'				=> 'wc-enhanced-select'
				),
				'dhl_prefix' => array(
					'title'             => __('Customer Prefix', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The package prefix is added to identify the package is coming from your shop. This value is limited to 5 charaters.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => '',
					'placeholder'		=> '',
					'custom_attributes'	=> array('maxlength' => '5')
				),
				'dhl_desc_default' => array(
					'title'             => __('Package Description', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Prefill the package description with one of the options.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => $select_dhl_desc_default,
					'class'				=> 'wc-enhanced-select'
				),
				'dhl_label_format' => array(
					'title'             => __('Label Format', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Select one of the formats to generate the shipping label in.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => array('PDF' => 'PDF', 'PNG' => 'PNG', 'ZPL' => 'ZPL'),
					'class'				=> 'wc-enhanced-select'
				),
				'dhl_label_layout' => array(
					'title'             => __('Label Layout', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Select the shipping label size.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => array('1x1' => '1x1', '4x1' => '4x1'),
					'class'				=> 'wc-enhanced-select'
				),
				'dhl_add_weight_type' => array(
					'title'             => __('Additional Weight Type', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Select whether to add an absolute weight amount or percentage amount to the total product weight.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => array('absolute' => 'Absolute', 'percentage' => 'Percentage'),
					'class'				=> 'wc-enhanced-select'
				),
				'dhl_add_weight' => array(
					'title'             => sprintf(__('Additional Weight (%s or %%)', 'dhl-for-woocommerce'), $weight_units),
					'type'              => 'text',
					'description'       => __('Add extra weight in addition to the products.  Either an absolute amount or percentage (e.g. 10 for 10%).', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => '',
					'placeholder'		=> '',
					'class'				=> 'wc_input_decimal'
				),
				'dhl_duties_default' => array(
					'title'             => __('Incoterms', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Select default for duties.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => $select_dhl_duties,
					'class'				=> 'wc-enhanced-select'
				),
				'dhl_order_note' => array(
					'title'             => __('Order Notes', 'dhl-for-woocommerce'),
					'type'              => 'checkbox',
					'label'             => __('Include Order Notes', 'dhl-for-woocommerce'),
					'default'           => 'no',
					'description'       => __('Please, tick here if you want to send the customer "Order Notes" to be added to the label.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
				),
				'dhl_default_additional_insurance' => array(
					'title'             => __('Additional Insurance default', 'dhl-for-woocommerce'),
					'type'              => 'checkbox',
					'label'             => __('Enabled', 'dhl-for-woocommerce'),
					'default'           => 'no',
					'description'       => __('Please, tick here if you want "Additional Insurance" service to be checked by default.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
				),
				'dhl_default_obox_service' => array(
					'title'             => __('Open Box Service default', 'dhl-for-woocommerce'),
					'type'              => 'checkbox',
					'label'             => __('Enabled', 'dhl-for-woocommerce'),
					'default'           => 'no',
					'description'       => __('Please, tick here if you want "Open Box" service to be checked by default.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
				),
				'dhl_tracking_note' => array(
					'title'             => __('Tracking Note', 'dhl-for-woocommerce'),
					'type'              => 'checkbox',
					'label'             => __('Make Private', 'dhl-for-woocommerce'),
					'default'           => 'no',
					'description'       => __('Please, tick here to not send an email to the customer when the tracking number is added to the order.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
				),
				'dhl_tracking_note_txt' => array(
					'title'             => __('Tracking Note', 'dhl-for-woocommerce'),
					'type'              => 'textarea',
					'description'       => __('Set the custom text when adding the tracking number to the order notes. {tracking-link} is where the tracking number will be set.', 'dhl-for-woocommerce'),
					'desc_tip'          => false,
					'default'           => __('DHL Tracking Number: {tracking-link}', 'dhl-for-woocommerce')
				),
				'dhl_api'           => array(
					'title'           => __('API Settings', 'dhl-for-woocommerce'),
					'type'            => 'title',
					'description'     => __('Please enter your DHL eCommerce API credentials:', 'dhl-for-woocommerce'),
					'class'			  => '',
				),
				'dhl_api_key' => array(
					'title'             => __('Client Id', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The client ID (a 36 digits alphanumerical string made from 5 blocks) is required for authentication and is provided to you within your contract.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_api_secret' => array(
					'title'             => __('Client Secret', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The client secret (also a 36 digits alphanumerical string made from 5 blocks) is required for authentication (together with the client ID) and creates the tokens needed to ensure secure access. It is part of your contract provided by your DHL sales partner.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_sandbox' => array(
					'title'             => __('Sandbox Mode', 'dhl-for-woocommerce'),
					'type'              => 'checkbox',
					'label'             => __('Enable Sandbox Mode', 'dhl-for-woocommerce'),
					'default'           => 'no',
					'description'       => __('Please, tick here if you want to test the plug-in installation against the DHL Sandbox Environment. Labels generated via Sandbox cannot be used for shipping and you need to enter your client ID and client secret for the Sandbox environment instead of the ones for production!', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
				),
				'dhl_test_connection_button' => array(
					'title'             => PR_DHL_BUTTON_TEST_CONNECTION,
					'type'              => 'button',
					'custom_attributes' => array(
						'onclick' => "dhlTestConnection('#woocommerce_pr_dhl_ecs_asia_dhl_test_connection_button');",
					),
					'description'       => __('Press the button for testing the connection against our DHL eCommerce Gateways (depending on the selected environment this test is being done against the Sandbox or the Production Environment).', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
				),
				'dhl_debug' => array(
					'title'             => __('Debug Log', 'dhl-for-woocommerce'),
					'type'              => 'checkbox',
					'label'             => __('Enable logging', 'dhl-for-woocommerce'),
					'default'           => 'yes',
					'description'       => sprintf(__('A log file containing the communication to the DHL server will be maintained if this option is checked. This can be used in case of technical issues and can be found %shere%s.', 'dhl-for-woocommerce'), '<a href="' . $log_path . '" target = "_blank">', '</a>')
				),
				'dhl_shipper'           => array(
					'title'           => __('Pickup / Shipper Address', 'dhl-for-woocommerce'),
					'type'            => 'title',
					'description'     => __('Enter a Pickup & Shipper Address below (this is used as the Shipper Address with Tax ID as well as for the "Pickup Address" via "DHL Parcel Metro" product):', 'dhl-for-woocommerce'),
				),
				'dhl_contact_name' => array(
					'title'             => __('Name', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The account name will be provided by your local DHL sales organization and tells us where to pick up your shipments.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_email' => array(
					'title'             => __('Email', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The email.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => '',
					'placeholder'		=> 'name@email.com'
				),
				'dhl_phone' => array(
					'title'             => __('Phone', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The phone.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => '',
					'placeholder'		=> '0214543433'
				),
				'dhl_shipper_company' => array(
					'title'             => __('Company', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('Enter Pickup Company.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_shipper_address_1' => array(
					'title'             => __('Address 1', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('Enter Pickup Address.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_shipper_address_2' => array(
					'title'             => __('Address 2', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('Enter Pickup Address 2.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_shipper_address_city' => array(
					'title'             => __('City', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('Enter Pickup City.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_shipper_address_state' => array(
					'title'             => __('State', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('Enter Pickup County.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_shipper_address_district' => array(
					'title'             => __('District', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('Enter Pickup District.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_shipper_address_country' => array(
					'title'             => __('Country', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('Enter Pickup Country.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => WC()->countries->get_countries(),
					'class'				=> 'wc-enhanced-select',
					'default'           => ''
				),
				'dhl_shipper_address_postcode' => array(
					'title'             => __('Postcode', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('Enter Pickup Postcode.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
				'dhl_shipper_tax_info'           => array(
					'title'           => __('Shipper Tax ID Defaults', 'dhl-for-woocommerce'),
					'type'            => 'title',
					'description'     => __('Enter the default Tax ID below: ', 'dhl-for-woocommerce'),
				),
				'dhl_shipper_tax_id_type' => array(
					'title'             => __('Shipper Tax ID Type', 'dhl-for-woocommerce'),
					'type'              => 'select',
					'description'       => __('The default Tax ID Type for the shipper.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'options'           => $select_dhl_tax_id_types,
					'class'				=> 'wc-enhanced-select',
					'default'           => ''
				),
				'dhl_shipper_tax_id' => array(
					'title'             => __('Shipper Tax ID', 'dhl-for-woocommerce'),
					'type'              => 'text',
					'description'       => __('The default Tax ID for the shipper.', 'dhl-for-woocommerce'),
					'desc_tip'          => true,
					'default'           => ''
				),
			);
		}

		public function admin_options()
		{

			parent::admin_options();

			// add js inline to show or hide conditional tax id based on tax id type
			if (isset($this->form_fields['dhl_shipper_tax_id_type']))
			{

				ob_start();
			?>
				$( '#woocommerce_<?php echo $this->id; ?>_dhl_shipper_tax_id_type' ).change( function() {

				var tax_id_type = $(this).val();

				if ( tax_id_type == '4' || tax_id_type == 'none' || tax_id_type == '' ) {
				$( '#woocommerce_<?php echo $this->id; ?>_dhl_shipper_tax_id' ).closest( 'tr' ).hide();
				$( '#woocommerce_<?php echo $this->id; ?>_dhl_shipper_tax_id' ).val('');
				} else {
				$( '#woocommerce_<?php echo $this->id; ?>_dhl_shipper_tax_id' ).closest( 'tr' ).show();
				}

				} ).change();
			<?php
				wc_enqueue_js(ob_get_clean());
			}
		}

		/**
		 * Generate Button HTML.
		 *
		 * @access public
		 * @param mixed $key
		 * @param mixed $data
		 * @since 1.0.0
		 * @return string
		 */
		public function generate_button_html($key, $data)
		{
			$field    = $this->plugin_id . $this->id . '_' . $key;
			$defaults = array(
				'class'             => 'button-secondary',
				'css'               => '',
				'custom_attributes' => array(),
				'desc_tip'          => false,
				'description'       => '',
				'title'             => '',
			);

			$data = wp_parse_args($data, $defaults);

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
					<?php echo $this->get_tooltip_html($data); ?>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
						<button class="<?php echo esc_attr($data['class']); ?>" type="button" name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" style="<?php echo esc_attr($data['css']); ?>" <?php echo $this->get_custom_attribute_html($data); ?>><?php echo wp_kses_post($data['title']); ?></button>
						<?php echo $this->get_description_html($data); ?>
					</fieldset>
				</td>
			</tr>
<?php
			return ob_get_clean();
		}

		/**
		 * Validate the pickup field
		 * @see validate_settings_fields()
		 */
		public function validate_dhl_pickup_field($key, $value)
		{
			// $value = wc_clean( $_POST[ $this->plugin_id . $this->id . '_' . $key ] );

			try
			{

				$dhl_obj = PR_DHL()->get_dhl_factory();
				$dhl_obj->dhl_validate_field('pickup', $value);
			}
			catch (Exception $e)
			{
				echo $this->get_message(__('Pickup Account Number: ', 'dhl-for-woocommerce') . $e->getMessage());
				throw $e;
			}

			return $value;
		}

		/**
		 * Validate the distribution field
		 * @see validate_settings_fields()
		 */
		public function validate_dhl_distribution_field($key, $value)
		{
			// $value = wc_clean( $_POST[ $this->plugin_id . $this->id . '_' . $key ] );

			try
			{

				$dhl_obj = PR_DHL()->get_dhl_factory();
				$dhl_obj->dhl_validate_field('distribution', $value);
			}
			catch (Exception $e)
			{

				echo $this->get_message(__('Distribution Center: ', 'dhl-for-woocommerce') . $e->getMessage());
				throw $e;
			}

			return $value;
		}

		/**
		 * Validate the label format field
		 * @see validate_settings_fields()
		 */
		public function validate_dhl_label_format_field($key, $value)
		{
			// $value = wc_clean( $_POST[ $this->plugin_id . $this->id . '_' . $key ] );
			$post_data = $this->get_post_data();
			/*
		$label_page = $post_data[ $this->plugin_id . $this->id . '_' . 'dhl_label_page' ];
		if( ( $value == 'PNG' || $value == 'ZPL' ) && ( $label_page == 'A4' ) ) {
			$msg = __('The selected format does not support "A4"', 'dhl-for-woocommerce');
			echo $this->get_message($msg);
			throw new Exception( $msg );
		}
		*/
			return $value;
		}

		/**
		 * Validate the label size field
		 * @see validate_settings_fields()
		 */
		public function validate_dhl_label_size_field($key, $value)
		{
			// $value = wc_clean( $_POST[ $this->plugin_id . $this->id . '_' . $key ] );
			$distribution_centers = array('HKHKG1', 'CNSHA1', 'CNSZX1');
			$post_data = $this->get_post_data();

			$distribution = $post_data[$this->plugin_id . $this->id . '_' . 'dhl_distribution'];
			/*
		$label_page = $post_data[ $this->plugin_id . $this->id . '_' . 'dhl_label_page' ];

		if( ! in_array( $distribution, $distribution_centers )  && ( $value == '4x4' ) ) {
			$msg = __('Your distribution center does not support "4x4" label size.', 'dhl-for-woocommerce');
			echo $this->get_message( $msg );
			throw new Exception( $msg );
		}

		if( ( $value == '4x4' ) && ( $label_page == '400x600' ) ) {
			$msg = __('You cannot have a Label Size of "4x4" and Page Size of "400x600".', 'dhl-for-woocommerce');
			echo $this->get_message( $msg );
			throw new Exception( $msg );
		}

		if( ( $value == '4x6' ) && ( $label_page == '400x400' ) ) {
			$msg = __('You cannot have a Label Size of "4x6" and Page Size of "400x400".', 'dhl-for-woocommerce');
			echo $this->get_message( $msg );
			throw new Exception( $msg );
		}
		*/
			return $value;
		}

		/**
		 * Processes and saves options.
		 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
		 */
		public function process_admin_options()
		{

			try
			{

				$dhl_obj = PR_DHL()->get_dhl_factory();
				$dhl_obj->dhl_reset_connection();
			}
			catch (Exception $e)
			{

				echo $this->get_message(__('Could not reset connection: ', 'dhl-for-woocommerce') . $e->getMessage());
				// throw $e;
			}

			return parent::process_admin_options();
		}
	}

endif;
