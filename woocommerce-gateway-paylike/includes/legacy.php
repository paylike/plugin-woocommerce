<?php
if ( ! function_exists( 'get_woo_id' ) ) {
    /**
     *
     * Fix to prevent protected attribute access
     *
     * @param $object
     *
     * @return mixed
     */
    function get_woo_id( $object ) {
        if ( method_exists( $object, 'get_id' ) ) {
            return $object->get_id();
        } else {
            return $object->id;
        }
    }
}
if ( ! function_exists( 'dk_get_order_currency' ) ) {
    /**
     * @param WC_Order $order
     *
     * @return mixed
     */
    function dk_get_order_currency( $order ) {
        if ( method_exists( $order, 'get_currency' ) ) {
            return $order->get_currency();
        } else {
            return $order->get_order_currency();
        }
    }
}
