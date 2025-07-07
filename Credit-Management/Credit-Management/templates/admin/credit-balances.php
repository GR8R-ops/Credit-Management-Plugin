<div class="wrap">
    <h1><?php _e('Credit Balances', 'gr8r'); ?></h1>
    
    <div class="gr8r-admin-container">
        <div class="gr8r-admin-actions">
            <h2><?php _e('Add Credits', 'gr8r'); ?></h2>
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="gr8r-credit-balances" />
                
                <label for="vendor_id">
                    <?php _e('Vendor ID', 'gr8r'); ?>:
                    <input type="number" name="vendor_id" id="vendor_id" value="<?php echo esc_attr($_GET['vendor_id'] ?? ''); ?>" />
                </label>
                
                <label for="service_type" style="margin-left: 20px;">
                    <?php _e('Service Type', 'gr8r'); ?>:
                    <input type="text" name="service_type" id="service_type" value="<?php echo esc_attr($_GET['service_type'] ?? ''); ?>" />
                </label>
                
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'gr8r'); ?>" />
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