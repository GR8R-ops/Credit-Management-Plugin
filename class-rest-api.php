
<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route('gr8r-enhanced/v1', '/credits/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_credits'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        register_rest_route('gr8r-enhanced/v1', '/coupons/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_coupons'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        register_rest_route('gr8r-enhanced/v1', '/coupon/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_coupon'],
            'permission_callback' => [$this, 'check_admin_permissions']
        ]);
    }
    
    public function check_permissions($request) {
        return is_user_logged_in();
    }
    
    public function check_admin_permissions($request) {
        return current_user_can('manage_options');
    }
    
    public function get_user_credits($request) {
        $user_id = $request->get_param('user_id');
        $credits_manager = new GR8R_Enhanced_Credits_Manager();
        $credits = $credits_manager->get_user_credits($user_id);
        
        return rest_ensure_response($credits);
    }
    
    public function get_user_coupons($request) {
        $user_id = $request->get_param('user_id');
        $coupon_manager = new GR8R_Enhanced_Coupon_Manager();
        $coupons = $coupon_manager->get_user_coupons($user_id);
        
        return rest_ensure_response($coupons);
    }
    
    public function generate_coupon($request) {
        $params = $request->get_json_params();
        
        $coupon_manager = new GR8R_Enhanced_Coupon_Manager();
        $result = $coupon_manager->generate_single_use_coupon(
            $params['user_id'],
            $params['vendor_id'],
            $params['session_id'] ?? null,
            $params['discount_value'],
            $params['discount_type']
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(['coupon_code' => $result]);
    }
}
