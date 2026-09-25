<?php
/**
 * EDU Nexus — مسح سجلات التدقيق
 * admin/clear_audit.php
 *
 * ملف مستقل — لا يحتوي على HTML أو header.php
 * ينفذ TRUNCATE TABLE audit_logs RESTART IDENTITY
 * ثم يُعيد التوجيه إلى audit_logs.php بمعامل الحالة
 * محمي: super_admin فقط
 */

// ── 1. بدء الجلسة والتحقق منها ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

// ── 2. حماية صارمة: super_admin فقط ─────────────────────────
$role = $_SESSION['role'] ?? '';
if ($role !== 'super_admin') {
 header('Location: audit_logs.php?cleared=denied');
 exit;
}

// ── 3. التحقق من رمز CSRF ────────────────────────────────────
// يُمرَّر عبر GET من رابط موقَّع بمعرّف الجلسة
$expectedToken = md5(session_id() . '_audit_clear');
$receivedToken = $_GET['token'] ?? '';

if (!hash_equals($expectedToken, $receivedToken)) {
 header('Location: audit_logs.php?cleared=invalid_token');
 exit;
}

// ── 4. تحميل اتصال قاعدة البيانات ───────────────────────────
require_once __DIR__ . '/../database/db_connection.php';
$pdo = get_pdo();

// ── 5. تنفيذ TRUNCATE ────────────────────────────────────────
try {
 $pdo->exec('TRUNCATE TABLE audit_logs RESTART IDENTITY');
 // إعادة توجيه بمعامل نجاح
 header('Location: audit_logs.php?cleared=1');
 exit;
} catch (PDOException $e) {
 error_log('[Audit Clear] TRUNCATE failed: ' . $e->getMessage());
 // إعادة توجيه بمعامل فشل (مُشفَّر)
 header('Location: audit_logs.php?cleared=error&msg=' . urlencode($e->getMessage()));
 exit;
}
