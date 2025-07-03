<?php

class GR8R_Credits_Admin {

public function handleRequest() {
    $view = $_GET['view'] ?? 'dashboard';

    // TODO: [Security] Check user permissions and/or nonce before rendering anything.
    switch ($view) {
        case 'balances':
            $this->render_credit_balances();
            break;
        case 'transactions':
            $this->render_transactions();
            break;
        case 'settings':
            $this->render_settings();
            break;
        default:
            $this->render_admin_dashboard();
    }
}


    public function handleFormSubmission() {
        if (!isset($_POST['gr8r_action'])) {
            return;
        }

        $action = $_POST['gr8r_action'];

        // TODO: [Security] Check user permissions and/or nonce before processing the form.

        require_once __DIR__ . '/class-gr8r-credits-manager.php';

        $manager = new GR8R_Credits_Manager();

        if ($action === 'add_credits') {
            $user_id = (int)$_POST['user_id'];
            $amount = (float)$_POST['amount'];
            $description = strip_tags($_POST['description']);

            $balance = $manager->get_balance($user_id);
            $credit_id = $balance ? $balance['credit_id'] : $manager->create_credit_account($user_id);

            $manager->add_transaction(
                $credit_id,
                $amount,
                'credit',
                'admin_adjustment',
                $description,
                1 // Replace with actual admin ID if available
            );

            // TODO: It's better to use wp_safe_redirect() - @see https://developer.wordpress.org/reference/functions/wp_safe_redirect/
            header("Location: ?page=balances&status=credits_added");
            exit;
        }

        // Add more cases here (e.g., adjust_credits)
    }

    public function render_admin_dashboard() {
        include plugin_dir_path(__DIR__) . 'templates/admin/dashboard.php';
    }

public function render_credit_balances() {
    global $wpdb;
    $table = $wpdb->prefix . 'gr8r_credit_balances';

    $vendor_filter = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
    $service_filter = isset($_GET['service_type']) ? sanitize_text_field($_GET['service_type']) : '';

    // Start the query with no conditions to handle the OR logic below
    $query = "SELECT * FROM $table";

    $conditions = [];
    $params = [];

    if ($vendor_filter) {
        $conditions[] = "vendor_id = %d";
        $params[] = $vendor_filter;
    }

    if (!empty($service_filter)) {
        $conditions[] = "service_type = %s";
        $params[] = $service_filter;
    }

    if ($conditions) {
        // Join conditions with OR
        $query .= " WHERE (" . implode(' OR ', $conditions) . ")";
        // Prepare query with params
        $query = $wpdb->prepare($query, ...$params);
    }

    $balances = $wpdb->get_results($query, ARRAY_A);

    include plugin_dir_path(__FILE__) . '../templates/admin/credit-balances.php';
}
public function render_transactions() {
    global $wpdb;

    $table = $wpdb->prefix . 'gr8r_credit_transactions';
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';

    $query = "SELECT * FROM $table WHERE 1=1";
    $params = [];

    if ($user_id) {
        $query .= " AND user_id = %d";
        $params[] = $user_id;
    }

    if (!empty($date)) {
        $query .= " AND DATE(created_at) = %s";
        $params[] = $date;
    }

    $prepared = $wpdb->prepare($query, ...$params);
    $transactions = $wpdb->get_results($prepared, ARRAY_A);

    include plugin_dir_path(__DIR__) . 'templates/admin/transactions.php';
}



    public function render_settings() {
        include plugin_dir_path(__DIR__) . 'templates/admin/settings.php';
    }
}
