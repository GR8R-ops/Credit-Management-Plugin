
<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_WooCommerce_Integration {
    
    public function __construct() {
        add_action('woocommerce_checkout_order_processed', [$this, 'process_order_coupons']);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_custom_coupon'], 10, 2);
        add_action('woocommerce_applied_coupon', [$this, 'mark_coupon_as_used']);
    }
    
    public function process_order_coupons($order_id) {
        $order = wc_get_order($order_id);
        $coupons = $order->get_coupon_codes();
        
        foreach ($coupons as $coupon_code) {
            $this->mark_gr8r_coupon_as_used($coupon_code, $order_id);
        }
    }
    
    public function validate_custom_coupon($valid, $coupon) {
        if (!$valid) {
            return $valid;
        }
        
        // Check if it's a GR8R coupon
        if (strpos($coupon->get_code(), 'GR8R') === 0) {
            return $this->validate_gr8r_coupon($coupon);
        }
        
        return $valid;
    }
    
    public function mark_coupon_as_used($coupon_code) {
        if (strpos($coupon_code, 'GR8R') === 0) {
            $this->mark_gr8r_coupon_as_used($coupon_code);
        }
    }
    
    private function validate_gr8r_coupon($coupon) {
        global $wpdb;
        
        $coupon_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_coupons WHERE coupon_code = %s",
            $coupon->get_code()
        ), ARRAY_A);
        
        if (!$coupon_data) {
            return false;
        }
        
        // Check if expired
        if (strtotime($coupon_data['expiry_date']) < time()) {
            return false;
        }
        
        // Check if already used
        if ($coupon_data['is_used']) {
            return false;
        }
        
        // Check user restriction
        if ($coupon_data['user_id'] != get_current_user_id()) {
            return false;
        }
        
        return true;
    }
    
    private function mark_gr8r_coupon_as_used($coupon_code, $order_id = null) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'gr8r_enhanced_coupons',
            [
                'is_used' => 1,
                'used_at' => current_time('mysql'),
                'metadata' => json_encode(['order_id' => $order_id])
            ],
            ['coupon_code' => $coupon_code],
            ['%d', '%s', '%s'],
            ['%s']
        );
    }
}
