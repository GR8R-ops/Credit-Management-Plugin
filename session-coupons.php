
<div class="gr8r-enhanced-session-coupons">
    <div class="enhanced-session-header">
        <h3><?php echo sprintf(__('Session: %s', 'gr8r-enhanced'), esc_html($session['session_name'])); ?></h3>
        <div class="enhanced-session-details">
            <p><strong><?php _e('Date:', 'gr8r-enhanced'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session['session_date'])); ?></p>
            <?php if ($session['price']): ?>
                <p><strong><?php _e('Price:', 'gr8r-enhanced'); ?></strong> $<?php echo number_format($session['price'], 2); ?></p>
            <?php endif; ?>
            <?php if ($session['max_participants']): ?>
                <p><strong><?php _e('Max Participants:', 'gr8r-enhanced'); ?></strong> <?php echo $session['max_participants']; ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="enhanced-session-booking">
        <h4><?php _e('Book This Session', 'gr8r-enhanced'); ?></h4>
        <p><?php _e('Click the button below to book this session. A coupon will be automatically generated for you.', 'gr8r-enhanced'); ?></p>
        
        <?php if (is_user_logged_in()): ?>
            <div class="enhanced-booking-actions">
                <a href="<?php echo add_query_arg(['session_id' => $session['session_id'], 'action' => 'book'], home_url('/book-session/')); ?>" 
                   class="enhanced-btn-primary">
                    📅 <?php _e('Book Session', 'gr8r-enhanced'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="enhanced-login-required">
                <p><?php _e('Please log in to book this session.', 'gr8r-enhanced'); ?></p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="enhanced-btn-primary">
                    🔐 <?php _e('Login to Book', 'gr8r-enhanced'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
