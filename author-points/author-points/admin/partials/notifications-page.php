<?php
// التأكد من الصلاحيات
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

?>

<div class="wrap">
    <h1>آخر الإشعارات المرسلة</h1>

    <?php
    // الحصول على الصفحة الحالية
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 10; // عدد العناصر في كل صفحة

    // الحصول على إجمالي عدد الإشعارات
    global $wpdb;
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}author_points_notifications");
    $total_pages = ceil($total_items / $per_page);

    // الحصول على الإشعارات مع الترقيم
    $offset = ($current_page - 1) * $per_page;
    $notifications = $wpdb->get_results($wpdb->prepare("
        SELECT n.*, p.post_title, u.display_name as author_name, n.campaign_id
        FROM {$wpdb->prefix}author_points_notifications n
        LEFT JOIN {$wpdb->posts} p ON n.post_id = p.ID
        LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
        ORDER BY n.schedule_at DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset));
    ?>

    <?php if (!empty($notifications)): ?>
        <div class="tablenav top">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                ?>
            </div>
        </div>

        <div class="table-responsive">
        <table class="wp-list-table striped">
            <thead>
                <tr>
                    <th>ID الإرسال</th>
                    <th>ID المقالة</th>
                    <th>الكاتب</th>
                    <th>عنوان المقال</th>
                    <th>وقت الجدولة</th>
                    <th>حالة الإشعار</th>
                </tr>
            </thead>
            <tbody>
                    <?php foreach ($notifications as $notification): 
                        // تحديد حالة الإشعار
                        $current_time = current_time('timestamp');
                        $schedule_time = strtotime($notification->schedule_at);
                        
                        if ($schedule_time > $current_time && !$notification->sent_time) {
                            // إشعار مجدول في المستقبل ولم يتم إرساله بعد
                            $status = 'مجدول';
                            $status_class = 'scheduled';
                            $can_cancel = true;
                        } elseif ($schedule_time <= $current_time || $notification->sent_time) {
                            // إشعار تم إرساله أو وقت جدولته قد مر
                            $status = 'تم الإرسال';
                            $status_class = 'sent';
                            $can_cancel = false;
                        } else {
                            // إشعار ملغي
                            $status = 'ملغي';
                            $status_class = 'cancelled';
                            $can_cancel = false;
                        }
                    ?>
                    <tr>
                        <td><?php echo esc_html($notification->id); ?></td>
                        <td><?php echo esc_html($notification->post_id); ?></td>
                        <td><?php echo esc_html($notification->author_name); ?></td>
                        <td class="title" title="<?php echo esc_attr(get_the_title($notification->post_id)); ?>">
                            <a href="<?php echo get_permalink($notification->post_id); ?>" target="_blank">
                                <?php echo esc_html(wp_trim_words(get_the_title($notification->post_id), 5, '...')); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($notification->schedule_at); ?></td>
                        <td class="status-<?php echo $status_class; ?>"><?php echo $status; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                ?>
            </div>
        </div>
    <?php else: ?>
        <div class="notice notice-info">
            <p>لا توجد إشعارات مرسلة حتى الآن.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.wrap {
    padding: 20px;
    border-radius: 5px;
    text-align: center;
}

.wrap h1 {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
}

table { 
    width: 100%;
    margin-bottom: 20px;
    background-color: #fff;
    border-collapse: collapse;
}

table a {
  transition: none;
  text-decoration: none;
}
table thead tr {
    background-color: #2271b1 !important;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    color: #fff;
}
table thead th {
    border-radius: 5px;
    padding: 10px;
    text-align: center;
    vertical-align: middle;
    font-size: 14px;
    font-weight: 600;
}
table tbody td {
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
    text-align: center;
    vertical-align: middle;
    font-size: 14px;
    color: #333;
    font-weight: 400;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    text-decoration: none;
}
table tbody tr:last-child td {
    border-bottom: none;
    border-radius: 5px;
}
table tbody tr:hover td {
    background-color: #f9f9f9;
    border-radius: 5px;
    border: 1px solid #e0e0e0;  
    transition: background-color 0.3s ease;
}
.status-scheduled { color: #0073aa; font-weight: bold; }
.status-sent { color: #46b450; font-weight: bold; }
.status-cancelled { color: #dc3232; font-weight: bold; }

@media (max-width: 768px) {
    table {
        max-width: 650px;
        width: 100%;
        overflow-x: auto;
        display: block;
        margin: 0 auto;
    }
}
</style>
<script>
jQuery(document).ready(function($) {
    $('.cancel-schedule').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var notificationId = button.data('id');
        var campaignId = button.data('campaign');
        var nonce = button.data('nonce');

        if (!campaignId) {
            alert('معرف الحملة غير متوفر');
            return;
        }

        if (confirm('هل أنت متأكد من إلغاء جدولة هذا الإشعار؟')) {
            button.prop('disabled', true).text('جاري الإلغاء...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cancel_push_schedule',
                    notification_id: notificationId,
                    campaign_id: campaignId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('خطأ: ' + response.data);
                        button.prop('disabled', false).text('إلغاء الجدولة');
                    }
                },
                error: function() {
                    alert('حدث خطأ أثناء إلغاء الجدولة');
                    button.prop('disabled', false).text('إلغاء الجدولة');
                }
            });
        }
    });
});
</script>