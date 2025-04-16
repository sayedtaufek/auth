<?php
/**
 * Class for logging in Author Points plugin
 */
class Author_Points_Logger {
    // تعريف مستويات التسجيل
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    // المستوى الافتراضي للتسجيل (يمكن تغييره من الإعدادات)
    private static $log_level = self::ERROR;
    
    /**
     * تهيئة المسجل
     */
    public static function init() {
        // تحديد مستوى التسجيل من إعدادات الووردبريس
        $level = get_option('author_points_log_level', self::ERROR);
        self::$log_level = $level;
    }
    
    /**
     * تسجيل رسالة خطأ
     */
    public static function error($message) {
        self::log(self::ERROR, $message);
    }
    
    /**
     * تسجيل رسالة تحذير
     */
    public static function warning($message) {
        self::log(self::WARNING, $message);
    }
    
    /**
     * تسجيل رسالة معلومات
     */
    public static function info($message) {
        self::log(self::INFO, $message);
    }
    
    /**
     * تسجيل رسالة تصحيح
     */
    public static function debug($message) {
        self::log(self::DEBUG, $message);
    }
    
    /**
     * تسجيل رسالة بمستوى محدد
     */
    private static function log($level, $message) {
        // التحقق من مستوى التسجيل
        if (!self::should_log($level)) {
            return;
        }
        
        // تنسيق الرسالة
        $formatted_message = sprintf('[Author Points] [%s] %s', strtoupper($level), $message);
        
        // تسجيل الرسالة
        error_log($formatted_message);
    }
    
    /**
     * التحقق مما إذا كان يجب تسجيل الرسالة بناءً على المستوى
     */
    private static function should_log($level) {
        // في بيئة التطوير، نسجل كل شيء
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }
        
        // ترتيب المستويات من الأقل إلى الأعلى أهمية
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3
        ];
        
        // التحقق من المستوى
        return isset($levels[$level]) && isset($levels[self::$log_level]) && 
               $levels[$level] >= $levels[self::$log_level];
    }
}
