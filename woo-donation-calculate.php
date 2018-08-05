<?php

/*
* Plugin Name: Woo donation calculate
* Plugin URI: https://github.com/maksbd19/woo-donation-on-calculate
* Description: Allow to have and calculate donation on products
* Author: Mahbub Alam <makjoybd@gmail.com>
* Version: 1.0
* Author URI: http://makjyoybd.com/
*/

class WOO_Donation_Calculate{
	public function __construct(  ) {

		//  initialization
		add_action( "admin_init", array($this, "woocommerce_installed") );
		add_action( "admin_init", array($this, "save_organisation") );
		add_action( "admin_init", array($this, "remove_organisation") );

		//  setup field in the product edit page
		add_action( "woocommerce_product_options_general_product_data", array($this, "product_fields") );
		add_action( "woocommerce_process_product_meta", array($this, "save_field_input") );

		//  display notification in the product page.
		add_action("woocommerce_after_shop_loop_item_title", array($this, "notification") );
		add_action("woocommerce_single_product_summary", array($this, "notification") );
		add_action("woocommerce_cart_item_subtotal", array($this, "cart_notification"), 20, 3 );

		add_shortcode( "product-donation-amount", array($this, 'product_donation_amount') );

		add_filter("formatted_woocommerce_price", array($this, "format_price"), 20, 5);
		add_filter("woocommerce_get_formatted_order_total", array($this, "formatted_order_total"));
		add_filter("woocommerce_order_formatted_line_subtotal", array($this, "formatted_order_subtotal"), 10, 3);

		add_action("admin_menu", array($this, "admin_menu"));

		// Uncomment it if you want to charge your users
		// add_action( 'woocommerce_before_calculate_totals', array($this, 'add_donation_price') );
	}

	/**
	 * Check if woocommerce is already installed or not
	 * If woocommerce is not installed then disable this plugin
	 * and show a notice in admin screen.
	 */

	public function woocommerce_installed() {
		if ( is_admin() && ( ! class_exists( 'WooCommerce' ) && current_user_can( 'activate_plugins' ) ) ) {
			add_action( 'admin_notices', array($this, "admin_notification") );

			deactivate_plugins( plugin_basename( __FILE__ ) );

			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
		}
	}

	public function admin_notification(  ) {
		echo '<div class="error"><p>' . sprintf( __('Activation failed: <strong>WooCommerce</strong> must be activated to use the <strong>Woo Donation Calculate</strong> plugin. %sVisit your plugins page to install and activate.', 'woocommerce' ), '<a href="' . admin_url( 'plugins.php#woocommerce' ) . '">' ) . '</a></p></div>';
	}

	/**
	 * add donation amount field in the product general tab
	 */

	public function product_fields(  ) {

		echo '<div class="wc_input">';

		woocommerce_wp_text_input(
			array(
				'id'          => '_woo_donation_input',
				'label'       => __( 'Donation for this product', 'woocommerce' ),
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => __( 'Enter the amount of donation for this product here in percent.', 'woo_uom' )
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => '_woo_donation_type',
				'label'       => __( 'Donation per quantity', 'woocommerce' ),
				'desc_tip'    => 'true',
				'description' => __( 'Check if the donation amount is per quantity.', 'woo_uom' )
			)
		);

		$organisations = self::get_organisations();
		array_unshift($organisations, "Select Organisation");

		woocommerce_wp_select(
            array(
                'id'          => '_woo_donation_organisation',
                'label'       => __( 'Donation organisation', 'woocommerce' ),
                'options'     => $organisations
            )
        );
		echo '</div>';
	}

	/**
	 * save meta value for the product
	 * @param $post_id
	 */

	public function save_field_input( $post_id ) {
		$woo_donation_input = isset( $_POST['_woo_donation_input'] ) ? sanitize_text_field( $_POST['_woo_donation_input'] ) : "";
		$woo_donation_type = isset( $_POST['_woo_donation_type'] ) ? "yes" : "";
		$woo_donation_organisation = isset( $_POST['_woo_donation_organisation'] ) ? sanitize_text_field( $_POST['_woo_donation_organisation'] ) : "";

		update_post_meta( $post_id, '_woo_donation_input', esc_attr( $woo_donation_input ) );
		update_post_meta( $post_id, '_woo_donation_type', esc_attr( $woo_donation_type ) );
		update_post_meta( $post_id, '_woo_donation_organisation', esc_attr( $woo_donation_organisation ) );
	}

	public function get_donation_amount( $ID ) {
		$amount = get_post_meta($ID, '_woo_donation_input', true);

		if($amount == "" || floatval($amount) === 0){
			return 0;
		}

		return floatval($amount) / 100;
	}

	public function get_adjusted_price( $ID, $qty = 1 ) {
		$amount = $this->get_donation_amount($ID);

		$product = wc_get_product($ID);

		$price = $product->get_price() * $amount;

		if($this->is_per_item($ID)){
			return $price * $qty;
		}

		return $price;
	}

	public function get_organisation_name( $ID ) {
		$organisationID = get_post_meta($ID, '_woo_donation_organisation', true);

		$organisations = self::get_organisations();

		return ($organisationID !== "" &&is_array($organisations) && !empty($organisations) && isset($organisations[$organisationID])) ? $organisations[$organisationID] : "";
	}

	public function is_per_item( $ID )
	{
		$per_item_donation = get_post_meta($ID, '_woo_donation_type', true);

		return $per_item_donation === 'yes';
	}
	/**
	 * display notification in the product category and single page
	 */

	public function notification(){
		global $product;

		$product_ID = $product->get_id();
		$amount = $this->get_adjusted_price($product_ID);
		$organisation = $this->get_organisation_name($product_ID);


		if($amount === 0){
			return;
		}

		// change this text as per your use case
		echo sprintf( __( '<p class="donation-amount">If you order this product <span class="woo-donation-calculate-price"><b>%s</b></span> will be donated' . ($organisation === "" ? '' : ' to <span class="woo-donation-calculate-organisation">%s</span>') . '.</p>', 'woocommerce' ), wc_price($amount), $organisation );

		if( is_product() && $this->is_per_item($product_ID) ):

		?>
		<script>
			jQuery(document).ready(function($){
			    $("body").on("change", "[name='quantity']", function(e){
                    var newPrice = parseInt(this.value) * parseFloat(<?php echo $this->get_adjusted_price($product->get_id());?>);
			        $(this).closest(".product").find(".woo-donation-calculate-price .woo-price-number-val").text(newPrice.toFixed(2));
			    });
			});
		</script>
		<?php

		endif;

	}

	/**
	 * Notification in the cart page
	 *
	 * @param $total
	 * @param $cart_item
	 * @param $cart_item_key
	 *
	 * @return string
	 */

	public function cart_notification( $total, $cart_item, $cart_item_key ) {
		$donation_message = "";

		$qty = $cart_item['quantity'];
		$product_id = $cart_item['product_id'];

		$organisation = $this->get_organisation_name($product_id);

		$adjusted_price = $this->get_adjusted_price( $product_id, $qty );

		if($adjusted_price !== 0){
			$donation_message = sprintf("<p class='donation-fee'>You are donating <span class='donation-fee-amount'>%s</span>" . ($organisation === "" ? "" : " to <span class='woo-donation-calculate-organisation'>%s</span>") . "</p>", wc_price($adjusted_price), $organisation);
		}

		return $total . $donation_message;
	}

	public function formatted_order_total( $returns ) {
		return $returns;
	}

	public function formatted_order_subtotal( $subtotal, $item, $order ) {

		$donation_message = "";
		$product_id = $item->get_product()->get_id();
		$qty = $item->get_quantity();

		$organisation = $this->get_organisation_name($product_id);

		$adjusted_price = $this->get_adjusted_price( $product_id, $qty );

		if($adjusted_price !== 0){
			$donation_message = sprintf("<p class='donation-fee'>You are donating <span class='donation-fee-amount'>%s</span>" . ($organisation === "" ? "" : " to <span class='woo-donation-calculate-organisation'>%s</span>") . "</p>", wc_price($adjusted_price), $organisation);
		}

		return $subtotal . $donation_message;
	}

	public function format_price($price){
		return "<span class='woo-price-number-val'>" . $price . "</span>";
	}

	/**
	 * Adjust cart before calculating total cart value
	 *
	 * @param $cart_object
	 */

	public function add_donation_price( $cart_object ) {

		foreach ( $cart_object->cart_contents as $key => $value ) {

			$product_id = $value['product_id'];
			$quantity = $value['quantity'];

			$adjusted_price = $this->get_adjusted_price($product_id);

			if($this->is_per_item($product_id)){
				$adjusted_price = $adjusted_price * $quantity;
			}

			if($adjusted_price !== 0){
				$price = floatval( $value['data']->get_price() );
				$value['data']->set_price($price + $adjusted_price);
			}

		}
	}

	public function admin_menu(){
		add_options_page("Woo Donation Organisations","Woo Donation Organisation","manage_options", "woo-donation-organisations", array($this, "organisations"));
	}

	public function organisations(){
		include_once trailingslashit(plugin_dir_path(__FILE__)) . "organisations.php";
	}

	public function save_organisation(){

		if(!isset($_POST) || !isset($_POST['_woo_org_token']) || !wp_verify_nonce($_POST['_woo_org_token'], '__woo_donation_add_organisations')){
			return;
		}

		$base_url = admin_url('options-general.php?page=woo-donation-organisations');
		$status = 1;

		if(!isset($_POST['org-name'])){
			$status = 2;
        }
        else if(esc_attr($_POST['org-name']) === ""){
	        $status = 3;
        }

        $organisations = self::get_organisations();

		$organisation = esc_attr($_POST['org-name']);
		$organisation_index = sanitize_title($organisation);

		if(isset($organisations[$organisation_index])){
		    $status = 4;
        }
        elseif($status === 1){
	        $organisations[$organisation_index] = $organisation;
	        update_option("_donation_organisations", $organisations);
        }

		$url = add_query_arg(array('status' => $status), $base_url);

		wp_safe_redirect($url);
		die();
	}

	public function remove_organisation(){
		if(!isset($_GET) || !isset($_GET['_woo_org_token']) || !wp_verify_nonce($_GET['_woo_org_token'], '__woo_donation_remove_organisation')){
			return;
		}

		$base_url = admin_url('options-general.php?page=woo-donation-organisations');
		$status = 5;

		if(!isset($_GET['organisation_key'])){
			$status = 6;
		}
		else if(esc_attr($_GET['organisation_key']) === ""){
			$status = 6;
		}

		$organisations = self::get_organisations();

		$organisation_index = esc_attr($_GET['organisation_key']);

		if(!isset($organisations[$organisation_index])){
			$status = 7;
		}
		elseif($status === 5){
			unset($organisations[$organisation_index]);
			update_option("_donation_organisations", $organisations);
		}

		$url = add_query_arg(array('status' => $status), $base_url);

		wp_safe_redirect($url);
		die();
	}

	public function product_donation_amount($atts){
		$atts = shortcode_atts( array(
			'type' => 'formatted',
			'ID' => ''
		), $atts, 'product-donation-amount' );

		if( $atts['ID'] == ""){
			global $product;
			$ID = $product->get_id();
		}
		else{
			$ID = $atts['ID'];
		}

		$price = $this->get_adjusted_price($ID);

		return $atts['type'] !== "raw" ? wc_price($price) : $price;
	}

	public static function get_organisations(){
		$organisations = get_option( "_donation_organisations" );

		if ( $organisations === "" || $organisations === null ){
			return array();
		}

		return $organisations;
	}

	public static function render_status_message( $status ){
        if(!$status){
            return;
        }

        $status_codes = array(
            '1' => 'Organisation saved successfully',
            '2' => 'Failed to save organisation. Invalid form submission',
            '3' => 'Failed to save organisation. Organisation name can not be empty',
            '4' => 'Failed to save organisation. Organisation already exists',
            '5' => 'Organisation removed successfully',
            '6' => 'Failed to remove organisation. Invalid request',
            '7' => 'Failed to remove organisation. Invalid organisation removal request',
        );

        if(isset($status_codes[$status])){
	        $status_message = $status_codes[$status];

	        $msg_class = ($status == 1 || $status == 5) ? "message" : "error";

            echo sprintf('<div class="alert alert-%s">%s</div>', $msg_class, $status_message);
        }
	}
}

new WOO_Donation_Calculate();