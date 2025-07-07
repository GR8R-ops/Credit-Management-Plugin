<?php
/*
Plugin Name: GreatR Credit Management
Description: Comprehensive credit management system for WordPress
Version: 1.0.0
Author: Your Name
Text Domain: gr8r
*/

defined('ABSPATH') || exit;

// Define constants
define('GR8R_VERSION', '1.0.0');
define('GR8R_PATH', plugin_dir_path(__FILE__));
define('GR8R_URL', plugin_dir_url(__FILE__));

// Load dependencies
require_once GR8R_PATH . 'includes/class-credits-manager.php';
require_once GR8R_PATH . 'includes/class-shortcodes.php';
require_once GR8R_PATH . 'includes/class-admin.php';
require_once GR8R_PATH . 'includes/class-rest-api.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, 'gr8r_create_database_tables');
register_deactivation_hook(__FILE__, 'gr8r_plugin_deactivation');

// Load text domain
add_action('plugins_loaded', function() {
    load_plugin_textdomain('gr8r', false, basename(GR8R_PATH) . '/languages/');
});
require_once GR8R_PATH . 'includes/class-shortcodes.php';
new GR8R_Shortcodes();
new GR8R_Admin();
new GR8R_REST_API();

/**
 * Create database tables on activation
 */
function gr8r_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Credits Table
    $table_name = $wpdb->prefix . 'gr8r_credits';
    $sql = "CREATE TABLE $table_name (
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
    
    // Transactions Table
    $table_name = $wpdb->prefix . 'gr8r_credit_transactions';
    $sql .= "CREATE TABLE $table_name (
        transaction_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        credit_id BIGINT(20) UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        transaction_type ENUM('credit', 'debit') NOT NULL,
        reference VARCHAR(100) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_by BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (transaction_id),
        KEY credit_id (credit_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Clean up on deactivation
 */
function gr8r_plugin_deactivation() {
    // Optional: Add cleanup code if needed
}