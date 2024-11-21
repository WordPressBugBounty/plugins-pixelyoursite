<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_FREE_PATH . '/modules/google_analytics/function-helpers.php';

use PixelYourSite\GA\Helpers;
use WC_Product;

require_once PYS_FREE_PATH . '/modules/google_analytics/function-collect-data-4v.php';

class GATags extends Settings {

	private static $_instance;
	private $isEnabled;

	private $googleBusinessVertical;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	public function __construct() {

		parent::__construct( 'gatags' );

		$this->locateOptions(
			PYS_FREE_PATH . '/modules/google_tags/options_fields.json',
            PYS_FREE_PATH . '/modules/google_tags/options_defaults.json'
		);

		$this->isEnabled = GA()->enabled();
        if($this->isEnabled){
            if(!is_admin() && $this->getOption('gtag_datalayer_type') !== 'disable') {
                add_action( 'wp_head', array($this,'pys_wp_header_top'), 1, 0 );
                add_action('template_redirect', array($this,'start_output_buffer'), 0);
                add_action('wp_footer', array($this,'end_output_buffer'), 100);
            }

        }
		$this->googleBusinessVertical = PYS()->getOption( 'google_retargeting_logic' ) == 'ecomm' ? 'retail' : 'custom';
	}
	public function enabled() {
		return $this->isEnabled;
	}
    public function pys_wp_header_top( $echo = true ) {

        $dataLayerName = 'dataLayerPYS';

        switch ($this->getOption('gtag_datalayer_type')){
            case 'disable':
                $dataLayerName = 'dataLayer';
                break;
            case 'default':
                $dataLayerName = 'dataLayerPYS';
                break;
            case 'custom':
                $dataLayerName = $this->getOption('gtag_datalayer_name');
                break;
        }

        $has_html5_support    = current_theme_supports( 'html5' );

        $_gtm_top_content = '
<!-- Google Tag Manager by PYS -->
    <script data-cfasync="false" data-pagespeed-no-defer' . ( $has_html5_support ? ' type="text/javascript"' : '' ) . '>
	    window.'.$dataLayerName.' = window.'.$dataLayerName.' || [];
	</script>
<!-- End Google Tag Manager by PYS -->';

        if ( $echo ) {
            echo wp_kses(
                $_gtm_top_content,
                array(
                    'script' => array(
                        'data-cfasync'            => array(),
                        'data-pagespeed-no-defer' => array(),
                        'data-cookieconsent'      => array(),
                    ),
                )
            );
        } else {
            return $_gtm_top_content;
        }
    }

    public function modify_analytics_datalayer($buffer) {
        $dataLayerName = 'dataLayerPYS';

        switch ($this->getOption('gtag_datalayer_type')){
            case 'disable':
                $dataLayerName = 'dataLayer';
                break;
            case 'default':
                $dataLayerName = 'dataLayerPYS';
                break;
            case 'custom':
                $dataLayerName = $this->getOption('gtag_datalayer_name');
                break;
        }

        $buffer = preg_replace_callback('/(<script\s+[^>]*src="https:\/\/www\.googletagmanager\.com\/gtag\/js\?id=[^"]+)/', function($matches)  use ($dataLayerName) {
            if (strpos($matches[0], '&l=dataLayer') !== false) {
                return str_replace('&l=dataLayer', '&l='.$dataLayerName, $matches[0]);
            } else {
                return $matches[0] . '&l=' . $dataLayerName;
            }
        }, $buffer);

        $buffer = preg_replace_callback(
            '/gtag\((.*?)\);/s',
            function($matches)  use ($dataLayerName) {
                return str_replace('dataLayer', $dataLayerName, $matches[0]);
            },
            $buffer
        );


        return $buffer;
    }

// Включение буферизации вывода и фильтрации
    public function start_output_buffer() {
        ob_start([$this, 'modify_analytics_datalayer']);
    }
    public function end_output_buffer() {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
}

/**
 * @return GATags
 */
function GATags() {
	return GATags::instance();
}

GATags();