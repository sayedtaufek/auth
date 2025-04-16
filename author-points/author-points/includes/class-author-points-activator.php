<?php
class Author_Points_Activator {
    public static function activate() {
        global $wpdb;
        
        // حذف الجداول القديمة أولاً
        self::delete_tables();
        
        // جدول النقاط
        $points_table = $wpdb->prefix . 'author_points';
        
        // جدول الإشعارات متوافق مع إعدادات LaraPush
        $notifications_table = $wpdb->prefix . 'author_points_notifications';
        
        $charset_collate = $wpdb->get_charset_collate();

        // إنشاء جدول النقاط مع إضافة فهارس على الأعمدة المستخدمة في الاستعلامات
        $sql1 = "CREATE TABLE $points_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) NOT NULL DEFAULT 0,
            last_push_time datetime DEFAULT NULL,
            is_enabled tinyint(1) DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY last_push_time (last_push_time),
            KEY is_enabled (is_enabled)
        ) $charset_collate;";

        // إنشاء جدول الإشعارات مع إضافة فهارس على الأعمدة المستخدمة في الاستعلامات
        $sql2 = "CREATE TABLE IF NOT EXISTS $notifications_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            sent_time datetime DEFAULT NULL,
            schedule_now tinyint(1) DEFAULT 1,
            schedule_at datetime DEFAULT NULL,
            points_used int(11) NOT NULL DEFAULT 3,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY sent_time (sent_time),
            KEY schedule_at (schedule_at),
            KEY user_sent_time (user_id, sent_time)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // تنفيذ الاستعلامات مباشرة
        $wpdb->query($sql1);
        if ($wpdb->last_error) {
            error_log('Error creating points table: ' . $wpdb->last_error);
        }
        
        $wpdb->query($sql2);
        if ($wpdb->last_error) {
            error_log('Error creating notifications table: ' . $wpdb->last_error);
        }

        // التحقق من إنشاء الجداول
        $points_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$points_table'") === $points_table;
        $notifications_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notifications_table'") === $notifications_table;

        if (!$points_table_exists) {
            error_log('Points table was not created successfully');
        }
        if (!$notifications_table_exists) {
            error_log('Notifications table was not created successfully');
        }
        
        // إضافة الإعدادات الافتراضية إذا لم تكن موجودة
        if (get_option('author_points_enabled') === false) {
            add_option('author_points_enabled', 1);
        }
        
        if (get_option('points_per_post') === false) {
            add_option('points_per_post', 1);
        }
        
        if (get_option('points_required_for_push') === false) {
            add_option('points_required_for_push', 3);
        }
        
        if (get_option('push_cooldown_minutes') === false) {
            add_option('push_cooldown_minutes', 10);
        }
        
        if (get_option('max_notifications_per_day') === false) {
            add_option('max_notifications_per_day', 3);
        }
    }

    public static function delete_tables() {
        global $wpdb;
        
        // أسماء الجداول
        $points_table = $wpdb->prefix . 'author_points';
        $notifications_table = $wpdb->prefix . 'author_points_notifications';
        
        // حذف الجداول
        $wpdb->query("DROP TABLE IF EXISTS $notifications_table");
        if ($wpdb->last_error) {
            error_log('Error dropping notifications table: ' . $wpdb->last_error);
        }
        
        $wpdb->query("DROP TABLE IF EXISTS $points_table");
        if ($wpdb->last_error) {
            error_log('Error dropping points table: ' . $wpdb->last_error);
        }
    }
    
    // دالة لإضافة الفهارس إلى الجداول الموجودة دون إعادة إنشائها
    public static function add_indexes() {
        global $wpdb;
        
        // جدول النقاط
        $points_table = $wpdb->prefix . 'author_points';
        
        // جدول الإشعارات
        $notifications_table = $wpdb->prefix . 'author_points_notifications';
        
        // إضافة فهارس إلى جدول النقاط
        $wpdb->query("ALTER TABLE $points_table ADD INDEX last_push_time (last_push_time)");
        $wpdb->query("ALTER TABLE $points_table ADD INDEX is_enabled (is_enabled)");
        
        // إضافة فهارس إلى جدول الإشعارات
        $wpdb->query("ALTER TABLE $notifications_table ADD INDEX user_id (user_id)");
        $wpdb->query("ALTER TABLE $notifications_table ADD INDEX post_id (post_id)");
        $wpdb->query("ALTER TABLE $notifications_table ADD INDEX sent_time (sent_time)");
        $wpdb->query("ALTER TABLE $notifications_table ADD INDEX schedule_at (schedule_at)");
        $wpdb->query("ALTER TABLE $notifications_table ADD INDEX user_sent_time (user_id, sent_time)");
    }
}
