<?php
defined('ABSPATH') || exit;

class GR8R_Enhanced_Credits_Manager {
    
    private $security;
    
    public function __construct() {
        $this->security = GR8R_Enhanced_Security::get_instance();
        add_action('wp_loaded', [$this, 'init']);
        add_action('gr8r_enhanced_cleanup_expired_credits', [$this, 'cleanup_expired_credits']);
    }
    
    public function init() {
        // Register AJAX handlers
        add_action('wp_ajax_gr8r_add_credits', [$this, 'ajax_add_credits']);
        add_action('wp_ajax_gr8r_deduct_credits', [$this, 'ajax_deduct_credits']);
        add_action('wp_ajax_gr8r_get_balance', [$this, 'ajax_get_balance']);
        add_action('wp_ajax_gr8r_get_transactions', [$this, 'ajax_get_transactions']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('gr8r_enhanced_cleanup_expired_credits')) {
            wp_schedule_event(time(), 'daily', 'gr8r_enhanced_cleanup_expired_credits');
        }
    }
    
    /**
     * Add credits to user account with validation
     */
    public function add_credits($user_id, $vendor_id, $service_type, $amount, $description = '', $reference = '', $expiry_date = null) {
        global $wpdb;
        
        // Validate inputs
        $user_id = $this->security->sanitize_input($user_id, 'number');
        $vendor_id = $this->security->sanitize_input($vendor_id, 'number');
        $service_type = $this->security->sanitize_input($service_type, 'text');
        $amount = $this->security->sanitize_input($amount, 'float');
        $description = $this->security->sanitize_input($description, 'text');
        $reference = $this->security->sanitize_input($reference, 'text');
        $expiry_date = $expiry_date ? $this->security->sanitize_input($expiry_date, 'text') : null;
        
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', __('Credit amount must be positive', 'gr8r-enhanced'));
        }

        // Get or create credit record
        $credit_record = $this->get_or_create_credit_record($user_id, $vendor_id, $service_type);
        if (!$credit_record) {
            return new WP_Error('credit_record_error', __('Could not create credit record', 'gr8r-enhanced'));
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Add transaction
            $transaction_data = [
                'credit_id' => $credit_record['credit_id'],
                'amount' => $amount,
                'transaction_type' => 'credit',
                'reference' => $reference,
                'description' => $description,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ];
            
            if ($expiry_date) {
                $transaction_data['expiry_date'] = $expiry_date;
            }
            
            $transaction_id = $wpdb->insert(
                $wpdb->prefix . 'gr8r_enhanced_credit_transactions',
                $transaction_data,
                $expiry_date ? ['%d', '%f', '%s', '%s', '%s', '%d', '%s', '%s'] : ['%d', '%f', '%s', '%s', '%s', '%d', '%s']
            );
            
            if (!$transaction_id) {
                throw new Exception($wpdb->last_error);
            }
            
            // Update balance
            $balance_updated = $this->update_balance($credit_record['credit_id']);
            if (!$balance_updated) {
                throw new Exception('Balance update failed');
            }
            
            $wpdb->query('COMMIT');
            
            // Log action
            $this->security->log_event(
                'credit_added',
                sprintf('Added %s credits to account %d', $amount, $credit_record['credit_id'])
            );
            
            // Clear cache
            $this->clear_credit_cache($user_id, $vendor_id, $service_type);
            
            // Trigger action
            do_action('gr8r_credits_added', $credit_record['credit_id'], $amount, $reference, $transaction_id);
            
            return $transaction_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('GR8R Credits: Failed to add credits - ' . $e->getMessage());
            return new WP_Error('db_error', __('Database error occurred', 'gr8r-enhanced'), [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Deduct credits from user account with validation
     */
    public function deduct_credits($user_id, $vendor_id, $service_type, $amount, $description = '', $reference = '') {
        global $wpdb;
        
        // Validate inputs
        $user_id = $this->security->sanitize_input($user_id, 'number');
        $vendor_id = $this->security->sanitize_input($vendor_id, 'number');
        $service_type = $this->security->sanitize_input($service_type, 'text');
        $amount = $this->security->sanitize_input($amount, 'float');
        $description = $this->security->sanitize_input($description, 'text');
        $reference = $this->security->sanitize_input($reference, 'text');
        
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', __('Deduction amount must be positive', 'gr8r-enhanced'));
        }
        
        // Get credit record
        $credit_record = $this->get_credit_record($user_id, $vendor_id, $service_type);
        if (!$credit_record) {
            return new WP_Error('no_credit_record', __('No credit account found', 'gr8r-enhanced'));
        }
        
        // Check balance (using non-expired credits only)
        $available_balance = $this->get_available_balance($user_id, $vendor_id, $service_type);
        if ($available_balance < $amount) {
            return new WP_Error('insufficient_credits', __('Insufficient credits', 'gr8r-enhanced'), [
                'available_balance' => $available_balance,
                'requested_amount' => $amount
            ]);
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // First try to use oldest non-expired credits (FIFO)
            $credits_to_use = $wpdb->get_results($wpdb->prepare(
                "SELECT transaction_id, amount 
                 FROM {$wpdb->prefix}gr8r_enhanced_credit_transactions 
                 WHERE credit_id = %d 
                 AND transaction_type = 'credit' 
                 AND (expiry_date IS NULL OR expiry_date >= %s) 
                 AND amount > 0 
                 ORDER BY created_at ASC",
                $credit_record['credit_id'],
                current_time('mysql')
            ));
            
            $remaining_amount = $amount;
            $used_transactions = [];
            
            foreach ($credits_to_use as $credit) {
                $use_amount = min($credit->amount, $remaining_amount);
                
                // Record which transactions we're using
                $used_transactions[] = [
                    'id' => $credit->transaction_id,
                    'amount_used' => $use_amount
                ];
                
                $remaining_amount -= $use_amount;
                
                if ($remaining_amount <= 0) {
                    break;
                }
            }
            
            // Add debit transaction
            $transaction_id = $wpdb->insert(
                $wpdb->prefix . 'gr8r_enhanced_credit_transactions',
                [
                    'credit_id' => $credit_record['credit_id'],
                    'amount' => $amount,
                    'transaction_type' => 'debit',
                    'reference' => $reference,
                    'description' => $description,
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                    'linked_transactions' => maybe_serialize($used_transactions)
                ],
                ['%d', '%f', '%s', '%s', '%s', '%d', '%s', '%s']
            );
            
            if (!$transaction_id) {
                throw new Exception($wpdb->last_error);
            }
            
            // Update balance
            $balance_updated = $this->update_balance($credit_record['credit_id']);
            if (!$balance_updated) {
                throw new Exception('Balance update failed');
            }
            
            $wpdb->query('COMMIT');
            
            // Log action
            $this->security->log_event(
                'credit_deducted',
                sprintf('Deducted %s credits from account %d', $amount, $credit_record['credit_id'])
            );
            
            // Clear cache
            $this->clear_credit_cache($user_id, $vendor_id, $service_type);
            
            // Trigger action
            do_action('gr8r_credits_deducted', $credit_record['credit_id'], $amount, $reference, $transaction_id);
            
            return $transaction_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('GR8R Credits: Failed to deduct credits - ' . $e->getMessage());
            return new WP_Error('db_error', __('Database error occurred', 'gr8r-enhanced'), [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get available balance (excluding expired credits)
     */
    public function get_available_balance($user_id, $vendor_id, $service_type) {
        global $wpdb;
        
        $cache_key = "gr8r_available_credits_{$user_id}_{$vendor_id}_{$service_type}";
        $balance = wp_cache_get($cache_key, 'gr8r_credits');
        
        if (false === $balance) {
            $credit_record = $this->get_credit_record($user_id, $vendor_id, $service_type);
            if (!$credit_record) {
                return 0;
            }
            
            $balance = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(
                    CASE 
                        WHEN transaction_type = 'credit' AND (expiry_date IS NULL OR expiry_date >= %s) THEN amount 
                        WHEN transaction_type = 'debit' THEN -amount 
                        ELSE 0 
                    END
                ) 
                FROM {$wpdb->prefix}gr8r_enhanced_credit_transactions 
                WHERE credit_id = %d",
                current_time('mysql'),
                $credit_record['credit_id']
            )) ?: 0;
            
            wp_cache_set($cache_key, $balance, 'gr8r_credits', HOUR_IN_SECONDS);
        }
        
        return $balance;
    }
    
    /**
     * Get total balance (including expired credits)
     */
    public function get_balance($user_id, $vendor_id, $service_type) {
        global $wpdb;
        
        $cache_key = "gr8r_total_credits_{$user_id}_{$vendor_id}_{$service_type}";
        $balance = wp_cache_get($cache_key, 'gr8r_credits');
        
        if (false === $balance) {
            $credit_record = $this->get_credit_record($user_id, $vendor_id, $service_type);
            if (!$credit_record) {
                return 0;
            }
            
            $balance = $wpdb->get_var($wpdb->prepare(
                "SELECT balance FROM {$wpdb->prefix}gr8r_enhanced_credits WHERE credit_id = %d",
                $credit_record['credit_id']
            )) ?: 0;
            
            wp_cache_set($cache_key, $balance, 'gr8r_credits', HOUR_IN_SECONDS);
        }
        
        return $balance;
    }
    
    /**
     * AJAX handler for adding credits
     */
    public function ajax_add_credits() {
        try {
            $this->security->verify_nonce('gr8r_credit_actions');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied', 'gr8r-enhanced'), 403);
            }
            
            $user_id = $this->security->sanitize_input($_POST['user_id'], 'number');
            $vendor_id = $this->security->sanitize_input($_POST['vendor_id'], 'number');
            $service_type = $this->security->sanitize_input($_POST['service_type'], 'text');
            $amount = $this->security->sanitize_input($_POST['amount'], 'float');
            $description = $this->security->sanitize_input($_POST['description'] ?? '', 'text');
            $reference = $this->security->sanitize_input($_POST['reference'] ?? '', 'text');
            $expiry_date = !empty($_POST['expiry_date']) ? $this->security->sanitize_input($_POST['expiry_date'], 'text') : null;
            
            $result = $this->add_credits($user_id, $vendor_id, $service_type, $amount, $description, $reference, $expiry_date);
            
            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'data' => $result->get_error_data()
                ], 400);
            }
            
            wp_send_json_success([
                'message' => __('Credits added successfully', 'gr8r-enhanced'),
                'transaction_id' => $result,
                'new_balance' => $this->get_available_balance($user_id, $vendor_id, $service_type)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('An error occurred', 'gr8r-enhanced'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * AJAX handler for deducting credits
     */
    public function ajax_deduct_credits() {
        try {
            $this->security->verify_nonce('gr8r_credit_actions');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied', 'gr8r-enhanced'), 403);
            }
            
            $user_id = $this->security->sanitize_input($_POST['user_id'], 'number');
            $vendor_id = $this->security->sanitize_input($_POST['vendor_id'], 'number');
            $service_type = $this->security->sanitize_input($_POST['service_type'], 'text');
            $amount = $this->security->sanitize_input($_POST['amount'], 'float');
            $description = $this->security->sanitize_input($_POST['description'] ?? '', 'text');
            $reference = $this->security->sanitize_input($_POST['reference'] ?? '', 'text');
            
            $result = $this->deduct_credits($user_id, $vendor_id, $service_type, $amount, $description, $reference);
            
            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'data' => $result->get_error_data()
                ], 400);
            }
            
            wp_send_json_success([
                'message' => __('Credits deducted successfully', 'gr8r-enhanced'),
                'transaction_id' => $result,
                'new_balance' => $this->get_available_balance($user_id, $vendor_id, $service_type)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('An error occurred', 'gr8r-enhanced'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * AJAX handler for getting balance
     */
    public function ajax_get_balance() {
        try {
            $this->security->verify_nonce('gr8r_credit_actions');
            
            $user_id = $this->security->sanitize_input($_POST['user_id'], 'number');
            $vendor_id = $this->security->sanitize_input($_POST['vendor_id'] ?? 0, 'number');
            $service_type = $this->security->sanitize_input($_POST['service_type'] ?? '', 'text');
            
            // Users can only check their own balance unless they're admins
            if ($user_id != get_current_user_id() && !current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied', 'gr8r-enhanced'), 403);
            }
            
            $balance = $this->get_available_balance($user_id, $vendor_id, $service_type);
            
            wp_send_json_success([
                'balance' => $balance,
                'formatted' => $this->format_credit_amount($balance)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('An error occurred', 'gr8r-enhanced'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * AJAX handler for getting transactions
     */
    public function ajax_get_transactions() {
        try {
            $this->security->verify_nonce('gr8r_credit_actions');
            
            $user_id = $this->security->sanitize_input($_POST['user_id'], 'number');
            $vendor_id = $this->security->sanitize_input($_POST['vendor_id'] ?? 0, 'number');
            $service_type = $this->security->sanitize_input($_POST['service_type'] ?? '', 'text');
            $limit = $this->security->sanitize_input($_POST['limit'] ?? 50, 'number');
            
            // Users can only check their own transactions unless they're admins
            if ($user_id != get_current_user_id() && !current_user_can('manage_options')) {
                wp_send_json_error(__('Permission denied', 'gr8r-enhanced'), 403);
            }
            
            $credit_record = $this->get_credit_record($user_id, $vendor_id, $service_type);
            if (!$credit_record) {
                wp_send_json_success(['transactions' => []]);
            }
            
            $transactions = $this->get_transactions($credit_record['credit_id'], $limit);
            
            // Format transactions for display
            $formatted = array_map(function($transaction) {
                return [
                    'id' => $transaction['transaction_id'],
                    'amount' => $this->format_credit_amount(
                        $transaction['transaction_type'] === 'credit' ? $transaction['amount'] : -$transaction['amount']
                    ),
                    'type' => $transaction['transaction_type'],
                    'description' => $transaction['description'],
                    'date' => date_i18n(get_option('date_format') . ' ' . date_i18n(get_option('time_format'), strtotime($transaction['created_at'])),
                    'reference' => $transaction['reference'],
                    'expiry_date' => $transaction['expiry_date'] ? date_i18n(get_option('date_format'), strtotime($transaction['expiry_date'])) : null
                ];
            }, $transactions);
            
            wp_send_json_success([
                'transactions' => $formatted,
                'balance' => $this->format_credit_amount($credit_record['balance'])
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('An error occurred', 'gr8r-enhanced'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Format credit amount with currency symbol
     */
    public function format_credit_amount($amount) {
        return wc_price($amount, [
            'currency' => get_option('woocommerce_currency'),
            'decimals' => 2
        ]);
    }
    
    /**
     * Get or create credit record
     */
    private function get_or_create_credit_record($user_id, $vendor_id, $service_type) {
        global $wpdb;
        
        $cache_key = "gr8r_credit_record_{$user_id}_{$vendor_id}_{$service_type}";
        $record = wp_cache_get($cache_key, 'gr8r_credits');
        
        if (false === $record) {
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_credits 
                 WHERE user_id = %d AND vendor_id = %d AND service_type = %s",
                $user_id, $vendor_id, $service_type
            ), ARRAY_A);
            
            if (!$record) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'gr8r_enhanced_credits',
                    [
                        'user_id' => $user_id,
                        'vendor_id' => $vendor_id,
                        'service_type' => $service_type,
                        'balance' => 0,
                        'last_updated' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%f', '%s']
                );
                
                if ($result) {
                    $record = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_credits 
                         WHERE user_id = %d AND vendor_id = %d AND service_type = %s",
                        $user_id, $vendor_id, $service_type
                    ), ARRAY_A);
                }
            }
            
            if ($record) {
                wp_cache_set($cache_key, $record, 'gr8r_credits', DAY_IN_SECONDS);
            }
        }
        
        return $record;
    }
    
    /**
     * Get credit record
     */
    private function get_credit_record($user_id, $vendor_id, $service_type) {
        global $wpdb;
        
        $cache_key = "gr8r_credit_record_{$user_id}_{$vendor_id}_{$service_type}";
        $record = wp_cache_get($cache_key, 'gr8r_credits');
        
        if (false === $record) {
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_credits 
                 WHERE user_id = %d AND vendor_id = %d AND service_type = %s",
                $user_id, $vendor_id, $service_type
            ), ARRAY_A);
            
            if ($record) {
                wp_cache_set($cache_key, $record, 'gr8r_credits', DAY_IN_SECONDS);
            }
        }
        
        return $record;
    }
    
    /**
     * Update balance for a credit account
     */
    private function update_balance($credit_id) {
        global $wpdb;
        
        try {
            // Calculate new balance
            $balance = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(
                    CASE 
                        WHEN transaction_type = 'credit' THEN amount 
                        WHEN transaction_type = 'debit' THEN -amount 
                        ELSE 0 
                    END
                ) 
                FROM {$wpdb->prefix}gr8r_enhanced_credit_transactions 
                WHERE credit_id = %d",
                $credit_id
            )) ?: 0;
            
            // Update balance
            $result = $wpdb->update(
                $wpdb->prefix . 'gr8r_enhanced_credits',
                ['balance' => $balance, 'last_updated' => current_time('mysql')],
                ['credit_id' => $credit_id],
                ['%f', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            
            // Clear all related caches
            $credit_record = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id, vendor_id, service_type 
                 FROM {$wpdb->prefix}gr8r_enhanced_credits 
                 WHERE credit_id = %d",
                $credit_id
            ));
            
            if ($credit_record) {
                $this->clear_credit_cache($credit_record->user_id, $credit_record->vendor_id, $credit_record->service_type);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('GR8R Credits: Failed to update balance - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all cached data for a credit account
     */
    private function clear_credit_cache($user_id, $vendor_id, $service_type) {
        $keys = [
            "gr8r_credit_record_{$user_id}_{$vendor_id}_{$service_type}",
            "gr8r_available_credits_{$user_id}_{$vendor_id}_{$service_type}",
            "gr8r_total_credits_{$user_id}_{$vendor_id}_{$service_type}"
        ];
        
        foreach ($keys as $key) {
            wp_cache_delete($key, 'gr8r_credits');
        }
    }
    
    /**
     * Get all credits for user
     */
    public function get_user_credits($user_id) {
        global $wpdb;
        
        $cache_key = "gr8r_user_credits_{$user_id}";
        $credits = wp_cache_get($cache_key, 'gr8r_credits');
        
        if (false === $credits) {
            $credits = $wpdb->get_results($wpdb->prepare(
                "SELECT c.*, 
                 (SELECT SUM(amount) FROM {$wpdb->prefix}gr8r_enhanced_credit_transactions t 
                  WHERE t.credit_id = c.credit_id AND t.transaction_type = 'credit' 
                  AND (t.expiry_date IS NULL OR t.expiry_date >= %s)) as available_balance
                 FROM {$wpdb->prefix}gr8r_enhanced_credits c
                 WHERE c.user_id = %d",
                current_time('mysql'),
                $user_id
            ), ARRAY_A);
            
            wp_cache_set($cache_key, $credits, 'gr8r_credits', HOUR_IN_SECONDS);
        }
        
        return $credits;
    }
    
    /**
     * Get credit transactions
     */
    public function get_transactions($credit_id, $limit = 50) {
        global $wpdb;
        
        $cache_key = "gr8r_credit_transactions_{$credit_id}_{$limit}";
        $transactions = wp_cache_get($cache_key, 'gr8r_credits');
        
        if (false === $transactions) {
            $transactions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gr8r_enhanced_credit_transactions 
                 WHERE credit_id = %d 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $credit_id, $limit
            ), ARRAY_A);
            
            wp_cache_set($cache_key, $transactions, 'gr8r_credits', 15 * MINUTE_IN_SECONDS);
        }
        
        return $transactions;
    }
    
    /**
     * Cleanup expired credits
     */
    public function cleanup_expired_credits() {
        global $wpdb;
        
        try {
            // Find all credit transactions that have expired
            $expired_credits = $wpdb->get_results($wpdb->prepare(
                "SELECT t.credit_id, t.transaction_id, t.amount, c.user_id, c.vendor_id, c.service_type
                 FROM {$wpdb->prefix}gr8r_enhanced_credit_transactions t
                 JOIN {$wpdb->prefix}gr8r_enhanced_credits c ON t.credit_id = c.credit_id
                 WHERE t.transaction_type = 'credit'
                 AND t.expiry_date IS NOT NULL
                 AND t.expiry_date < %s
                 AND t.amount > 0",
                current_time('mysql')
            ));
            
            if (empty($expired_credits)) {
                return;
            }
            
            $wpdb->query('START TRANSACTION');
            
            foreach ($expired_credits as $credit) {
                // Add expiration transaction
                $wpdb->insert(
                    $wpdb->prefix . 'gr8r_enhanced_credit_transactions',
                    [
                        'credit_id' => $credit->credit_id,
                        'amount' => $credit->amount,
                        'transaction_type' => 'debit',
                        'reference' => 'expired',
                        'description' => __('Credit expired', 'gr8r-enhanced'),
                        'created_by' => 0, // System
                        'created_at' => current_time('mysql'),
                        'linked_transactions' => maybe_serialize([[
                            'id' => $credit->transaction_id,
                            'amount_used' => $credit->amount
                        ]])
                    ],
                    ['%d', '%f', '%s', '%s', '%s', '%d', '%s', '%s']
                );
                
                // Clear cache for this credit account
                $this->clear_credit_cache($credit->user_id, $credit->vendor_id, $credit->service_type);
            }
            
            // Update all affected balances
            $affected_credit_ids = array_unique(array_column($expired_credits, 'credit_id'));
            foreach ($affected_credit_ids as $credit_id) {
                $this->update_balance($credit_id);
            }
            
            $wpdb->query('COMMIT');
            
            $this->security->log_event(
                'credits_expired',
                sprintf('Processed expiration for %d credit transactions', count($expired_credits))
            );
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('GR8R Credits: Failed to process expired credits - ' . $e->getMessage());
        }
    }
    
    /**
     * Get all vendors that a user has credits with
     */
    public function get_user_vendors($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT vendor_id 
             FROM {$wpdb->prefix}gr8r_enhanced_credits 
             WHERE user_id = %d AND balance > 0",
            $user_id
        ));
    }
    
    /**
     * Get all service types that a user has credits for with a specific vendor
     */
    public function get_user_vendor_services($user_id, $vendor_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT service_type 
             FROM {$wpdb->prefix}gr8r_enhanced_credits 
             WHERE user_id = %d AND vendor_id = %d AND balance > 0",
            $user_id, $vendor_id
        ));
    }
}