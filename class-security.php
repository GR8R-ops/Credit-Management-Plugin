<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_Security {
    private static $instance = null;

    /**
     * Rate limiting configuration
     */
    private $rate_limits = [
        'coupon_apply' => ['limit' => 10, 'window' => 60],
        'coupon_generate' => ['limit' => 5, 'window' => 300],
        'admin_actions' => ['limit' => 50, 'window' => 60],
        'security_check' => ['limit' => 20, 'window' => 60]
    ];

    /**
     * Blocked IP addresses
     */
    private $blocked_ips = [];

    /**
     * Get singleton instance
     *
     * @return GR8R_Enhanced_Security
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_loaded', [$this, 'load_blocked_ips']);
    }

    public function init() {
        // Initialize only if security tables exist
        if ($this->check_security_tables_exist()) {
            add_action('wp_ajax_gr8r_enhanced_security_check', [$this, 'security_check']);
            add_action('wp_ajax_nopriv_gr8r_enhanced_security_check', [$this, 'security_check']);

            add_action('wp_ajax_gr8r_enhanced_apply_coupon', [$this, 'rate_limit_coupon_apply'], 1);
            add_action('wp_ajax_nopriv_gr8r_enhanced_apply_coupon', [$this, 'rate_limit_coupon_apply'], 1);
            add_action('wp_ajax_gr8r_enhanced_generate_coupon', [$this, 'rate_limit_coupon_generate'], 1);
            add_action('wp_ajax_gr8r_enhanced_delete_coupon', [$this, 'rate_limit_admin_actions'], 1);

            add_action('wp_ajax_gr8r_enhanced_apply_coupon', [$this, 'validate_coupon_apply_request'], 2);
            add_action('wp_ajax_gr8r_enhanced_generate_coupon', [$this, 'validate_admin_request'], 2);
            add_action('wp_ajax_gr8r_enhanced_delete_coupon', [$this, 'validate_admin_request'], 2);

            add_action('init', [$this, 'check_blocked_ip'], 1);

            if (!wp_next_scheduled('gr8r_enhanced_cleanup_old_logs')) {
                wp_schedule_event(time(), 'daily', 'gr8r_enhanced_cleanup_old_logs');
            }
            add_action('gr8r_enhanced_cleanup_old_logs', [$this, 'cleanup_old_logs']);
        }
    }

    /**
     * Check if security tables exist
     */
    private function check_security_tables_exist() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gr8r_enhanced_security_logs';
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Enhanced IP detection with validation
     */
    private function get_user_ip() {
        $ip = '';
        $server_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                        'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($server_keys as $key) {
            if (!empty($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                $ip = $_SERVER[$key];
                break;
            }
        }

        // Handle multiple IPs in X-Forwarded-For
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            foreach ($ips as $tmp_ip) {
                $tmp_ip = trim($tmp_ip);
                if (filter_var($tmp_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $tmp_ip;
                    break;
                }
            }
        }

        // Fallback to REMOTE_ADDR if no valid IP found
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        return sanitize_text_field($ip);
    }

    /**
     * Log security events with error handling
     */
    public function log_event($event, $details = '', $user_id = null) {
        global $wpdb;

        try {
            $user_id = $user_id ?: get_current_user_id();
            $ip_address = $this->get_user_ip();

            $context = [
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'referer' => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
                'request_uri' => esc_url_raw($_SERVER['REQUEST_URI'] ?? ''),
                'request_method' => sanitize_text_field($_SERVER['REQUEST_METHOD'] ?? '')
            ];

            $details = sanitize_text_field($details);
            $details_with_context = $details . ' | Context: ' . json_encode($context);

            $result = $wpdb->insert(
                $wpdb->prefix . 'gr8r_enhanced_security_logs',
                [
                    'timestamp' => current_time('mysql'),
                    'user_id' => $user_id,
                    'ip_address' => $ip_address,
                    'event' => sanitize_text_field($event),
                    'details' => $details_with_context
                ],
                ['%s', '%d', '%s', '%s', '%s']
            );

            if ($result === false) {
                error_log('GR8R Security: Failed to log event - ' . $wpdb->last_error);
                return false;
            }

            $this->check_auto_block($ip_address, $event);
            return $result;

        } catch (Exception $e) {
            error_log('GR8R Security: Exception in log_event - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load blocked IPs from DB
     */
    public function load_blocked_ips() {
        $this->blocked_ips = get_option('gr8r_enhanced_blocked_ips', []);
    }

    /**
     * Check if IP is blocked and block access if so
     */
    public function check_blocked_ip() {
        $ip = $this->get_user_ip();
        if (in_array($ip, (array)$this->blocked_ips, true)) {
            wp_die(__('Access denied for security reasons.', 'gr8r-enhanced'));
        }
    }

    /**
     * Auto-block IP after suspicious events
     */
    public function check_auto_block($ip, $event = '') {
        $block_events = ['rate_limit_exceeded', 'unauthorized_coupon_apply', 'invalid_coupon_request', 'invalid_coupon_format'];
        if (in_array($event, $block_events, true)) {
            $attempts = get_transient('gr8r_enhanced_block_attempts_' . md5($ip));
            $attempts = $attempts ? $attempts + 1 : 1;
            set_transient('gr8r_enhanced_block_attempts_' . md5($ip), $attempts, 6 * HOUR_IN_SECONDS);
            if ($attempts > 10) {
                $this->blocked_ips[] = $ip;
                update_option('gr8r_enhanced_blocked_ips', $this->blocked_ips);
            }
        }
    }

    /**
     * Enhanced rate limiting with transient fallback
     */
    public function rate_limit_check($action = 'default') {
        try {
            $ip = $this->get_user_ip();
            $config = $this->rate_limits[$action] ?? ['limit' => 10, 'window' => 60];

            // Use object cache if available
            $cache_key = "gr8r_enhanced_rate_limit_{$action}_" . md5($ip);
            $requests = wp_cache_get($cache_key);

            if ($requests === false) {
                $requests = get_transient($cache_key) ?: 0;
            }

            if ($requests >= $config['limit']) {
                $this->log_event('rate_limit_exceeded', "Rate limit exceeded for action: {$action}");
                wp_send_json_error([
                    'message' => __('Rate limit exceeded. Please try again later.', 'gr8r-enhanced'),
                    'retry_after' => $config['window']
                ], 429);
            }

            $new_count = $requests + 1;
            wp_cache_set($cache_key, $new_count, '', $config['window']);
            set_transient($cache_key, $new_count, $config['window']);

            return true;

        } catch (Exception $e) {
            error_log('GR8R Security: Exception in rate_limit_check - ' . $e->getMessage());
            return true; // Fail open rather than blocking legitimate users
        }
    }

    public function rate_limit_coupon_apply() { $this->rate_limit_check('coupon_apply'); }
    public function rate_limit_coupon_generate() { $this->rate_limit_check('coupon_generate'); }
    public function rate_limit_admin_actions() { $this->rate_limit_check('admin_actions'); }

    /**
     * Validate coupon application request with additional checks
     */
    public function validate_coupon_apply_request() {
        try {
            if (!is_user_logged_in()) {
                $this->log_event('unauthorized_coupon_apply', 'Non-logged in user attempted coupon application');
                wp_send_json_error([
                    'message' => __('Please log in to apply coupons', 'gr8r-enhanced'),
                    'login_required' => true
                ], 401);
            }

            $coupon_code = $this->sanitize_input($_POST['coupon_code'] ?? '', 'coupon_code');
            if (empty($coupon_code)) {
                $this->log_event('invalid_coupon_request', 'Missing coupon code in request');
                wp_send_json_error(__('Coupon code is required', 'gr8r-enhanced'), 400);
            }

            if (!$this->validate_coupon_code($coupon_code)) {
                $this->log_event('invalid_coupon_format', 'Invalid coupon code format: ' . $coupon_code);
                wp_send_json_error(__('Invalid coupon code format', 'gr8r-enhanced'), 400);
            }

            return true;

        } catch (Exception $e) {
            error_log('GR8R Security: Exception in validate_coupon_apply_request - ' . $e->getMessage());
            wp_send_json_error(__('An error occurred during validation', 'gr8r-enhanced'), 500);
        }
    }

    /**
     * Validate admin requests (capability check)
     */
    public function validate_admin_request() {
        if (!current_user_can('manage_woocommerce')) {
            $this->log_event('unauthorized_admin_action', 'User lacks manage_woocommerce capability');
            wp_send_json_error(__('You do not have permission to perform this action', 'gr8r-enhanced'), 403);
        }
        return true;
    }

    /**
     * Enhanced coupon code validation
     */
    public function validate_coupon_code($coupon_code) {
        // Basic format validation
        if (!is_string($coupon_code)) {
            return false;
        }
        // Check prefix and length
        $valid_prefix = (strpos($coupon_code, 'GR8R') === 0);
        $valid_length = (strlen($coupon_code) >= 10 && strlen($coupon_code) <= 50);
        $valid_chars = (preg_match('/^[A-Z0-9]+$/', $coupon_code) === 1);

        return $valid_prefix && $valid_length && $valid_chars;
    }

    /**
     * Sanitize input based on type
     */
    public function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'coupon_code':
                return strtoupper(preg_replace('/[^A-Z0-9]/', '', sanitize_text_field($input)));
            case 'int':
                return intval($input);
            case 'email':
                return sanitize_email($input);
            case 'array':
                return array_map('sanitize_text_field', (array)$input);
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Encrypt data with OpenSSL if available, fallback to base64
     */
    public function encrypt_data($data) {
        if (!is_string($data)) {
            return false;
        }
        try {
            if (function_exists('openssl_encrypt')) {
                $key = hash('sha256', wp_salt('secure_auth'), true);
                $iv_length = openssl_cipher_iv_length('AES-256-CBC');
                $iv = openssl_random_pseudo_bytes($iv_length);
                $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                return base64_encode($iv . $encrypted);
            }
            return base64_encode($data);
        } catch (Exception $e) {
            error_log('GR8R Security: Encryption failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypt data with OpenSSL if available, fallback to base64
     */
    public function decrypt_data($encrypted_data) {
        if (!is_string($encrypted_data)) {
            return false;
        }
        try {
            if (function_exists('openssl_decrypt')) {
                $data = base64_decode($encrypted_data);
                $key = hash('sha256', wp_salt('secure_auth'), true);
                $iv_length = openssl_cipher_iv_length('AES-256-CBC');
                $iv = substr($data, 0, $iv_length);
                $encrypted = substr($data, $iv_length);
                return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            }
            return base64_decode($encrypted_data);
        } catch (Exception $e) {
            error_log('GR8R Security: Decryption failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cleanup old logs (older than 30 days)
     */
    public function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gr8r_enhanced_security_logs';
        $wpdb->query($wpdb->prepare("
            DELETE FROM $table_name 
            WHERE timestamp < %s
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));
    }

    /**
     * Capability check
     */
    public static function user_can($capability) {
        return current_user_can($capability);
    }
}