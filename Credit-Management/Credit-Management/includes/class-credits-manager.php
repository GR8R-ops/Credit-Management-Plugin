<?php
defined('ABSPATH') || exit;

class GR8R_Credits_Manager {
    
    public function get_balance($user_id, $vendor_id = 0, $service_type = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gr8r_credits';
        $where = ['user_id' => $user_id];
        
        if ($vendor_id) $where['vendor_id'] = $vendor_id;
        if ($service_type) $where['service_type'] = $service_type;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE " . $this->build_where_clause($where),
            $where
        ), ARRAY_A);
    }
    
    public function get_transactions($credit_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gr8r_credit_transactions
            WHERE credit_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $credit_id, $limit, $offset
        ), ARRAY_A);
    }
    
    public function add_transaction($credit_id, $amount, $type, $reference = '', $description = '', $created_by = 0) {
        global $wpdb;
        
        $created_by = $created_by ?: get_current_user_id();
        
        $wpdb->insert(
            $wpdb->prefix . 'gr8r_credit_transactions',
            [
                'credit_id' => $credit_id,
                'amount' => abs($amount),
                'transaction_type' => $type,
                'reference' => $reference,
                'description' => $description,
                'created_by' => $created_by,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%f', '%s', '%s', '%s', '%d', '%s']
        );
        
        $this->update_credit_balance($credit_id, $amount, $type);
        
        return $wpdb->insert_id;
    }
    
    public function create_credit_account($user_id, $vendor_id = 0, $service_type = '', $initial_balance = 0) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'gr8r_credits',
            [
                'user_id' => $user_id,
                'vendor_id' => $vendor_id,
                'service_type' => $service_type,
                'balance' => $initial_balance,
                'last_updated' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%f', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    private function update_credit_balance($credit_id, $amount, $type) {
        global $wpdb;
        
        $operator = ($type === 'credit') ? '+' : '-';
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}gr8r_credits 
            SET balance = balance $operator %f,
            last_updated = %s
            WHERE credit_id = %d",
            $amount,
            current_time('mysql'),
            $credit_id
        ));
    }
    
    private function build_where_clause($conditions) {
        $where = [];
        foreach ($conditions as $field => $value) {
            $where[] = $field . ' = ' . (is_numeric($value) ? '%d' : '%s');
        }
        return implode(' AND ', $where);
    }
}