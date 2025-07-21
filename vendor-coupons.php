
<div class="gr8r-enhanced-vendor-coupons">
    <div class="enhanced-vendor-header">
        <h3><?php _e('Vendor Coupons Management', 'gr8r-enhanced'); ?></h3>
    </div>
    
    <?php
    $total_count = count($coupons);
    $active_count = 0;
    $used_count = 0;
    $expired_count = 0;
    
    foreach ($coupons as $coupon) {
        if ($coupon['is_used']) {
            $used_count++;
        } elseif (strtotime($coupon['expiry_date']) < time()) {
            $expired_count++;
        } else {
            $active_count++;
        }
    }
    ?>
    
    <div class="enhanced-stats-grid">
        <div class="enhanced-stat-card total">
            <div class="enhanced-stat-number"><?php echo $total_count; ?></div>
            <div class="enhanced-stat-label"><?php _e('Total Coupons', 'gr8r-enhanced'); ?></div>
        </div>
        <div class="enhanced-stat-card active">
            <div class="enhanced-stat-number"><?php echo $active_count; ?></div>
            <div class="enhanced-stat-label"><?php _e('Active', 'gr8r-enhanced'); ?></div>
        </div>
        <div class="enhanced-stat-card used">
            <div class="enhanced-stat-number"><?php echo $used_count; ?></div>
            <div class="enhanced-stat-label"><?php _e('Used', 'gr8r-enhanced'); ?></div>
        </div>
        <div class="enhanced-stat-card expired">
            <div class="enhanced-stat-number"><?php echo $expired_count; ?></div>
            <div class="enhanced-stat-label"><?php _e('Expired', 'gr8r-enhanced'); ?></div>
        </div>
    </div>
    
    <?php if (empty($coupons)): ?>
        <div class="enhanced-no-coupons">
            <div class="enhanced-no-coupons-icon">🎫</div>
            <h4><?php _e('No Coupons Found', 'gr8r-enhanced'); ?></h4>
            <p><?php _e('No coupons have been generated for your services yet.', 'gr8r-enhanced'); ?></p>
        </div>
    <?php else: ?>
        <div class="gr8r-enhanced-coupons-grid">
            <?php foreach ($coupons as $coupon): ?>
                <?php
                $is_expired = strtotime($coupon['expiry_date']) < time();
                $is_used = $coupon['is_used'];
                $status = $is_used ? 'used' : ($is_expired ? 'expired' : 'active');
                ?>
                <div class="gr8r-enhanced-coupon-card <?php echo $status; ?>">
                    <div class="enhanced-coupon-status">
                        <span class="enhanced-status-badge <?php echo $status; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>
                    
                    <div class="enhanced-coupon-header">
                        <div class="enhanced-coupon-code">
                            <strong><?php echo esc_html($coupon['coupon_code']); ?></strong>
                            <button class="enhanced-copy-code" data-code="<?php echo esc_attr($coupon['coupon_code']); ?>">
                                📋
                            </button>
                        </div>
                        <div class="enhanced-coupon-value">
                            <span class="enhanced-discount-amount">
                                <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                    <?php echo number_format($coupon['discount_value'], 0); ?>%
                                <?php else: ?>
                                    $<?php echo number_format($coupon['discount_value'], 2); ?>
                                <?php endif; ?>
                            </span>
                            <span class="enhanced-discount-type">
                                <?php echo $coupon['discount_type'] === 'percentage' ? __('OFF', 'gr8r-enhanced') : __('DISCOUNT', 'gr8r-enhanced'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="enhanced-coupon-session">
                        <div class="enhanced-session-info">
                            <strong><?php _e('Customer:', 'gr8r-enhanced'); ?></strong>
                            <span><?php echo esc_html($coupon['user_name']); ?></span>
                        </div>
                        <?php if ($coupon['session_name']): ?>
                            <div class="enhanced-session-info">
                                <strong><?php _e('Session:', 'gr8r-enhanced'); ?></strong>
                                <span><?php echo esc_html($coupon['session_name']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($coupon['session_date']): ?>
                            <div class="enhanced-session-date">
                                <strong><?php _e('Date:', 'gr8r-enhanced'); ?></strong>
                                <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($coupon['session_date'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="enhanced-coupon-details">
                        <div class="enhanced-expiry-info">
                            <strong><?php _e('Expires:', 'gr8r-enhanced'); ?></strong>
                            <span class="<?php echo $is_expired ? 'enhanced-expired-date' : 'enhanced-valid-date'; ?>">
                                <?php echo date_i18n(get_option('date_format'), strtotime($coupon['expiry_date'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($status === 'used'): ?>
                            <div class="enhanced-used-info">
                                <span class="enhanced-used-label"><?php _e('Used On:', 'gr8r-enhanced'); ?></span>
                                <span class="enhanced-used-date"><?php echo date_i18n(get_option('date_format'), strtotime($coupon['used_at'])); ?></span>
                            </div>
                        <?php elseif ($status === 'expired'): ?>
                            <div class="enhanced-expired-info">
                                <span class="enhanced-expired-label"><?php _e('This coupon has expired', 'gr8r-enhanced'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.enhanced-copy-code').forEach(button => {
        button.addEventListener('click', function() {
            const code = this.dataset.code;
            navigator.clipboard.writeText(code).then(() => {
                this.textContent = '✅';
                setTimeout(() => {
                    this.textContent = '📋';
                }, 2000);
            });
        });
    });
});
</script>
