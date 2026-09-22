<?php
/**
 * OpenAI Ads Event Definitions
 *
 * This file contains event definitions for the OpenAI Ads Pixel.
 * Used by both legacy UI and EST (Event Setup Tool).
 *
 * @package PixelYourSite
 */

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$field = function ( $label, $input_type = 'string', $ui_label = null ) {
    return array(
        'type'       => 'input',
        'label'      => $label,
        'ui_label'   => $ui_label === null ? $label : $ui_label,
        'name'       => 'pys[event][openai_params][' . $label . ']',
        'input_type' => $input_type,
        'required'   => false,
    );
};

$content_item = array(
    $field( 'content_id', 'string', 'contents content_id' ),
    $field( 'content_name', 'string', 'contents content_name' ),
    $field( 'content_type', 'string', 'contents content_type' ),
    $field( 'quantity', 'int', 'contents quantity' ),
);

$money = array(
    $field( 'amount', 'float' ),
    $field( 'currency' ),
);

$contents_shape        = array_merge( $money, $content_item );
$customer_action_shape = $money;
$plan_shape            = array_merge( array( $field( 'plan_id' ) ), $money, $content_item );

return array(

    /**
     * Event type => the fields the editor may offer for it.
     */
    'events' => array(
        ''                        => array(),
        'page_viewed'             => $contents_shape,
        'contents_viewed'         => $contents_shape,
        'items_added'             => $contents_shape,
        'checkout_started'        => $contents_shape,
        'order_created'           => $contents_shape,
        'lead_created'            => $customer_action_shape,
        'registration_completed'  => $customer_action_shape,
        'appointment_scheduled'   => $customer_action_shape,
        'subscription_created'    => $plan_shape,
        'trial_started'           => $plan_shape,
        'custom'                  => $plan_shape,
    ),

    /**
     * Which `data` shape each event takes, and whether it may carry fields of
     * the user's own. Read through PYS_Event_Definitions::get_openai_shapes().
     */
    'shapes' => array(
        'page_viewed'             => array( 'data_type' => 'contents' ),
        'contents_viewed'         => array( 'data_type' => 'contents' ),
        'items_added'             => array( 'data_type' => 'contents' ),
        'checkout_started'        => array( 'data_type' => 'contents' ),
        'order_created'           => array( 'data_type' => 'contents' ),
        'lead_created'            => array( 'data_type' => 'customer_action' ),
        'registration_completed'  => array( 'data_type' => 'customer_action' ),
        'appointment_scheduled'   => array( 'data_type' => 'customer_action' ),
        'subscription_created'    => array( 'data_type' => 'plan_enrollment' ),
        'trial_started'           => array( 'data_type' => 'plan_enrollment' ),
        'custom'                  => array( 'data_type' => 'custom' ),
    ),

    /**
     * The only keys a contents[] item may carry. Verbatim from the docs: "Use
     * only these fields in each contents[] item."
     */
    'content_item_fields' => array( 'id', 'name', 'content_type', 'quantity', 'amount', 'currency' ),

    /**
     * Params that take a literal value and cannot be read off the page.
     */
    'static_params' => array( 'amount', 'currency' ),
);
