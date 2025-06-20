<div class="gr8r-credit-balance">
    <h3><?php _e('Your Credit Balance', 'gr8r'); ?></h3>
    <div class="balance-amount">
        <?php echo number_format($balance['balance'], 2); ?>
    </div>
    <?php if ($atts['service_type']) : ?>
        <p class="service-type"><?php echo esc_html($atts['service_type']); ?></p>
    <?php endif; ?>
</div>