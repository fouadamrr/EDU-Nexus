<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}
// هات ملفات اللغة والاتصال بالداتا بيز
require_once __DIR__ . '/../lang.php';
require_once __DIR__ . '/../db.php'; 

// بنحدد إحنا فين في الفولدرات دلوقتي
$_project_root = realpath(__DIR__ . '/..');
$_current_file = realpath($_SERVER['SCRIPT_FILENAME']);
$_current_dir = $_current_file ? dirname($_current_file) : __DIR__ . '/..';

if ($_current_dir && $_project_root && strpos($_current_dir, $_project_root) === 0) {
 $_rel = trim(substr($_current_dir, strlen($_project_root)), '\\/');
 $_depth = ($_rel === '') ? 0 : count(explode('/', str_replace('\\', '/', $_rel)));
} else {
 $_depth = 0;
}
$base_path = str_repeat('../', $_depth);
$root_path = ''; // المسار بتاع الروت

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $base_path . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'] ?? 'student';

// هات صورة البروفايل بتاعته لو موجودة
$profile_pic = '';
try {
    $ppStmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $ppStmt->execute([$user_id]);
    $ppRow = $ppStmt->fetch();
    if ($ppRow && !empty($ppRow['profile_pic'])) {
        $profile_pic = $ppRow['profile_pic'];
    }
} catch (Exception $e) { /* تجاهل لو مفيش صورة أو الجدول لسه متكارنش */ }

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/helpers.php';

// افتكر القوائم اللي كان فاتحها (بالكوكيز)
$_active_menus = [];
if (isset($_COOKIE['edu_nexus_active_menus'])) {
 $_active_menus = json_decode($_COOKIE['edu_nexus_active_menus'], true) ?: [];
}

// بنشوف الموقع في وضع الصيانة ولا لاء
$maintenance_mode = '0';
try {
 $mStmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'maintenance_mode' AND college_id IS NULL LIMIT 1");
 $mStmt->execute();
 $mRow = $mStmt->fetch();
 if ($mRow) { $maintenance_mode = $mRow['value']; }
} catch (Exception $e) { $maintenance_mode = '0'; }

if ($maintenance_mode === '1' && !in_array($role, ['super_admin', 'admin'])) {
 header('Location: ' . $base_path . 'maintenance.php');
 exit;
}

$current_page = basename($_SERVER['PHP_SELF'], ".php");
$page_titles = [
 'dashboard' => $lang['home'],
 'profile' => $lang['icon_profile'],
 'results' => $lang['icon_results'],
 'schedule' => $lang['icon_schedule'],
 'exams' => $lang['icon_exams'],
 'warnings' => $lang['academic_warnings'],
 'registration' => $lang['icon_registration'],
 'attendance' => $lang['icon_attendance'] ?? 'الغياب والحضور',
 'surveys' => $lang['icon_surveys'] ?? 'الاستبيانات',
 'military' => $lang['icon_military'] ?? 'التربية العسكرية',
 'books'             => $lang['icon_books'] ?? 'المقررات الدراسية',
 'majors'            => $lang['icon_majors'] ?? 'التشعيب',
 'forms'             => $lang['icon_forms'] ?? 'النماذج',
 'admin_dashboard' => 'لوحة تحكم الإدارة',
 'instructor_dashboard' => 'لوحة تحكم عضو هيئة التدريس',
 'manage_users' => 'إدارة المستخدمين',
 'course_students' => 'قائمة الطلاب',
 'submit_grades' => 'إدخال الدرجات',
 'official_documents' => 'الوثائق الرسمية',
 'gpa_calculator' => 'حاسبة المعدل التراكمي',
 'all_students' => 'جميع الطلاب',
 'student_data_edit' => 'تعديل البيانات الدراسية للطلاب',
 'lecture_schedules' => 'جداول المحاضرات الدراسية',
 'manage_schedule' => 'إدارة مواعيد المحاضرات',
 'student_groups' => 'مجموعات الطلاب الدراسية',
];
$page_title = $page_titles[$current_page] ?? 'لوحة التحكم';

// التبويبات اللي بالعرض
$default_tab = in_array($current_page, ['admin_dashboard','super_admin_dashboard']) ? '' : 'students';
$active_tab = $_GET['tab'] ?? $default_tab;

// ألوان الأقسام والـ Icons
$section_colors = [
  'dashboard_main' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200'],
  'students' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'border' => 'border-sky-200'],
  'programs' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'border' => 'border-purple-200'],
  'schedules' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
  'exams' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-200'],
  'results' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-200'],
  'absence' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-200'],
  'mail' => ['bg' => 'bg-pink-50', 'text' => 'text-pink-600', 'border' => 'border-pink-200'],
  'supervision' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'border' => 'border-slate-200'],
  'student_main' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200'],
  'student_academic' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-200'],
  'student_services' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'border' => 'border-sky-200'],
  'qr_attendance' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200'],
  'online_exams' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'border' => 'border-blue-200']
];

$horizontal_tabs = [
 'students' => ['title' => 'بيانات الطلاب', 'icon' => 'fa-users'],
 'programs' => ['title' => 'البرامج الدراسية', 'icon' => 'fa-graduation-cap'],
 'schedules' => ['title' => 'الجداول الدراسية', 'icon' => 'fa-calendar-alt'],
 'exams' => ['title' => 'تعليم وامتحانات', 'icon' => 'fa-file-signature'],
 'results' => ['title' => 'الكنترول والنتائج', 'icon' => 'fa-poll-h'],
 'absence' => ['title' => 'غياب الطلاب', 'icon' => 'fa-user-clock'],
 'mail' => ['title' => 'البريد الداخلي', 'icon' => 'fa-envelope-open-text'],
 'supervision' => ['title' => 'الاشراف على النظام', 'icon' => 'fa-cogs'],
];

$tab_sidebars = [
 'students' => [
 'إدارة الطلاب' => [
 ['link' => 'all_students.php', 'icon' => 'fa-user-graduate','title' => 'جميع الطلاب', 'match' => 'all_students'],
 ['link' => 'student_data_edit.php','icon' => 'fa-user-edit', 'title' => 'تعديل البيانات الدراسية', 'match' => 'student_data_edit'],
 ['link' => 'stats_registered.php', 'icon' => 'fa-chart-bar', 'title' => 'إحصائية الطلبة المسجلين', 'match' => 'stats_registered'],
 ['link' => 'manage_departments.php', 'icon' => 'fa-sitemap', 'title' => 'إدارة الأقسام العلمية', 'match' => 'manage_departments'],
 ['link' => 'manage_specializations.php', 'icon' => 'fa-tasks', 'title' => 'طلبات التشعيب والتحويل', 'match' => 'manage_specializations'],
 ],
 ],
 'programs' => [
 'المقررات الدراسية' => [
 ['link' => 'manage_courses.php', 'icon' => 'fa-book-open', 'title' => 'إدارة المقررات الدراسية', 'match' => 'manage_courses'],
 ],
 'الكليات والأقسام' => [
 ['link' => 'manage_colleges.php', 'icon' => 'fa-university', 'title' => 'إدارة بيانات الكلية', 'match' => 'manage_colleges'],
 ]
 ],
 'schedules' => [
 'الجداول الدراسية' => [
 ['link' => 'lecture_schedules.php','icon' => 'fa-calendar-alt','title' => 'جداول المحاضرات (مجمع)', 'match' => 'lecture_schedules'],
 ['link' => 'manage_schedule.php', 'icon' => 'fa-edit', 'title' => 'إدارة مواعيد المحاضرات', 'match' => 'manage_schedule'],
 ['link' => 'student_groups.php', 'icon' => 'fa-list-ol', 'title' => 'توزيع الطلاب على المجموعات','match' => 'student_groups'],
 ],
 ],
 'exams' => [
 'إدارة اللجان' => [
 ['link' => 'exam_management.php?action=define', 'icon' => 'fa-edit', 'title' => 'تعريف اللجان', 'match' => 'define_committees'],
 ['link' => 'exam_management.php?action=distribute', 'icon' => 'fa-user-plus', 'title' => 'توزيع الطلاب على اللجان', 'match' => 'distribute_students'],
 ['link' => 'exam_management.php?action=distribute_unassigned', 'icon' => 'fa-users-slash', 'title' => 'توزيع طلاب بدون لجان', 'match' => 'distribute_unassigned'],
 ],
 'تقارير وطباعة' => [
 ['link' => 'student_id_cards.php', 'icon' => 'fa-id-card', 'title' => 'طباعة بطاقات الجلوس', 'match' => 'student_id_cards'],
 ['link' => 'exam_reports.php?type=seats', 'icon' => 'fa-chair', 'title' => 'كشف مقاعد الطلاب', 'match' => 'committee_seats'],
 ['link' => 'exam_reports.php?type=distribution_sheet', 'icon' => 'fa-file-invoice','title' => 'كشف توزيع الطلاب على اللجان', 'match' => 'distribution_sheet'],
 ['link' => 'exam_reports.php?type=no_seats', 'icon' => 'fa-user-times', 'title' => 'طلاب بدون مقاعد لجان', 'match' => 'students_no_seats'],
 ['link' => 'exam_reports.php', 'icon' => 'fa-file-alt', 'title' => 'جميع تقارير الامتحانات', 'match' => 'exam_reports'],
 ]
 ],
 'results' => [
 'نتائج الطلاب' => [
 ['link' => 'results.php', 'icon' => 'fa-poll', 'title' => 'عرض نتائج الطلاب', 'match' => 'results'],
 ['link' => 'student_grade_report.php','icon' => 'fa-file-alt', 'title' => 'كشف درجات الطالب', 'match' => 'student_grade_report'],
 ['link' => 'gpa_calculator.php', 'icon' => 'fa-calculator', 'title' => 'حساب المعدلات التراكمية', 'match' => 'gpa_calculator'],
 ],
 'اعتماد النتائج' => [
 ['link' => 'submit_grades.php', 'icon' => 'fa-check-double', 'title' => 'اعتماد النتائج النهائية', 'match' => 'submit_grades'],
 ],
 ],
 'library' => [
 'المكتبة المركزية' => [
 ['link' => 'manage_books.php', 'icon' => 'fa-book-open', 'title' => 'أرصدة المكتبة', 'match' => 'manage_books'],
 ['link' => 'manage_borrow.php', 'icon' => 'fa-exchange-alt', 'title' => 'إدارة طلبات الاستعارة', 'match' => 'manage_borrow'],
 ]
 ],
 'absence' => [
 'الحضور والغياب' => [
 ['link' => 'attendance.php', 'icon' => 'fa-user-check', 'title' => 'متابعة غياب الطلاب', 'match' => 'attendance'],
 ['link' => 'take_attendance.php', 'icon' => 'fa-clipboard-list','title' => 'رصد غياب يومي', 'match' => 'take_attendance'],
 ['link' => 'attendance_report.php', 'icon' => 'fa-file-excel', 'title' => 'تقرير الغياب الشهري', 'match' => 'attendance_report'],
 ],
 'الحضور بـ QR Code' => [
 ['link' => 'qr_attendance/start_session.php','icon' => 'fa-qrcode', 'title' => 'بدء محاضرة وتوليد QR', 'match' => 'start_session'],
 ['link' => 'qr_attendance/my_sessions.php', 'icon' => 'fa-history', 'title' => 'سجل جلسات الحضور', 'match' => 'my_sessions'],
 ['link' => 'attendance_report.php', 'icon' => 'fa-chart-bar','title'=> 'تقرير الحضور التفصيلي', 'match' => 'attendance_report'],
 ]
 ],
 'mail' => [
 'البريد والرسائل' => [
 ['link' => 'messages.php?action=compose','icon' => 'fa-edit', 'title' => 'إنشاء رسالة جديدة', 'match' => 'compose'],
 ['link' => 'messages.php?folder=inbox', 'icon' => 'fa-inbox', 'title' => 'صندوق الوارد', 'match' => 'inbox'],
 ['link' => 'messages.php?folder=sent', 'icon' => 'fa-paper-plane','title' => 'الرسائل المرسلة', 'match' => 'sent'],
 ]
 ],
 'supervision' => [
 'إدارة الحسابات' => [
 ['link' => 'manage_users.php', 'icon' => 'fa-users-cog', 'title' => 'إدارة حسابات المستخدمين', 'match' => 'manage_users'],
 ['link' => 'announcements.php', 'icon' => 'fa-bullhorn', 'title' => 'إدارة الإعلانات والتعميمات','match' => 'announcements'],
 ['link' => 'admin/audit_logs.php','icon' => 'fa-shield-alt','title' => 'سجل التدقيق (Audit Trail)', 'match' => 'audit_logs'],
 ],
 'التربية العسكرية' => [
 ['link' => 'admin_military.php', 'icon' => 'fa-user-shield','title' => 'إدارة التربية العسكرية', 'match' => 'admin_military'],
 ],
 'إعدادات النظام' => [
 ['link' => 'settings.php', 'icon' => 'fa-cogs', 'title' => 'إعدادات النظام العامة', 'match' => 'settings'],
 ['link' => 'settings.php#backup', 'icon' => 'fa-database', 'title' => 'النسخ الاحتياطي للبيانات', 'match' => 'backup'],
 ],
 ],
 'survey' => [
 'الاستبيانات' => [
 ['link' => 'manage_surveys.php', 'icon' => 'fa-poll', 'title' => 'إدارة الاستبيانات الأكاديمية','match' => 'manage_surveys'],
 ['link' => 'surveys.php', 'icon' => 'fa-clipboard-check','title' => 'تفعيل استبيان لمقرر', 'match' => 'surveys'],
 ['link' => 'survey_results.php', 'icon' => 'fa-chart-pie', 'title' => 'نتائج وتحليل الاستبيانات', 'match' => 'survey_results'],
 ]
 ]
];

// بنبني المنيو الجانبي على حسب الرتبة (أدمن، دكتور، طالب)
$sidebar_sections = [];

if (in_array($role, ['super_admin', 'admin', 'dean', 'affairs', 'instructor'])) {
    $home_link = ($role === 'super_admin') ? 'super_admin_dashboard.php' : (in_array($role,['admin','dean','affairs']) ? 'admin_dashboard.php' : 'instructor_dashboard.php');
 $home_match = ($role === 'super_admin') ? 'super_admin_dashboard' : (in_array($role,['admin','dean','affairs']) ? 'admin_dashboard' : 'instructor_dashboard');

 // 1. Dashboard & Profile Section
 if ($role === 'instructor') {
 $sidebar_sections['dashboard_main'] = [
 'title' => 'الرئيسية والشخصية',
 'icon' => 'fa-th-large',
 'items' => [
 ['link' => $home_link, 'icon' => 'fa-home', 'title' => 'الرئيسية', 'match' => $home_match],
 ['link' => 'profile.php', 'icon' => 'fa-user-circle', 'title' => 'الملف الشخصي', 'match' => 'profile'],
 ]
 ];
 } else {
 $sidebar_sections['dashboard_main'] = [
 'title' => 'الرئيسية والشخصية',
 'icon' => 'fa-th-large',
 'items' => [
 ['link' => $home_link, 'icon' => 'fa-home', 'title' => 'الرئيسية', 'match' => $home_match],
 ['link' => 'profile.php', 'icon' => 'fa-user-circle', 'title' => 'الملف الشخصي', 'match' => 'profile'],
 ['link' => 'messages.php', 'icon' => 'fa-envelope', 'title' => 'الرسائل', 'match' => 'messages'],
 ]
 ];
 }

 // 2. Instructor Specific Sections
 if ($role === 'instructor') {
 $sidebar_sections['qr_attendance'] = [
 'title' => 'الحضور والغياب',
 'icon' => 'fa-qrcode',
 'items' => [
 ['link' => 'qr_attendance/start_session.php', 'icon' => 'fa-play-circle', 'title' => 'بدء محاضرة QR', 'match' => 'start_session'],
 ['link' => 'qr_attendance/my_sessions.php', 'icon' => 'fa-history', 'title' => 'سجل الجلسات', 'match' => 'my_sessions'],
 ['link' => 'take_attendance.php', 'icon' => 'fa-clipboard-list','title' => 'رصد الغياب اليدوي','match' => 'take_attendance'],
 ]
 ];
 $sidebar_sections['online_exams'] = [
 'title' => 'الامتحانات الإلكترونية',
 'icon' => 'fa-laptop-code',
 'items' => [
 ['link' => 'online_exam/manage_exam.php', 'icon' => 'fa-file-alt', 'title' => 'إدارة الامتحانات', 'match' => 'manage_exam'],
 ['link' => 'online_exam/create_exam.php', 'icon' => 'fa-plus-circle', 'title' => 'إنشاء امتحان جديد', 'match' => 'create_exam'],
 ]
 ];
 }

 // 3. Academic Management Categories (Filtered by Permission)
 if ($role !== 'instructor') {
  foreach ($horizontal_tabs as $tab_id => $tab_info) {
   if (!has_permission($tab_id)) continue;
   if (!isset($tab_sidebars[$tab_id])) continue;
   $merged_items = [];
   foreach ($tab_sidebars[$tab_id] as $sub_title => $items) {
    $merged_items = array_merge($merged_items, $items);
   }
   $sidebar_sections[$tab_id] = [
    'title' => $tab_info['title'],
    'icon'  => $tab_info['icon'],
    'items' => $merged_items
   ];
  }
 }

} else {
 // Student nav
 $sidebar_sections = [
  'student_main' => [
   'title' => 'الرئيسية والشخصية',
 'icon' => 'fa-home',
 'items' => [
 ['link' => 'dashboard.php', 'icon' => 'fa-home', 'title' => $lang['home'], 'match' => 'dashboard'],
 ['link' => 'profile.php', 'icon' => 'fa-user', 'title' => $lang['icon_profile'], 'match' => 'profile'],
 ]
 ],
 'student_academic' => [
 'title' => 'الأكاديمية والامتحانات',
 'icon' => 'fa-graduation-cap',
 'items' => [
 ['link' => 'results.php', 'icon' => 'fa-graduation-cap', 'title' => $lang['icon_results'], 'match' => 'results'],
 ['link' => 'exams.php', 'icon' => 'fa-file-alt', 'title' => $lang['icon_exams'], 'match' => 'exams'],
 ['link' => 'online_exam/student_exams.php', 'icon' => 'fa-laptop', 'title' => 'امتحاناتي الإلكترونية', 'match' => 'student_exams'],
 ['link' => 'attendance.php', 'icon' => 'fa-user-check', 'title' => 'الغياب والحضور', 'match' => 'attendance'],
 ['link' => 'student_qr_scan.php', 'icon' => 'fa-qrcode', 'title' => 'تسجيل حضور بـ QR', 'match' => 'student_qr_scan'],
 ]
 ],
 'student_services' => [
 'title' => 'الخدمات الطلابية',
 'icon' => 'fa-concierge-bell',
 'items' => [
 ['link' => 'registration.php',  'icon' => 'fa-edit',            'title' => $lang['icon_registration'], 'match' => 'registration'],
 ['link' => 'books.php',         'icon' => 'fa-file-pdf',        'title' => 'مقررات مرجعية',            'match' => 'books'],
 ['link' => 'library.php',       'icon' => 'fa-book-reader',     'title' => 'المكتبة الجامعية',         'match' => 'library'],
 ['link' => 'majors.php',        'icon' => 'fa-project-diagram', 'title' => 'التشعيب وتغيير المسار',    'match' => 'majors'],
 ['link' => 'military.php',      'icon' => 'fa-shield-alt',      'title' => 'التربية العسكرية',         'match' => 'military'],
 ['link' => 'gpa_calculator.php','icon' => 'fa-calculator',      'title' => 'حاسبة المعدل',             'match' => 'gpa_calculator'],
 ]
 ]
 ];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang['code']; ?>" dir="<?php echo $lang['dir']; ?>">

<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?> - EDU Nexus</title>
  <meta name="description" content="EDU Nexus هو منصة تعليمية متكاملة تهدف لتطوير العملية الأكاديمية، توفر للطلاب وأعضاء هيئة التدريس أدوات ذكية لإدارة النتائج، الجداول، والامتحانات الإلكترونية بأعلى معايير الأمان.">
  <link rel="shortcut icon" href="<?php echo $base_path; ?>assets/images/logo.png?v=1.1" type="image/x-icon">
  <link rel="icon" type="image/png" href="<?php echo $base_path; ?>assets/images/logo.png?v=1.1">
 <!-- Font Awesome — CDN (local assets/css folder not found) -->
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
 <!-- Google Fonts -->
 <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
 <script src="https://cdn.tailwindcss.com"></script>
 <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#1e3a8a', 
            bright: '#2563eb',
            accent: '#60a5fa',
            'bg-soft': '#f8fafc',
            'text-dark': '#111827',
            success: '#10b981',
            danger: '#e11d48',
            warning: '#f59e0b',
          },
          fontFamily: {
            cairo: ['Cairo', 'sans-serif'],
          },
          boxShadow: {
            'soft': '0 4px 20px -2px rgba(0, 0, 0, 0.05)',
            'card': '0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01)',
          },
          borderRadius: {
            '2xl': '1rem',
            '3xl': '1.5rem',
          }
        }
      }
    }
  </script>
 <style>
  :root {
    --primary: #1e3a8a;
    --bright: #2563eb;
    --accent: #60a5fa;
    --bg-soft: #f8fafc;
    --bg-main: #f3f4f6; 
    --text-dark: #111827;
    --surface: #ffffff;
    --border-color: #E2E8F0;
    --glass-bg: rgba(255, 255, 255, 0.95);
    --header-bg: rgba(255, 255, 255, 0.98);
    --card-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
  }
  body {
    font-family: 'Cairo', sans-serif;
    background-color: var(--bg-soft) !important;
    color: var(--text-dark);
    -webkit-font-smoothing: antialiased;
  }
  .edu-card,
  .bg-white.rounded-xl,
  .bg-white.rounded-2xl,
  .bg-white.rounded-lg,
  .bg-white.rounded-3xl {
    background: #FFFFFF !important;
    border-radius: 1rem !important; /* rounded-2xl */
    box-shadow: var(--card-shadow) !important;
    border: 1px solid rgba(0, 0, 0, 0.07) !important;
    transition: all 0.2s ease;
  }
  .edu-card:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05) !important; }

  /* Semantic Status Logic - Synced with security-lab palette */
  .status-passed, .status-active, .status-success, .status-paid { background-color: #ecfdf5 !important; color: #059669 !important; border: 1px solid #d1fae5 !important; }
  .status-failed, .status-danger, .status-warning-red, .status-overdue { background-color: #fff1f2 !important; color: #e11d48 !important; border: 1px solid #ffe4e6 !important; }
  .status-pending, .status-warning-amber, .status-process { background-color: #fffbeb !important; color: #b45309 !important; border: 1px solid #fef3c7 !important; }

  .glass-panel {
    background: var(--glass-bg) !important;
    backdrop-filter: blur(12px) saturate(180%);
    -webkit-backdrop-filter: blur(12px) saturate(180%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.3) !important;
  }
 .bg-white { background-color: var(--surface) !important; }
 .bg-bg { background-color: var(--bg-main) !important; }
 .text-secondary opacity-80 { color: var(--text-muted) !important; }
 .text-slate-600 { color: var(--text-muted) !important; }
 .text-slate-700 { color: var(--text-main) !important; }
 .text-slate-800 { color: var(--text-main) !important; }
 .text-secondary { color: var(--text-main) !important; }
 .border-slate-100,
 .border-slate-200 { border-color: var(--border-color) !important; }
 .shadow-sm { box-shadow: var(--card-shadow) !important; }
 .sidebar-scroll::-webkit-scrollbar { width: 4px; }
 .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
 .sidebar-scroll::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,0.12); border-radius: 20px; }
 .nav-active {
 background: linear-gradient(90deg, rgba(30,58,138,0.13) 0%, transparent 100%);
 border-right: 4px solid #1E3A8A;
 color: #1E3A8A;
 }
 .nav-active i { color: #1E3A8A !important; }
 .sidebar-section-title {
 display: flex;
 align-items: center;
 justify-content: space-between;
 padding: 5px 10px 5px 8px;
 margin-bottom: 2px;
 margin-top: 8px;
 font-size: 10px !important;
 font-weight: 800 !important;
 letter-spacing: 0.08em !important;
 text-transform: uppercase !important;
 color: #111827 !important;
 background: #F3F4F6 !important;
 border-right: 3px solid #1E3A8A !important;
 border-radius: 6px 0 0 6px !important;
 }
 .sidebar-section-title span { color: #1E3A8A; opacity: 0.9; }
 .sidebar-section-title i { color: #60A5FA; font-size: 9px; }
 .sidebar-sub-item {
 display: flex;
 align-items: center;
 gap: 10px;
 padding: 8px 12px;
 font-size: 13px;
 font-weight: 500;
 color: #111827;
 border-radius: 8px;
 margin: 1px 2px;
 transition: all 0.18s ease;
 text-decoration: none;
 }
 .sidebar-sub-item i {
 width: 18px;
 text-align: center;
 font-size: 12px;
 color: #2563EB !important;
 flex-shrink: 0;
 transition: color 0.18s;
 }
 .sidebar-sub-item:hover { background: rgba(30,58,138,0.08); color: #1E3A8A; padding-right: 20px; }
 .sidebar-sub-item:hover i { color: #1E3A8A !important; }
 .sidebar-sub-item.active { background: linear-gradient(90deg,rgba(30,58,138,0.14),rgba(30,58,138,0.05)); color:#1E3A8A; font-weight:700; border-right:3px solid #1E3A8A; }
 .sidebar-sub-item.active i { color: #1E3A8A !important; }
 .glass-panel {
 background: var(--header-bg) !important;
 backdrop-filter: blur(15px);
 -webkit-backdrop-filter: blur(15px);
 border-bottom: 1px solid var(--border-color) !important;
 }
 .avatar-initial {
 width: 38px;
 height: 38px;
 border-radius: 50%;
 background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 60%, #60A5FA 100%);
 display: flex;
 align-items: center;
 justify-content: center;
 color: #fff;
 font-weight: 700;
 font-size: 15px;
 box-shadow: 0 2px 10px rgba(30,58,138,0.30);
 flex-shrink: 0;
 transition: box-shadow 0.2s ease, transform 0.2s ease;
 text-decoration: none;
 }
 .avatar-initial:hover { box-shadow:0 4px 16px rgba(30,58,138,0.40); transform:scale(1.07); }
 .profile-image-container {
   width: 48px;
   height: 48px;
   border-radius: 12px;
   border: 2px solid #fff;
   box-shadow: 0 4px 12px rgba(30,58,138,0.15);
   overflow: hidden;
   position: relative;
 }
 .profile-image-container img {
   width: 100%;
   height: 100%;
   object-fit: cover;
 }
 .header-search {
 display: flex;
 align-items: center;
 gap: 10px;
 background: #F3F4F6;
 border-radius: 24px;
 padding: 8px 18px;
 border: 1.5px solid transparent;
 transition: all 0.2s ease;
 min-width: 180px;
 max-width: 300px;
 width: 100%;
 text-decoration: none;
 }
 .header-search:hover { background:#EEF2FF; border-color:rgba(30,58,138,0.30); }
 .header-search i { color:#2563EB; font-size:13px; flex-shrink:0; }
 .header-search span { color:#2563EB; font-size:13px; font-weight:400; white-space:nowrap; overflow:hidden; }
 h1, h2 { font-weight: 700 !important; letter-spacing: -0.01em; }
 h3, h4 { font-weight: 600 !important; }
 h5, h6 { font-weight: 500 !important; }
 label { font-weight: 600 !important; }
 p, td, li { font-weight: 400; line-height: 1.75; }
 input[type="text"], input[type="email"], input[type="password"],
 input[type="number"], input[type="search"], input[type="date"],
 input[type="tel"], input[type="url"], input[type="time"],
 select, textarea {
 border-radius: 10px !important;
 padding: 10px 14px !important;
 border: 1px solid rgba(0,0,0,0.1) !important;
 box-shadow: 0 2px 6px rgba(0,0,0,0.04) !important;
 transition: border-color 0.2s, box-shadow 0.2s !important;
 font-family: 'Cairo', sans-serif !important;
 }
 input:focus, select:focus, textarea:focus {
 border-color: #1E3A8A !important;
 box-shadow: 0 0 0 3px rgba(30,58,138,0.15) !important;
 outline: none !important;
 }
 th {
 font-weight: 700 !important;
 font-size: 0.78rem !important;
 letter-spacing: 0.05em !important;
 text-transform: uppercase !important;
 padding: 14px 18px !important;
 background: #F3F4F6 !important;
 color: #111827 !important;
 }
 td { padding: 14px 18px !important; font-weight: 400 !important; }
 tbody tr { transition: background 0.15s ease; }
 tbody tr:hover { background: rgba(30,58,138,0.04) !important; }
 .shadow, .shadow-sm, .shadow-card, .shadow-soft {
 box-shadow: 0 4px 20px rgba(30,58,138,0.07) !important;
 }
 .shadow-lg { box-shadow: 0 8px 30px rgba(30,58,138,0.10) !important; }
 .bg-white.rounded-xl,
 .bg-white.rounded-2xl,
 .bg-white.rounded-lg {
 border-radius: 16px !important;
 box-shadow: 0 4px 20px rgba(30,58,138,0.06) !important;
 border: 1px solid rgba(30,58,138,0.06) !important;
 }
 /* تأثير الوقوف على الزراير */
 button:not([onclick="toggleSidebar()"]):hover,
 a.btn:hover, input[type="submit"]:hover {
 transform: translateY(-1px);
 transition: transform 0.2s ease, box-shadow 0.2s ease;
 }
 i.fas, i.far, i.fab {
 transition: color 0.2s ease;
 color: inherit;
 }
 .sidebar-section-title {
 display: flex;
 align-items: center;
 justify-content: space-between;
 padding: 10px 14px;
 margin-bottom: 2px;
 margin-top: 4px;
 font-size: 11px !important;
 font-weight: 800 !important;
 color: #111827 !important;
 background: #F3F4F6 !important;
 border-right: 4px solid #1E3A8A !important;
 border-radius: 8px;
 cursor: pointer;
 transition: background 0.2s;
 }
 .sidebar-section-title:hover { background: #E8EDF8 !important; }
 .sidebar-section-title i.fa-chevron-down { font-size: 9px; opacity: 0.6; }
 .sidebar-sub-item {
 display: flex;
 align-items: center;
 gap: 12px;
 padding: 9px 16px;
 font-size: 13px;
 font-weight: 500;
 color: #111827;
 border-radius: 8px;
 margin: 2px 4px;
 transition: all 0.2s ease;
 text-decoration: none;
 }
 .sidebar-sub-item i { width: 16px; text-align: center; }
 .sidebar-sub-item:hover { background: rgba(30,58,138,0.08); color: #1E3A8A; }
 .sidebar-sub-item.active { background: #EEF2FF; color:#1E3A8A; font-weight:700; border-right:3px solid #1E3A8A; }
 .sidebar-sub-item.active i { color: #1E3A8A !important; }
 .sidebar-accordion-content {
 max-height: 0;
 overflow: hidden;
 transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
 opacity: 0;
 }
 .sidebar-accordion-content.expanded {
 max-height: 1000px;
 opacity: 1;
 }
 main { padding-top: 80px !important; }
 </style>
</head>

<body class="flex h-screen overflow-hidden bg-bg">

 <!-- خلفية القائمة الجانبية في الموبايل -->
 <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden hidden backdrop-blur-sm transition-opacity" onclick="toggleSidebar()"></div>

 <!-- القائمة الجانبية اليمين -->
 <aside id="sidebar" class="fixed inset-y-0 right-0 z-50 w-72 bg-white shadow-card transform translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col border-l border-slate-100 print:hidden">

 <!-- هيدر القائمة الجانبية -->
 <div class="h-20 flex items-center justify-center px-6 border-b border-slate-100 bg-white">
 <a href="<?php echo $base_path . ($role === 'super_admin' ? 'super_admin_dashboard.php' : (in_array($role,['admin','dean','affairs']) ? 'admin_dashboard.php' : ($role === 'instructor' ? 'instructor_dashboard.php' : 'dashboard.php'))); ?>" class="flex items-center gap-3 w-full group">
 <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center border border-slate-200 group-hover:border-primary shadow-sm overflow-hidden p-0.5 transition-all duration-300">
 <img src="<?php echo $base_path; ?>assets/images/logo.png" alt="University Logo" class="h-10 w-auto object-contain">
 </div>
 <div>
 <h1 class="font-bold text-lg text-secondary leading-tight group-hover:text-primary transition-colors">EDU Nexus</h1>
 <span class="text-xs text-secondary opacity-80 font-medium">البوابة الأكاديمية</span>
 </div>
 </a>
 <!-- Mobile Close -->
 <button onclick="toggleSidebar()" class="lg:hidden text-secondary opacity-70 hover:text-primary transition-colors mr-auto">
 <i class="fas fa-times text-xl"></i>
 </button>
 </div>

  <!-- بروفايل المستخدم -->
  <div class="p-4 border-b border-slate-100 bg-bg/30">
  <div class="flex items-center gap-4">
  <div class="relative group"> 
  <div class="profile-image-container bg-white flex items-center justify-center text-dark ring-2 ring-primary/5 transition-all group-hover:ring-primary/20">
  <?php 
  $abs_profile_pic = $_project_root . DIRECTORY_SEPARATOR . $profile_pic;
  if (!empty($profile_pic) && file_exists($abs_profile_pic)): 
  ?>
    <img src="<?php echo $base_path . $profile_pic; ?>" alt="Profile" class="h-full w-full object-cover text-[0]">
  <?php else: ?>
    <span class="text-xl font-bold text-primary"><?php echo mb_substr($full_name, 0, 1, 'UTF-8'); ?></span>
  <?php endif; ?>
  </div>
  <!-- Role Icon Badge on Avatar -->
  <?php
  $role_icons = [
  'super_admin' => 'fa-crown text-amber-500',
  'admin' => 'fa-shield-alt text-primary',
  'dean' => 'fa-user-tie text-accent',
  'affairs' => 'fa-users-cog text-blue-600',
  'instructor' => 'fa-chalkboard-teacher text-accent',
  'student' => 'fa-user-graduate text-primary'
  ];
  $icon_class = $role_icons[$role] ?? 'fa-user text-primary';
  ?>
  <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-white shadow-sm border border-slate-100 rounded-lg flex items-center justify-center">
    <i class="fas <?php echo $icon_class; ?> text-[10px]"></i>
  </div>
  </div>
  
  <div class="flex-1 overflow-hidden">
  <div class="font-black text-secondary group-hover:text-primary transition-colors truncate leading-tight">
    <?php echo htmlspecialchars($full_name); ?>
  </div>
  <?php if ($role === 'dean' && isset($_SESSION['college_name'])): ?>
    <div class="text-[10px] text-secondary/60 font-bold mt-1 truncate">
    <i class="fas fa-school mr-1"></i> <?php echo htmlspecialchars($_SESSION['college_name']); ?>
    </div>
  <?php endif; ?>
  </div>
  </div>
  </div>

 <!-- منيو التنقل -->
 <div class="flex-1 overflow-y-auto sidebar-scroll py-6 px-4">
 <p class="px-4 text-xs font-bold text-secondary opacity-70 uppercase tracking-wider mb-4">القائمة الرئيسية</p>

 <div class="space-y-2">
 <?php foreach ($sidebar_sections as $sec_key => $sec_data):
 $section_title = $sec_data['title'];
 $items = $sec_data['items'];
 $sec_id = 'acc-' . $sec_key;
 
 $has_active = false;
 foreach ($items as $_i) { if ($current_page == $_i['match']) { $has_active = true; break; } }
 
 $is_expanded = $has_active || (isset($_active_menus[$sec_id]) && $_active_menus[$sec_id]);
 ?>
 <div class="sidebar-accordion-item">
 <div class="sidebar-section-title" onclick="toggleSidebarSection('<?php echo $sec_id; ?>')">
 <span class="flex items-center gap-3">
 <i class="fas <?php echo $sec_data['icon']; ?> w-5 text-center"></i>
 <?php echo $section_title; ?>
 </span>
 <i id="chevron-<?php echo $sec_id; ?>" class="fas fa-chevron-down transition-transform duration-300 <?php echo $is_expanded ? 'rotate-180' : ''; ?>"></i>
 </div>
 <div id="<?php echo $sec_id; ?>" class="sidebar-accordion-content <?php echo $is_expanded ? 'expanded' : ''; ?>">
 <div class="bg-bg/50 py-1 border-r-2 border-slate-100 mr-2 mt-1 rounded-l-lg mb-2">
 <?php foreach ($items as $item):
 $isActive = ($current_page == $item['match']);
 ?>
 <a href="<?php echo $base_path . ltrim($item['link'], '/'); ?>" class="sidebar-sub-item <?php echo $isActive ? 'active' : ''; ?>">
 <i class="fas <?php echo $item['icon']; ?>"></i>
 <span><?php echo $item['title']; ?></span>
 </a>
 <?php endforeach; ?>
 </div>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
 </div>

 <!-- فوتر القائمة الجانبية -->
 <div class="p-4 border-t border-slate-100 bg-white space-y-2">
 <?php if ($role === 'super_admin'): ?>
 <a href="<?php echo $base_path; ?>admin/audit_logs.php"
 class="flex items-center gap-6 px-1 py-0 rounded-lg font-bold transition-all duration-200 w-full
 <?php echo ($current_page === 'audit_logs') ? 'bg-bg text-secondary border border-accent' : 'text-secondary hover:bg-bg hover:text-primary'; ?>"
 title="سجل الرقابة الإدارية — Audit Trail">
 <i class="fas fa-history text-lg"></i>
 <span>سجل الرقابة الإدارية</span>
 <?php if ($current_page === 'audit_logs'): ?>
 <span class="mr-auto w-3.5 h-3.5 rounded-full bg-accent"></span>
 <?php endif; ?>
 </a>
 <?php endif; ?>
 <a href="<?php echo $base_path; ?>logout.php" class="flex items-center gap-6 px-1 py-0 rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 font-bold transition-all duration-200 w-full">
 <i class="fas fa-sign-out-alt text-lg"></i>
 <span>تسجيـــــــل الخــــروج</span>
 </a>
 </div>
 </aside>

 <script src="<?php echo $base_path; ?>assets/js/ui-fix.js" defer></script>

 <!-- محتوى الصفحة الأساسي -->
 <div class="flex-1 flex flex-col h-screen overflow-hidden transition-all duration-300 lg:pr-72 w-full print:pr-0 print:h-auto print:overflow-visible">

 <!-- الهيدر اللي فوق -->
 <header class="h-20 glass-panel shadow-sm sticky top-0 z-30 flex items-center px-5 lg:px-8 border-b border-slate-200/50 print:hidden gap-3 lg:gap-5">

 <!-- Zone 1: Right/Start — Hamburger + Brand (mobile) + Page Title (desktop) -->
 <div class="flex items-center gap-3 flex-shrink-0">
 <button onclick="toggleSidebar()" class="lg:hidden text-secondary opacity-80 hover:text-primary focus:outline-none transition-colors p-2 rounded-xl hover:bg-bg">
 <i class="fas fa-bars text-xl"></i>
 </button>
 <!-- Brand — mobile only (sidebar hidden) -->
 <a href="<?php echo $base_path . ($role === 'super_admin' ? 'super_admin_dashboard.php' : (in_array($role,['admin','dean','affairs']) ? 'admin_dashboard.php' : ($role === 'instructor' ? 'instructor_dashboard.php' : 'dashboard.php'))); ?>" class="lg:hidden flex items-center gap-2 group">
 <div class="w-8 h-8 rounded-xl bg-white flex items-center justify-center border border-slate-200 overflow-hidden p-0.5 shadow-sm">
 <img src="<?php echo $base_path; ?>assets/images/logo.png" alt="EDU Nexus" class="h-8 w-auto object-contain">
 </div>
 <span class="font-bold text-secondary text-sm group-hover:text-primary transition-colors">EDU Nexus</span>
 </a>
 <!-- Page Title removed per user request -->
 <h2 class="text-lg font-bold text-secondary hidden lg:flex items-center gap-2">
 </h2>
 </div>

 <!-- Zone 2: Center — Search Bar -->
 <div class="flex-1 flex justify-center px-2">
            <!-- Admin/Super Admin Maintenance Link -->
            <?php if (in_array($role, ['super_admin', 'admin'])): ?>
            <a href="admin/maintenance.php" class="<?php echo $current_page == 'maintenance' ? 'bg-primary text-white' : 'text-slate-600 hover:bg-bg'; ?> p-2.5 rounded-xl transition-all flex items-center justify-center gap-2" title="صيانة النظام">
                <i class="fas fa-tools text-lg"></i>
                <span class="hidden xl:inline text-xs font-bold">صيانة النظام</span>
            </a>
            <?php endif; ?>
 <?php if ($role !== 'affairs'): ?>
 <a href="<?php echo $base_path; ?>search.php" class="header-search hidden md:flex" title="<?php echo $lang['search'] ?? 'بحث'; ?>">
 <i class="fas fa-search"></i>
 <span><?php echo $lang['search'] ?? 'بحث في النظام...'; ?></span>
 </a>
 <?php endif; ?>
 </div>

 <!-- Zone 3: Left/End — Date · Language · Notifications · Avatar -->
 <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
 <!-- Date -->
 <div class="hidden md:flex items-center gap-2 text-xs text-secondary opacity-80 bg-bg/80 px-3 py-1.5 rounded-full font-medium border border-slate-200/50">
 <i class="fas fa-calendar-day text-primary text-xs"></i>
 <span><?php echo date('Y/m/d'); ?></span>
 </div>
 <!-- Language Toggle Removed -->
 <!-- Notifications Bell -->
 <button id="notif-btn" class="relative w-9 h-9 rounded-full flex items-center justify-center text-secondary opacity-80 hover:text-primary hover:bg-bg transition-all focus:outline-none border border-slate-200/80">
 <i class="fas fa-bell text-base"></i>
 <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-primary rounded-full ring-2 ring-white"></span>
 </button>
 <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>
 <!-- Profile Avatar -->
 <a href="<?php echo $base_path; ?>profile.php" class="avatar-initial" title="<?php echo htmlspecialchars($full_name); ?>">
 <?php echo mb_substr($full_name, 0, 1, 'UTF-8'); ?>
 </a>
 </div>
 </header>

 <!-- Page Content Scrollable Area -->
 <main class="flex-1 flex flex-col overflow-y-auto bg-bg p-6 lg:p-10 print:overflow-visible print:bg-white print:p-0">
 <div class="max-w-7xl mx-auto space-y-8 flex-1 w-full flex flex-col">
 <!-- Content injected here by trailing files -->