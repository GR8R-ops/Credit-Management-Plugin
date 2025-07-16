
<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_gr8r_enhanced_add_credits', [$this, 'handle_add_credits']);
        add_action('admin_post_gr8r_enhanced_generate_coupon', [$this, 'handle_generate_coupon']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('GR8R Enhanced', 'gr8r-enhanced'),
            __('GR8R Enhanced', 'gr8r-enhanced'),
            'manage_options',
            'gr8r-enhanced',
            [$this, 'dashboard_page'],
            'dashicons-tickets-alt',
            30
        );
        
        add_submenu_page(
            'gr8r-enhanced',
            __('Dashboard', 'gr8r-enhanced'),
            __('Dashboard', 'gr8r-enhanced'),
            'manage_options',
            'gr8r-enhanced',
            [$this, 'dashboard_page']
        );
        
        add_submenu_page(
            'gr8r-enhanced',
            __('Credits', 'gr8r-enhanced'),
            __('Credits', 'gr8r-enhanced'),
            'manage_options',
            'gr8r-enhanced-credits',
            [$this, 'credits_page']
        );
        
        add_submenu_page(
            'gr8r-enhanced',
            __('Coupons', 'gr8r-enhanced'),
            __('Coupons', 'gr8r-enhanced'),
            'manage_options',
            'gr8r-enhanced-coupons',
            [$this, 'coupons_page']
        );
        
        add_submenu_page(
            'gr8r-enhanced',
            __('Sessions', 'gr8r-enhanced'),
            __('Sessions', 'gr8r-enhanced'),
            'manage_options',
            'gr8r-enhanced-sessions',
            [$this, 'sessions_page']
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'gr8r-enhanced') !== false) {
            wp_enqueue_style('gr8r-enhanced-admin', GR8R_ENHANCED_URL . 'assets/css/admin.css', [], GR8R_ENHANCED_VERSION);
            wp_enqueue_script('gr8r-enhanced-admin', GR8R_ENHANCED_URL . 'assets/js/admin.js', ['jquery'], GR8R_ENHANCED_VERSION, true);
            
            wp_localize_script('gr8r-enhanced-admin', 'gr8r_enhanced_admin', [
                'nonce' => wp_create_nonce('gr8r_enhanced_admin'),
                'ajax_url' => admin_url('admin-ajax.php')
            ]);
        }
    }
    
    public function dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('GR8R Enhanced Dashboard', 'gr8r-enhanced') . '</h1>';
        echo '<div class="gr8r-enhanced-stats-boxes">';
        
        // Stats
        global $wpdb;
        $total_credits = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gr8r_enhanced_credits");
        $total_coupons = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gr8r_enhanced_coupons");
        $active_coupons = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gr8r_enhanced_coupons WHERE is_used = 0 AND expiry_date > NOW()");
        $total_sessions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gr8r_enhanced_sessions");
        
        echo '<div class="gr8r-enhanced-stat-box">';
        echo '<span class="stat-number">' . $total_credits . '</span>';
        echo '<span class="stat-label">' . __('Total Credits', 'gr8r-enhanced') . '</span>';
        echo '</div>';
        
        echo '<div class="gr8r-enhanced-stat-box">';
        echo '<span class="stat-number">' . $total_coupons . '</span>';
        echo '<span class="stat-label">' . __('Total Coupons', 'gr8r-enhanced') . '</span>';
        echo '</div>';
        
        echo '<div class="gr8r-enhanced-stat-box">';
        echo '<span class="stat-number">' . $active_coupons . '</span>';
        echo '<span class="stat-label">' . __('Active Coupons', 'gr8r-enhanced') . '</span>';
        echo '</div>';
        
        echo '<div class="gr8r-enhanced-stat-box">';
        echo '<span class="stat-number">' . $total_sessions . '</span>';
        echo '<span class="stat-label">' . __('Total Sessions', 'gr8r-enhanced') . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    public function credits_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Credit Management', 'gr8r-enhanced') . '</h1>';
        
        // Add credit form
        echo '<div class="gr8r-enhanced-admin-container">';
        echo '<div class="gr8r-enhanced-admin-actions">';
        echo '<h2>' . __('Add Credits', 'gr8r-enhanced') . '</h2>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="gr8r_enhanced_add_credits">';
        wp_nonce_field('gr8r_enhanced_add_credits');
        
        echo '<p>';
        echo '<label for="user_id">' . __('User ID', 'gr8r-enhanced') . '</label>';
        echo '<input type="number" id="user_id" name="user_id" required>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="vendor_id">' . __('Vendor ID', 'gr8r-enhanced') . '</label>';
        echo '<input type="number" id="vendor_id" name="vendor_id" value="0">';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="service_type">' . __('Service Type', 'gr8r-enhanced') . '</label>';
        echo '<input type="text" id="service_type" name="service_type" value="general" required>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="amount">' . __('Amount', 'gr8r-enhanced') . '</label>';
        echo '<input type="number" id="amount" name="amount" step="0.01" required>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="description">' . __('Description', 'gr8r-enhanced') . '</label>';
        echo '<textarea id="description" name="description"></textarea>';
        echo '</p>';
        
        echo '<p>';
        echo '<input type="submit" class="button-primary" value="' . __('Add Credits', 'gr8r-enhanced') . '">';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    public function coupons_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Coupon Management', 'gr8r-enhanced') . '</h1>';
        
        // Generate coupon form
        echo '<div class="gr8r-enhanced-admin-container">';
        echo '<div class="gr8r-enhanced-admin-actions">';
        echo '<h2>' . __('Generate Coupon', 'gr8r-enhanced') . '</h2>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="gr8r_enhanced_generate_coupon">';
        wp_nonce_field('gr8r_enhanced_generate_coupon');
        
        echo '<p>';
        echo '<label for="user_id">' . __('User ID', 'gr8r-enhanced') . '</label>';
        echo '<input type="number" id="user_id" name="user_id" required>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="vendor_id">' . __('Vendor ID', 'gr8r-enhanced') . '</label>';
        echo '<input type="number" id="vendor_id" name="vendor_id" required>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="session_id">' . __('Session ID', 'gr8r-enhanced') . '</label>';
        echo '<input type="number" id="session_id" name="session_id">';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="discount_value">' . __('Discount Value', 'gr8r-enhanced') . '</label>';
        echo '<input type="number" id="discount_value" name="discount_value" step="0.01" required>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="discount_type">' . __('Discount Type', 'gr8r-enhanced') . '</label>';
        echo '<select id="discount_type" name="discount_type">';
        echo '<option value="fixed">' . __('Fixed Amount', 'gr8r-enhanced') . '</option>';
        echo '<option value="percentage">' . __('Percentage', 'gr8r-enhanced') . '</option>';
        echo '</select>';
        echo '</p>';
        
        echo '<p>';
        echo '<input type="submit" class="button-primary" value="' . __('Generate Coupon', 'gr8r-enhanced') . '">';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    public function sessions_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Session Management', 'gr8r-enhanced') . '</h1>';
        echo '<p>' . __('Session management features coming soon.', 'gr8r-enhanced') . '</p>';
        echo '</div>';
    }
    
    public function handle_add_credits() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'gr8r_enhanced_add_credits')) {
            wp_die(__('Security check failed', 'gr8r-enhanced'));
        }
        
        $credits_manager = new GR8R_Enhanced_Credits_Manager();
        $result = $credits_manager->add_credits(
            absint($_POST['user_id']),
            absint($_POST['vendor_id']),
            sanitize_text_field($_POST['service_type']),
            floatval($_POST['amount']),
            sanitize_textarea_field($_POST['description'])
        );
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=gr8r-enhanced-credits&message=credits-added'));
        } else {
            wp_redirect(admin_url('admin.php?page=gr8r-enhanced-credits&message=error'));
        }
        exit;
    }
    
    public function handle_generate_coupon() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'gr8r_enhanced_generate_coupon')) {
            wp_die(__('Security check failed', 'gr8r-enhanced'));
        }
        
        $coupon_manager = new GR8R_Enhanced_Coupon_Manager();
        $result = $coupon_manager->generate_single_use_coupon(
            absint($_POST['user_id']),
            absint($_POST['vendor_id']),
            absint($_POST['session_id']) ?: null,
            floatval($_POST['discount_value']),
            sanitize_text_field($_POST['discount_type'])
        );
        
        if ($result && !is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=gr8r-enhanced-coupons&message=coupon-generated&code=' . $result));
        } else {
            wp_redirect(admin_url('admin.php?page=gr8r-enhanced-coupons&message=error'));
        }
        exit;
    }
}
