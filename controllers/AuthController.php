<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
 
 public function login(string $username, string $password, string $login_type = 'student'): array {
 $userModel = new User();
 $user = $userModel->authenticate($username, $password);
 
 if (!$user) {
 return ['status' => false, 'message' => 'بيانات الدخول غير صحيحة'];
 }

 // اتأكد إن نوع الدخول ماشي مع الرتبة بتاعته
 $role = $user['role'];
 $role_valid = false;
 
 if ($login_type === 'staff' && in_array($role, ['super_admin', 'admin', 'dean', 'affairs', 'instructor'])) {
 $role_valid = true;
 } elseif ($login_type === 'student' && $role === 'student') {
 $role_valid = true;
 }
 
 if (!$role_valid) {
 $msg = $login_type === 'staff' ? 'هذا الحساب ليس عضو هيئة تدريس' : 'هذا الحساب ليس طالب';
 return ['status' => false, 'message' => $msg];
 }
 
 if ($user['status'] !== 'active') {
 return ['status' => false, 'message' => 'هذا الحساب موقوف، يرجى مراجعة الإدارة'];
 }

 // افتح الـ session وسجل بياناته
 if (session_status() === PHP_SESSION_NONE) {
 session_start();
 }
 
 $_SESSION['user_id'] = $user['id'];
 $_SESSION['username'] = $user['username'];
 $_SESSION['role'] = $user['role'];
 $_SESSION['college_id'] = $user['college_id'] ?? 0;
 $_SESSION['full_name'] = $user['full_name'];

 // هنوديه على فين بعد ما يدخل؟
 $redirect = 'index.php';
 switch ($user['role']) {
 case 'super_admin':
 case 'admin':
 $redirect = 'super_admin_dashboard.php';
 if ($user['role'] === 'admin') $redirect = 'admin_dashboard.php';
 break;
 case 'dean':
 case 'affairs':
 $redirect = 'admin_dashboard.php';
 break;
 case 'instructor':
 $redirect = 'instructor_dashboard.php';
 break;
 case 'student':
 $redirect = 'dashboard.php';
 break;
 }

 // بنسجل إن اليوزر دا دخل السيستم دلوقتي عشان المتابعة
 try {
 require_once __DIR__ . '/../database/db_connection.php';
 require_once __DIR__ . '/../includes/audit.php';
 $auditPdo = get_pdo();
 auditLogLogin($auditPdo);
 } catch (Throwable $e) {
 // حتى لو حصل مشكلة في تسجيل الدخول كـ audit، متبوظش عليه الدخول نفسه
 error_log('[EDU Nexus Audit] Login log failed: ' . $e->getMessage());
 }

 return ['status' => true, 'redirect' => $redirect];
 }
}
