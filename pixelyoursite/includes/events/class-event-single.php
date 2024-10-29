<?php
namespace PixelYourSite;
class SingleEvent extends PYSEvent{

    public $params = array(
    );
    public $payload = array(
        'delay' => 0
    );
    private $ecommerceParamArray = array(
        'currency',
        'value',
        'items',
        'tax',
        'shipping',
        'coupon',
        'affiliation',
        'transaction_id',
        'total_value',
        'ecomm_prodid',
        'ecomm_pagetype',
        'ecomm_totalvalue'
    );

    private $ecommerceEventNames = array(
        'add_payment_info',
        'add_shipping_info',
        'add_to_cart',
        'add_to_wishlist',
        'begin_checkout',
        'generate_lead',
        'purchase',
        'refund',
        'remove_from_cart',
        'select_item',
        'select_promotion',
        'view_cart',
        'view_item',
        'view_item_list',
        'view_promotion'
    );

    public function __construct($id,$type,$category=''){
        parent::__construct($id,$type,$category);
        $this->payload['type'] = $type;
    }


    /**
     * Insert Array params for event
     * @param array $data
     */
    function addParams($data) {

        if(is_array($data)) {
            if (isset($this->params['triggerType']['type']) &&
                ($this->params['triggerType']['type'] === 'ecommerce' ||
                    ( $this->params['triggerType']['type'] === 'manual' && isset($this->payload['name']) && in_array($this->payload['name'], $this->ecommerceEventNames)))) {
                foreach ( $data as $key => $value ) {
                    if ( in_array( $key, $this->ecommerceParamArray ) ) {
                        $this->params['ecommerce'][ $key ] = $data[ $key ];
                    } else {
                        $this->params[ $key ] = $data[ $key ];
                    }
                }
            }
            else{
                $this->params = array_merge($this->params, $data);
            }
        } else {
            error_log("addParams no array ".print_r($data,true));
        }

    }

    /**
     * Insert additional Array data for event
     * @param array $data
     */
    function addPayload($data) {
        if(is_array($data)) {
            $this->payload = array_merge($this->payload,$data);
        } else {
            error_log("addPayload no array ".print_r($data,true));
        }

    }

    function getData() {
        $data = $this->payload;
        $data['params'] = sanitizeParams($this->params);
        $data['e_id'] = $this->getId();

        $data['delay'] = isset( $this->payload['delay'] ) ? $this->payload['delay'] : 0;
        $data['ids'] = isset( $this->payload['ids'] ) ? $this->payload['ids'] : array();
        $data['hasTimeWindow'] = isset( $this->payload['hasTimeWindow'] )  ? $this->payload['hasTimeWindow'] : false;
        $data['timeWindow'] = isset( $this->payload['timeWindow'] )  ? $this->payload['timeWindow'] : 0;
        $data['pixelIds'] = isset( $this->payload['pixelIds'] ) ? $this->payload['pixelIds'] : array();
        $data['eventID'] = isset( $this->payload['eventID'] ) ? $this->payload['eventID'] : "";
        $data['woo_order'] = isset( $this->payload['woo_order'] ) ? $this->payload['woo_order'] : "";
        $data['edd_order'] = isset( $this->payload['edd_order'] ) ? $this->payload['edd_order'] : "";

        return $data;
    }
    function getPayloadValue($key) {
        if(isset($this->payload[$key]))
            return $this->payload[$key];
        return null;
    }
}
