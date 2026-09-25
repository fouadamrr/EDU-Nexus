<?php
/**
 * EDU Nexus — تصدير سجل التدقيق (CSV)
 * admin/export_audit.php
 *
 * ملف مستقل — لا يحتوي على HTML أو header.php
 * يُنتج مباشرةً ملف CSV عربي مع BOM لـ Excel
 * محمي: super_admin فقط
 */

// ── 1. بدء الجلسة والتحقق من الجلسة ─────────────────────────
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

// ── 2. حماية: super_admin فقط ────────────────────────────────
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['super_admin', 'admin'])) {
 http_response_code(403);
 exit('403 Forbidden — Access denied.');
}

// ── 3. تحميل اتصال قاعدة البيانات ───────────────────────────
require_once __DIR__ . '/../database/db_connection.php';

// ── 4. تحميل محرك التدقيق (اختياري — للتأكد من توافر الدوال) ─
if (!function_exists('auditLogAuto')) {
 require_once __DIR__ . '/../includes/audit.php';
}

// ── 5. إعداد اتصال PDO ───────────────────────────────────────
$pdo = get_pdo();

// ── 6. بناء فلاتر الاستعلام (تمرير من audit_logs.php) ────────
$where = ['1=1'];
$params = [];

$fActor = trim($_GET['actor'] ?? '');
$fRole = trim($_GET['role'] ?? '');
$fAction = trim($_GET['action_type'] ?? '');
$fTarget = trim($_GET['target_type'] ?? '');
$fDateFrom = trim($_GET['date_from'] ?? '');
$fDateTo = trim($_GET['date_to'] ?? '');
$fSearch = trim($_GET['search'] ?? '');

if ($fActor !== '') {
 $where[] = "(actor_name ILIKE :actor OR actor_id::text = :actor_id)";
 $params[':actor'] = '%' . $fActor . '%';
 $params[':actor_id'] = $fActor;
}
if ($fRole !== '') {
 $where[] = "actor_role = :role";
 $params[':role'] = $fRole;
}
if ($fAction !== '') {
 $where[] = "action_type = :action_type";
 $params[':action_type'] = strtoupper($fAction);
}
if ($fTarget !== '') {
 $where[] = "target_type = :target_type";
 $params[':target_type'] = strtoupper($fTarget);
}
if ($fDateFrom !== '') {
 $where[] = "created_at >= :date_from";
 $params[':date_from'] = $fDateFrom . ' 00:00:00';
}
if ($fDateTo !== '') {
 $where[] = "created_at <= :date_to";
 $params[':date_to'] = $fDateTo . ' 23:59:59';
}
if ($fSearch !== '') {
 $where[] = "(description ILIKE :search OR old_values ILIKE :search2 OR new_values ILIKE :search3)";
 $params[':search'] = '%' . $fSearch . '%';
 $params[':search2'] = '%' . $fSearch . '%';
 $params[':search3'] = '%' . $fSearch . '%';
}

$whereStr = implode(' AND ', $where);

// ── 7. جلب البيانات من قاعدة البيانات ────────────────────────
$exportRows = [];
try {
 $stmt = $pdo->prepare("
 SELECT id, actor_id, actor_role, actor_name, action_type, target_type,
 target_id, old_values, new_values, description, ip_address, created_at
 FROM audit_logs
 WHERE $whereStr
 ORDER BY created_at DESC
 LIMIT 10000
 ");
 $stmt->execute($params);
 $exportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
 error_log('[Audit Export] DB error: ' . $e->getMessage());
 http_response_code(500);
 exit('خطأ في قاعدة البيانات: ' . htmlspecialchars($e->getMessage()));
}

// ── 8. مسح أي بافر سابق تماماً ───────────────────────────────
while (ob_get_level() > 0) {
 ob_end_clean();
}

// ── 9. إرسال ترويسات HTTP الصحيحة ────────────────────────────
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_logs.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ── 10. BOM للـ Excel — يجب أن يكون أول مخرج حرفياً ─────────
echo "\xEF\xBB\xBF";

// ── 11. كتابة CSV بشكل نظيف بدون أي HTML ────────────────────
$fp = fopen('php://output', 'w');

// رأس الجدول بالعربية
fputcsv($fp, [
 'الرقم',
 'معرف المسؤول',
 'الدور',
 'الاسم الكامل',
 'نوع العملية',
 'الهدف',
 'معرف الهدف',
 'القيم القديمة',
 'القيم الجديدة',
 'الوصف',
 'عنوان IP',
 'الوقت',
]);

// صفوف البيانات
foreach ($exportRows as $r) {
 fputcsv($fp, [
 $r['id'] ?? '',
 $r['actor_id'] ?? '',
 $r['actor_role'] ?? '',
 $r['actor_name'] ?? '',
 $r['action_type'] ?? '',
 $r['target_type'] ?? '',
 $r['target_id'] ?? '',
 $r['old_values'] ?? '',
 $r['new_values'] ?? '',
 $r['description'] ?? '',
 $r['ip_address'] ?? '',
 $r['created_at'] ?? '',
 ]);
}

fclose($fp);
exit;
