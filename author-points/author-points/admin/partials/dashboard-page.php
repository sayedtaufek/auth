<div class="wrap author-points-dashboard">
<?php if ($author_points_enabled == 1): ?>
    <!-- بوكس النقاط الحالية وإرسال الإشعار -->
    <div class="points-overview">
        <h1>نظام نقاط الكتّاب</h1>
    
        <div class="points-card-container">
            <div class="points-card">
                <h2>نقاطك الحالية</h2>
                <div class="points-value"><?php echo esc_html($user_points); ?></div>
                <p class="points-info">تحتاج إلى <?php echo esc_html($required_points); ?> نقاط لإرسال إشعار</p>
                <?php if ($last_push_time): ?>
                    <p class="last-push-info">
                        آخر إشعار: <?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($last_push_time))); ?>
                        <?php if ($time_remaining > 0): ?>
                            <br>
                            الوقت المتبقي للإشعار التالي: 
                            <?php 
                            if ($time_remaining < 1) {
                                echo 'أقل من دقيقة';
                            } else {
                                echo round($time_remaining) . ' دقيقة';
                            }
                            ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="points-card">
                <h2>الإشعارات التى ارسلتها اليوم</h2>
                <div class="points-value"><?php echo esc_html($notifications_sent_today); ?></div>
                <p class="points-info">عدد الإشعارات التى ارسلتها اليوم</p>
            </div>

            <div class="points-card">
                <h2>الإشعارات المسموح بها في اليوم</h2>
                <div class="points-value"><?php echo esc_html($max_notifications_per_day); ?></div>
                <p class="points-info">عدد الإشعارات المسموح بها في اليوم</p>
            </div>
        </div>

        <div class="push-notification-form-container">
        <?php if ($this->is_user_enabled(get_current_user_id())): ?>
            <?php if ($user_points >= $required_points): ?>
                <?php if ($time_remaining <= 0): ?>
                    <?php if ($notifications_sent_today < $max_notifications_per_day): ?>
                        <div class="push-notification-form">
                            <h2>إرسال إشعار</h2>
                            <div class="form-group">
                                <label for="post_id">رقم المقال (ID):</label>
                                <input type="number" id="post_id" name="post_id" required>
                            </div>
                            <button type="button" id="send_push" class="button button-primary">إرسال الإشعار</button>
                        </div>
                    <?php else: ?>
                        <div class="push-notification-blocked">
                            <p>لا يمكنك إرسال إشعار حالياً.</p>
                            <p>لقد وصلت للحد الأقصى من الإشعارات اليوم (<?php echo $notifications_sent_today; ?>/<?php echo $max_notifications_per_day; ?>)</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="push-notification-blocked">
                        <p>لا يمكنك إرسال إشعار حالياً.</p>
                        <p>يجب الانتظار <?php echo round($time_remaining); ?> دقيقة قبل إرسال الإشعار التالي</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="push-notification-blocked">
                    <p>لا يمكنك إرسال إشعار حالياً.</p>
                    <p>تحتاج إلى <?php echo $required_points; ?> نقاط على الأقل (لديك حالياً <?php echo $user_points; ?> نقاط)</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="push-notification-blocked">
                <p>تم تعطيل نظام النقاط لحسابك.</p>
                <p>يرجى التواصل مع المشرف للمزيد من المعلومات.</p>
            </div>
        <?php endif; ?>
        </div>

        <div id="notification_message" class="notice" style="display: none;"></div>
    </div>

    <div class="recent-notifications-table-container">
    <!-- جدول الإشعارات -->
    <h2>آخر الإشعارات المرسلة</h2>
    <?php 
    $notifications = $this->get_notifications(10, get_current_user_id());
    if (!empty($notifications)): 
    ?>
    <div class="table-responsive">  
    <table class="wp-list-table striped">
        <thead>
            <tr>
                <th>ID الإرسال</th>
                <th>ID المقالة</th>
                <th>عنوان المقال</th>
                <th>وقت الجدولة</th>
                <th>حالة الإشعار</th>
                <th>النقاط المستخدمة</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // ترتيب الإشعارات تنازلياً حسب وقت الجدولة
            usort($notifications, function($a, $b) {
                return strtotime($b->schedule_at) - strtotime($a->schedule_at);
            });
            
            foreach ($notifications as $notification): 
                $status = strtotime($notification->schedule_at) > current_time('timestamp') ? 'مجدول' : 'تم الإرسال';
                $status_class = $status === 'مجدول' ? 'scheduled' : 'sent';
            ?>
                <tr>
                    <td><?php echo esc_html($notification->id); ?></td>
                    <td><?php echo esc_html($notification->post_id); ?></td>
                    <td class="title" title="<?php echo esc_html(get_the_title($notification->post_id)); ?>">
                        <a href="<?php echo get_permalink($notification->post_id); ?>" target="_blank">
                            <?php echo esc_html(wp_trim_words(get_the_title($notification->post_id), 5, '...')); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($notification->schedule_at))); ?></td>
                    <td class="status-<?php echo $status_class; ?>"><?php echo $status; ?></td>
                    <td><?php echo esc_html($notification->points_used); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>    
    </div>

    <style>
        .status-scheduled {
            color: #0073aa;
            font-weight: bold;
        }
        .status-sent {
            color: #46b450;
            font-weight: bold;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .wp-list-table td {
            padding: 8px;
        }
        .wp-list-table .title {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
    <?php else: ?>
        <div class="notice notice-info">
            <p>لا توجد إشعارات مرسلة حتى الآن.</p>
        </div>
    <?php endif; ?>
    </div>
<?php else: ?>
    <div class="points-closed notice notice-error">
        <p>نظام النقاط معطل. يرجى التواصل مع المشرف للمزيد من المعلومات.</p>
    </div>
<?php endif; ?>
</div>
