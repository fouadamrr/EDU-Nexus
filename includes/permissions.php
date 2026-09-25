<?php
// موضوع الصلاحيات - EDU Nexus
// الملف دا هو اللي بيحدد مين يقدر يشوف إيه ومين لاء

// كل حتة في النظام ليها مفتاح (الموديولات)
if (!defined('SYSTEM_PERMISSIONS')) {
 define('SYSTEM_PERMISSIONS', [
 'registration' => [
 'label' => 'التسجيل الأكاديمي',
 'icon' => 'fa-edit',
 'color' => 'blue',
 'desc' => 'التسجيل، فتح المواد، وتعديل مقررات الطلاب',
 ],
 'students' => [
 'label' => 'بيانات الطلاب',
 'icon' => 'fa-users',
 'color' => 'indigo',
 'desc' => 'عرض وتعديل البيانات الدراسية للطلاب',
 ],
 'programs' => [
 'label' => 'البرامج الدراسية',
 'icon' => 'fa-graduation-cap',
 'color' => 'violet',
 'desc' => 'إدارة المقررات والبرامج الأكاديمية',
 ],
 'schedules' => [
 'label' => 'الجداول الدراسية',
 'icon' => 'fa-calendar-alt',
 'color' => 'cyan',
 'desc' => 'جداول المحاضرات وتوزيع الطلاب',
 ],
 'exams' => [
 'label' => 'اللجان والامتحانات',
 'icon' => 'fa-file-signature',
 'color' => 'orange',
 'desc' => 'تعريف اللجان وتوزيع الطلاب على المقاعد',
 ],
 'results' => [
 'label' => 'الكنترول والنتائج',
 'icon' => 'fa-poll-h',
 'color' => 'emerald',
 'desc' => 'إدخال الدرجات وحساب المعدلات واعتماد النتائج',
 ],
 'financial' => [
 'label' => 'البيانات المالية',
 'icon' => 'fa-money-bill-wave',
 'color' => 'green',
 'desc' => 'الرسوم الدراسية والمصروفات والسدادات',
 ],
 'absence' => [
 'label' => 'الحضور والغياب',
 'icon' => 'fa-user-clock',
 'color' => 'amber',
 'desc' => 'رصد الغياب اليومي وتقارير الحضور',
 ],
 'library' => [
 'label' => 'المكتبة الجامعية',
 'icon' => 'fa-book-open',
 'color' => 'rose',
 'desc' => 'أرصدة المكتبة وطلبات الاستعارة',
 ],
 'mail' => [
 'label' => 'البريد الداخلي',
 'icon' => 'fa-envelope-open-text',
 'color' => 'sky',
 'desc' => 'الرسائل الداخلية والتواصل بين المستخدمين',
 ],
 'supervision' => [
 'label' => 'الإشراف على النظام',
 'icon' => 'fa-cogs',
 'color' => 'slate',
 'desc' => 'إدارة المستخدمين، الإعدادات، وسجل النشاطات',
 ],
 ]);
}

// بنجيب صلاحيات اليوزر من الداتا بيز وبنحطها في cache عشان السرعة
$_edu_permissions_cache = null;

function _edu_load_permissions(): array
{
 global $_edu_permissions_cache, $pdo, $user_id, $role;

 if ($_edu_permissions_cache !== null) {
 return $_edu_permissions_cache;
 }

 // Super admin and admin always have everything
 if (in_array($role ?? '', ['super_admin', 'admin'])) {
 $_edu_permissions_cache = array_keys(SYSTEM_PERMISSIONS);
 return $_edu_permissions_cache;
 }

 // Students use a completely separate interface — no admin permissions
 if (($role ?? '') === 'student') {
 $_edu_permissions_cache = [];
 return $_edu_permissions_cache;
 }

 // Dean, Affairs, Instructor — load from DB
 if (!isset($pdo, $user_id)) {
 $_edu_permissions_cache = [];
 return $_edu_permissions_cache;
 }

 try {
 $stmt = $pdo->prepare(
 "SELECT permission FROM user_permissions WHERE user_id = :uid"
 );
 $stmt->execute([':uid' => (int)$user_id]);
 $_edu_permissions_cache = array_column(
 $stmt->fetchAll(PDO::FETCH_ASSOC),
 'permission'
 );
 } catch (Exception $e) {
 $_edu_permissions_cache = [];
 }

 return $_edu_permissions_cache;
}

// الدوال اللي بنستخدمها في الصفحات عشان نتأكد

/**
 * Returns true if the current user has the given module permission.
 * Super Admin and Admin always return true.
 */
function has_permission(string $perm): bool
{
 global $role;
 if (in_array($role ?? '', ['super_admin', 'admin'])) return true;
 return in_array($perm, _edu_load_permissions());
}

/**
 * Halts execution and redirects if the current user lacks the permission.
 * Call this at the top of any admin-side page.
 */
function require_permission(string $perm): void
{
 if (has_permission($perm)) return;

 global $role;
 $dest = ($role ?? '') === 'instructor'
 ? 'instructor_dashboard.php'
 : 'admin_dashboard.php';

 // Use JS redirect instead of header() since this is often called after includes/header.php has sent HTML
 echo "<script>
 alert('عذراً، لم يتم منحك صلاحية الوصول إلى هذه الوحدة. يرجى مراجعة الإدارة.');
 window.location.href = '{$dest}';
 </script>";
 exit;
}

/**
 * Returns the count of permissions the current user has.
 */
function count_permissions(): int
{
 return count(_edu_load_permissions());
}

// Auto-load on include
_edu_load_permissions();