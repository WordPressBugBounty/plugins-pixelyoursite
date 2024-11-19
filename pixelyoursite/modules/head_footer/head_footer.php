<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class HeadFooter extends Settings {

	private static $_instance;

	private $is_mobile;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	public function __construct() {
		
		parent::__construct( 'head_footer' );
		
		$this->locateOptions(
            PYS_FREE_PATH . '/modules/head_footer/options_fields.json',
            PYS_FREE_PATH . '/modules/head_footer/options_defaults.json'
		);
		
		add_action( 'pys_register_plugins', function( $core ) {
			/** @var PYS $core */
			$core->registerPlugin( $this );
		} );

		if ( $this->getOption( 'enabled' ) ) {
			add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_meta_box' ) );
		}
		
		if ( $this->getOption( 'enabled' ) ) {
			add_action( 'template_redirect', array( $this, 'output_scripts' ) );
		}
		
	}

	/**
	 * Register meta box for each public post type.
	 */
	public function register_meta_box() {
		
		if ( current_user_can( 'manage_pys' ) && current_user_can('unfiltered_html') ) {

			$screens = get_post_types( array( 'public' => true ) );

			foreach ( $screens as $screen ) {
				add_meta_box( 'pys-head-footer', 'PixelYourSite Head & Footer Scripts',
					array( $this, 'render_meta_box' ),
					$screen );
			}

		}

	}

	public function render_meta_box() {
		include 'views/html-meta-box.php';
	}
	/*function check_for_script_with_base64_or_base64($data) {
		// Checking for the presence of a <script> tag with base64 data
		if (preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $data, $matches)) {
			foreach ($matches[1] as $script_content) {
				// Checking the contents of the <script> tag for the presence of a base64 string
				if ($this->is_base64($script_content)) {
					return true;
				}
			}
		}

		// Checking for a base64 encoded string
		if ($this->is_base64($data)) {
			return true;
		}

		return false;
	}

	function is_base64($string) {
		// Checking if the string is potentially base64 (valid characters only)
		if (preg_match('/^[a-zA-Z0-9\/+]*={0,2}$/', $string)) {
			// Trying to decode a string
			$decoded_data = base64_decode($string, true);

			// If decoding is successful and the result is encoded back into the same string
			if ($decoded_data !== false && base64_encode($decoded_data) === $string) {
				return true;
			}
		}
		return false;
	}*/
	public function save_meta_box( $post_id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! current_user_can( 'manage_pys' ) && ! current_user_can('unfiltered_html')) {
			return;
		}

		if ( ! isset( $_POST['pys_head_footer'] ) ) {
		//	delete_post_meta( $post_id, '_pys_head_footer' );
			return;
		}

		$data = $_POST['pys_head_footer'];
        $meta = array(
            'disable_global' => isset( $data['disable_global'] ) ? true : false,
        );
        foreach ( $data as $key => $val ) {
	        /*if (!empty($val) && $this->check_for_script_with_base64_or_base64($val)) {
		        //
		        error_log('Find base64 in:'. print_r($val,true));
				continue;
	        }*/
			switch ($key) {
				case "head_any":
					$meta['head_any'] = isset($val) ? trim($val) : '';
					break;
				case "head_desktop":
					$meta['head_desktop'] = isset($val) ? trim($val) : '';
					break;
				case "head_mobile":
					$meta['head_mobile'] = isset($val) ? trim($val) : '';
					break;
				case "footer_any":
					$meta['footer_any'] = isset($val) ? trim($val) : '';
					break;
				case "footer_desktop":
					$meta['footer_desktop'] = isset($val) ? trim($val) : '';
					break;
				case "footer_mobile":
					$meta['footer_mobile'] = isset($val) ? trim($val) : '';
					break;
			}
        }

		update_post_meta( $post_id, '_pys_head_footer', $meta );

	}

	public function output_scripts() {
		global $post;

		if ( is_admin() || defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) ) {
			return;
		}
        
        $this->is_mobile = wp_is_mobile();

		/**
		 * WooCommerce Order Received page
		 */

		if ( isWooCommerceActive() && PYS()->woo_is_order_received_page() ) {
			add_action( 'wp_head', array( $this, 'output_head_woo_order_received' ) );
			add_action( 'wp_footer', array( $this, 'output_footer_woo_order_received' ) );
		}

		$disabled_by_woo = isWooCommerceActive() && PYS()->woo_is_order_received_page() &&
		                   $this->getOption( 'woo_order_received_disable_global' );

		if ( $disabled_by_woo ) {
			return;
		}

		/**
		 * Single Post
		 */

		if ( is_singular() && $post ) {
			$post_meta = get_post_meta( $post->ID, '_pys_head_footer', true );
		} else {
			$post_meta = array();
		}

		if ( ! empty( $post_meta ) ) {
			add_action( 'wp_head', array( $this, 'output_head_post' ) );
			add_action( 'wp_footer', array( $this, 'output_footer_post' ) );
		}

		/**
		 * Global
		 */

		$disabled_by_post = ! empty( $post_meta ) && isset($post_meta['disable_global']) && $post_meta['disable_global'];

		if ( ! $disabled_by_post ) {
			add_action( 'wp_head', array( $this, 'output_head_global' ) );
			add_action( 'wp_footer', array( $this, 'output_footer_global' ) );
		}

	}

	public function output_head_woo_order_received() {

		$scripts_any = esc_js($this->getOption( 'woo_order_received_head_any' ));

		if ( $scripts_any ) {
            echo "\r\n{$scripts_any}\r\n";
		}

		if ( $this->is_mobile ) {
			$scripts_by_device = $this->getOption( 'woo_order_received_head_mobile' );
		} else {
			$scripts_by_device = $this->getOption( 'woo_order_received_head_desktop' );
		}

		if ( $scripts_by_device ) {
			echo "\r\n{$scripts_by_device}\r\n";
		}

	}

	public function output_footer_woo_order_received() {

		$scripts_any = $this->getOption( 'woo_order_received_footer_any' );

		if ( $scripts_any ) {
            echo "\r\n{$scripts_any}\r\n";
		}

		if ( $this->is_mobile ) {
			$scripts_by_device = $this->getOption( 'woo_order_received_footer_mobile' );
		} else {
			$scripts_by_device = $this->getOption( 'woo_order_received_footer_desktop' );
		}

		if ( $scripts_by_device ) {
            echo "\r\n{$scripts_by_device}\r\n";
		}

	}

	public function output_head_global() {

		$scripts_any = $this->getOption( 'head_any' );

		if ( $scripts_any ) {
            echo "\r\n{$scripts_any}\r\n";
		}

		if ( $this->is_mobile ) {
			$scripts_by_device = $this->getOption( 'head_mobile' );
		} else {
			$scripts_by_device = $this->getOption( 'head_desktop' );
		}

		if ( $scripts_by_device ) {
            echo "\r\n{$scripts_by_device}\r\n";
		}

	}

	public function output_footer_global() {

		$scripts_any = $this->getOption( 'footer_any' );

		if ( $scripts_any ) {
            echo "\r\n{$scripts_any}\r\n";
		}

		if ( $this->is_mobile ) {
			$scripts_by_device = $this->getOption( 'footer_mobile' );
		} else {
			$scripts_by_device = $this->getOption( 'footer_desktop' );
		}

		if ( $scripts_by_device ) {
            echo "\r\n{$scripts_by_device}\r\n";
		}

	}

	public function output_head_post() {
		global $post;

		$post_meta = get_post_meta( $post->ID, '_pys_head_footer', true );

		$scripts_any = isset( $post_meta['head_any'] ) ? $post_meta['head_any'] : false;

		if ( $scripts_any ) {
            echo "\r\n{$scripts_any}\r\n";
		}

		if ( $this->is_mobile ) {
			$scripts_by_device = isset( $post_meta['head_mobile'] ) ? $post_meta['head_mobile'] : false;
		} else {
			$scripts_by_device = isset( $post_meta['head_desktop'] ) ? $post_meta['head_desktop'] : false;
		}

		if ( $scripts_by_device ) {
            echo "\r\n{$scripts_by_device}\r\n";
		}

	}

	public function output_footer_post() {
		global $post;

		$post_meta = get_post_meta( $post->ID, '_pys_head_footer', true );

		$scripts_any = isset( $post_meta['footer_any'] ) ? $post_meta['footer_any'] : false;

		if ( $scripts_any ) {
			echo "\r\n{$scripts_any}\r\n";
		}

		if ( $this->is_mobile ) {
			$scripts_by_device = isset( $post_meta['footer_mobile'] ) ? $post_meta['footer_mobile'] : false;
		} else {
			$scripts_by_device = isset( $post_meta['footer_desktop'] ) ? $post_meta['footer_desktop'] : false;
		}

		if ( $scripts_by_device ) {
            echo "\r\n{$scripts_by_device}\r\n";
		}

	}

}

/**
 * @return HeadFooter
 */
function HeadFooter() {
	return HeadFooter::instance();
}

HeadFooter();