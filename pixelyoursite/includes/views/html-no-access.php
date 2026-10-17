<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Access notice shown in place of a page the current user may not open.
 *
 * @var string $no_access_title
 * @var string $no_access_message
 */

?>
<div class="card card-style6 card-static">
    <div class="card-body">
        <div class="d-flex flex-column align-items-center text-center gap-24">
            <h4 class="secondary_heading_type2"><?php echo esc_html( $no_access_title ); ?></h4>
            <p class="text-gray"><?php echo esc_html( $no_access_message ); ?></p>
            <a class="btn btn-primary btn-primary-type2" href="<?php echo esc_url( buildAdminUrl( 'pixelyoursite' ) ); ?>"><?php esc_html_e( 'Go to Dashboard', 'pys' ); ?></a>
        </div>
    </div>
</div>
