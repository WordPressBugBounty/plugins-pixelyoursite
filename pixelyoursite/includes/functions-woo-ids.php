<?php
/**
 * WooCommerce product ID resolution shared by every tag.
 *
 * Every module used to carry its own copy of the "ID source / SKU fallback /
 * prefix / suffix" logic. They all live here now; the per-module helpers
 * (getFacebookWooProductContentId(), GA\Helpers\getWooProductContentId(), ...)
 * are thin wrappers that keep their historical return shape.
 *
 * Two kinds of ID exist:
 *
 *  - Item ID (getWooProductItemId): a simple product, a variation, or a
 *    variable parent listed as a product. Follows the tag's ID source
 *    (product_id / product_sku) and is never empty.
 *  - Group ID (getWooProductGroupId): the variable parent used to tie its
 *    variations together (Meta / TikTok `product_group` events, the feed's
 *    item_group_id). Always the parent's post ID, never the SKU.
 *
 * Both get the tag's prefix and suffix.
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Option key of the per-tag "If a variation has no SKU of its own" setting.
 */
const WOO_CONTENT_ID_VARIATION_SKU_KEY = 'woo_content_id_variation_sku';

/**
 * Resolve the raw item ID (before prefix/suffix) for a product post ID.
 *
 * @param int|string $product_id Post ID of the item (variation ID for variations).
 * @param Settings   $settings   The tag whose woo_content_id* options apply.
 *
 * @return string Never empty for product_id / product_sku sources.
 *                For the Pro-only custom_field source an empty string means
 *                "no value", exactly as before.
 */
function getWooItemIdRaw( $product_id, $settings ) {

	$source = $settings->getOption( 'woo_content_id' );

	if ( $source == 'product_sku' ) {

		$product = pysWooGetProduct( $product_id );

		if ( $product && $product->is_type( 'variation' ) ) {

			if ( $settings->getOption( WOO_CONTENT_ID_VARIATION_SKU_KEY, 'parent_sku' ) === 'variation_id' ) {
				// Only the variation's own SKU; never inherit the parent's.
				$content_id = $product->get_sku( 'edit' );
			} else {
				// Legacy: get_sku() returns the parent's SKU when the variation has none.
				$content_id = $product->get_sku();
				if ( empty( $content_id ) ) {
					$parent_product = pysWooGetProduct( $product->get_parent_id() );
					if ( $parent_product ) {
						$content_id = $parent_product->get_sku();
					}
				}
			}

			if ( empty( $content_id ) ) {
				$content_id = $product_id;
			}

		} elseif ( $product ) {
			$content_id = $product->get_sku();
			if ( empty( $content_id ) ) {
				$content_id = $product_id;
			}
		} else {
			$content_id = $product_id;
		}

	} elseif ( $source == 'custom_field' ) {

		$field_name = $settings->getOption( 'woo_content_id_custom_field_name' );

		if ( empty( $field_name ) ) {
			return '';
		}

		$product = pysWooGetProduct( $product_id );

		if ( $product && $product->is_type( 'variation' ) ) {
			$content_id = get_post_meta( $product_id, $field_name, true );
			if ( empty( $content_id ) ) {
				$content_id = get_post_meta( $product->get_parent_id(), $field_name, true );
			}
		} else {
			$content_id = get_post_meta( $product_id, $field_name, true );
		}

		if ( empty( $content_id ) ) {
			if ( class_exists( '\PixelYourSite\PysContentIdScanner' ) ) {
				PysContentIdScanner::logMissingProduct( $settings->getSlug(), (int) $product_id );
			}
			return '';
		}

	} else {
		$content_id = $product_id;
	}

	return (string) $content_id;
}

/**
 * Apply the tag's prefix and suffix.
 *
 * @param string   $content_id
 * @param Settings $settings
 * @param bool     $trim Whether to trim() prefix and suffix (module-specific legacy).
 *
 * @return string
 */
function applyWooContentIdAffixes( $content_id, $settings, $trim = false ) {

	$prefix = (string) $settings->getOption( 'woo_content_id_prefix' );
	$suffix = (string) $settings->getOption( 'woo_content_id_suffix' );

	if ( $trim ) {
		$prefix = trim( $prefix );
		$suffix = trim( $suffix );
	}

	return $prefix . $content_id . $suffix;
}

/**
 * Item ID with prefix and suffix, after WPML mapping.
 *
 * @param int|string $product_id
 * @param Settings   $settings
 * @param bool       $trim
 *
 * @return string Empty only for the custom_field source with no value.
 */
function getWooProductItemId( $product_id, $settings, $trim = false ) {

	if ( isWPMLActive() ) {
		$product_id = getWPMLProductId( $product_id, $settings );
	}

	$content_id = getWooItemIdRaw( $product_id, $settings );

	if ( $content_id === '' ) {
		return '';
	}

	return applyWooContentIdAffixes( $content_id, $settings, $trim );
}

/**
 * Group ID: the variable parent's post ID with prefix and suffix.
 *
 * Accepts either the parent's or a variation's post ID.
 *
 * @param int|string $product_id
 * @param Settings   $settings
 * @param bool       $trim
 *
 * @return string
 */
function getWooProductGroupId( $product_id, $settings, $trim = false ) {

	if ( isWPMLActive() ) {
		$product_id = getWPMLProductId( $product_id, $settings );
	}

	$product = pysWooGetProduct( $product_id );

	if ( $product && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
		$product_id = $product->get_parent_id();
	}

	return applyWooContentIdAffixes( (string) $product_id, $settings, $trim );
}
