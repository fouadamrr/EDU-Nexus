<?php
// صفحة تسجيل الخروج من السيستم
// بنسجل عملية الخروج في اللوج قبل ما نمسح السيشين ونوديه للوجن
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

// بنسجل إن اليوزر خرج بس لو كان في يوزر مسجل دخول أصلاً
if (isset($_SESSION['user_id'])) {
 try {
 // بنحمل كونكشن الداتا بيز وكود اللوجات (Audit)
 require_once __DIR__ . '/database/db_connection.php';
 require_once __DIR__ . '/includes/audit.php';
 $pdo = get_pdo();
 auditLogLogout($pdo);
 } catch (Throwable $e) {
 // مش عايزين أي غلطة في اللوجات تعطل اليوزر إنه يخرج
 error_log('[EDU Nexus Audit] Logout log failed: ' . $e->getMessage());
 }
}

session_destroy();
header('Location: index.php');
exit;
