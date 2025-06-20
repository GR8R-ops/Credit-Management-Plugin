<?php
defined('ABSPATH') || exit;

class GR8R_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('GreatR Credits', 'gr8r'),
            __('GreatR Credits', 'gr8r'),
            'manage_options',
            'gr8r-credits',
            [$this, 'render_admin_dashboard'],
            'dashicons-money-alt'
        );
        
        add_submenu_page(
            'gr8r-credits',
            __('Credit Balances', 'gr8r'),
            __('Balances', 'gr8r'),
            'manage_options',
            'gr8r-credit-balances',
            [$this, 'render_credit_balances']
        );
        
        add_submenu_page(
            'gr8r-credits',
            __('Transactions', 'gr8r'),
            __('Transactions', 'gr8r'),
            'manage_options',
            'gr8r-transactions',
            [$this, 'render_transactions']
        );
        
        add_submenu_page(
            'gr8r-credits',
            __('Settings', 'gr8r'),
            __('Settings', 'gr8r'),
            'manage_options',
            'gr8r-settings',
            [$this, 'render_settings']
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'gr8r-') !== false) {
            wp_enqueue_style('gr8r-admin', GR8R_URL . 'assets/css/admin.css', [], GR8R_VERSION);
            wp_enqueue_script('gr8r-admin', GR8R_URL . 'assets/js/admin.js', ['jquery'], GR8R_VERSION, true);
            
            wp_localize_script('gr8r-admin', 'gr8r_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gr8r_admin_nonce')
            ]);
        }
    }
    
    public function handle_admin_actions() {
        if (!isset($_POST['gr8r_action']) || !current_user_can('manage_options')) {
            return;
        }
        
        check_admin_referer('gr8r_admin_actions');
        
        $action = sanitize_text_field($_POST['gr8r_action']);
        $manager = new GR8R_Credits_Manager();
        
        switch ($action) {
            case 'add_credits':
                $user_id = absint($_POST['user_id']);
                $amount = floatval($_POST['amount']);
                $description = sanitize_text_field($_POST['description']);
                
                $balance = $manager->get_balance($user_id);
                if (!$balance) {
                    $credit_id = $manager->create_credit_account($user_id);
                } else {
                    $credit_id = $balance['credit_id'];
                }
                
                $manager->add_transaction(
                    $credit_id,
                    $amount,
                    'credit',
                    'admin_adjustment',
                    $description,
                    get_current_user_id()
                );
                
                wp_redirect(admin_url('admin.php?page=gr8r-credit-balances&message=credits_added'));
                exit;
                
            case 'adjust_credits':
                // Similar to add_credits but with validation
                break;
        }
    }
    
    public function render_admin_dashboard() {
        include GR8R_PATH . 'templates/admin/dashboard.php';
    }
    
    public function render_credit_balances() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gr8r_credits';
        $balances = $wpdb->get_results("SELECT * FROM $table ORDER BY last_updated DESC", ARRAY_A);
        
        include GR8R_PATH . 'templates/admin/credit-balances.php';
    }
    
    public function render_transactions() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gr8r_credit_transactions';
        $transactions = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100", ARRAY_A);
        
        include GR8R_PATH . 'templates/admin/transactions.php';
    }
    
    public function render_settings() {
        include GR8R_PATH . 'templates/admin/settings.php';
    }
}