<?php
defined('ABSPATH') || exit;

class GR8R_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route('gr8r/v1', '/credit-balance', [
            'methods' => 'GET',
            'callback' => [$this, 'get_credit_balance'],
            'permission_callback' => [$this, 'check_user_permissions'],
            'args' => [
                'user_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'vendor_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'service_type' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        register_rest_route('gr8r/v1', '/add-credit', [
            'methods' => 'POST',
            'callback' => [$this, 'add_credit'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ],
                'amount' => [
                    'required' => true,
                    'type' => 'number',
                    'sanitize_callback' => 'floatval'
                ],
                'description' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }
    
    public function get_credit_balance(WP_REST_Request $request) {
        $params = $request->get_params();
        $manager = new GR8R_Credits_Manager();
        
        $balance = $manager->get_balance(
            $params['user_id'] ?? get_current_user_id(),
            $params['vendor_id'] ?? 0,
            $params['service_type'] ?? ''
        );
        
        if (!$balance) {
            return new WP_Error('no_balance', __('No credit balance found', 'gr8r'), ['status' => 404]);
        }
        
        return rest_ensure_response($balance);
    }
    
    public function add_credit(WP_REST_Request $request) {
        $params = $request->get_params();
        $manager = new GR8R_Credits_Manager();
        
        $balance = $manager->get_balance($params['user_id']);
        if (!$balance) {
            $credit_id = $manager->create_credit_account($params['user_id']);
        } else {
            $credit_id = $balance['credit_id'];
        }
        
        $transaction_id = $manager->add_transaction(
            $credit_id,
            $params['amount'],
            'credit',
            'api_adjustment',
            $params['description'] ?? '',
            get_current_user_id()
        );
        
        return rest_ensure_response([
            'success' => true,
            'transaction_id' => $transaction_id,
            'new_balance' => $manager->get_balance($params['user_id'])
        ]);
    }
    
    public function check_user_permissions() {
        return is_user_logged_in();
    }
    
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }
}