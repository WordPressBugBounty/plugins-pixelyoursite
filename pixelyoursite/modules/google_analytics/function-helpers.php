<?php

namespace PixelYourSite\GA\Helpers;

use PixelYourSite;
use function PixelYourSite\GATags;
use function PixelYourSite\isWPMLActive;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Render Cross Domain Domain text field
 *
 * @param int    $index
 */
function renderCrossDomainDomain( $index = 0 ) {
    
    $slug = PixelYourSite\GA()->getSlug();
    
    $attr_name = "pys[$slug][cross_domain_domains][]";
    $attr_id = 'pys_' . $slug . '_cross_domain_domains_' . $index;
    
    $values = (array) PixelYourSite\GA()->getOption( 'cross_domain_domains' );
    $attr_value = isset( $values[ $index ] ) ? $values[ $index ] : null;
    
    ?>

    <input type="text" class="input-short" name="<?php echo esc_attr( $attr_name ); ?>"
           id="<?php echo esc_attr( $attr_id ); ?>"
           value="<?php echo esc_attr( $attr_value ); ?>"
           placeholder="Enter domain"
    >
    
    <?php
    
}

function getWooProductContentId( $product_id ) {
    return PixelYourSite\getWooProductItemId( $product_id, PixelYourSite\GATags() );
}

function getWooCartItemId( $item ) {

    if ( ! PixelYourSite\GATags()->getOption( 'woo_variable_as_simple' ) && isset( $item['variation_id'] ) && $item['variation_id'] !== 0 ) {
        $product_id = $item['variation_id'];
    } else {
        $product_id = $item['product_id'];
    }

    return $product_id;
}

/*
 * EASY DIGITAL DOWNLOADS
 */

function getEddDownloadContentId( $download_id, $price_id = null )
{
    return PixelYourSite\getEddContentId( PixelYourSite\GATags(), $download_id, $price_id );
}