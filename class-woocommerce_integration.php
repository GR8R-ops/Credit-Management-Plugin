<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_WooCommerce_Integration {
    
    public function __construct() {
        add_action('woocommerce_checkout_order_processed', [$this, 'process_order_coupons']);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_custom_coupon'], 10, 2);
        add_action('woocommerce_applied_coupon', [$this, 'mark_coupon_as_used']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'maybe_apply_auto_coupon'], 20, 1);
    }

    /**
     * Track coupon usage in order meta and mark coupon as used (in custom table)
     */
    public function process_order_coupons($order_id) {
        $order = wc_get_order($order_id);
        $coupons = $order->get_coupon_codes();

        foreach ($coupons as $coupon_code) {
            if (strpos($coupon_code, 'GR8R') === 0) {
                $this->mark_gr8r_coupon_as_used($coupon_code, $order_id);

                // Track coupon usage in order meta
                $order->update_meta_data('_gr8r_coupon_used', $coupon_code);
            }
        }
        $order->save();
    }

    /**
     * Validate the coupon according to GR8R logic
     */
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

    /**
     * Mark the coupon as used in the custom table after application
     */
    public function mark_coupon_as_used($coupon_code) {
        if (strpos($coupon_code, 'GR8R') === 0) {
            $this->mark_gr8r_coupon_as_used($coupon_code);
        }
    }

    /**
     * Validate GR8R coupon: session, vendor, user, expiry, usage
     */
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

        // Check user restriction (unless coupon is vendor/global)
        if ($coupon_data['user_id'] && $coupon_data['user_id'] != get_current_user_id()) {
            return false;
        }

        // Check session restriction (if present)
        if (!empty($coupon_data['session_id'])) {
            $user_sessions = $this->get_user_sessions(get_current_user_id());
            if (!in_array($coupon_data['session_id'], $user_sessions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark GR8R coupon as used in the custom table
     */
    private function mark_gr8r_coupon_as_used($coupon_code, $order_id = null) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'gr8r_enhanced_coupons',
            [
                'is_used' => 1,
                'used_at' => current_time('mysql'),
                'used_order_id' => $order_id,
                'metadata' => json_encode(['order_id' => $order_id])
            ],
            ['coupon_code' => $coupon_code],
            ['%d', '%s', '%d', '%s'],
            ['%s']
        );
    }

    /**
     * Get sessions for a user (for session-limited coupon validation)
     */
    private function get_user_sessions($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gr8r_enhanced_session_participants';
        return $wpdb->get_col($wpdb->prepare("SELECT session_id FROM $table WHERE user_id = %d", $user_id));
    }

    /**
     * Auto-apply valid GR8R coupon if set in session (used for auto-application flow)
     */
    public function maybe_apply_auto_coupon($cart) {
        if (!is_admin() && is_user_logged_in() && WC()->session) {
            $auto_coupon = WC()->session->get('gr8r_enhanced_auto_coupon');
            if ($auto_coupon && !$cart->has_discount($auto_coupon)) {
                $cart->add_discount($auto_coupon);
                WC()->session->set('gr8r_enhanced_auto_coupon', null);
            }
        }
    }
}