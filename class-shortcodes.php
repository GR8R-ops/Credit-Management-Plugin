
<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_Shortcodes {
    
    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_styles']);
    }
    
    public function register_shortcodes() {
        add_shortcode('gr8r_enhanced_user_coupons', [$this, 'user_coupons_shortcode']);
        add_shortcode('gr8r_enhanced_vendor_coupons', [$this, 'vendor_coupons_shortcode']);
        add_shortcode('gr8r_enhanced_session_coupons', [$this, 'session_coupons_shortcode']);
        add_shortcode('gr8r_enhanced_credit_balance', [$this, 'credit_balance_shortcode']);
    }
    
    public function enqueue_frontend_styles() {
        wp_enqueue_style('gr8r-enhanced-frontend', GR8R_ENHANCED_URL . 'assets/css/frontend.css', [], GR8R_ENHANCED_VERSION);
        wp_enqueue_script('gr8r-enhanced-frontend', GR8R_ENHANCED_URL . 'assets/js/frontend.js', ['jquery'], GR8R_ENHANCED_VERSION, true);
    }
    
    /**
     * User coupons shortcode
     */
    public function user_coupons_shortcode($atts) {
        $atts = shortcode_atts([
            'user_id' => get_current_user_id(),
            'show_used' => 'false'
        ], $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your coupons.', 'gr8r-enhanced') . '</p>';
        }
        
        $coupon_manager = new GR8R_Enhanced_Coupon_Manager();
        $coupons = $coupon_manager->get_user_coupons($atts['user_id'], $atts['show_used'] === 'true');
        
        ob_start();
        include GR8R_ENHANCED_PATH . 'templates/shortcodes/user-coupons.php';
        return ob_get_clean();
    }
    
    /**
     * Vendor coupons shortcode
     */
    public function vendor_coupons_shortcode($atts) {
        $atts = shortcode_atts([
            'vendor_id' => get_current_user_id(),
            'show_used' => 'false'
        ], $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view vendor coupons.', 'gr8r-enhanced') . '</p>';
        }
        
        $coupon_manager = new GR8R_Enhanced_Coupon_Manager();
        $coupons = $coupon_manager->get_vendor_coupons($atts['vendor_id'], $atts['show_used'] === 'true');
        
        ob_start();
        include GR8R_ENHANCED_PATH . 'templates/shortcodes/vendor-coupons.php';
        return ob_get_clean();
    }
    
    /**
     * Session coupons shortcode
     */
    public function session_coupons_shortcode($atts) {
        $atts = shortcode_atts([
            'session_id' => 0
        ], $atts);
        
        if (!$atts['session_id']) {
            return '<p>' . __('Session ID is required.', 'gr8r-enhanced') . '</p>';
        }
        
        global $wpdb;
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_sessions WHERE session_id = %d",
            $atts['session_id']
        ), ARRAY_A);
        
        if (!$session) {
            return '<p>' . __('Session not found.', 'gr8r-enhanced') . '</p>';
        }
        
        ob_start();
        include GR8R_ENHANCED_PATH . 'templates/shortcodes/session-coupons.php';
        return ob_get_clean();
    }
    
    /**
     * Credit balance shortcode
     */
    public function credit_balance_shortcode($atts) {
        $atts = shortcode_atts([
            'user_id' => get_current_user_id(),
            'vendor_id' => 0,
            'service_type' => 'general'
        ], $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your balance.', 'gr8r-enhanced') . '</p>';
        }
        
        $credits_manager = new GR8R_Enhanced_Credits_Manager();
        $balance = $credits_manager->get_balance($atts['user_id'], $atts['vendor_id'], $atts['service_type']);
        
        return '<div class="gr8r-enhanced-balance">' . sprintf(__('Balance: %s credits', 'gr8r-enhanced'), number_format($balance, 2)) . '</div>';
    }
}
