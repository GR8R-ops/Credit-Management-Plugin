<?php
/*
Plugin Name: GR8R Coupon Management
Description: Enhanced coupon management system for WordPress with WooCommerce, WooCommerce Bookings, and Dokan Pro integration
Version: 7.0.0
Author: Resego
Text Domain: gr8r-enhanced
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 8.0
*/

defined('ABSPATH') || exit;

// Define constants
define('GR8R_ENHANCED_VERSION', '7.0.0');
define('GR8R_ENHANCED_PATH', plugin_dir_path(__FILE__));
define('GR8R_ENHANCED_URL', plugin_dir_url(__FILE__));

// Load dependencies
$autoload_files = [
    'enhanced-includes/class-security.php',
    'enhanced-includes/class-credits-manager.php',
    'enhanced-includes/class-coupon-manager.php',
    'enhanced-includes/class-shortcodes.php',
    'enhanced-includes/class-admin.php',
    'enhanced-includes/class-woocommerce-integration.php',
    'enhanced-includes/class-bookings-integration.php',
    'enhanced-includes/class-dokan-integration.php',
    'enhanced-includes/class-auto-apply-handler.php'
];
foreach ($autoload_files as $file) {
    $path = GR8R_ENHANCED_PATH . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        add_action('admin_notices', function () use ($file) {
            echo '<div class="notice notice-error"><p>GR8R Enhanced missing required file: ' . esc_html($file) . '</p></div>';
        });
    }
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'gr8r_enhanced_create_database_tables');
register_deactivation_hook(__FILE__, 'gr8r_enhanced_plugin_deactivation');

// Load text domain and initialize
add_action('plugins_loaded', function() {
    load_plugin_textdomain('gr8r-enhanced', false, basename(GR8R_ENHANCED_PATH) . '/languages/');
    
    // Check dependencies
    $missing_dependencies = gr8r_enhanced_check_dependencies();
    if (!empty($missing_dependencies)) {
        add_action('admin_notices', function() use ($missing_dependencies) {
            echo '<div class="notice notice-error"><p>' . 
                 sprintf(__('GR8R Enhanced requires the following plugins to be active: %s', 'gr8r-enhanced'), 
                 implode(', ', $missing_dependencies)) . '</p></div>';
        });
        return;
    }
    
    // Initialize classes
    GR8R_Enhanced_Security::get_instance();
    new GR8R_Enhanced_Credits_Manager();
    new GR8R_Enhanced_Coupon_Manager();
    new GR8R_Enhanced_Shortcodes();
    new GR8R_Enhanced_Admin();
    new GR8R_Enhanced_WooCommerce_Integration();
    new GR8R_Enhanced_Bookings_Integration();
    new GR8R_Enhanced_Dokan_Integration();
    new GR8R_Enhanced_Auto_Apply_Handler();
});

/**
 * Check for required plugin dependencies
 */
function gr8r_enhanced_check_dependencies() {
    $missing = [];
    if (!class_exists('WooCommerce')) {
        $missing[] = 'WooCommerce';
    }
    if (!class_exists('WC_Bookings')) {
        $missing[] = 'WooCommerce Bookings';
    }
    if (!class_exists('WeDevs_Dokan')) {
        $missing[] = 'Dokan Pro';
    }

    if (!empty($missing)) {
        add_action('admin_notices', function() use ($missing) {
            echo '<div class="notice notice-error"><p>' . 
                 sprintf(
                     __('GR8R Enhanced requires the following plugins to be installed and active: %s', 'gr8r-enhanced'), 
                     implode(', ', $missing)
                 ) . '</p></div>';
        });
        
        // Deactivate the plugin
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins(plugin_basename(__FILE__));
    }

    return $missing;
}

/**
 * Create database tables on activation
 */
function gr8r_enhanced_create_database_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $tables = [];

    // Credits Table
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_credits (
        credit_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        vendor_id BIGINT(20) UNSIGNED DEFAULT 0,
        service_type VARCHAR(50) NOT NULL,
        balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        last_updated DATETIME NOT NULL,
        PRIMARY KEY  (credit_id),
        KEY user_id (user_id),
        KEY vendor_service (vendor_id, service_type)
    ) $charset_collate;";

    // Credit Transactions Table (with expiry_date and linked_transactions!)
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_credit_transactions (
        transaction_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        credit_id BIGINT(20) UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        transaction_type ENUM('credit', 'debit') NOT NULL,
        reference VARCHAR(100) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_by BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        expiry_date DATETIME DEFAULT NULL,
        linked_transactions TEXT DEFAULT NULL,
        PRIMARY KEY  (transaction_id),
        KEY credit_id (credit_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    // Activity Logs Table
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_activity_logs (
        log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        coupon_code VARCHAR(50) NOT NULL,
        action VARCHAR(50) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        timestamp DATETIME NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        PRIMARY KEY (log_id),
        KEY coupon_code (coupon_code),
        KEY user_id (user_id),
        KEY action (action)
    ) $charset_collate;";

    // Enhanced Coupons Table
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_coupons (
        coupon_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        coupon_code VARCHAR(50) NOT NULL UNIQUE,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        vendor_id BIGINT(20) UNSIGNED NOT NULL,
        session_id BIGINT(20) UNSIGNED DEFAULT NULL,
        product_id BIGINT(20) UNSIGNED DEFAULT NULL,
        booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
        discount_type ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
        discount_value DECIMAL(15,2) NOT NULL,
        minimum_amount DECIMAL(15,2) DEFAULT NULL,
        expiry_date DATETIME NOT NULL,
        auto_apply_token VARCHAR(100) DEFAULT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        used_at DATETIME NULL,
        used_order_id BIGINT(20) UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL,
        created_by BIGINT(20) UNSIGNED NOT NULL,
        metadata TEXT DEFAULT NULL,
        PRIMARY KEY  (coupon_id),
        KEY coupon_code (coupon_code),
        KEY user_vendor (user_id, vendor_id),
        KEY session_id (session_id),
        KEY expiry_date (expiry_date),
        KEY is_used (is_used),
        KEY auto_apply_token (auto_apply_token)
    ) $charset_collate;";

    // Enhanced Sessions Table
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_sessions (
        session_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        vendor_id BIGINT(20) UNSIGNED NOT NULL,
        wrapper_product_id BIGINT(20) UNSIGNED DEFAULT NULL,
        bookable_product_id BIGINT(20) UNSIGNED DEFAULT NULL,
        session_name VARCHAR(255) NOT NULL,
        session_description TEXT DEFAULT NULL,
        session_date DATETIME NOT NULL,
        session_end_date DATETIME DEFAULT NULL,
        max_participants INT(11) DEFAULT NULL,
        current_participants INT(11) DEFAULT 0,
        price DECIMAL(15,2) DEFAULT NULL,
        booking_url VARCHAR(500) DEFAULT NULL,
        status ENUM('active', 'inactive', 'full', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
        booking_status ENUM('open', 'closed', 'waitlist') NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (session_id),
        KEY vendor_id (vendor_id),
        KEY wrapper_product_id (wrapper_product_id),
        KEY bookable_product_id (bookable_product_id),
        KEY session_date (session_date),
        KEY status (status),
        KEY booking_status (booking_status)
    ) $charset_collate;";

    // Session Participants Table
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_session_participants (
        participant_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
        order_id BIGINT(20) UNSIGNED DEFAULT NULL,
        coupon_used VARCHAR(50) DEFAULT NULL,
        status ENUM('registered', 'attended', 'no_show', 'cancelled') NOT NULL DEFAULT 'registered',
        registered_at DATETIME NOT NULL,
        PRIMARY KEY  (participant_id),
        KEY session_user (session_id, user_id),
        KEY booking_id (booking_id),
        KEY order_id (order_id),
        UNIQUE KEY unique_session_user (session_id, user_id)
    ) $charset_collate;";

    // Wrapper Products Table (for linking products to sessions)
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_wrapper_products (
        wrapper_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        vendor_id BIGINT(20) UNSIGNED NOT NULL,
        session_id BIGINT(20) UNSIGNED DEFAULT NULL,
        bookable_product_id BIGINT(20) UNSIGNED DEFAULT NULL,
        wrapper_type ENUM('session', 'service', 'booking') NOT NULL DEFAULT 'session',
        coupon_generation_rules TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (wrapper_id),
        KEY product_id (product_id),
        KEY vendor_id (vendor_id),
        KEY session_id (session_id),
        KEY bookable_product_id (bookable_product_id)
    ) $charset_collate;";

    // Security logs table
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_security_logs (
        log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        timestamp DATETIME NOT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT 0,
        ip_address VARCHAR(45) NOT NULL,
        event VARCHAR(100) NOT NULL,
        details TEXT DEFAULT NULL,
        PRIMARY KEY  (log_id),
        KEY timestamp (timestamp),
        KEY user_id (user_id),
        KEY event (event)
    ) $charset_collate;";

    // Auto-apply tokens table
    $tables[] = "CREATE TABLE {$wpdb->prefix}gr8r_enhanced_auto_apply_tokens (
        token_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        token VARCHAR(100) NOT NULL UNIQUE,
        coupon_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        vendor_id BIGINT(20) UNSIGNED NOT NULL,
        session_id BIGINT(20) UNSIGNED DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (token_id),
        KEY token (token),
        KEY coupon_id (coupon_id),
        KEY expires_at (expires_at),
        KEY is_used (is_used)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    add_option('gr8r_enhanced_db_version', GR8R_ENHANCED_VERSION);

    // Schedule cleanup events
    if (!wp_next_scheduled('gr8r_enhanced_cleanup_expired_coupons')) {
        wp_schedule_event(time(), 'daily', 'gr8r_enhanced_cleanup_expired_coupons');
    }
    if (!wp_next_scheduled('gr8r_enhanced_cleanup_old_logs')) {
        wp_schedule_event(time(), 'weekly', 'gr8r_enhanced_cleanup_old_logs');
    }
    if (!wp_next_scheduled('gr8r_enhanced_cleanup_expired_tokens')) {
        wp_schedule_event(time(), 'hourly', 'gr8r_enhanced_cleanup_expired_tokens');
    }
}

/**
 * Clean up on deactivation
 */
function gr8r_enhanced_plugin_deactivation() {
    wp_clear_scheduled_hook('gr8r_enhanced_cleanup_expired_coupons');
    wp_clear_scheduled_hook('gr8r_enhanced_cleanup_old_logs');
    wp_clear_scheduled_hook('gr8r_enhanced_cleanup_expired_tokens');
}

// Add cleanup hooks
add_action('gr8r_enhanced_cleanup_expired_coupons', 'gr8r_enhanced_cleanup_expired_coupons');
add_action('gr8r_enhanced_cleanup_old_logs', 'gr8r_enhanced_cleanup_old_logs');
add_action('gr8r_enhanced_cleanup_expired_tokens', 'gr8r_enhanced_cleanup_expired_tokens');

/**
 * Cleanup expired coupons
 */
function gr8r_enhanced_cleanup_expired_coupons() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gr8r_enhanced_coupons';
    $wpdb->query($wpdb->prepare("
        DELETE FROM $table_name 
        WHERE expiry_date < %s 
        AND is_used = 0
    ", current_time('mysql')));
}

/**
 * Cleanup old security logs
 */
function gr8r_enhanced_cleanup_old_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gr8r_enhanced_security_logs';
    $wpdb->query($wpdb->prepare("
        DELETE FROM $table_name 
        WHERE timestamp < %s
    ", date('Y-m-d H:i:s', strtotime('-30 days'))));
}

/**
 * Cleanup expired auto-apply tokens
 */
function gr8r_enhanced_cleanup_expired_tokens() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gr8r_enhanced_auto_apply_tokens';
    $wpdb->query($wpdb->prepare("
        DELETE FROM $table_name 
        WHERE expires_at < %s
    ", current_time('mysql')));
}

// Add Dokan integration hooks
add_action('init', function() {
    if (class_exists('WeDevs_Dokan')) {
        add_filter('dokan_get_dashboard_nav', 'gr8r_enhanced_add_dashboard_nav');
        add_filter('dokan_query_var_filter', 'gr8r_enhanced_add_query_vars');
        add_action('dokan_load_custom_template', 'gr8r_enhanced_load_custom_templates');
        add_action('dokan_dashboard_content_before', 'gr8r_enhanced_dashboard_content');
    }
});

/**
 * Add navigation items to Dokan dashboard
 */
function gr8r_enhanced_add_dashboard_nav($urls) {
    $urls['enhanced-coupons'] = [
        'title' => __('Enhanced Coupons', 'gr8r-enhanced'),
        'icon'  => '<i class="fas fa-ticket-alt"></i>',
        'url'   => dokan_get_navigation_url('enhanced-coupons'),
        'pos'   => 55
    ];
    $urls['enhanced-sessions'] = [
        'title' => __('Sessions', 'gr8r-enhanced'),
        'icon'  => '<i class="fas fa-calendar-alt"></i>',
        'url'   => dokan_get_navigation_url('enhanced-sessions'),
        'pos'   => 56
    ];
    $urls['enhanced-credits'] = [
        'title' => __('Credits', 'gr8r-enhanced'),
        'icon'  => '<i class="fas fa-coins"></i>',
        'url'   => dokan_get_navigation_url('enhanced-credits'),
        'pos'   => 57
    ];
    return $urls;
}

/**
 * Add query vars for Dokan
 */
function gr8r_enhanced_add_query_vars($vars) {
    $vars[] = 'enhanced-coupons';
    $vars[] = 'enhanced-sessions';
    $vars[] = 'enhanced-credits';
    return $vars;
}

/**
 * Load custom templates for Dokan
 */
function gr8r_enhanced_load_custom_templates($query_vars) {
    if (isset($query_vars['enhanced-coupons'])) {
        echo do_shortcode('[gr8r_enhanced_vendor_coupons]');
        return;
    }
    if (isset($query_vars['enhanced-sessions'])) {
        echo do_shortcode('[gr8r_enhanced_vendor_sessions]');
        return;
    }
    if (isset($query_vars['enhanced-credits'])) {
        echo do_shortcode('[gr8r_enhanced_vendor_credits]');
        return;
    }
}

/**
 * Add dashboard content
 */
function gr8r_enhanced_dashboard_content() {
    if (get_query_var('enhanced-coupons') || get_query_var('enhanced-sessions') || get_query_var('enhanced-credits')) {
        wp_enqueue_style('gr8r-enhanced-dashboard', GR8R_ENHANCED_URL . 'assets/css/dashboard.css', [], GR8R_ENHANCED_VERSION);
        wp_enqueue_script('gr8r-enhanced-dashboard', GR8R_ENHANCED_URL . 'assets/js/dashboard.js', ['jquery'], GR8R_ENHANCED_VERSION, true);
        wp_localize_script('gr8r-enhanced-dashboard', 'gr8r_enhanced_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gr8r_enhanced_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'gr8r-enhanced'),
                'loading' => __('Loading...', 'gr8r-enhanced'),
                'error' => __('An error occurred. Please try again.', 'gr8r-enhanced')
            ]
        ]);
    }
}

// Add WooCommerce My Account integration
add_action('init', function() {
    if (class_exists('WooCommerce')) {
        add_filter('woocommerce_account_menu_items', 'gr8r_enhanced_add_account_menu_items');
        add_action('woocommerce_account_enhanced-coupons_endpoint', 'gr8r_enhanced_account_coupons_content');
        add_action('woocommerce_account_enhanced-sessions_endpoint', 'gr8r_enhanced_account_sessions_content');
        add_action('init', 'gr8r_enhanced_add_account_endpoints');
    }
});

/**
 * Add endpoints to WooCommerce My Account
 */
function gr8r_enhanced_add_account_endpoints() {
    add_rewrite_endpoint('enhanced-coupons', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('enhanced-sessions', EP_ROOT | EP_PAGES);
}

/**
 * Add menu items to WooCommerce My Account
 */
function gr8r_enhanced_add_account_menu_items($items) {
    $items['enhanced-coupons'] = __('My Coupons', 'gr8r-enhanced');
    $items['enhanced-sessions'] = __('My Sessions', 'gr8r-enhanced');
    return $items;
}

/**
 * Display coupons content in My Account
 */
function gr8r_enhanced_account_coupons_content() {
    echo do_shortcode('[gr8r_enhanced_user_coupons]');
}

/**
 * Display sessions content in My Account
 */
function gr8r_enhanced_account_sessions_content() {
    echo do_shortcode('[gr8r_enhanced_user_sessions]');
}

// Auto-apply coupon handling
add_action('wp', 'gr8r_enhanced_handle_auto_apply_coupon');

/**
 * Handle auto-apply coupon from URL
 */
function gr8r_enhanced_handle_auto_apply_coupon() {
    if (isset($_GET['gr8r_apply_coupon']) && !empty($_GET['gr8r_apply_coupon'])) {
        $token = sanitize_text_field($_GET['gr8r_apply_coupon']);
        if (class_exists('GR8R_Enhanced_Auto_Apply_Handler')) {
            $handler = new GR8R_Enhanced_Auto_Apply_Handler();
            $handler->handle_auto_apply($token);
        }
    }
}

// Add product editor integration for session linking
add_action('add_meta_boxes', 'gr8r_enhanced_add_product_meta_boxes');
add_action('save_post', 'gr8r_enhanced_save_product_meta');

/**
 * Add meta boxes to product editor
 */
function gr8r_enhanced_add_product_meta_boxes() {
    add_meta_box(
        'gr8r_enhanced_session_link',
        __('GR8R Session Link', 'gr8r-enhanced'),
        'gr8r_enhanced_session_link_meta_box',
        'product',
        'normal',
        'default'
    );
}

/**
 * Display session link meta box
 */
function gr8r_enhanced_session_link_meta_box($post) {
    global $wpdb;
    wp_nonce_field('gr8r_enhanced_session_link_nonce', 'gr8r_enhanced_session_link_nonce');
    $current_session = get_post_meta($post->ID, '_gr8r_enhanced_session_id', true);
    $current_bookable = get_post_meta($post->ID, '_gr8r_enhanced_bookable_product_id', true);
    $vendor_id = get_post_field('post_author', $post->ID);
    $sessions = $wpdb->get_results($wpdb->prepare("
        SELECT session_id, session_name, session_date 
        FROM {$wpdb->prefix}gr8r_enhanced_sessions 
        WHERE vendor_id = %d AND status = 'active'
        ORDER BY session_date ASC
    ", $vendor_id));
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label for="gr8r_enhanced_session_id">' . __('Link to Session', 'gr8r-enhanced') . '</label></th>';
    echo '<td>';
    echo '<select name="gr8r_enhanced_session_id" id="gr8r_enhanced_session_id">';
    echo '<option value="">' . __('Select a session...', 'gr8r-enhanced') . '</option>';
    foreach ($sessions as $session) {
        $selected = selected($current_session, $session->session_id, false);
        echo '<option value="' . $session->session_id . '" ' . $selected . '>';
        echo esc_html($session->session_name . ' - ' . date('Y-m-d H:i', strtotime($session->session_date)));
        echo '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . __('Link this wrapper product to a specific session.', 'gr8r-enhanced') . '</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="gr8r_enhanced_bookable_product_id">' . __('Bookable Product ID', 'gr8r-enhanced') . '</label></th>';
    echo '<td>';
    echo '<input type="number" name="gr8r_enhanced_bookable_product_id" id="gr8r_enhanced_bookable_product_id" value="' . esc_attr($current_bookable) . '" class="regular-text" />';
    echo '<p class="description">' . __('Enter the ID of the WooCommerce Bookings product that customers will actually book.', 'gr8r-enhanced') . '</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
}

/**
 * Save product meta
 */
function gr8r_enhanced_save_product_meta($post_id) {
    if (!isset($_POST['gr8r_enhanced_session_link_nonce']) || 
        !wp_verify_nonce($_POST['gr8r_enhanced_session_link_nonce'], 'gr8r_enhanced_session_link_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    $session_id = isset($_POST['gr8r_enhanced_session_id']) ? intval($_POST['gr8r_enhanced_session_id']) : 0;
    $bookable_product_id = isset($_POST['gr8r_enhanced_bookable_product_id']) ? intval($_POST['gr8r_enhanced_bookable_product_id']) : 0;
    update_post_meta($post_id, '_gr8r_enhanced_session_id', $session_id);
    update_post_meta($post_id, '_gr8r_enhanced_bookable_product_id', $bookable_product_id);
    global $wpdb;
    $vendor_id = get_post_field('post_author', $post_id);
    $wpdb->replace(
        $wpdb->prefix . 'gr8r_enhanced_wrapper_products',
        [
            'product_id' => $post_id,
            'vendor_id' => $vendor_id,
            'session_id' => $session_id ?: null,
            'bookable_product_id' => $bookable_product_id ?: null,
            'wrapper_type' => 'session',
            'created_at' => current_time('mysql')
        ],
        ['%d', '%d', '%d', '%d', '%s', '%s']
    );
}

// Flush rewrite rules on activation/deactivation
register_activation_hook(__FILE__, function() {
    gr8r_enhanced_add_account_endpoints();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});