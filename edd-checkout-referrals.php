<?php
/*
Plugin Name: Easy Digital Downloads - Checkout Referrals
Plugin URI: http://affiliatewp.com
Description: Allows a customer to select an affiliate at checkout to receive commission on their purchase
Version: 1.0
Author: Andrew Munro, Sumobi
Author URI: http://sumobi.com/
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Checkout_Referrals' ) ) {

	final class EDD_Checkout_Referrals {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of EDD Checkout Referrals exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 1.0
		 */
		private static $instance;

		/**
		 * Plugin Version
		 */
		private $version = '1.0';

		/**
		 * Plugin Title
		 */
		public $title = 'EDD Checkout Referrals';

		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0
		 *
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Checkout_Referrals ) ) {
				self::$instance = new EDD_Checkout_Referrals;
				self::$instance->setup_constants();
				self::$instance->hooks();
			}

			return self::$instance;
		}

		/**
		 * Constructor Function
		 *
		 * @since 1.0
		 * @access private
		 */
		private function __construct() {
			self::$instance = $this;
		}

		/**
		 * Reset the instance of the class
		 *
		 * @since 1.0
		 * @access public
		 * @static
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Setup plugin constants
		 *
		 * @access private
		 * @since 1.0
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version
			if ( ! defined( 'EDD_CHECKOUT_REFERRALS_VERSION' ) ) {
				define( 'EDD_CHECKOUT_REFERRALS_VERSION', $this->version );
			}

			// Plugin Folder Path
			if ( ! defined( 'EDD_CHECKOUT_REFERRALS_PLUGIN_DIR' ) ) {
				define( 'EDD_CHECKOUT_REFERRALS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL
			if ( ! defined( 'EDD_CHECKOUT_REFERRALS_PLUGIN_URL' ) ) {
				define( 'EDD_CHECKOUT_REFERRALS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File
			if ( ! defined( 'EDD_CHECKOUT_REFERRALS_PLUGIN_FILE' ) ) {
				define( 'EDD_CHECKOUT_REFERRALS_PLUGIN_FILE', __FILE__ );
			}
		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function hooks() {
			// activation
			add_action( 'admin_init', array( $this, 'activation' ) );

			// plugin meta
			add_filter( 'plugin_row_meta', array( $this, 'plugin_meta' ), 10, 2 );
			
			// settings
			add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );

			// text domain
			add_action( 'after_setup_theme', array( $this, 'load_textdomain' ) );

			// list affiliates at checkout
			add_action( 'edd_purchase_form_before_submit', array( $this, 'list_affiliates' ) );

			// check the affiliate field
			add_action( 'edd_checkout_error_checks', array( $this, 'check_affiliate_field' ), 10, 2 );

			// create referral
			add_action( 'edd_complete_purchase', array( $this, 'create_referral' ) );

			// load scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
			
			// load js in footer
			add_action( 'wp_footer', array( $this, 'footer_js' ) );

			do_action( 'edd_checkout_referrals_setup_actions' );
		}

		/**
		 * Activation function fires when the plugin is activated.
		 *
		 * This function is fired when the activation hook is called by WordPress,
		 * it flushes the rewrite rules and disables the plugin if EDD isn't active
		 * and throws an error.
		 *
		 * @since 1.0
		 * @access public
		 *
		 * @return void
		 */
		public function activation() {
			global $wpdb;
			if ( ! class_exists( 'Easy_Digital_Downloads' ) || ! class_exists( 'Affiliate_WP' ) ) {

				// is this plugin active?
				if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					// deactivate the plugin
			 		deactivate_plugins( plugin_basename( __FILE__ ) );
			 		// unset activation notice
			 		unset( $_GET[ 'activate' ] );
			 		// display notice
			 		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
				}

			}
		}

		/**
		 * Admin notices
		 *
		 * @since 1.0
		*/
		public function admin_notices() {
			$edd_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/easy-digital-downloads/easy-digital-downloads.php', false, false );

			if ( ! is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') ) {
				echo '<div class="error"><p>' . sprintf( __( 'You must install %sEasy Digital Downloads%s to use %s.', 'edd-checkout-referrals' ), '<a href="http://easydigitaldownloads.com" title="Easy Digital Downloads" target="_blank">', '</a>', $this->title ) . '</p></div>';
			}

			if ( ! is_plugin_active( 'affiliatewp/affiliate-wp.php' ) ) {
				echo '<div class="error"><p>' . sprintf( __( 'You must install %sAffiliateWP%s to use %s', 'edd-favorites' ), '<a href="https://affiliatewp.com/pricing" title="AffiliateWP" target="_blank">', '</a>', $this->title ) . '</p></div>';
			}

			if ( $edd_plugin_data['Version'] < '1.9' ) {
				echo '<div class="error"><p>' . sprintf( __( '%s requires Easy Digital Downloads Version 1.9 or greater. Please update Easy Digital Downloads.', 'edd-checkout-referrals' ), $this->title ) . '</p></div>';
			}
		}

		/**
		 * Loads the plugin language files
		 *
		 * @access public
		 * @since 1.0
		 * @return void
		 */
		public function load_textdomain() {
			// Set filter for plugin's languages directory
			$lang_dir = dirname( plugin_basename( EDD_CHECKOUT_REFERRALS_PLUGIN_DIR ) ) . '/languages/';
			$lang_dir = apply_filters( 'edd_checkout_referrals_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale        = apply_filters( 'plugin_locale',  get_locale(), 'edd_checkout_referrals' );
			$mofile        = sprintf( '%1$s-%2$s.mo', 'edd_checkout_referrals', $locale );

			// Setup paths to current locale file
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/edd-checkout-referrals/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-auto-register folder
				load_textdomain( 'edd_checkout_referrals', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-auto-register/languages/ folder
				load_textdomain( 'edd_checkout_referrals', $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'edd_checkout_referrals', false, $lang_dir );
			}
		}


		/**
		 * Modify plugin metalinks
		 *
		 * @access      public
		 * @since       1.0
		 * @param       array $links The current links array
		 * @param       string $file A specific plugin table entry
		 * @return      array $links The modified links array
		 */
		public function plugin_meta( $links, $file ) {
		    if ( $file == plugin_basename( __FILE__ ) ) {
		        $plugins_link = array(
		            '<a title="View more plugins for Easy Digital Downloads by Sumobi" href="https://easydigitaldownloads.com/blog/author/andrewmunro/?ref=166" target="_blank">' . __( 'Author\'s EDD plugins', 'edd-checkout-referrals' ) . '</a>'
		        );

		        $links = array_merge( $links, $plugins_link );
		    }

		    return $links;
		}

		

		/**
		 * Settings
		 * 
		 * @since  1.0
		 * @return array $settings
		 */
		public function settings( $settings ) {

		  	$new_settings = array(
				array(
					'id' 		=> 'edd_checkout_referrals_header',
					'name' 		=> '<strong>' . edd_checkout_referrals()->title . '</strong>',
					'type' 		=> 'header'
				),
				array(
					'id' 		=> 'edd_checkout_referrals_checkout_text',
					'name' 		=> __( 'Checkout Text', 'edd-checkout-referrals' ),
					'desc' 		=> '<p class="description">' . __( 'Enter the text that is shown with the affiliate select menu at checkout', 'edd-checkout-referrals' ) . '</p>',
					'type' 		=> 'text',
					'std'		=> __( 'Select the affiliate you would like to credit the referral to', 'edd-checkout-referrals' )
				),
				array(
					'id' 		=> 'edd_checkout_referrals_require_affiliate',
					'name' 		=> __( 'Require Affiliate Selection', 'edd-checkout-referrals' ),
					'desc' 		=> __( 'Customer must select an Affiliate to credit the referral to', 'edd-checkout-referrals' ),
					'type' 		=> 'checkbox',
				),
				array(
					'id' 		=> 'edd_checkout_referrals_affiliate_display',
					'name' 		=> __( 'Affiliate Display', 'edd-checkout-referrals' ),
					'desc' 		=> __( 'Select how the Affiliate should be displayed at checkout', 'edd-checkout-referrals' ),
					'type' 		=> 'radio',
					'options'	=> array(
						'user_nicename' => 'User Nicename',
						'display_name'	=> 'Display Name',
						'nickname'		=> 'Nickname'
					),
					'std'	=> 'user_nicename'
				),
			);

			// merge with old settings
			return array_merge( $settings, $new_settings );
		}

		/**
		 * Load scripts
		 * 
		 * @return void
		 * @since  1.0
		 */
		public function scripts() {
			$js_dir  = EDD_PLUGIN_URL . 'assets/js/';
			$css_dir = EDD_PLUGIN_URL . 'assets/css/';

			// Use minified libraries if SCRIPT_DEBUG is turned off
			$suffix  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			if ( edd_is_checkout() && ! $this->already_tracking_referral() ) {
				wp_enqueue_script( 'jquery-chosen', $js_dir . 'chosen.jquery' . $suffix . '.js', array( 'jquery' ), EDD_VERSION );
				wp_enqueue_style( 'jquery-chosen', $css_dir . 'chosen' . $suffix . '.css', array(), EDD_VERSION );
			}
		}
		
		/**
		 * JS for admin page to allow options to be visible
		 *
		 * @return  void
		 * @since 1.0
		*/
		function footer_js() { 
			if ( ! ( edd_is_checkout() && ! $this->already_tracking_referral() ) )
				return;
			?>
			<script>
				jQuery(document).ready(function ($) {

					$('.edd-select').chosen({
						width: '100%',
						no_results_text: "No affiliates match"
					});

				});
			</script>
		<?php }

		/**
		 * Check to see if user is already tracking a referral link in their cookies
		 * 
		 * @return boolean true if tracking affiliate, false otherwise
		 * @since  1.0
		 */
		public function already_tracking_referral() {
			$affwp_ref = isset( $_COOKIE['affwp_ref'] ) ? $_COOKIE['affwp_ref'] : '';

			if ( $affwp_ref )
				return (bool) true;

			return (bool) false;
		}
		
		/**
		 * Check that an affiliate has been selected
		 * @param  array $valid_data valid data
		 * @param  array $post posted data
		 * @return void
		 * @since  1.0
		 */
		public function check_affiliate_field( $valid_data, $post ) {
			
			if ( $this->already_tracking_referral() )
				return;

			$require_affiliate = edd_get_option( 'edd_checkout_referrals_require_affiliate' );

			$affiliate = isset( $post['edd_affiliate'] ) ? $post['edd_affiliate'] : '';

			if ( ! is_numeric( $affiliate ) && $require_affiliate ) {
				edd_set_error( 'invalid_affiliate', apply_filters( 'edd_checkout_referrals_require_affiliate_error', __( 'Please choose an affiliate', 'edd-checkout-referrals' ) ) );
			}
		}
		
		/**
		 * List affiliates in dropdown at checkout
		 *
		 * @return  void
		 * @since  1.0
		 */
		public function list_affiliates() {

			if ( $this->already_tracking_referral() )
				return;

			$affiliates = affiliate_wp()->affiliates->get_affiliates( array( 'status' => 'active' ) );

			$affiliate_list = array();

			foreach ( $affiliates as $affiliate ) {
				$affiliate_list[ $affiliate->affiliate_id ] = $affiliate->user_id;
			}
			
			$description = edd_get_option( 'edd_checkout_referrals_checkout_text' );

			// affiliate display
			$display = edd_get_option( 'edd_checkout_referrals_affiliate_display', 'nickname' );
			?>

			<p>
				<?php if ( $description ) : ?>
				<label for="edd-affiliate"><?php echo esc_attr( $description ); ?></label>
				<?php endif; ?>

				<select id="edd-affiliate" name="edd_affiliate" class="edd-select">
				
				<option name=""><?php _e( 'Select', 'edd-checkout-referrals' ); ?></option>
				<?php foreach ( $affiliate_list as $key => $affiliate ) : 
					$user_info = get_userdata( $affiliate );
				?>
					<option value="<?php echo $affiliate; ?>"><?php echo $user_info->$display; ?></option>
				<?php endforeach; ?>
				</select>
			</p>
		<?php }
	
		/**
		 * Increase affiliate's referral count on completed purchase
		 *
		 * @param int $payment_id Payment ID
		 * @return  void
		 * @since  1.0
		 */
		public function create_referral( $payment_id ) {

			// return if already tracking referral
			if ( $this->already_tracking_referral() )
				return;

			$payment_meta = edd_get_payment_meta( $payment_id );
			$purchase_session = edd_get_purchase_session();
			$price = $purchase_session['price'];

			$user_id = isset( $purchase_session['post_data']['edd_affiliate'] ) ? $purchase_session['post_data']['edd_affiliate'] : null;

			if ( ! is_numeric( $user_id ) )
				return;

			// get affiliate ID
			$affiliate = affiliate_wp()->affiliates->get_by( 'user_id', $user_id );
			$affiliate_id = $affiliate->affiliate_id;

			// calculate referral amount
			$amount = affwp_calc_referral_amount( $price, $affiliate_id );

			// description
			$description = '';
			$downloads   = edd_get_payment_meta_downloads( $payment_id );
			foreach ( $downloads as $key => $item ) {
				$description .= get_the_title( $item['id'] );
				if ( $key + 1 < count( $downloads ) ) {
					$description .= ', ';
				}
			}

			$customer_email = edd_get_payment_user_email( $payment_id );
			$affiliate_email = affwp_get_affiliate_email( $affiliate_id );

			if ( $affiliate_email == $customer_email ) {
				return; // Customers cannot refer themselves
			}

			// create referral
			$args = array(
				'user_id' 		=> $user_id,
				'amount'  		=> $amount,
				'reference'		=> $payment_id,
				'description'	=> $description,
				'status'		=> 'unpaid',
				'context'		=> 'edd'
			);

			// add referral
			affwp_add_referral( $args );

			// create payment note
			$referral = affiliate_wp()->referrals->get_by( 'reference', $payment_id, 'edd' );
			$amount   = affwp_currency_filter( affwp_format_amount( $referral->amount ) );
			$name     = affiliate_wp()->affiliates->get_affiliate_name( $affiliate_id );

			edd_insert_payment_note( $payment_id, sprintf( __( 'Referral #%d for %s recorded for %s', 'edd-checkout-referrals' ), $referral->referral_id, $amount, $name ) );
		}
	}
}

/**
 * Loads a single instance of EDD Checkout Referrals
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $edd_checkout_referrals = edd_checkout_referrals(); ?>
 *
 * @since 1.0
 *
 * @see EDD_Checkout_Referrals::get_instance()
 *
 * @return object Returns an instance of the EDD_Checkout_Referrals class
 */
function edd_checkout_referrals() {
	return EDD_Checkout_Referrals::get_instance();
}

/**
 * Loads plugin after all the others have loaded and have registered their hooks and filters
 *
 * @since 1.0
*/
add_action( 'plugins_loaded', 'edd_checkout_referrals', apply_filters( 'edd_checkout_referrals_action_priority', 10 ) );