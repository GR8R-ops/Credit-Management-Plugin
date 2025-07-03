<?php
/*
Plugin Name: GR8R Credit Manager
Description: Admin interface for managing vendor credits.
Version: 1.0
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create the tables for the plugin on activation.
 */
function gr8r_credits_create_tables() {
	gr8r_credits_create_credit_balance_table();
	gr8r_credits_create_credit_transaction_table();
}

// Hook: Create Tables on Activation
register_activation_hook( __FILE__, 'gr8r_credits_create_tables' );

/**
 * Create or update the credit balance table - [wp]_gr8r_credit_balances
 */
function gr8r_credits_create_credit_balance_table() {
	global $wpdb;

	/*
		[Functional Comment]
		As written, the table has the following columns:
		 - id
		 - user_id
		 - vendor_id
		 - service_type
		 - balance
		 - last_updated
		What strikes me is that there is no relationship between this data and the product(s) the credits are for.
		Without knowing the details, I can think of two ways that a credit might work:
		 - A gym instructor offers x number of sessions as part of a package.
		   * This should then be a reference to the WooCommerce product (or however we are representing the specific session)
		   * We may need a second flag to indicate whether the credit is a monetary amount e.g. P200, or a number of sessions, e.g. 3 sessions
		     - That suggests that `balance` might need to be an integer, not a float
		 - A gym instructor offers a general credit towards one of a few products, e.g. P200
		   * This may need to reference a group of product IDs or a product category ID
		 - A gym instructor offers a choice of session types or lengths
		   * This feels like a future need, and not something short term

		QUESTIONS:
		 - How are we representing the product(s) the credits are for?
		 - What level of flexibility are we expecting?
	*/
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

/**
 * Create the credit transaction table - [wp]_gr8r_credit_transactions
 */
function gr8r_credits_create_credit_transaction_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'gr8r_credit_transactions';
	$charset_collate = $wpdb->get_charset_collate();
	/*
		[Functional Comment]
		As written, the table has the following columns:
		 - id
		 - credit_id
		 - user_id
		 - vendor_id
		 - amount
		 - transaction_type
		 - description
		 - created_at
		As I see it, this table is lacking some details. I am thinking about the following:
		 - The user must have purchased a product or a subscription as a starting point. 
		   * We definitely need a reference to the order and what was paid etc.
		     - I am not sure how that works for subscription renewals, but we'd want each credits transaction to link back to the specific purchase that added it.
		   * We almost certainly need a reference to the specific product within that order as well - the purchase may have included multiple products.
		   * Do we need an amount to reflect the effective value of the credit? What happens if a user purchases 4 sessions, attends 2 and then the gym instructor leaves the program? Do we compute the refund manually?
		 - As for the credits table, we need to indicate what the credit is for. Is this a monetary amount? Or a quantity of sessions? Or a group/set/category of items?

		QUESTIONS:
		 - How are we representing the product(s) the credits are for?
		 - How will the gym instructor (or an admin) set up the gym subscriptions/bundles to represent the credits that they include?
		   * At an implementation level, it feels like we should store this data as via product meta for the one-time product or subscription, and integrate with the WooCommerce product editor/UI to edit the fields
		   * We can then use the same product meta to show accurate details to users
		   * We may need separate fields for monetary amounts and session quantities
		   * It will probably be best to have clear ways to identify what bundling options we need to support, and make that a drop-down control in the product editor UI
		 - We need to keep in mind that we need to be able to list what outstanding credits a vendor has
	 */
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
// TODO: Move this into an action so it can be run in a known state; the `plugins_loaded` or `init` actions are likely the right ones.
if ( ! get_option('gr8r_credit_tables_created') ) {
	gr8r_credits_create_tables();
	update_option('gr8r_credit_tables_created', 1);
}

// Admin Menu
add_action('admin_menu', function () {
	require_once plugin_dir_path(__FILE__) . 'includes/class-gr8r-credits-admin.php';

	add_menu_page(
		'GR8R Credit Manager',
		'GR8R Credits',
		'manage_options',
		'gr8r-credits',
		function () {
			$admin = new GR8R_Credits_Admin();
			$admin->handleFormSubmission();
			$admin->handleRequest();
		},
		'dashicons-money-alt',
		26
	);
} );
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
	// TODO: [Security] Confirm whether we need a nonce or other security check here.
	if (isset($query_vars['gr8r-credit-report'])) {
		include plugin_dir_path(__FILE__) . 'templates/vendor/credit-report.php';
	}
}

// AJAX: Vendor Report
add_action('wp_ajax_gr8r_vendor_report', 'gr8r_handle_vendor_report');

/**
 * Handle the vendor report AJAX request.
 */
function gr8r_handle_vendor_report() {
	// TODO: [Security] Confirm the intended permissions for this check. As things stand, this check will allow the following users through:
	//  - User has the dokandar and manage_woocommerce capabilities.
	//  - User has the dokandar capability, but not the manage_woocommerce capability.
	//  - User has the manage_woocommerce capability, but not the dokandar capability.
	if ( ! current_user_can('dokandar') && ! current_user_can('manage_woocommerce') ) {
		wp_die(__('Unauthorized access', 'gr8r'));
	}

	$vendor_id = get_current_user_id();
	global $wpdb;

	$balances = $wpdb->get_results(
		$wpdb->prepare(
			"
				SELECT user_id, service_type, balance, last_updated
				FROM {$wpdb->prefix}gr8r_credit_balances
				WHERE vendor_id = %d
			",
			$vendor_id
		),
		ARRAY_A
	);

	$debits = $wpdb->get_results(
		$wpdb->prepare(
			"
				SELECT user_id, SUM(amount) AS total_debit
				FROM {$wpdb->prefix}gr8r_credit_transactions
				WHERE vendor_id = %d AND transaction_type = 'debit'
				GROUP BY user_id
			",
			$vendor_id
		),
		OBJECT_K
	);

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

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"
				SELECT vendor_id, service_type, balance
				FROM {$wpdb->prefix}gr8r_credit_balances
				WHERE user_id = %d
			",
			$user_id
		),
		ARRAY_A
	);

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
