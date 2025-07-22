<?php
/**
 * Auto Apply Coupon Handler for GR8R Enhanced Plugin
 *
 * Handles automatic application of coupons for users on cart/checkout based on tokens or session/user logic.
 */

defined('ABSPATH') || exit;

class GR8R_Enhanced_Auto_Apply_Handler {

    public function __construct() {
        // Auto-apply coupon for logged-in users on cart page
        add_action('woocommerce_before_cart', [$this, 'auto_apply_coupons']);
        // Handle auto-apply coupon from URL parameter
        add_action('wp', [$this, 'handle_auto_apply_coupon']);
    }

    /**
     * Automatically apply all valid coupons for the current user, if not already applied.
     */
    public function auto_apply_coupons() {
        if (!is_user_logged_in() || !WC()->cart) {
            return;
        }

        $user_id = get_current_user_id();
        $coupons = $this->get_user_coupons($user_id);

        foreach ($coupons as $coupon) {
            if (!WC()->cart->has_discount($coupon)) {
                WC()->cart->add_discount($coupon);
            }
        }
    }

    /**
     * Get all valid coupons for a user (not expired or used).
     */
    public function get_user_coupons($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gr8r_enhanced_activity_logs';

        // Get all unique coupon codes generated for this user that are not used/expired
        $coupons = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT coupon_code 
             FROM $table 
             WHERE user_id = %d AND action = 'generated'
             ORDER BY timestamp DESC",
            $user_id
        ));

        // Filter out expired or used coupons from coupon table
        if (empty($coupons)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($coupons), '%s'));
        $coupon_table = $wpdb->prefix . 'gr8r_enhanced_coupons';
        $valid_coupons = $wpdb->get_col($wpdb->prepare(
            "SELECT coupon_code FROM $coupon_table 
             WHERE coupon_code IN ($placeholders) AND is_used = 0 AND expiry_date > %s",
            array_merge($coupons, [current_time('mysql')])
        ));

        return $valid_coupons;
    }

    /**
     * Handle auto-apply coupon from URL (e.g., ?gr8r_apply_coupon=TOKEN).
     * This can be invoked anywhere on the site.
     */
    public function handle_auto_apply_coupon() {
        if (isset($_GET['gr8r_apply_coupon']) && !empty($_GET['gr8r_apply_coupon'])) {
            $token = sanitize_text_field($_GET['gr8r_apply_coupon']);
            $coupon_code = $this->get_coupon_code_from_token($token);

            if ($coupon_code && is_user_logged_in()) {
                WC()->session->set('gr8r_enhanced_auto_coupon', $coupon_code);
                add_action('woocommerce_before_cart', function() use ($coupon_code) {
                    if (!WC()->cart->has_discount($coupon_code)) {
                        WC()->cart->add_discount($coupon_code);
                        wc_add_notice(sprintf(__('Coupon %s has been auto-applied to your cart.', 'gr8r-enhanced'), $coupon_code), 'success');
                    }
                });
            }
        }
    }

    /**
     * Retrieve coupon code from auto-apply token.
     */
    public function get_coupon_code_from_token($token) {
        global $wpdb;
        $token_table = $wpdb->prefix . 'gr8r_enhanced_auto_apply_tokens';
        $coupon_table = $wpdb->prefix . 'gr8r_enhanced_coupons';

        // Validate token and get associated coupon code
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT t.coupon_id, c.coupon_code
             FROM $token_table t
             INNER JOIN $coupon_table c ON t.coupon_id = c.coupon_id
             WHERE t.token = %s AND t.is_used = 0 AND t.expires_at > %s",
            $token, current_time('mysql')
        ));

        return $result ? $result->coupon_code : false;
    }
}

// Initialize the handler
$GLOBALS['gr8r_enhanced_auto_apply_handler'] = new GR8R_Enhanced_Auto_Apply_Handler();