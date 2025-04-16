<div class="wrap">

    
    <?php settings_errors(); ?>
    <div class="author-points-settings">
        <h1><?php echo get_admin_page_title(); ?></h1>

    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>المستخدم</th>
                <th>النقاط الحالية</th>
                <th>آخر إشعار</th>
                <th>المقالات المنشورة</th>
                <th>حالة النظام</th>
                <th>تعديل النقاط</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $users = get_users(['role__in' => ['author', 'contributor', 'editor']]);
            foreach ($users as $user) {
                $points = $this->get_user_points($user->ID) ?: 0;
                $last_push = $this->get_last_push_time($user->ID);
                $posts_count = count_user_posts($user->ID, 'post', true);
                $is_enabled = $this->is_user_enabled($user->ID);
                ?>
                <tr>
                    <td><?php echo esc_html($user->display_name); ?></td>
                    <td><?php echo esc_html($points); ?></td>
                    <td><?php echo $last_push ? esc_html(date_i18n('Y-m-d H:i:s', strtotime($last_push))) : '-'; ?></td>
                    <td><?php echo esc_html($posts_count); ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('toggle_user_status_nonce', 'status_nonce'); ?>
                            <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="button <?php echo $is_enabled ? 'button-primary' : 'button-secondary'; ?>">
                                <?php echo $is_enabled ? 'تعطيل' : 'تفعيل'; ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <form method="post" style="display: flex; gap: 10px; align-items: center;">
                            <?php wp_nonce_field('update_points_nonce', 'points_nonce'); ?>
                            <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                            <input type="number" 
                                   name="points" 
                                   value="<?php echo esc_attr($points); ?>" 
                                   style="width: 70px;"
                                   min="0">
                            <input type="submit" 
                                   name="update_points" 
                                   class="button button-primary" 
                                   value="تحديث">
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>

    <div class="points-management-help" style="margin-top: 20px; background: #fff; padding: 15px; border-left: 4px solid #2271b1;">
        <h3>تعليمات:</h3>
        <ul>
            <li>يمكنك تعديل نقاط أي مستخدم مباشرة من خلال تغيير الرقم في الحقل المخصص</li>
            <li>النقاط يجب أن تكون أرقاماً صحيحة موجبة</li>
            <li>يتم خصم 3 نقاط تلقائياً عند إرسال كل إشعار</li>
            <li>يحصل المستخدم على نقطة واحدة عند نشر مقال جديد</li>
        </ul>
    </div>
</div> 

<style>
.author-points-settings {
  max-width: 1000px;
  margin: 3em auto;
  position: relative;
}
.author-points-settings h1 {
    text-align: center;
    font-size: 2em;
    font-weight: bold;
    color: #000;
}
.author-points-settings table {
    width: 100%;
    border-radius: 5px;
}

@media (max-width: 768px) {
    .author-points-settings table {
        width: 100%;
        overflow-x: auto;
        border-radius: 0;
        max-width: 700px;
        display: block;
        border: 0;
    }
}
</style>