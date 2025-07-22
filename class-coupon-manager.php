<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_Coupon_Manager {

    public function __construct() {
        add_action('wp_loaded', [$this, 'init']);
        add_action('wp', [$this, 'handle_auto_apply_coupon']);
        add_action('woocommerce_before_checkout_form', [$this, 'apply_session_coupon']);
        add_action('wp_ajax_gr8r_enhanced_generate_coupon', [$this, 'ajax_generate_coupon']);
        add_action('wp_ajax_gr8r_enhanced_delete_coupon', [$this, 'ajax_delete_coupon']);
        add_action('admin_post_gr8r_enhanced_generate_coupon', [$this, 'handle_generate_coupon']);
        
        // Schedule cleanup
        if (!wp_next_scheduled('gr8r_enhanced_cleanup_expired_coupons')) {
            wp_schedule_event(time(), 'daily', 'gr8r_enhanced_cleanup_expired_coupons');
        }
        add_action('gr8r_enhanced_cleanup_expired_coupons', [$this, 'cleanup_expired_coupons']);
    }

    public function init() {
        // Initialize coupon management functionality
        add_action('wp_ajax_gr8r_enhanced_apply_coupon', [$this, 'ajax_apply_coupon']);
        add_action('wp_ajax_nopriv_gr8r_enhanced_apply_coupon', [$this, 'ajax_apply_coupon']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script('gr8r-enhanced-frontend', plugin_dir_url(__FILE__) . '../assets/js/enhanced-frontend.js', ['jquery'], '1.0.0', true);
        wp_enqueue_style('gr8r-enhanced-frontend', plugin_dir_url(__FILE__) . '../assets/css/enhanced-frontend.css', [], '1.0.0');
        
        wp_localize_script('gr8r-enhanced-frontend', 'gr8r_enhanced_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gr8r_enhanced_coupon'),
            'messages' => [
                'copied' => __('Copied!', 'gr8r-enhanced'),
                'copy_failed' => __('Failed to copy', 'gr8r-enhanced'),
                'confirm_delete' => __('Are you sure you want to delete this coupon?', 'gr8r-enhanced')
            ]
        ]);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_script('gr8r-enhanced-admin', plugin_dir_url(__FILE__) . '../assets/js/enhanced-admin.js', ['jquery'], '1.0.0', true);
        wp_enqueue_style('gr8r-enhanced-admin', plugin_dir_url(__FILE__) . '../assets/css/enhanced-admin.css', [], '1.0.0');
    }

    /**
     * Generate single-use coupon for specific user, vendor, and session
     */
    public function generate_single_use_coupon($user_id, $vendor_id, $session_id = null, $discount_value = 0, $discount_type = 'fixed', $expiry_hours = 24) {
        global $wpdb;

        if (!$user_id || !$vendor_id || !$discount_value) {
            return new WP_Error('missing_params', __('Missing required parameters', 'gr8r-enhanced'));
        }

        // Validate user exists
        if (!get_user_by('id', $user_id)) {
            return new WP_Error('invalid_user', __('User does not exist', 'gr8r-enhanced'));
        }

        // Validate discount value
        if ($discount_type === 'percentage' && $discount_value > 100) {
            return new WP_Error('invalid_discount', __('Percentage discount cannot exceed 100%', 'gr8r-enhanced'));
        }

        // Generate unique coupon code
        $coupon_code = $this->generate_coupon_code($user_id, $vendor_id, $session_id);

        // Set expiry date
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));

        // Insert coupon
        $result = $wpdb->insert(
            $wpdb->prefix . 'gr8r_enhanced_coupons',
            [
                'coupon_code' => $coupon_code,
                'user_id' => $user_id,
                'vendor_id' => $vendor_id,
                'session_id' => $session_id,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'expiry_date' => $expiry_date,
                'is_used' => 0,
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id() ?: $vendor_id,
                'metadata' => json_encode([
                    'auto_generated' => true,
                    'source' => 'admin'
                ])
            ],
            ['%s', '%d', '%d', '%d', '%s', '%f', '%s', '%d', '%s', '%d', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create coupon', 'gr8r-enhanced'));
        }

        // Log the creation
        $this->log_coupon_activity($coupon_code, 'created', $user_id);

        return $coupon_code;
    }

    /**
     * Get user coupons
     */
    public function get_user_coupons($user_id, $include_used = false) {
        global $wpdb;

        $where = "c.user_id = %d";
        $params = [$user_id];

        if (!$include_used) {
            $where .= " AND c.is_used = 0 AND c.expiry_date > NOW()";
        }

        $query = $wpdb->prepare(
            "SELECT c.*, s.session_name, s.session_date, u.display_name as user_name
             FROM {$wpdb->prefix}gr8r_enhanced_coupons c
             LEFT JOIN {$wpdb->prefix}gr8r_enhanced_sessions s ON c.session_id = s.session_id
             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE {$where}
             ORDER BY c.created_at DESC",
            $params
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get vendor coupons
     */
    public function get_vendor_coupons($vendor_id, $include_used = false) {
        global $wpdb;

        $where = "c.vendor_id = %d";
        $params = [$vendor_id];

        if (!$include_used) {
            $where .= " AND c.is_used = 0 AND c.expiry_date > NOW()";
        }

        $query = $wpdb->prepare(
            "SELECT c.*, s.session_name, s.session_date, u.display_name as user_name
             FROM {$wpdb->prefix}gr8r_enhanced_coupons c
             LEFT JOIN {$wpdb->prefix}gr8r_enhanced_sessions s ON c.session_id = s.session_id
             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE {$where}
             ORDER BY c.created_at DESC",
            $params
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get session coupons
     */
    public function get_session_coupons($session_id, $include_used = false) {
        global $wpdb;

        $where = "c.session_id = %d";
        $params = [$session_id];

        if (!$include_used) {
            $where .= " AND c.is_used = 0 AND c.expiry_date > NOW()";
        }

        $query = $wpdb->prepare(
            "SELECT c.*, s.session_name, s.session_date, u.display_name as user_name
             FROM {$wpdb->prefix}gr8r_enhanced_coupons c
             LEFT JOIN {$wpdb->prefix}gr8r_enhanced_sessions s ON c.session_id = s.session_id
             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE {$where}
             ORDER BY c.created_at DESC",
            $params
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Validate coupon
     */
    public function validate_coupon($coupon_code, $user_id = null) {
        global $wpdb;

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_coupons WHERE coupon_code = %s",
            $coupon_code
        ), ARRAY_A);

        if (!$coupon) {
            return new WP_Error('invalid_coupon', __('Coupon not found', 'gr8r-enhanced'));
        }

        // Check if expired
        if (strtotime($coupon['expiry_date']) < time()) {
            return new WP_Error('expired_coupon', __('Coupon has expired', 'gr8r-enhanced'));
        }

        // Check if already used
        if ($coupon['is_used']) {
            return new WP_Error('used_coupon', __('Coupon has already been used', 'gr8r-enhanced'));
        }

        // Check user restriction
        if ($user_id && $coupon['user_id'] != $user_id) {
            return new WP_Error('invalid_user', __('Coupon is not valid for this user', 'gr8r-enhanced'));
        }

        return $coupon;
    }

    /**
     * Apply coupon
     */
    public function apply_coupon($coupon_code, $user_id = null) {
        $validation = $this->validate_coupon($coupon_code, $user_id);

        if (is_wp_error($validation)) {
            return $validation;
        }

        // Mark as used
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'gr8r_enhanced_coupons',
            [
                'is_used' => 1,
                'used_at' => current_time('mysql'),
                'metadata' => json_encode([
                    'applied_by' => get_current_user_id(),
                    'applied_at' => current_time('mysql')
                ])
            ],
            ['coupon_code' => $coupon_code],
            ['%d', '%s', '%s'],
            ['%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to apply coupon', 'gr8r-enhanced'));
        }

        // Log the application
        $this->log_coupon_activity($coupon_code, 'applied', $user_id);

        return $validation;
    }

    /**
     * Handle auto-apply coupon from URL
     */
    public function handle_auto_apply_coupon() {
        if (isset($_GET['coupon']) && isset($_GET['auto_apply']) && $_GET['auto_apply'] === '1') {
            $coupon_code = sanitize_text_field($_GET['coupon']);

            if (is_user_logged_in()) {
                $validation = $this->validate_coupon($coupon_code, get_current_user_id());

                if (!is_wp_error($validation)) {
                    // Store in session for checkout
                    WC()->session->set('gr8r_enhanced_auto_coupon', $coupon_code);

                    // Add notice
                    wc_add_notice(
                        sprintf(__('Coupon %s will be applied at checkout', 'gr8r-enhanced'), $coupon_code),
                        'success'
                    );
                } else {
                    wc_add_notice($validation->get_error_message(), 'error');
                }
            }
        }
    }

    /**
     * Apply session coupon at checkout
     */
    public function apply_session_coupon() {
        if (is_admin() || !WC()->session) {
            return;
        }

        $coupon_code = WC()->session->get('gr8r_enhanced_auto_coupon');
        
        if ($coupon_code) {
            $validation = $this->validate_coupon($coupon_code, get_current_user_id());
            
            if (!is_wp_error($validation)) {
                // Apply the coupon to WooCommerce cart
                if (!WC()->cart->has_discount($coupon_code)) {
                    WC()->cart->apply_coupon($coupon_code);
                }
            }
            
            // Clear from session
            WC()->session->set('gr8r_enhanced_auto_coupon', null);
        }
    }

    /**
     * AJAX apply coupon
     */
    public function ajax_apply_coupon() {
        check_ajax_referer('gr8r_enhanced_coupon', 'nonce');

        $coupon_code = sanitize_text_field($_POST['coupon_code']);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('Please log in to apply coupon', 'gr8r-enhanced'));
        }

        $result = $this->apply_coupon($coupon_code, $user_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Coupon applied successfully', 'gr8r-enhanced'));
    }

    /**
     * AJAX generate coupon
     */
    public function ajax_generate_coupon() {
        check_ajax_referer('gr8r_enhanced_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'gr8r-enhanced'));
        }

        $user_id = absint($_POST['user_id']);
        $vendor_id = absint($_POST['vendor_id']);
        $session_id = !empty($_POST['session_id']) ? absint($_POST['session_id']) : null;
        $discount_value = floatval($_POST['discount_value']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $expiry_hours = absint($_POST['expiry_hours']) ?: 24;

        $result = $this->generate_single_use_coupon($user_id, $vendor_id, $session_id, $discount_value, $discount_type, $expiry_hours);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'coupon_code' => $result,
            'message' => __('Coupon generated successfully', 'gr8r-enhanced')
        ]);
    }

    /**
     * AJAX delete coupon
     */
    public function ajax_delete_coupon() {
        check_ajax_referer('gr8r_enhanced_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'gr8r-enhanced'));
        }

        $coupon_code = sanitize_text_field($_POST['coupon_code']);
        $result = $this->delete_coupon($coupon_code);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Coupon deleted successfully', 'gr8r-enhanced'));
    }

    /**
     * Handle admin form submission
     */
    public function handle_generate_coupon() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'gr8r-enhanced'));
        }

        check_admin_referer('gr8r_enhanced_generate_coupon');

        $user_id = absint($_POST['user_id']);
        $vendor_id = absint($_POST['vendor_id']);
        $session_id = !empty($_POST['session_id']) ? absint($_POST['session_id']) : null;
        $discount_value = floatval($_POST['discount_value']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $expiry_hours = absint($_POST['expiry_hours']) ?: 24;

        $result = $this->generate_single_use_coupon($user_id, $vendor_id, $session_id, $discount_value, $discount_type, $expiry_hours);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(['error' => $result->get_error_message()], wp_get_referer()));
        } else {
            wp_redirect(add_query_arg(['success' => 'Coupon generated: ' . $result], wp_get_referer()));
        }
        exit;
    }

    /**
     * Delete coupon
     */
    public function delete_coupon($coupon_code) {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'gr8r_enhanced_coupons',
            ['coupon_code' => $coupon_code],
            ['%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete coupon', 'gr8r-enhanced'));
        }

        if ($result === 0) {
            return new WP_Error('not_found', __('Coupon not found', 'gr8r-enhanced'));
        }

        // Log the deletion
        $this->log_coupon_activity($coupon_code, 'deleted');

        return true;
    }

    /**
     * Generate unique coupon code
     */
    private function generate_coupon_code($user_id, $vendor_id, $session_id = null) {
        $prefix = 'GR8R';
        $timestamp = time();
        $random = wp_generate_password(6, false);
        $session_part = $session_id ? $session_id : 'G';

        return strtoupper($prefix . $user_id . $vendor_id . $session_part . substr($timestamp, -4) . $random);
    }

    /**
     * Get coupon by code
     */
    public function get_coupon_by_code($coupon_code) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_coupons WHERE coupon_code = %s",
            $coupon_code
        ), ARRAY_A);
    }

    /**
     * Get coupon stats
     */
    public function get_coupon_stats($vendor_id = null) {
        global $wpdb;

        $where = $vendor_id ? "WHERE vendor_id = %d" : "";
        $params = $vendor_id ? [$vendor_id] : [];

        $query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used,
            SUM(CASE WHEN is_used = 0 AND expiry_date > NOW() THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_used = 0 AND expiry_date <= NOW() THEN 1 ELSE 0 END) as expired
            FROM {$wpdb->prefix}gr8r_enhanced_coupons {$where}";

        if ($vendor_id) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Log coupon activity
     */
    private function log_coupon_activity($coupon_code, $action, $user_id = null) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'gr8r_enhanced_activity_logs',
            [
                'coupon_code' => $coupon_code,
                'action' => $action,
                'user_id' => $user_id ?: get_current_user_id(),
                'timestamp' => current_time('mysql'),
                'ip_address' => $this->get_client_ip()
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }

    /**
     * Delete expired coupons
     */
    public function cleanup_expired_coupons() {
        global $wpdb;

        return $wpdb->query(
            "DELETE FROM {$wpdb->prefix}gr8r_enhanced_coupons 
             WHERE expiry_date < NOW() AND is_used = 0"
        );
    }

    /**
     * Create WooCommerce coupon
     */
    public function create_woocommerce_coupon($coupon_data) {
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_data['coupon_code']);
        $coupon->set_discount_type($coupon_data['discount_type'] === 'percentage' ? 'percent' : 'fixed_cart');
        $coupon->set_amount($coupon_data['discount_value']);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_date_expires(strtotime($coupon_data['expiry_date']));
        $coupon->set_email_restrictions([$coupon_data['user_email']]);
        
        return $coupon->save();
    }

    /**
     * Generate coupon URL with auto-apply
     */
    public function generate_coupon_url($coupon_code, $redirect_url = null) {
        $url = $redirect_url ?: wc_get_cart_url();
        return add_query_arg([
            'coupon' => $coupon_code,
            'auto_apply' => '1'
        ], $url);
    }

    /**
     * Get coupon usage statistics
     */
    public function get_usage_statistics($period = '30 days') {
        global $wpdb;

        $date_from = date('Y-m-d H:i:s', strtotime("-{$period}"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as created,
                SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used
             FROM {$wpdb->prefix}gr8r_enhanced_coupons 
             WHERE created_at >= %s
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            $date_from
        ), ARRAY_A);
    }
}