<?php
defined('ABSPATH') || exit;

class GR8R_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('GreatR Credits', 'gr8r'),
            __('GreatR Credits', 'gr8r'),
            'manage_options',
            'gr8r-credits',
            [$this, 'render_admin_dashboard'],
            'dashicons-money-alt'
        );

        add_submenu_page(
            'gr8r-credits',
            __('Credit Balances', 'gr8r'),
            __('Balances', 'gr8r'),
            'manage_options',
            'gr8r-credit-balances',
            [$this, 'render_credit_balances']
        );

        add_submenu_page(
            'gr8r-credits',
            __('Transactions', 'gr8r'),
            __('Transactions', 'gr8r'),
            'manage_options',
            'gr8r-transactions',
            [$this, 'render_transactions']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'gr8r-') !== false) {
            wp_enqueue_style('gr8r-admin', GR8R_URL . 'assets/css/admin.css', [], GR8R_VERSION);
            wp_enqueue_script('gr8r-admin', GR8R_URL . 'assets/js/admin.js', ['jquery'], GR8R_VERSION, true);
        }
    }

    public function render_admin_dashboard() {
        echo '<div class="wrap"><h1>' . __('GreatR Credit Management', 'gr8r') . '</h1>';
        echo '<p>' . __('Use the submenus to view balances and transactions.', 'gr8r') . '</p></div>';
    }

    public function render_credit_balances() {
        global $wpdb;

        $table = $wpdb->prefix . 'gr8r_credits';

        // Prepare WHERE clause
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

        if ($params) {
            $query = $wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY last_updated DESC", $params);
        } else {
            $query = "SELECT * FROM $table WHERE $where ORDER BY last_updated DESC";
        }

        $balances = $wpdb->get_results($query, ARRAY_A);

        ?>
        <div class="wrap">
            <h1><?php _e('Credit Balances', 'gr8r'); ?></h1>

            <form method="get" action="">
                <input type="hidden" name="page" value="gr8r-credit-balances" />
                <label><?php _e('Vendor ID:', 'gr8r'); ?>
                    <input type="number" name="vendor_id" value="<?php echo esc_attr($_GET['vendor_id'] ?? ''); ?>" />
                </label>
                <label><?php _e('Service Type:', 'gr8r'); ?>
                    <input type="text" name="service_type" value="<?php echo esc_attr($_GET['service_type'] ?? ''); ?>" />
                </label>
                <input type="submit" class="button" value="<?php _e('Filter', 'gr8r'); ?>" />
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'gr8r'); ?></th>
                        <th><?php _e('Vendor ID', 'gr8r'); ?></th>
                        <th><?php _e('Service Type', 'gr8r'); ?></th>
                        <th><?php _e('Balance', 'gr8r'); ?></th>
                        <th><?php _e('Last Updated', 'gr8r'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($balances)) : ?>
                        <?php foreach ($balances as $balance) : 
                            $user = get_user_by('id', $balance['user_id']);
                        ?>
                        <tr>
                            <td>
                                <?php if ($user) : ?>
                                    <a href="<?php echo get_edit_user_link($balance['user_id']); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                <?php else : ?>
                                    <?php _e('User not found', 'gr8r'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($balance['vendor_id']); ?></td>
                            <td><?php echo esc_html($balance['service_type']); ?></td>
                            <td><?php echo number_format($balance['balance'], 2); ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($balance['last_updated'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5"><?php _e('No credit balances found.', 'gr8r'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_transactions() {
        global $wpdb;

        $table = $wpdb->prefix . 'gr8r_credit_transactions';

        $where = "1=1";
        $params = [];
        $join_credit = "";

        // Filter by user_id (via credits table)
        if (!empty($_GET['user_id'])) {
            $user_id = absint($_GET['user_id']);
            $join_credit = "JOIN {$wpdb->prefix}gr8r_credits c ON t.credit_id = c.credit_id";
            $where .= " AND c.user_id = %d";
            $params[] = $user_id;
        }

        // Filter by date range
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $start = sanitize_text_field($_GET['start_date']);
            $end = sanitize_text_field($_GET['end_date']);
            $where .= " AND t.created_at BETWEEN %s AND %s";
            $params[] = $start . ' 00:00:00';
            $params[] = $end . ' 23:59:59';
        }

        $sql = "SELECT t.* FROM $table t $join_credit WHERE $where ORDER BY t.created_at DESC LIMIT 100";

        if ($params) {
            $query = $wpdb->prepare($sql, $params);
        } else {
            $query = $sql;
        }

        $transactions = $wpdb->get_results($query, ARRAY_A);
        ?>
        <div class="wrap">
            <h1><?php _e('Credit Transactions', 'gr8r'); ?></h1>

            <form method="get" action="">
                <input type="hidden" name="page" value="gr8r-transactions" />
                <label><?php _e('User ID:', 'gr8r'); ?>
                    <input type="number" name="user_id" value="<?php echo esc_attr($_GET['user_id'] ?? ''); ?>" />
                </label>
                <label><?php _e('Start Date:', 'gr8r'); ?>
                    <input type="date" name="start_date" value="<?php echo esc_attr($_GET['start_date'] ?? ''); ?>" />
                </label>
                <label><?php _e('End Date:', 'gr8r'); ?>
                    <input type="date" name="end_date" value="<?php echo esc_attr($_GET['end_date'] ?? ''); ?>" />
                </label>
                <input type="submit" class="button" value="<?php _e('Filter', 'gr8r'); ?>" />
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Transaction ID', 'gr8r'); ?></th>
                        <th><?php _e('Credit ID', 'gr8r'); ?></th>
                        <th><?php _e('User', 'gr8r'); ?></th>
                        <th><?php _e('Amount', 'gr8r'); ?></th>
                        <th><?php _e('Type', 'gr8r'); ?></th>
                        <th><?php _e('Description', 'gr8r'); ?></th>
                        <th><?php _e('Date', 'gr8r'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)) : ?>
                        <?php foreach ($transactions as $transaction) : 
                            // get user linked to this transaction's credit
                            $credit = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}gr8r_credits WHERE credit_id = %d", 
                                $transaction['credit_id']
                            ));
                            $user = $credit ? get_user_by('id', $credit->user_id) : false;
                        ?>
                        <tr>
                            <td><?php echo esc_html($transaction['transaction_id']); ?></td>
                            <td><?php echo esc_html($transaction['credit_id']); ?></td>
                            <td>
                                <?php if ($user) : ?>
                                    <a href="<?php echo get_edit_user_link($user->ID); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                <?php else : ?>
                                    <?php _e('User not found', 'gr8r'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($transaction['amount'], 2); ?></td>
                            <td><?php echo esc_html(ucfirst($transaction['transaction_type'])); ?></td>
                            <td><?php echo esc_html($transaction['description']); ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($transaction['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="7"><?php _e('No transactions found.', 'gr8r'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
