<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once 'functions-helpers.php';

use PixelYourSite\HeadFooter\Helpers;

class HeadFooter extends Settings {

    private static $_instance;

    private $is_mobile;

    private $replacements = array();

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


        add_action( 'template_redirect', array( $this, 'output_scripts' ) );

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
            if ( $this->getOption( 'woo_order_received_enabled' ) ) {
                if ( $this->getOption( 'woo_order_received_head_enabled' ) ) {
                    add_action( 'wp_head', array(
                        $this,
                        'output_head_woo_order_received'
                    ) );
                }

                if ( $this->getOption( 'woo_order_received_footer_enabled' ) ) {
                    add_action( 'wp_footer', array(
                        $this,
                        'output_footer_woo_order_received'
                    ) );
                }
            }

            return;
        }

        /**
         * Single Post
         */
        if ($this->getOption( 'enabled' )) {
            if ( is_singular() && $post ) {
                $post_meta = get_post_meta( $post->ID, '_pys_head_footer', true );
            } else {
                $post_meta = array();
            }

            if ( ! empty( $post_meta ) ) {
                if ( $this->getOption( 'head_enabled' ) ) {
                    add_action( 'wp_head', array(
                        $this,
                        'output_head_post'
                    ) );
                }

                if ( $this->getOption( 'footer_enabled' ) ) {
                    add_action( 'wp_footer', array(
                        $this,
                        'output_footer_post'
                    ) );
                }
            }

            /**
             * Global
             */
            $disabled_by_post = ! empty( $post_meta ) && isset( $post_meta['disable_global'] ) && $post_meta['disable_global'];
            if ( ! $disabled_by_post ) {
                if ( $this->getOption( 'head_enabled' ) ) {
                    add_action( 'wp_head', array(
                        $this,
                        'output_head_global'
                    ), 100 );
                }

                if ( $this->getOption( 'footer_enabled' ) ) {
                    add_action( 'wp_footer', array(
                        $this,
                        'output_footer_global'
                    ), 100);
                }
            }
        }
    }

    public function output_head_woo_order_received() {

        $scripts_any = $this->getOption( 'woo_order_received_head_any' );

        if ( $scripts_any ) {
            echo "\r\n" . $this->replace_variables( $scripts_any ) . "\r\n";
        }

        if ( $this->is_mobile ) {
            $scripts_by_device = $this->getOption( 'woo_order_received_head_mobile' );
        } else {
            $scripts_by_device = $this->getOption( 'woo_order_received_head_desktop' );
        }

        if ( $scripts_by_device ) {
            echo "\r\n" . $this->replace_variables( $scripts_by_device ) . "\r\n";
        }

    }

    public function output_footer_woo_order_received() {

        $scripts_any = $this->getOption( 'woo_order_received_footer_any' );

        if ( $scripts_any ) {
            echo "\r\n" . $this->replace_variables( $scripts_any ) . "\r\n";
        }

        if ( $this->is_mobile ) {
            $scripts_by_device = $this->getOption( 'woo_order_received_footer_mobile' );
        } else {
            $scripts_by_device = $this->getOption( 'woo_order_received_footer_desktop' );
        }

        if ( $scripts_by_device ) {
            echo "\r\n" . $this->replace_variables( $scripts_by_device ) . "\r\n";
        }

    }

    public function output_head_global() {

        $scripts_any = $this->getOption( 'head_any' );
        if ( $scripts_any ) {
            echo "\r\n" . $this->replace_variables( $scripts_any ) . "\r\n";
        }

        if ( $this->is_mobile ) {
            $scripts_by_device = $this->getOption( 'head_mobile' );
        } else {
            $scripts_by_device = $this->getOption( 'head_desktop' );
        }

        if ( $scripts_by_device ) {
            echo "\r\n" . $this->replace_variables( $scripts_by_device ) . "\r\n";
        }

    }

    public function output_footer_global() {

        $scripts_any = $this->getOption( 'footer_any' );

        if ( $scripts_any ) {
            echo "\r\n" . $this->replace_variables( $scripts_any ) . "\r\n";
        }

        if ( $this->is_mobile ) {
            $scripts_by_device = $this->getOption( 'footer_mobile' );
        } else {
            $scripts_by_device = $this->getOption( 'footer_desktop' );
        }

        if ( $scripts_by_device ) {
            echo "\r\n" . $this->replace_variables( $scripts_by_device ) . "\r\n";
        }

    }

    public function output_head_post() {
        global $post;

        $post_meta = get_post_meta( $post->ID, '_pys_head_footer', true );

        $scripts_any = isset( $post_meta['head_any'] ) ? $post_meta['head_any'] : false;

        if ( $scripts_any ) {
            echo "\r\n" . $this->replace_variables( $scripts_any ) . "\r\n";
        }

        if ( $this->is_mobile ) {
            $scripts_by_device = isset( $post_meta['head_mobile'] ) ? $post_meta['head_mobile'] : false;
        } else {
            $scripts_by_device = isset( $post_meta['head_desktop'] ) ? $post_meta['head_desktop'] : false;
        }

        if ( $scripts_by_device ) {
            echo "\r\n" . $this->replace_variables( $scripts_by_device ) . "\r\n";
        }

    }

    public function output_footer_post() {
        global $post;

        $post_meta = get_post_meta( $post->ID, '_pys_head_footer', true );

        $scripts_any = isset( $post_meta['footer_any'] ) ? $post_meta['footer_any'] : false;

        if ( $scripts_any ) {
            echo "\r\n" . $this->replace_variables( $scripts_any ) . "\r\n";
        }

        if ( $this->is_mobile ) {
            $scripts_by_device = isset( $post_meta['footer_mobile'] ) ? $post_meta['footer_mobile'] : false;
        } else {
            $scripts_by_device = isset( $post_meta['footer_desktop'] ) ? $post_meta['footer_desktop'] : false;
        }

        if ( $scripts_by_device ) {
            echo "\r\n" . $this->replace_variables( $scripts_by_device ) . "\r\n";
        }

    }

    /**
     * Replace variables with values.
     *
     * @param string $content
     *
     * @return string
     */
    private function replace_variables( $content ) {

        if ( empty( $this->replacements ) ) {
            $this->set_replacements_values();
        }

        return str_replace( array_keys( $this->replacements ), array_values( $this->replacements ), $content );

    }

    /**
     * Initialize replacements values.
     */
    private function set_replacements_values() {

        $email = Helpers\get_user_email();
        $hashed_email = "";
        if($email) {
            $hashed_email = hash('sha256', $email, false);
        }

        $replacements = array(
            '[id]'             => Helpers\get_content_id(),
            '[title]'          => Helpers\get_content_title(),
            '[categories]'     => Helpers\get_content_categories(),
            '[email]'          => $email,
            '[hashed_email]'   => $hashed_email,
            '[first_name]'     => Helpers\get_user_first_name(),
            '[last_name]'      => Helpers\get_user_last_name(),
            '[order_number]'   => Helpers\get_order_id(),
            '[order_subtotal]' => Helpers\get_order_subtotal(),
            '[order_total]'    => Helpers\get_order_total(),
            '[currency]'       => Helpers\get_order_currency(),
        );


        //url encode values
        foreach ( $replacements as $key => $value ) {
            $replacements[ $key ] = json_encode( $value );
        }

        $this->replacements = $replacements;

    }

}

/**
 * @return HeadFooter
 */
function HeadFooter() {
    return HeadFooter::instance();
}

HeadFooter();