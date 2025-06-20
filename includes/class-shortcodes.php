<?php
defined('ABSPATH') || exit;

class GR8R_Shortcodes {
    
    public function __construct() {
        add_shortcode('gr8r_credit_balance', [$this, 'credit_balance_shortcode']);
        add_shortcode('gr8r_credit_transactions', [$this, 'credit_transactions_shortcode']);
        add_shortcode('gr8r_vendor_credits', [$this, 'vendor_credits_shortcode']);
    }

    public function credit_balance_shortcode($atts) {
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }

        $atts = shortcode_atts([
            'vendor_id' => 0,
            'service_type' => '',
            'show_all' => 'no'
        ], $atts, 'gr8r_credit_balance');

        $manager = new GR8R_Credits_Manager();
        $balance = $manager->get_balance(
            get_current_user_id(),
            $atts['vendor_id'],
            $atts['service_type']
        );

        ob_start();
        include GR8R_PATH . 'templates/shortcodes/credit-balance.php';
        return ob_get_clean();
    }

    public function credit_transactions_shortcode($atts) {
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }

        $atts = shortcode_atts([
            'vendor_id' => 0,
            'service_type' => '',
            'limit' => 10,
            'pagination' => 'yes'
        ], $atts, 'gr8r_credit_transactions');

        $manager = new GR8R_Credits_Manager();
        $balance = $manager->get_balance(
            get_current_user_id(),
            $atts['vendor_id'],
            $atts['service_type']
        );

        if (!$balance) {
            return '<p>' . __('No credit account found.', 'gr8r') . '</p>';
        }

        $transactions = $manager->get_transactions($balance['credit_id'], $atts['limit']);

        ob_start();
        include GR8R_PATH . 'templates/shortcodes/credit-transactions.php';
        return ob_get_clean();
    }

    public function vendor_credits_shortcode($atts) {
        if (!is_user_logged_in()) {
            return $this->login_required_message();
        }

        if (!current_user_can('manage_options') && !current_user_can('vendor')) {
            return $this->insufficient_permissions_message();
        }

        $atts = shortcode_atts([
            'service_type' => '',
            'show_inactive' => 'no',
            'per_page' => 20
        ], $atts, 'gr8r_vendor_credits');

        ob_start();
        include GR8R_PATH . 'templates/shortcodes/vendor-credits.php';
        return ob_get_clean();
    }

    protected function login_required_message() {
        ob_start();
        ?>
        <div class="gr8r-message gr8r-error">
            <p><?php _e('You must be logged in to view this content.', 'gr8r'); ?></p>
            <?php echo wp_login_form(['echo' => false]); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function insufficient_permissions_message() {
        ob_start();
        ?>
        <div class="gr8r-message gr8r-error">
            <p><?php _e('You do not have sufficient permissions to view this content.', 'gr8r'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}