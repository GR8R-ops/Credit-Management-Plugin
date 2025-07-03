<div class="wrap">
    <h1><?php _e('GR8R Admin Dashboard', 'gr8r'); ?></h1>

    <p><?php _e('Welcome to the GR8R Credit Manager admin panel. Use the menu to view credit balances, transactions, or settings.', 'gr8r'); ?></p>

    <hr>

<ul>
    <li><a href="<?php echo admin_url('admin.php?page=gr8r-credits&view=balances'); ?>">View Credit Balances</a></li>
    <li><a href="<?php echo admin_url('admin.php?page=gr8r-credits&view=transactions'); ?>">View Transactions</a></li>
    <li><a href="<?php echo admin_url('admin.php?page=gr8r-credits&view=settings'); ?>">Plugin Settings</a></li>
</ul>

</div>
