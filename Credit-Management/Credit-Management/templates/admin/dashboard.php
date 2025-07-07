<?php // Inside class dashboard

class dashboard {

public function render_credit_balances() {
    global $wpdb;

    $table = $wpdb->prefix . 'gr8r_credits';

    $where = "1=1";
    $params = [];

    if (!empty($_GET['vendor_id'])) {
        $vendor_id = absint($_GET['vendor_id']);
        $where .= " AND vendor_id = %d";
        $params[] = $vendor_id;
    }

    if (!empty($_GET['service_type'])) {
        $service = sanitize_text_field($_GET['service_type']);
        $where .= " AND service_type = %s";
        $params[] = $service;
    }

    $query = $wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY last_updated DESC", $params);
    $balances = $wpdb->get_results($query, ARRAY_A);

    include GR8R_PATH . 'templates/admin/credit-balances.php';
}

public function render_transactions() {
    global $wpdb;

    $table = $wpdb->prefix . 'gr8r_credit_transactions';
    $where = "1=1";
    $params = [];

    if (!empty($_GET['user_id'])) {
        $user_id = absint($_GET['user_id']);
        // Join with credits table to filter by user
        $query = $wpdb->prepare("
            SELECT t.* FROM $table t
            JOIN {$wpdb->prefix}gr8r_credits c ON t.credit_id = c.credit_id
            WHERE c.user_id = %d
            ORDER BY t.created_at DESC
            LIMIT 100
        ", $user_id);
        $transactions = $wpdb->get_results($query, ARRAY_A);
    } else if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $start = sanitize_text_field($_GET['start_date']);
        $end = sanitize_text_field($_GET['end_date']);
        $query = $wpdb->prepare("SELECT * FROM $table WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC LIMIT 100", $start, $end);
        $transactions = $wpdb->get_results($query, ARRAY_A);
    } else {
        // No filters, get last 100
        $transactions = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100", ARRAY_A);
    }

    include GR8R_PATH . 'templates/admin/transactions.php';
}

} // end class
?>
<!-- dashboard.php -->
<div class="wrap">
    <h1><?php _e('GreatR Credit Management Dashboard', 'gr8r'); ?></h1>
    <p>Welcome to the admin dashboard. Add your dashboard content here.</p>
</div>
