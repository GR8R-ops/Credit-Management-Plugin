<div class="wrap">
    <a href="<?php echo admin_url('admin.php?page=gr8r-credits'); ?>" class="button">Back</a>
    <h1>Transaction Logs</h1>

    <form method="get">
        <input type="hidden" name="page" value="gr8r-credits">
        <input type="hidden" name="view" value="transactions">
        <label>User ID: <input type="number" name="user_id" value="<?php echo esc_attr($_GET['user_id'] ?? '') ?>"></label>
        <label>Date: <input type="date" name="date" value="<?php echo esc_attr($_GET['date'] ?? '') ?>"></label>
        <button type="submit" class="button">Filter</button>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>User</th>
                <th>Vendor ID</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Description</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($transactions)): ?>
                <?php foreach ($transactions as $t): 
                    $user = get_user_by('ID', $t['user_id']);
                ?>
                <tr>
                    <td><?php echo $user ? esc_html($user->display_name) : 'Unknown'; ?></td>
                    <td><?php echo esc_html($t['vendor_id']); ?></td>
                    <td><?php echo number_format($t['amount'], 2); ?></td>
                    <td><?php echo esc_html($t['transaction_type']); ?></td>
                    <td><?php echo esc_html($t['description']); ?></td>
                    <td><?php echo esc_html($t['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">No transactions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
