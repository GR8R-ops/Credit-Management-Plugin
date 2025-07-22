<?php
/**
 * Dokan Integration for GR8R Enhanced Plugin
 *
 * @package GR8R_Enhanced
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class GR8R_Enhanced_Dokan_Integration {

    public function __construct() {
        // Add custom menu items for coupons, sessions, credits
        add_filter('dokan_get_dashboard_nav', [$this, 'add_custom_menu_to_vendor_dashboard']);
        // Add custom query vars for navigation
        add_filter('dokan_query_var_filter', [$this, 'add_query_vars']);
        // Load custom content for dashboard menu
        add_action('dokan_load_custom_template', [$this, 'load_custom_vendor_dashboard_page']);
        // Add dashboard content before the main area
        add_action('dokan_dashboard_content_before', [$this, 'dashboard_content_before']);
    }

    /**
     * Add multiple custom menu items to Dokan vendor dashboard navigation.
     */
    public function add_custom_menu_to_vendor_dashboard($urls) {
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
        // Optionally: keep legacy single dashboard
        $urls['gr8r-enhanced'] = [
            'title' => __('GR8R Enhanced', 'gr8r-enhanced'),
            'icon'  => '<i class="fas fa-gift"></i>',
            'url'   => dokan_get_navigation_url('gr8r-enhanced'),
            'pos'   => 99
        ];
        return $urls;
    }

    /**
     * Add custom query vars for navigation endpoints.
     */
    public function add_query_vars($vars) {
        $vars[] = 'enhanced-coupons';
        $vars[] = 'enhanced-sessions';
        $vars[] = 'enhanced-credits';
        $vars[] = 'gr8r-enhanced';
        return $vars;
    }

    /**
     * Load custom dashboard page content for each menu tab.
     */
    public function load_custom_vendor_dashboard_page($query_vars) {
        // Enhanced Coupons tab
        if (isset($query_vars['enhanced-coupons'])) {
            echo '<div class="dokan-dashboard-content gr8r-enhanced-dashboard">';
            echo '<h2>' . esc_html__('Enhanced Coupons', 'gr8r-enhanced') . '</h2>';
            echo do_shortcode('[gr8r_enhanced_vendor_coupons]');
            echo '</div>';
            return;
        }
        // Sessions tab
        if (isset($query_vars['enhanced-sessions'])) {
            echo '<div class="dokan-dashboard-content gr8r-enhanced-dashboard">';
            echo '<h2>' . esc_html__('Vendor Sessions', 'gr8r-enhanced') . '</h2>';
            echo do_shortcode('[gr8r_enhanced_vendor_sessions]');
            echo '</div>';
            return;
        }
        // Credits tab
        if (isset($query_vars['enhanced-credits'])) {
            echo '<div class="dokan-dashboard-content gr8r-enhanced-dashboard">';
            echo '<h2>' . esc_html__('Vendor Credits', 'gr8r-enhanced') . '</h2>';
            echo do_shortcode('[gr8r_enhanced_vendor_credits]');
            echo '</div>';
            return;
        }
        // Legacy: main dashboard tab
        if (isset($query_vars['gr8r-enhanced'])) {
            echo '<div class="dokan-dashboard-content gr8r-enhanced-dashboard">';
            echo '<h2>' . esc_html__('GR8R Enhanced Dashboard', 'gr8r-enhanced') . '</h2>';
            echo '<p>' . esc_html__('Welcome to your enhanced vendor dashboard!', 'gr8r-enhanced') . '</p>';
            echo '</div>';
            return;
        }
    }

    /**
     * Optionally add dashboard content before main area for enhanced pages.
     */
    public function dashboard_content_before() {
        $qv = get_query_var('enhanced-coupons') || get_query_var('enhanced-sessions') || get_query_var('enhanced-credits') || get_query_var('gr8r-enhanced');
        if ($qv) {
            wp_enqueue_style('gr8r-enhanced-dashboard', plugin_dir_url(__FILE__) . '../assets/css/dashboard.css', [], '1.0.0');
            wp_enqueue_script('gr8r-enhanced-dashboard', plugin_dir_url(__FILE__) . '../assets/js/dashboard.js', ['jquery'], '1.0.0', true);
            // Optionally localize script
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
}

// Initialize the class if Dokan is active
if (defined('DOKAN_PLUGIN_VERSION')) {
    $GLOBALS['gr8r_enhanced_dokan_integration'] = new GR8R_Enhanced_Dokan_Integration();
}