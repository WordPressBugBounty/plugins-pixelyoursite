<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$no_access_title   = __( "You don't have access to logs", 'pys' );
$no_access_message = __( 'Logs can contain customer data. Ask a site administrator to add your role to "Customer Data Export" in Global Settings → User role permissions.', 'pys' );

include __DIR__ . '/html-no-access.php';
