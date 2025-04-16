<?php
/**
 * Plugin Name: Author Points for LaraPush (Optimized)
 * Description: نظام نقاط للكتّاب للإشعارات عبر LaraPush - نسخة محسنة الأداء
 * Version: 1.0.1
 * Author: Sayed Taufek
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('WPINC')) {
    die;
}

define('AUTHOR_POINTS_VERSION', '1.0.1');
define('AUTHOR_POINTS_PLUGIN_DIR', plugin_dir_path(__FILE__));

// إضافة دعم التخزين المؤقت
function author_points_cache_init() {
    // تسجيل مجموعة التخزين المؤقت
    wp_cache_add_non_persistent_groups('author_points');
}
add_action('plugins_loaded', 'author_points_cache_init', 5);

// تهيئة نظام التسجيل
function author_points_logger_init() {
    require_once AUTHOR_POINTS_PLUGIN_DIR . 'includes/class-author-points-logger.php';
    Author_Points_Logger::init();
}
add_action('plugins_loaded', 'author_points_logger_init', 5);

// التأكد من وجود إضافة LaraPush
function check_larapush_dependency() {
    if (!class_exists('Unlimited_Push_Notifications_By_Larapush_Admin')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>إضافة Author Points تتطلب تثبيت وتفعيل إضافة LaraPush</p></div>';
        });
        return false;
    }
    return true;
}

// تفعيل الإضافة
function activate_author_points() {
    require_once AUTHOR_POINTS_PLUGIN_DIR . 'includes/class-author-points-activator.php';
    Author_Points_Activator::activate();
}
register_activation_hook(__FILE__, 'activate_author_points');

// إضافة دالة إلغاء التفعيل
function deactivate_author_points() {
    require_once AUTHOR_POINTS_PLUGIN_DIR . 'includes/class-author-points-activator.php';
    Author_Points_Activator::delete_tables();
}
register_deactivation_hook(__FILE__, 'deactivate_author_points');

// تحميل الإضافة
function init_author_points() {
    if (check_larapush_dependency()) {
        require_once AUTHOR_POINTS_PLUGIN_DIR . 'includes/class-author-points.php';
        new Author_Points();
    }
}
add_action('plugins_loaded', 'init_author_points');
