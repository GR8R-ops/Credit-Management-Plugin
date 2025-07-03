<?php
/*
Plugin Name: GR8R Credit Manager
Description: Admin interface for managing vendor credits.
Version: 1.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hook: Create Tables on Activation
register_activation_hook(__FILE__, function () {
	gr8r_create_credit_balance_table();
	gr8r_create_credit_transaction_table();
});

function gr8r_create_credit_balance_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'gr8r_credit_balances';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		user_id BIGINT UNSIGNED NOT NULL,
		vendor_id BIGINT UNSIGNED NOT NULL,
		service_type VARCHAR(100) NOT NULL,
		balance FLOAT DEFAULT 0,
		last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
		UNIQUE KEY user_vendor_service (user_id, vendor_id, service_type)
	) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);
}

function gr8r_create_credit_transaction_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'gr8r_credit_transactions';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		credit_id BIGINT UNSIGNED NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL,
		vendor_id BIGINT UNSIGNED NOT NULL,
		amount FLOAT NOT NULL,
		transaction_type VARCHAR(20) NOT NULL,
		description TEXT,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP
	) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta($sql);
}

// Optional table creation for dev/testing
if (!get_option('gr8r_credit_tables_created')) {
	gr8r_create_credit_balance_table();
	gr8r_create_credit_transaction_table();
	update_option('gr8r_credit_tables_created', 1);
}

// Admin Menu
add_action('admin_menu', function () {
	require_once plugin_dir_path(__FILE__) . 'includes/class-admin.php';

    add_menu_page(
		'GR8R Credit Manager',
		'GR8R Credits',
		'manage_options',
		'gr8r-credits',
		function () {
			$admin = new GR8R_Admin();
			$admin->handleFormSubmission();
			$admin->handleRequest();
		},
		'dashicons-money-alt',
		26
	);
});

// Dokan Vendor Dashboard Tab
add_filter('dokan_get_dashboard_nav', 'gr8r_add_vendor_report_tab');
function gr8r_add_vendor_report_tab($urls) {
	$urls['gr8r-credit-report'] = [
		'title' => __('Credit Report', 'gr8r'),
		'icon' => 'fa-credit-card',
		'url' => dokan_get_navigation_url('gr8r-credit-report'),
		'position' => 60
	];
	return $urls;
}

// Render Vendor Report Page
add_action('dokan_load_custom_template', 'gr8r_load_vendor_report_page');
function gr8r_load_vendor_report_page($query_vars) {
	if (isset($query_vars['gr8r-credit-report'])) {
		include plugin_dir_path(__FILE__) . 'templates/vendor/credit-report.php';
	}
}

// AJAX: Vendor Report
add_action('wp_ajax_gr8r_vendor_report', 'gr8r_handle_vendor_report');
function gr8r_handle_vendor_report() {
	if (!current_user_can('dokandar') && !current_user_can('manage_woocommerce')) {
		wp_die(__('Unauthorized access', 'gr8r'));
	}

	$vendor_id = get_current_user_id();
	global $wpdb;

	$balances = $wpdb->get_results($wpdb->prepare("
		SELECT user_id, service_type, balance, last_updated 
		FROM {$wpdb->prefix}gr8r_credit_balances
		WHERE vendor_id = %d
	", $vendor_id), ARRAY_A);

	$debits = $wpdb->get_results($wpdb->prepare("
		SELECT user_id, SUM(amount) AS total_debit
		FROM {$wpdb->prefix}gr8r_credit_transactions
		WHERE vendor_id = %d AND transaction_type = 'debit'
		GROUP BY user_id
	", $vendor_id), OBJECT_K);

	echo '<table class="widefat striped">';
	echo '<thead><tr><th>User</th><th>Service</th><th>Balance</th><th>Used</th><th>Last Updated</th></tr></thead><tbody>';

	$total_balance = 0;
	$total_used = 0;

	if (empty($balances)) {
		echo '<tr><td colspan="5">No credit records found.</td></tr>';
	} else {
		foreach ($balances as $b) {
			$user = get_user_by('id', $b['user_id']);
			$used = isset($debits[$b['user_id']]) ? $debits[$b['user_id']]->total_debit : 0;

			$total_balance += $b['balance'];
			$total_used += $used;

			echo '<tr>';
			echo '<td>' . esc_html($user ? $user->display_name : 'Unknown') . '</td>';
			echo '<td>' . esc_html($b['service_type']) . '</td>';
			echo '<td>' . number_format($b['balance'], 2) . '</td>';
			echo '<td>' . number_format($used, 2) . '</td>';
			echo '<td>' . esc_html($b['last_updated']) . '</td>';
			echo '</tr>';
		}
	}

	echo '</tbody>';
	echo '<tfoot><tr><td><strong>Total</strong></td><td></td><td><strong>' . number_format($total_balance, 2) . '</strong></td><td><strong>' . number_format($total_used, 2) . '</strong></td><td></td></tr></tfoot>';
	echo '</table>';

	wp_die();
}

// Shortcode for Users: [gr8r_credit_balance]
add_shortcode('gr8r_credit_balance', function () {
	if (!is_user_logged_in()) {
		return '<p>Please log in to view your credit balances.</p>';
	}

	global $wpdb;
	$user_id = get_current_user_id();

	$results = $wpdb->get_results($wpdb->prepare("
		SELECT vendor_id, service_type, balance
		FROM {$wpdb->prefix}gr8r_credit_balances
		WHERE user_id = %d
	", $user_id), ARRAY_A);

	if (empty($results)) {
		return '<p>You currently have no credits available.</p>';
	}

	ob_start();
	echo '<table class="widefat striped"><thead><tr><th>Vendor</th><th>Service</th><th>Balance</th></tr></thead><tbody>';
	foreach ($results as $r) {
		$vendor = get_user_by('ID', $r['vendor_id']);
		echo '<tr>';
		echo '<td>' . esc_html($vendor ? $vendor->display_name : 'Vendor #' . $r['vendor_id']) . '</td>';
		echo '<td>' . esc_html($r['service_type']) . '</td>';
		echo '<td>' . number_format($r['balance'], 2) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	return ob_get_clean();
});
