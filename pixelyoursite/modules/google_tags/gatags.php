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

		$this->googleBusinessVertical = PYS()->getOption( 'google_retargeting_logic' ) == 'ecomm' ? 'retail' : 'custom';
	}
	public function enabled() {
		return $this->isEnabled;
	}
}

/**
 * @return GATags
 */
function GATags() {
	return GATags::instance();
}

GATags();