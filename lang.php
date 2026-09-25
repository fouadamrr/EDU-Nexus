<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

// 1. تثبيت اللغة: قافشين على العربي حالياً
$_SESSION['lang'] = 'ar';
$lang_code = 'ar';

// 2. مصفوفات الكلام المترجم
$lang = [];

if ($lang_code == 'en') {
 $lang = [
 'code' => 'en',
 'dir' => 'ltr',
 'align' => 'left',
 'title' => 'EDU Nexus',

 // Header
 'home' => 'Home',
 'profile' => 'Profile',
 'results' => 'Results',
 'logout' => 'Logout',
 'search' => 'Search',
 'welcome' => 'Welcome',
 'level' => 'Level',
 'gpa' => 'GPA',
 'notifications' => 'Notifications',
 'switch_lang' => 'عربي',
 'switch_link' => '?lang=ar',

 // Titles
 'announcements' => 'Announcements & News',
 'academic_warnings' => 'Academic Warnings',

 // 13 Icons
 'icon_profile' => 'Personal Data',
 'icon_results' => 'Study Results',
 'icon_schedule' => 'Class Schedule',
 'icon_registration' => 'Academic Registration',
 'icon_fees' => 'Tuition Fees',
 'icon_military' => 'Military Education',
 'icon_exams' => 'Exams Schedule',
 'icon_surveys' => 'Surveys',
 'icon_books' => 'Book List',
 'icon_attendance' => 'Attendance',
 'icon_majors' => 'Department Division',
 'icon_forms' => 'Student Forms',

 // Subjects
 'subject_net_sec' => 'Network Security',
 'subject_db_sys' => 'Database Systems',
 'subject_web_eng' => 'Web Engineering',
 'subject_crypto' => 'Cryptography',
 'subject_ethical_hack' => 'Ethical Hacking',

 // Subjects & Departments (New)
 'dept_ed_tech' => 'Educational Technology',
 'subj_inst_design' => 'Instructional Design',

 'dept_music' => 'Music Education',
 'subj_music_inst' => 'Musical Instruments',

 'dept_art' => 'Art Education',
 'subj_graphic' => 'Graphic Design',

 'dept_home_eco' => 'Home Economics',
 'subj_nutrition' => 'Nutrition',

 'dept_ed_media' => 'Educational Media',
 'subj_dig_photo' => 'Digital Photography',

 // Books (New)
 'my_books' => 'My Textbooks',
 'book_title' => 'Book Title',
 'upload_book' => 'Upload Book',
 'download' => 'Download',
 'delete' => 'Delete',
 'no_books' => 'No books uploaded yet.',
 'upload_success' => 'File uploaded successfully!',

 // صفحة الدخول
 'login' => 'Login',
 'username' => 'Username',
 'password' => 'Password',
 'login_btn' => 'Sign In'
 ];
}
else {
 // العربي (دا اللي شغال دلوقتي)
 $lang = [
 'code' => 'ar',
 'dir' => 'rtl',
 'align' => 'right',
 'title' => 'EDU Nexus',

 // Header
 'home' => 'الرئيسية',
 'profile' => 'ملفي',
 'results' => 'النتائج',
 'logout' => 'تسجيل الخروج',
 'search' => 'بحث',
 'welcome' => 'مرحباً',
 'level' => 'المستوى',
 'gpa' => 'المعدل',
 'notifications' => 'الإشعارات',
 'switch_lang' => 'English',
 'switch_link' => '?lang=en',

 // Titles
 'announcements' => 'الإعلانات والأخبار',
 'academic_warnings' => 'الإنذارات الأكاديمية',

 // 13 Icons
 'icon_profile' => 'البيانات الشخصية',
 'icon_results' => 'النتائج الدراسية',
 'icon_schedule' => 'الجدول الدراسي',
 'icon_registration' => 'التسجيل الأكاديمي',
 'icon_fees' => 'الرسوم الدراسية',
 'icon_military' => 'التربية العسكرية',
 'icon_exams' => 'جدول الإمتحانات',
 'icon_surveys' => 'الاستبيانات',
 'icon_books' => 'قائمة الكتب',
 'icon_attendance' => 'الغياب',
 'icon_majors' => 'التشعيب',
 'icon_forms' => 'النماذج والاستمارات',

 // Subjects
 'subject_net_sec' => 'أمن الشبكات',
 'subject_db_sys' => 'أنظمة قواعد البيانات',
 'subject_web_eng' => 'هندسة الويب',
 'subject_crypto' => 'تشفير البيانات',
 'subject_ethical_hack' => 'الاختراق الأخلاقي',

 // Subjects & Departments (New)
 'dept_ed_tech' => 'تكنولوجيا التعليم',
 'subj_inst_design' => 'تصميم التعليم',

 'dept_music' => 'التربية الموسيقية',
 'subj_music_inst' => 'آلات موسيقية',

 'dept_art' => 'التربية الفنية',
 'subj_graphic' => 'تصميم جرافيك',

 'dept_home_eco' => 'الاقتصاد المنزلي',
 'subj_nutrition' => 'تغذية',

 'dept_ed_media' => 'الإعلام التربوي',
 'subj_dig_photo' => 'تصوير رقمي',

 // Books (New)
 'my_books' => 'كتبي الدراسية',
 'book_title' => 'عنوان الكتاب',
 'upload_book' => 'رفع الكتاب',
 'download' => 'تحميل',
 'delete' => 'حذف',
 'no_books' => 'لا توجد كتب مرفوعة حالياً.',
 'upload_success' => 'تم رفع الملف بنجاح!',

 // Login
 'login' => 'تسجيل الدخول',
 'username' => 'اسم المستخدم',
 'password' => 'كلمة المرور',
 'login_btn' => 'دخول'
 ];
}
?>
