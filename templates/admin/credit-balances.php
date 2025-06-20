<div class="wrap">
    <h1><?php _e('Credit Balances', 'gr8r'); ?></h1>
    
    <div class="gr8r-admin-container">
        <div class="gr8r-admin-actions">
            <h2><?php _e('Add Credits', 'gr8r'); ?></h2>
            <form method="post" action="<?php echo admin_url('admin.php?page=gr8r-credit-balances'); ?>">
                <?php wp_nonce_field('gr8r_admin_actions'); ?>
                <input type="hidden" name="gr8r_action" value="add_credits">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="user_id"><?php _e('User ID', 'gr8r'); ?></label></th>
                        <td>
                            <input type="number" name="user_id" id="user_id" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="amount"><?php _e('Amount', 'gr8r'); ?></label></th>
                        <td>
                            <input type="number" step="0.01" name="amount" id="amount" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php _e('Description', 'gr8r'); ?></label></th>
                        <td>
                            <input type="text" name="description" id="description">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Add Credits', 'gr8r')); ?>
            </form>
        </div>
        
        <div class="gr8r-balances-list">
            <h2><?php _e('Current Balances', 'gr8r'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'gr8r'); ?></th>
                        <th><?php _e('Balance', 'gr8r'); ?></th>
                        <th><?php _e('Service Type', 'gr8r'); ?></th>
                        <th><?php _e('Last Updated', 'gr8r'); ?></th>
                    </tr>
                </thead>
                <tbody>
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
                                <?php echo __('User not found', 'gr8r'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($balance['balance'], 2); ?></td>
                        <td><?php echo esc_html($balance['service_type']); ?></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($balance['last_updated'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>