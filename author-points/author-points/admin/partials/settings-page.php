<div class="wrap">
    
    <div class="author-points-settings">
    <h1><?php echo get_admin_page_title(); ?></h1>

    <div class="author-points-nav nav-tab-wrapper">
        <ul class="author-points-nav-tab-wrapper">
            <li><a href="#general" class="author-points-nav-tab nav-tab active">إعدادات عامة</a></li>
            <li><a href="#points" class="author-points-nav-tab nav-tab">إعدادات النقاط</a></li>
            <li><a href="#notifications" class="author-points-nav-tab nav-tab">إعدادات الإشعارات</a></li>
        </ul>
    </div>

    <div class="author-points-tab-content">
        <form method="post" action="options.php">
            <?php settings_fields('author_points_settings'); ?>
            
            <div id="general" class="author-points-tab-pane active">
                <h2>الإعدادات العامة</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">تفعيل النظام</th>
                        <td>
                            <input type="checkbox" name="author_points_enabled" value="1" 
                                <?php checked(1, get_option('author_points_enabled', 1)); ?>>
                            <p class="description">تفعيل أو تعطيل نظام النقاط بالكامل</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="points" class="author-points-tab-pane">
                <h2>إعدادات النقاط</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">النقاط لكل مقال جديد</th>
                        <td>
                            <input type="number" name="points_per_post" 
                                   value="<?php echo esc_attr(get_option('points_per_post', 1)); ?>" min="1">
                            <p class="description">عدد النقاط التي يحصل عليها الكاتب عند نشر مقال جديد</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">النقاط المطلوبة للإشعار</th>
                        <td>
                            <input type="number" name="points_required_for_push" 
                                   value="<?php echo esc_attr(get_option('points_required_for_push', 3)); ?>" min="1">
                            <p class="description">عدد النقاط المطلوبة لإرسال إشعار</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="notifications" class="author-points-tab-pane">
                <h2>إعدادات الإشعارات</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">وقت الانتظار بين الإشعارات (بالدقائق)</th>
                        <td>
                            <input type="number" name="push_cooldown_minutes" 
                                   value="<?php echo esc_attr(get_option('push_cooldown_minutes', 10)); ?>" min="1">
                            <p class="description">الوقت المطلوب الانتظار بين كل إشعار (بالدقائق)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">عدد الإشعارات المسموح بها في اليوم</th>
                        <td>
                            <input type="number" name="max_notifications_per_day" 
                                   value="<?php echo esc_attr(get_option('max_notifications_per_day', 3)); ?>" min="1">
                            <p class="description">عدد الإشعارات المسموح بها في اليوم</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button('حفظ جميع الإعدادات'); ?>
        </form>
    </div>
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
.author-points-nav {
  padding: 10px;
  background: #34495E;
  z-index: 10;
}   
.author-points-nav-tab-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0px;
    padding: 0px;
    list-style: none;
    gap: 5px;
    border: 0;
    flex-wrap: wrap;
}
.author-points-nav-tab-wrapper li {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  margin:0;
}
.author-points-nav-tab {
  display: block;
  padding: 0.75em 1em;
  font-weight: bold;
  text-align: center;
  border: 1px solid rgba(255,255,255,0.3);
  border-radius: 6px;
  width: 100%;
}
.author-points-nav-tab.active {
    background-color: #2C3E50;
    color: #fff;
}
.author-points-tab-content {
    margin-top: 20px;
}
.author-points-tab-pane {
    display: none;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
}
.author-points-tab-pane.active {
    display: block;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.author-points-nav-tab').click(function(e) {
        e.preventDefault();
        
        // إزالة الكلاس النشط من جميع التابات
        $('.author-points-nav-tab').removeClass('active');
        $('.author-points-tab-pane').removeClass('active');
        
        // إضافة الكلاس النشط للتاب المحدد
        $(this).addClass('active');
        
        // عرض المحتوى المرتبط
        var target = $(this).attr('href').substring(1);
        $('#' + target).addClass('active');
    });
});
</script>