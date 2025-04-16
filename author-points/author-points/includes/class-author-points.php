<?php
class Author_Points {
    private $wpdb;
    private $cache = array(); // إضافة مصفوفة للتخزين المؤقت داخل الكلاس

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // إضافة القائمة في لوحة التحكم
        add_action('admin_menu', array($this, 'add_menu_page'));
        
        // إضافة النقاط عند نشر مقال
        add_action('transition_post_status', array($this, 'check_post_publication'), 10, 3);
        
        // إضافة Ajax handlers
        add_action('wp_ajax_send_push_notification', array($this, 'handle_push_notification'));
        add_action('wp_ajax_cancel_schedule', array($this, 'handle_cancel_schedule'));
        
        // إضافة styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // إضافة تسجيل الإعدادات
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function enqueue_admin_styles($hook) {
        if ('toplevel_page_author-points' !== $hook) {
            return;
        }
        wp_enqueue_style('author-points-admin', plugins_url('admin/css/author-points-admin.css', dirname(__FILE__)));
        wp_enqueue_script('author-points-admin', plugins_url('admin/js/author-points-admin.js', dirname(__FILE__)), array('jquery'), '', true);
        wp_localize_script('author-points-admin', 'authorPoints', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('author_points_nonce')
        ));
    }

    public function add_menu_page() {
        if (current_user_can('manage_options')) {
            // القائمة الرئيسية للمشرف
            add_menu_page(
                'إعدادات نقاط البوش',
                'نقاط البوش',
                'manage_options',
                'author-points-settings',
                array($this, 'render_settings_page'),
                'dashicons-admin-generic',
                30
            );

            // صفحة إدارة نقاط المستخدمين (للمشرف)
            add_submenu_page(
                'author-points-settings',
                'إدارة نقاط المستخدمين',
                'إدارة النقاط',
                'manage_options',
                'author-points-management',
                array($this, 'render_points_management_page')
            );
            add_submenu_page(
                'author-points-settings',
                'آخر الإشعارات',
                'آخر الإشعارات',
                'manage_options',
                'author-points-notifications',
                array($this, 'render_notifications_page')
            );
        }

        // صفحة نقاط الكاتب (تظهر للكتاب فقط)
        if (current_user_can('edit_posts') && !current_user_can('manage_options')) {
            add_menu_page(
                'نقاطي',
                'نقاطي',
                'edit_posts',
                'author-points',
                array($this, 'render_dashboard_page'),
                'dashicons-awards',
                30
            );
        }
    }

    public function check_post_publication($new_status, $old_status, $post) {
        // تجاهل التحديثات التلقائية والمسودات
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post)) return;
        
        // التحقق من أن هذا منشور جديد وليس تحديثاً
        if ($new_status === 'publish' && $old_status !== 'publish' && $post->post_type === 'post') {
            // إضافة النقاط
            $this->add_points($post->post_author, 1);
            
            // إضافة سجل meta للتأكد من عدم تكرار إضافة النقاط
            add_post_meta($post->ID, '_points_added', true, true);
        }
    }

    private function add_points($user_id, $points) {
        $table_name = $this->wpdb->prefix . 'author_points';
        
        // استخدام INSERT ... ON DUPLICATE KEY UPDATE بدلاً من استعلامين منفصلين
        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO $table_name (user_id, points) 
             VALUES (%d, %d) 
             ON DUPLICATE KEY UPDATE points = points + %d",
            $user_id, $points, $points
        ));
        
        if ($result === false) {
            // تسجيل الخطأ فقط في حالة الفشل
            error_log('Failed to update points: ' . $this->wpdb->last_error);
        }
        
        // إبطال التخزين المؤقت
        $this->invalidate_user_cache($user_id);
    }

    // دالة للحصول على بيانات المستخدم الكاملة (تحسين: استعلام واحد بدلاً من عدة استعلامات)
    public function get_user_data($user_id) {
        // التحقق من وجود البيانات في التخزين المؤقت
        $cache_key = 'user_data_' . $user_id;
        
        // التحقق من وجود البيانات في التخزين المؤقت الداخلي
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // التحقق من وجود البيانات في التخزين المؤقت للووردبريس
        $cached_data = wp_cache_get($cache_key, 'author_points');
        if (false !== $cached_data) {
            // تخزين في الكاش الداخلي أيضاً
            $this->cache[$cache_key] = $cached_data;
            return $cached_data;
        }
        
        $table_name = $this->wpdb->prefix . 'author_points';
        $user_data = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        // إذا لم يكن هناك بيانات، نعيد قيمة افتراضية
        if (null === $user_data) {
            $user_data = array(
                'user_id' => $user_id,
                'points' => 0,
                'last_push_time' => null,
                'is_enabled' => 1
            );
        }
        
        // تخزين البيانات في التخزين المؤقت
        wp_cache_set($cache_key, $user_data, 'author_points', 3600); // تخزين لمدة ساعة
        $this->cache[$cache_key] = $user_data;
        
        return $user_data;
    }

    // دالة لإبطال التخزين المؤقت للمستخدم
    private function invalidate_user_cache($user_id) {
        $cache_key = 'user_data_' . $user_id;
        wp_cache_delete($cache_key, 'author_points');
        unset($this->cache[$cache_key]);
    }

    public function get_user_points($user_id) {
        $user_data = $this->get_user_data($user_id);
        return isset($user_data['points']) ? (int)$user_data['points'] : 0;
    }

    public function render_dashboard_page() {
        $current_user = wp_get_current_user();
        $user_data = $this->get_user_data($current_user->ID);
        $user_points = $user_data['points'];
               
        $can_push = $this->can_send_push($current_user->ID);
        
        $required_points = get_option('points_required_for_push', 3);
        $cooldown_minutes = get_option('push_cooldown_minutes', 10);

        $points_per_post = get_option('points_per_post', 1);
        $author_points_enabled = get_option('author_points_enabled', 1);
        $max_notifications_per_day = get_option('max_notifications_per_day', 3);
        $notifications_sent_today = $this->get_notifications_sent_today($current_user->ID);

        $last_push_time = $user_data['last_push_time'];
        $time_remaining = 0;
        
        if ($last_push_time) {
            $last_push = strtotime($last_push_time);
            $current_time = current_time('timestamp');
            $time_passed = ($current_time - $last_push) / 60;
            $time_remaining = max(0, $cooldown_minutes - $time_passed);
        }
        
        include AUTHOR_POINTS_PLUGIN_DIR . 'admin/partials/dashboard-page.php';
    }

    public function can_send_push($user_id) {
        // الحصول على بيانات المستخدم في استعلام واحد
        $user_data = $this->get_user_data($user_id);
        
        // التحقق من تفعيل المستخدم
        if (empty($user_data['is_enabled'])) {
            return false;
        }
        
        // التحقق من عدد الإشعارات اليومية
        $max_notifications = get_option('max_notifications_per_day', 3);
        $notifications_sent = $this->get_notifications_sent_today($user_id);
        
        if ($notifications_sent >= $max_notifications) {
            return false;
        }
        
        // التحقق من النقاط المطلوبة
        $required_points = get_option('points_required_for_push', 3);
        if ($user_data['points'] < $required_points) {
            return false;
        }
        
        // التحقق من وقت الانتظار
        $cooldown_minutes = get_option('push_cooldown_minutes', 10);
        if (!empty($user_data['last_push_time'])) {
            $last_push = strtotime($user_data['last_push_time']);
            $current_time = current_time('timestamp');
            $time_passed = ($current_time - $last_push) / 60;
            
            if ($time_passed < $cooldown_minutes) {
                return false;
            }
        }

        return true;
    }

    public function handle_push_notification() {
        check_ajax_referer('author_points_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $current_user = wp_get_current_user();
        $campaign_id = 0;
        
        try {
            // التحقق من المقال والصلاحيات
            $post = get_post($post_id);
            if (!$post || $post->post_author != $current_user->ID || $post->post_status !== 'draft') {
                throw new Exception('خطأ في المقال أو الصلاحيات');
            }

            if (!$this->can_send_push($current_user->ID)) {
                throw new Exception('لا يمكنك إرسال إشعار الآن. تأكد من امتلاكك للنقاط الكافية');
            }

            // حساب وقت الجدولة
            $current_time = current_time('mysql');
            $current_timestamp = strtotime($current_time);
            $last_scheduled_time = $this->get_last_scheduled_time();
            
            $schedule_timestamp = $last_scheduled_time && strtotime($last_scheduled_time) > $current_timestamp 
                ? strtotime('+10 minutes', strtotime($last_scheduled_time))
                : strtotime('+10 minutes', $current_timestamp);

            $schedule_at = date('Y-m-d H:i:s', $schedule_timestamp);
            $gmt_schedule_at = get_gmt_from_date($schedule_at);

            if (class_exists('Unlimited_Push_Notifications_By_Larapush_Admin_Helper')) {
                // الحصول على بيانات المقال
                $meta = Unlimited_Push_Notifications_By_Larapush_Admin_Helper::get_meta($post_id);
                
                // الحصول على بيانات الاتصال
                $url = Unlimited_Push_Notifications_By_Larapush_Admin_Helper::decode(
                    get_option('unlimited_push_notifications_by_larapush_panel_url', '')
                );
                $email = Unlimited_Push_Notifications_By_Larapush_Admin_Helper::decode(
                    get_option('unlimited_push_notifications_by_larapush_panel_email', '')
                );
                $password = Unlimited_Push_Notifications_By_Larapush_Admin_Helper::decode(
                    get_option('unlimited_push_notifications_by_larapush_panel_password', '')
                );

                // إنشاء الحملة
                $panel_url = Unlimited_Push_Notifications_By_Larapush_Admin_Helper::assambleUrl($url, '/api/createCampaign');

                $response = wp_remote_post($panel_url, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'content-type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'email' => $email,
                        'password' => $password,
                        'domains' => get_option('unlimited_push_notifications_by_larapush_panel_domains_selected', []),
                        'title' => $meta['title'],
                        'message' => $meta['body'],
                        'icon' => $meta['icon'],
                        'image' => $meta['image'],
                        'url' => $meta['url'],
                        'schedule_now' => 0,
                        'schedule_at' => $schedule_at,
                        'source' => 'WordPress Author Points'
                    ])
                ]);

                if (is_wp_error($response)) {
                    throw new Exception('فشل في الاتصال بخدمة LaraPush: ' . $response->get_error_message());
                }

                $body = json_decode($response['body']);
                
                if (!$body->success) {
                    throw new Exception('فشل في جدولة الإشعار: ' . ($body->message ?? 'خطأ غير معروف'));
                }

                // استخراج معرف الحملة من الاستجابة
                $campaign_id = 0;
                $response_url = '';

                if (isset($body->data) && !empty($body->data)) {
                    if (isset($body->data->id)) {
                        $campaign_id = $body->data->id;
                    } elseif (isset($body->data->url)) {
                        $response_url = $body->data->url;
                        // استخراج معرف الحملة من URL
                        if (preg_match('/\/(\d+)$/', $response_url, $matches)) {
                            $campaign_id = $matches[1];
                        }
                    }
                }

                // تخزين في قاعدة البيانات
                $points_notification = $this->wpdb->insert(
                    $this->wpdb->prefix . 'author_points_notifications',
                    array(
                        'user_id' => $current_user->ID,
                        'post_id' => $post_id,
                        'campaign_id' => $campaign_id,
                        'sent_time' => null,
                        'schedule_now' => 0,
                        'schedule_at' => $schedule_at,
                        'points_used' => 3
                    ),
                    array('%d', '%d', '%d', '%s', '%d', '%s', '%d')
                );

                // جدولة المقال
                $post_update = wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'future',
                    'post_date' => $schedule_at,
                    'post_date_gmt' => $gmt_schedule_at,
                    'edit_date' => true
                ], true);

                if (is_wp_error($post_update)) {
                    throw new Exception('فشل في جدولة المقال');
                }

                // خصم النقاط
                $this->use_points_for_push($current_user->ID);
                
                // إبطال التخزين المؤقت للإشعارات اليومية
                $this->invalidate_notifications_cache($current_user->ID);

                wp_send_json_success(sprintf(
                    'تمت جدولة المقال والإشعار بنجاح للنشر في %s',
                    date_i18n('Y-m-d H:i:s', $schedule_timestamp)
                ));
            } else {
                throw new Exception('إضافة LaraPush غير متوفرة');
            }

        } catch (Exception $e) {
            // تسجيل الخطأ فقط في حالة الفشل
            error_log('Error in push notification: ' . $e->getMessage());
            
            // إعادة المقال إلى مسودة في حالة الخطأ
            if (isset($post_update) && !is_wp_error($post_update)) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'draft'
                ]);
            }
            
            wp_send_json_error($e->getMessage());
        }
    }

    // دالة للحصول على آخر وقت جدولة من جدولنا
    private function get_last_scheduled_time() {
        // استخدام التخزين المؤقت للاستعلام
        $cache_key = 'last_scheduled_time';
        $cached_time = wp_cache_get($cache_key, 'author_points');
        
        if (false !== $cached_time) {
            return $cached_time;
        }
        
        $last_time = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT schedule_at 
             FROM {$this->wpdb->prefix}author_points_notifications 
             WHERE schedule_at > %s 
             ORDER BY schedule_at DESC 
             LIMIT 1",
            current_time('mysql')
        ));
        
        // تخزين النتيجة لمدة دقيقة واحدة
        wp_cache_set($cache_key, $last_time, 'author_points', 60);
        
        return $last_time;
    }

    private function use_points_for_push($user_id) {
        $points_table = $this->wpdb->prefix . 'author_points';
        $result = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE $points_table 
             SET points = points - 3, 
                 last_push_time = NOW() 
             WHERE user_id = %d",
            $user_id
        ));
        
        // إبطال التخزين المؤقت
        $this->invalidate_user_cache($user_id);
        
        return $result;
    }

    public function get_last_push_time($user_id) {
        $user_data = $this->get_user_data($user_id);
        return $user_data['last_push_time'];
    }

    private function log_notification($user_id, $post_id, $schedule_at) {
        $table_name = $this->wpdb->prefix . 'author_points_notifications';
        $data = array(
            'user_id' => $user_id,
            'post_id' => $post_id,
            'sent_time' => null,
            'schedule_now' => 0,
            'schedule_at' => $schedule_at,
            'points_used' => 3
        );
        
        $result = $this->wpdb->insert(
            $table_name,
            $data,
            array('%d', '%d', '%s', '%d', '%s', '%d')
        );
        
        // إبطال التخزين المؤقت للإشعارات
        $this->invalidate_notifications_cache($user_id);
        
        return $result;
    }
    
    // دالة لإبطال التخزين المؤقت للإشعارات
    private function invalidate_notifications_cache($user_id) {
        $cache_key = 'notifications_' . $user_id;
        wp_cache_delete($cache_key, 'author_points');
        
        $cache_key_today = 'notifications_today_' . $user_id;
        wp_cache_delete($cache_key_today, 'author_points');
    }
    
    public function get_notifications($limit = 10, $user_id = null) {
        // التحقق من صلاحيات المستخدم
        $is_admin = current_user_can('administrator');
        
        // إنشاء مفتاح التخزين المؤقت
        $cache_key = 'notifications_' . ($user_id ?: 'all') . '_' . $limit;
        
        // التحقق من وجود البيانات في التخزين المؤقت
        $cached_notifications = wp_cache_get($cache_key, 'author_points');
        if (false !== $cached_notifications) {
            return $cached_notifications;
        }
        
        // تحسين الاستعلام باستخدام مؤشرات أفضل
        $query = "SELECT n.*, p.post_title 
                  FROM {$this->wpdb->prefix}author_points_notifications n
                  LEFT JOIN {$this->wpdb->posts} p ON n.post_id = p.ID
                  WHERE 1=1";
        
        // إذا لم يكن مشرف، نعرض فقط إشعارات المستخدم
        if (!$is_admin && $user_id) {
            $query .= $this->wpdb->prepare(" AND n.user_id = %d", $user_id);
        }
        
        // ترتيب تنازلي حسب وقت الجدولة
        $query .= " ORDER BY n.schedule_at DESC";
        
        // تحديد عدد النتائج
        if ($limit > 0) {
            $query .= $this->wpdb->prepare(" LIMIT %d", $limit);
        }
        
        $notifications = $this->wpdb->get_results($query);
        
        // تخزين النتائج في التخزين المؤقت لمدة 5 دقائق
        wp_cache_set($cache_key, $notifications, 'author_points', 300);
        
        return $notifications;
    }

    // تحسين دالة الحصول على عدد الإشعارات المرسلة اليوم
    public function get_notifications_sent_today($user_id) {
        // إنشاء مفتاح التخزين المؤقت
        $cache_key = 'notifications_today_' . $user_id;
        
        // التحقق من وجود البيانات في التخزين المؤقت
        $cached_count = wp_cache_get($cache_key, 'author_points');
        if (false !== $cached_count) {
            return (int)$cached_count;
        }
        
        $table_name = $this->wpdb->prefix . 'author_points_notifications';
        
        // تحسين الاستعلام لاستخدام الفهارس بشكل أفضل
        // بدلاً من استخدام DATE() على عمود في قاعدة البيانات
        $today_start = date('Y-m-d 00:00:00', current_time('timestamp'));
        $today_end = date('Y-m-d 23:59:59', current_time('timestamp'));
        
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) 
             FROM $table_name 
             WHERE user_id = %d 
             AND sent_time >= %s 
             AND sent_time <= %s",
            $user_id,
            $today_start,
            $today_end
        ));
        
        // تخزين النتيجة في التخزين المؤقت لمدة 5 دقائق
        wp_cache_set($cache_key, $count, 'author_points', 300);
        
        return (int)$count;
    }

    // دالة معالجة تعديل النقاط
    public function handle_points_update() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['update_points']) && wp_verify_nonce($_POST['points_nonce'], 'update_points_nonce')) {
            $user_id = intval($_POST['user_id']);
            $points = intval($_POST['points']);
            
            if ($this->update_user_points($user_id, $points)) {
                add_settings_error(
                    'points_updated',
                    'points_updated',
                    'تم تحديث النقاط بنجاح',
                    'updated'
                );
            } else {
                add_settings_error(
                    'points_updated',
                    'points_updated',
                    'حدث خطأ أثناء تحديث النقاط',
                    'error'
                );
            }
        }
    }

    // دالة عرض صفحة إدارة النقاط
    public function render_points_management_page() {
        $this->handle_points_management();
        include AUTHOR_POINTS_PLUGIN_DIR . 'admin/partials/points-management-page.php';
    }
    
    public function render_notifications_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
    
        // استخدام نفس الدالة مع حد أقصى 50 إشعار للمشرفين
        $notifications = $this->get_notifications(50);
        
        // عرض الصفحة
        include AUTHOR_POINTS_PLUGIN_DIR . 'admin/partials/notifications-page.php';
    }

    // دالة عرض صفحة الإعدادات
    public function render_settings_page() {
        include AUTHOR_POINTS_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    public function register_settings() {
        // تسجيل مجموعة الإعدادات
        register_setting('author_points_settings', 'author_points_enabled');
        register_setting('author_points_settings', 'points_per_post');
        register_setting('author_points_settings', 'points_required_for_push');
        register_setting('author_points_settings', 'push_cooldown_minutes');
        register_setting('author_points_settings', 'max_notifications_per_day');

        // إضافة قسم الإعدادات
        add_settings_section(
            'author_points_general_section',
            'الإعدادات العامة',
            null,
            'author_points_settings'
        );
    }

    public function is_user_enabled($user_id) {
        $user_data = $this->get_user_data($user_id);
        return !empty($user_data['is_enabled']);
    }

    public function toggle_user_status($user_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $user_data = $this->get_user_data($user_id);
        $current_status = !empty($user_data['is_enabled']);
        
        $table_name = $this->wpdb->prefix . 'author_points';
        
        // استخدام INSERT ... ON DUPLICATE KEY UPDATE
        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO $table_name (user_id, points, is_enabled) 
             VALUES (%d, %d, %d) 
             ON DUPLICATE KEY UPDATE is_enabled = %d",
            $user_id, 0, !$current_status, !$current_status
        ));
        
        // إبطال التخزين المؤقت
        $this->invalidate_user_cache($user_id);
        
        return $result !== false;
    }

    public function handle_points_management() {
        if (!current_user_can('manage_options')) {
            return;
        }
    
        // معالجة تغيير حالة المستخدم
        if (isset($_POST['action']) && $_POST['action'] === 'toggle_status' 
            && isset($_POST['status_nonce']) && wp_verify_nonce($_POST['status_nonce'], 'toggle_user_status_nonce')) {
            
            $user_id = intval($_POST['user_id']);
            
            if ($this->toggle_user_status($user_id)) {
                add_settings_error(
                    'user_status',
                    'user_status',
                    'تم تحديث حالة المستخدم بنجاح',
                    'updated'
                );
            } else {
                add_settings_error(
                    'user_status',
                    'user_status',
                    'حدث خطأ أثناء تحديث حالة المستخدم',
                    'error'
                );
            }
        }

        // معالجة تحديث النقاط
        if (isset($_POST['update_points']) && wp_verify_nonce($_POST['points_nonce'], 'update_points_nonce')) {
            $user_id = intval($_POST['user_id']);
            $points = intval($_POST['points']);
            
            if ($this->update_user_points($user_id, $points)) {
                add_settings_error(
                    'points_updated',
                    'points_updated',
                    'تم تحديث النقاط بنجاح',
                    'updated'
                );
            } else {
                add_settings_error(
                    'points_updated',
                    'points_updated',
                    'حدث خطأ أثناء تحديث النقاط',
                    'error'
                );
            }
        }
    }

    private function update_user_points($user_id, $points) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $table_name = $this->wpdb->prefix . 'author_points';
        
        // استخدام INSERT ... ON DUPLICATE KEY UPDATE
        $result = $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO $table_name (user_id, points, is_enabled) 
             VALUES (%d, %d, 1) 
             ON DUPLICATE KEY UPDATE points = %d",
            $user_id, $points, $points
        ));
        
        if ($result === false) {
            error_log('فشل تحديث النقاط. User ID: ' . $user_id . ', Points: ' . $points);
            error_log('Database Error: ' . $this->wpdb->last_error);
            return false;
        }
        
        // إبطال التخزين المؤقت
        $this->invalidate_user_cache($user_id);
        
        return true;
    }

    public function handle_cancel_push_schedule() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('غير مصرح لك بهذا الإجراء');
        }

        try {
            $notification_id = intval($_POST['notification_id']);
            
            if (!wp_verify_nonce($_POST['nonce'], 'cancel_schedule_' . $notification_id)) {
                wp_send_json_error('رمز الأمان غير صالح');
            }

            // الحصول على معلومات الإشعار
            $notification = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}author_points_notifications WHERE id = %d",
                $notification_id
            ));

            if (!$notification) {
                throw new Exception('الإشعار غير موجود');
            }

            if (class_exists('Unlimited_Push_Notifications_By_Larapush_Admin_Helper')) {
                $url = Unlimited_Push_Notifications_By_Larapush_Admin_Helper::decode(
                    get_option('unlimited_push_notifications_by_larapush_panel_url', '')
                );

                // الحصول على معرف الحملة من URL
                $campaign_url = $notification->campaign_id;
                preg_match('/\/(\d+)$/', $campaign_url, $matches);
                $campaign_id = $matches[1] ?? null;

                if (!$campaign_id) {
                    throw new Exception('معرف الحملة غير صالح');
                }

                // إلغاء الحملة
                $delete_url = Unlimited_Push_Notifications_By_Larapush_Admin_Helper::assambleUrl($url, '/notification/' . $campaign_id);

                $delete_response = wp_remote_request($delete_url, [
                    'method' => 'DELETE',
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'X-Requested-With' => 'XMLHttpRequest'
                    ],
                    'body' => json_encode([
                        '_token' => 'uTmynpZyacypkLwczSlc0DHVK0wHLejzB8U5jLlI'
                    ])
                ]);

                if (is_wp_error($delete_response)) {
                    throw new Exception('فشل في إلغاء الحملة: ' . $delete_response->get_error_message());
                }

                $delete_body = json_decode(wp_remote_retrieve_body($delete_response));

                if (!isset($delete_body->success) || !$delete_body->success) {
                    throw new Exception('فشل في إلغاء الحملة: ' . ($delete_body->message ?? 'خطأ غير معروف'));
                }

                // تحديث حالة الإشعار في قاعدة البيانات
                $this->wpdb->update(
                    $this->wpdb->prefix . 'author_points_notifications',
                    ['sent_time' => current_time('mysql')],
                    ['id' => $notification_id],
                    ['%s'],
                    ['%d']
                );
                
                // إبطال التخزين المؤقت للإشعارات
                $this->invalidate_notifications_cache($notification->user_id);

                // إعادة المقال إلى مسودة
                wp_update_post([
                    'ID' => $notification->post_id,
                    'post_status' => 'draft'
                ]);

                wp_send_json_success([
                    'message' => 'تم إلغاء الحملة بنجاح',
                    'notification_id' => $notification_id
                ]);
            }

        } catch (Exception $e) {
            error_log('خطأ في إلغاء الإشعار: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
}
