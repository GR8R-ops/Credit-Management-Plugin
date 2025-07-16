<?php
/**
 * WooCommerce Bookings Integration Class
 * 
 * @package GR8R_Enhanced
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

class GR8R_Enhanced_Bookings_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize hooks
     */
    public function init() {
        if (!class_exists('WC_Bookings')) {
            return;
        }
        
        // Booking creation hooks
        add_action('woocommerce_booking_confirmed', array($this, 'on_booking_confirmed'));
        add_action('woocommerce_booking_cancelled', array($this, 'on_booking_cancelled'));
        add_action('woocommerce_booking_complete', array($this, 'on_booking_complete'));
        
        // Product booking hooks
        add_action('woocommerce_add_to_cart', array($this, 'handle_booking_add_to_cart'), 10, 6);
        add_action('woocommerce_checkout_order_processed', array($this, 'process_booking_order'), 10, 3);
        
        // Admin hooks
        add_action('add_meta_boxes', array($this, 'add_booking_meta_boxes'));
        add_filter('woocommerce_booking_form_fields', array($this, 'add_coupon_field_to_booking_form'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_gr8r_link_booking_to_session', array($this, 'ajax_link_booking_to_session'));
        add_action('wp_ajax_gr8r_get_session_bookings', array($this, 'ajax_get_session_bookings'));
    }
    
    /**
     * Handle booking confirmation
     */
    public function on_booking_confirmed($booking_id) {
        $booking = new WC_Booking($booking_id);
        $this->update_session_participation($booking, 'registered');
        $this->log_booking_event($booking_id, 'confirmed');
    }
    
    /**
     * Handle booking cancellation
     */
    public function on_booking_cancelled($booking_id) {
        $booking = new WC_Booking($booking_id);
        $this->update_session_participation($booking, 'cancelled');
        $this->log_booking_event($booking_id, 'cancelled');
    }
    
    /**
     * Handle booking completion
     */
    public function on_booking_complete($booking_id) {
        $booking = new WC_Booking($booking_id);
        $this->update_session_participation($booking, 'attended');
        $this->log_booking_event($booking_id, 'completed');
    }
    
    /**
     * Handle add to cart for booking products
     */
    public function handle_booking_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        if (!$this->is_bookable_product($product_id)) {
            return;
        }
        
        // Check if this is linked to a session
        $session_id = $this->get_session_for_product($product_id);
        if ($session_id) {
            WC()->session->set('gr8r_booking_session_' . $product_id, $session_id);
        }
    }
    
    /**
     * Process booking order
     */
    public function process_booking_order($order_id, $posted_data, $order) {
        $order_items = $order->get_items();
        
        foreach ($order_items as $item) {
            $product_id = $item->get_product_id();
            
            if (!$this->is_bookable_product($product_id)) {
                continue;
            }
            
            $session_id = WC()->session->get('gr8r_booking_session_' . $product_id);
            if (!$session_id) {
                continue;
            }
            
            // Get booking ID from order
            $booking_id = $this->get_booking_id_from_order_item($item);
            if (!$booking_id) {
                continue;
            }
            
            // Add participant to session
            $this->add_session_participant($session_id, $order->get_user_id(), $booking_id, $order_id);
            
            // Clear session
            WC()->session->set('gr8r_booking_session_' . $product_id, null);
        }
    }
    
    /**
     * Add meta boxes for booking management
     */
    public function add_booking_meta_boxes() {
        add_meta_box(
            'gr8r_booking_session_link',
            __('GR8R Session Link', 'gr8r-enhanced'),
            array($this, 'booking_session_link_meta_box'),
            'wc_booking',
            'normal',
            'default'
        );
    }
    
    /**
     * Display booking session link meta box
     */
    public function booking_session_link_meta_box($post) {
        $booking = new WC_Booking($post->ID);
        $product_id = $booking->get_product_id();
        
        $session_id = $this->get_session_for_product($product_id);
        $session_info = $session_id ? $this->get_session_info($session_id) : null;
        
        echo '<table class="form-table">';
        if ($session_info) {
            echo '<tr>';
            echo '<th>' . __('Linked Session', 'gr8r-enhanced') . '</th>';
            echo '<td>';
            echo '<strong>' . esc_html($session_info->session_name) . '</strong><br>';
            echo '<small>' . date('Y-m-d H:i', strtotime($session_info->session_date)) . '</small>';
            echo '</td>';
            echo '</tr>';
            
            echo '<tr>';
            echo '<th>' . __('Participation Status', 'gr8r-enhanced') . '</th>';
            echo '<td>';
            $participant = $this->get_participant_status($session_id, $booking->get_user_id());
            echo '<select name="gr8r_participation_status" id="gr8r_participation_status">';
            echo '<option value="registered"' . selected($participant->status ?? 'registered', 'registered', false) . '>' . __('Registered', 'gr8r-enhanced') . '</option>';
            echo '<option value="attended"' . selected($participant->status ?? '', 'attended', false) . '>' . __('Attended', 'gr8r-enhanced') . '</option>';
            echo '<option value="no_show"' . selected($participant->status ?? '', 'no_show', false) . '>' . __('No Show', 'gr8r-enhanced') . '</option>';
            echo '<option value="cancelled"' . selected($participant->status ?? '', 'cancelled', false) . '>' . __('Cancelled', 'gr8r-enhanced') . '</option>';
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        } else {
            echo '<tr>';
            echo '<td colspan="2">' . __('This booking is not linked to any session.', 'gr8r-enhanced') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    /**
     * Add coupon field to booking form
     */
    public function add_coupon_field_to_booking_form($fields, $product) {
        if (!$this->is_bookable_product($product->get_id())) {
            return $fields;
        }
        
        $session_id = $this->get_session_for_product($product->get_id());
        if (!$session_id) {
            return $fields;
        }
        
        // Check if user has available coupons for this session
        $user_coupons = $this->get_user_session_coupons(get_current_user_id(), $session_id);
        
        if (empty($user_coupons)) {
            return $fields;
        }
        
        $fields['gr8r_session_coupon'] = array(
            'type' => 'select',
            'label' => __('Apply Session Coupon', 'gr8r-enhanced'),
            'options' => $this->format_coupon_options($user_coupons),
            'required' => false,
            'priority' => 5
        );
        
        return $fields;
    }
    
    /**
     * AJAX: Link booking to session
     */
    public function ajax_link_booking_to_session() {
        check_ajax_referer('gr8r_enhanced_nonce', 'nonce');
        
        $booking_id = intval($_POST['booking_id']);
        $session_id = intval($_POST['session_id']);
        
        if (!$booking_id || !$session_id) {
            wp_send_json_error(__('Invalid parameters', 'gr8r-enhanced'));
        }
        
        $booking = new WC_Booking($booking_id);
        $result = $this->add_session_participant($session_id, $booking->get_user_id(), $booking_id, $booking->get_order_id());
        
        if ($result) {
            wp_send_json_success(__('Booking linked to session successfully', 'gr8r-enhanced'));
        } else {
            wp_send_json_error(__('Failed to link booking to session', 'gr8r-enhanced'));
        }
    }
    
    /**
     * AJAX: Get session bookings
     */
    public function ajax_get_session_bookings() {
        check_ajax_referer('gr8r_enhanced_nonce', 'nonce');
        
        $session_id = intval($_POST['session_id']);
        if (!$session_id) {
            wp_send_json_error(__('Invalid session ID', 'gr8r-enhanced'));
        }
        
        $bookings = $this->get_session_bookings($session_id);
        wp_send_json_success($bookings);
    }
    
    /**
     * Update session participation
     */
    private function update_session_participation($booking, $status) {
        global $wpdb;
        
        $product_id = $booking->get_product_id();
        $session_id = $this->get_session_for_product($product_id);
        
        if (!$session_id) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'gr8r_enhanced_session_participants';
        
        $wpdb->update(
            $table_name,
            array('status' => $status),
            array(
                'session_id' => $session_id,
                'user_id' => $booking->get_user_id(),
                'booking_id' => $booking->get_id()
            ),
            array('%s'),
            array('%d', '%d', '%d')
        );
    }
    
    /**
     * Add session participant
     */
    private function add_session_participant($session_id, $user_id, $booking_id, $order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gr8r_enhanced_session_participants';
        
        return $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'user_id' => $user_id,
                'booking_id' => $booking_id,
                'order_id' => $order_id,
                'status' => 'registered',
                'registered_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get session for product
     */
    private function get_session_for_product($product_id) {
        return get_post_meta($product_id, '_gr8r_enhanced_session_id', true);
    }
    
    /**
     * Get session info
     */
    private function get_session_info($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gr8r_enhanced_sessions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %d",
            $session_id
        ));
    }
    
    /**
     * Get participant status
     */
    private function get_participant_status($session_id, $user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gr8r_enhanced_session_participants';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %d AND user_id = %d",
            $session_id,
            $user_id
        ));
    }
    
    /**
     * Get session bookings
     */
    private function get_session_bookings($session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gr8r_enhanced_session_participants';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT sp.*, u.display_name 
             FROM $table_name sp 
             LEFT JOIN {$wpdb->users} u ON sp.user_id = u.ID 
             WHERE sp.session_id = %d
             ORDER BY sp.registered_at DESC",
            $session_id
        ));
    }
    
    /**
     * Get user session coupons
     */
    private function get_user_session_coupons($user_id, $session_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gr8r_enhanced_coupons';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d AND session_id = %d 
             AND is_used = 0 AND expiry_date > %s",
            $user_id,
            $session_id,
            current_time('mysql')
        ));
    }
    
    /**
     * Format coupon options for select field
     */
    private function format_coupon_options($coupons) {
        $options = array('' => __('Select a coupon...', 'gr8r-enhanced'));
        
        foreach ($coupons as $coupon) {
            $label = $coupon->coupon_code;
            if ($coupon->discount_type === 'percentage') {
                $label .= ' (' . $coupon->discount_value . '%)';
            } else {
                $label .= ' (' . wc_price($coupon->discount_value) . ')';
            }
            $options[$coupon->coupon_code] = $label;
        }
        
        return $options;
    }
    
    /**
     * Check if product is bookable
     */
    private function is_bookable_product($product_id) {
        $product = wc_get_product($product_id);
        return $product && $product->is_type('booking');
    }
    
    /**
     * Get booking ID from order item
     */
    private function get_booking_id_from_order_item($item) {
        $bookings = WC_Bookings_Controller::get_bookings_for_order($item->get_order_id());
        
        foreach ($bookings as $booking) {
            if ($booking->get_product_id() == $item->get_product_id()) {
                return $booking->get_id();
            }
        }
        
        return null;
    }
    
    /**
     * Log booking event
     */
    private function log_booking_event($booking_id, $event) {
        $security = GR8R_Enhanced_Security::get_instance();
        $security->log_event('booking_' . $event, array(
            'booking_id' => $booking_id,
            'timestamp' => current_time('mysql')
        ));
    }
}