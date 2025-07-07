<?php
defined('ABSPATH') || exit;

class GR8R_Shortcodes {
    
    public function __construct() {
        add_shortcode('gr8r_dashboard', [$this, 'render_dashboard']);
        add_shortcode('gr8r_user_credits', [$this, 'render_user_credits']);
    }

    // Dashboard determines what to show based on user role
    public function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to view this page.</p>';
        }

        $user = wp_get_current_user();

        if (in_array('vendor', $user->roles)) {
            return $this->render_vendor_dashboard($user->ID);
        } else {
            return $this->render_user_credits($user->ID);
        }
    }

    // User credits: sum of credit - debit from transactions
    public function render_user_credits($user_id = null) {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to view your credits.</p>';
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        global $wpdb;
        $transactions_table = $wpdb->prefix . 'gr8r_credit_transactions';
        $credits_table = $wpdb->prefix . 'gr8r_credits';

        // Get the user's credit_id(s)
        $credit_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT credit_id FROM $credits_table WHERE user_id = %d",
            $user_id
        ));

        if (empty($credit_ids)) {
            return '<p>No credit records found for you.</p>';
        }

        $credit_id_list = implode(',', array_map('intval', $credit_ids));

        // Get total credited and debited
        $total_credit = $wpdb->get_var("SELECT SUM(amount) FROM $transactions_table WHERE credit_id IN ($credit_id_list) AND transaction_type = 'credit'");
        $total_debit  = $wpdb->get_var("SELECT SUM(amount) FROM $transactions_table WHERE credit_id IN ($credit_id_list) AND transaction_type = 'debit'");

        $total_credit = $total_credit ?: 0;
        $total_debit  = $total_debit ?: 0;
        $balance = $total_credit - $total_debit;

        ob_start();
        ?>
        <h3>Your Current Credit Balance</h3>
        <p><strong>Total Credited:</strong> <?php echo number_format($total_credit, 2); ?></p>
        <p><strong>Total Debited:</strong> <?php echo number_format($total_debit, 2); ?></p>
        <p><strong>Available Balance:</strong> <?php echo number_format($balance, 2); ?></p>
        <?php
        return ob_get_clean();
    }

    // Vendor dashboard
    public function render_vendor_dashboard($vendor_id) {
        global $wpdb;
        $credits_table = $wpdb->prefix . 'gr8r_credits';

        $credit_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT service_type, SUM(balance) as total_balance FROM $credits_table WHERE vendor_id = %d GROUP BY service_type",
            $vendor_id
        ), ARRAY_A);

        // Optional: bookings
        $booking_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}bookings'")) {
            $booking_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bookings WHERE vendor_id = %d",
                $vendor_id
            ));
        }

        ob_start();
        ?>
        <h3>Vendor Dashboard</h3>

        <?php if (empty($credit_rows)) : ?>
            <p>No credits or earnings yet.</p>
        <?php else : ?>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Total Earnings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credit_rows as $credit) : ?>
                        <tr>
                            <td><?php echo esc_html($credit['service_type']); ?></td>
                            <td><?php echo number_format($credit['total_balance'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><strong>Total Bookings:</strong> <?php echo intval($booking_count); ?></p>
        <?php
        return ob_get_clean();
    }
}
