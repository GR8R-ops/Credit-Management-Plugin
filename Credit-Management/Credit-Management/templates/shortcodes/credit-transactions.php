<div class="gr8r-credit-transactions">
    <h3><?php _e('Your Transactions', 'gr8r'); ?></h3>
    
    <?php if (empty($transactions)) : ?>
        <p><?php _e('No transactions found.', 'gr8r'); ?></p>
    <?php else : ?>
        <table class="gr8r-transactions-table">
            <thead>
                <tr>
                    <th><?php _e('Date', 'gr8r'); ?></th>
                    <th><?php _e('Amount', 'gr8r'); ?></th>
                    <th><?php _e('Type', 'gr8r'); ?></th>
                    <th><?php _e('Description', 'gr8r'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction) : ?>
                <tr>
                    <td><?php echo date_i18n(get_option('date_format'), strtotime($transaction['created_at'])); ?></td>
                    <td class="<?php echo $transaction['transaction_type']; ?>">
                        <?php echo ($transaction['transaction_type'] === 'credit' ? '+' : '-') . number_format($transaction['amount'], 2); ?>
                    </td>
                    <td><?php echo ucfirst($transaction['transaction_type']); ?></td>
                    <td><?php echo esc_html($transaction['description']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>