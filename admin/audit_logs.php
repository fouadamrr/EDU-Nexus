<?php
/*
 * صفحة سجل الرقابة الإدارية - بنراقب فيها كل اللي بيحصل في السيستم
 */

require_once __DIR__ . '/../includes/header.php';

// بنحمل محرك الرقابة عشان نتأكد إن الفانكشنز بتاعته شغالة
if (!function_exists('auditLogAuto')) {
 require_once __DIR__ . '/../includes/audit.php';
}

// التأكد إن اللي داخل أدمن أو سوبر أدمن بس، غير كدا يرجع للوحة التحكم
if (!in_array($role ?? '', ['super_admin', 'admin'])) {
 echo "<script>alert('عذراً، هذه الصفحة مخصصة للمشرفين فقط.');window.location.href='../admin_dashboard.php';</script>";
 exit;
}

// بنشوف لو فيه رسالة جاية من عملية مسح السجلات
$truncateMsg = null;
$cleared = $_GET['cleared'] ?? '';
if ($cleared === '1') {
 $truncateMsg = ['ok' => true, 'text' => 'تم مسح سجلات التجربة بنجاح، العداد بدأ من 1 تاني.'];
} elseif ($cleared === 'error') {
 $truncateMsg = ['ok' => false, 'text' => 'حصل مشكلة في المسح: ' . htmlspecialchars($_GET['msg'] ?? 'خطأ غير معروف')];
} elseif ($cleared === 'denied') {
 $truncateMsg = ['ok' => false, 'text' => 'ممنوع: الحركة دي للسوبر أدمن بس.'];
} elseif ($cleared === 'invalid_token') {
 $truncateMsg = ['ok' => false, 'text' => 'رمز الأمان مش صح.'];
}

// بنتأكد إن نظام الرقابة شغال والجدول موجود فعلاً
$auditSystemActive = false;
$auditTableExists = false;
$auditFunctionExists = false;

try {
 $chk = $pdo->query("SELECT COUNT(*) FROM audit_logs LIMIT 1");
 $auditTableExists = true;
} catch (PDOException $e) {
 $auditTableExists = false;
}

$auditFunctionExists = function_exists('auditLogAuto');
$auditSystemActive = $auditTableExists && $auditFunctionExists;

// إعدادات الصفحات والتقسيم (عشان منجيبش ألف سجل في صفحة واحدة)
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// الفلاتر اللي الأدمن اختارها عشان يدور على حاجة معينة
$fActor = trim($_GET['actor'] ?? '');
$fRole = trim($_GET['role'] ?? '');
$fAction = trim($_GET['action_type'] ?? '');
$fTarget = trim($_GET['target_type'] ?? '');
$fDateFrom = trim($_GET['date_from'] ?? '');
$fDateTo = trim($_GET['date_to'] ?? '');
$fSearch = trim($_GET['search'] ?? '');

// بنبني جملة الاستعلام (SQL) على حسب الفلاتر النشطة
$where = ['1=1'];
$params = [];

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

// بنحسب عدد السجلات الكلي عشان نعرف هنقسمهم على كام صفحة
$totalRows = 0;
$totalPages = 1;
if ($auditTableExists) {
 try {
 $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE $whereStr");
 $cntStmt->execute($params);
 $totalRows = (int)$cntStmt->fetchColumn();
 $totalPages = max(1, (int)ceil($totalRows / $perPage));
 } catch (PDOException $e) {}
}



// بنجيب السجلات الفعلية للصفحة اللي إحنا واقفين عليها دلوقتي
$logs = [];
$dbError = null;
if ($auditTableExists) {
 try {
 $stmt = $pdo->prepare("
 SELECT id, actor_id, actor_role, actor_name, action_type, target_type,
 target_id, old_values, new_values, description, ip_address, created_at
 FROM audit_logs WHERE $whereStr
 ORDER BY created_at DESC
 LIMIT $perPage OFFSET $offset
 ");
 $stmt->execute($params);
 $logs = $stmt->fetchAll();
 } catch (PDOException $e) {
 $dbError = $e->getMessage();
 }
}

// بنجيب القيم الفريدة عشان نحطها في قوائم الاختيار (Dropdowns)
$distinctRoles = [];
$distinctActions = [];
$distinctTargets = [];
if ($auditTableExists) {
 try {
 $distinctRoles = $pdo->query("SELECT DISTINCT actor_role FROM audit_logs WHERE actor_role IS NOT NULL ORDER BY actor_role")->fetchAll(PDO::FETCH_COLUMN);
 $distinctActions = $pdo->query("SELECT DISTINCT action_type FROM audit_logs WHERE action_type IS NOT NULL ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
 $distinctTargets = $pdo->query("SELECT DISTINCT target_type FROM audit_logs WHERE target_type IS NOT NULL ORDER BY target_type")->fetchAll(PDO::FETCH_COLUMN);
 } catch (PDOException $e) {}
}

// بنجهز الأرقام السريعة اللي بتظهر في المربعات اللي فوق (Dashboard Cards)
$stats = ['total' => 0, 'today' => 0, 'danger' => 0, 'logins' => 0];
if ($auditTableExists) {
 try {
 $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
 $stats['today'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURRENT_DATE")->fetchColumn();
 $stats['danger'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action_type IN ('DELETE','SYSTEM_CHANGE')")->fetchColumn();
 $stats['logins'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action_type = 'LOGIN'")->fetchColumn();
 } catch (PDOException $e) {}
}

// فنكشن بسيطة لترجمة أنواع العمليات من إنجليزي لعربي
function translateAction(string $action): string {
 $map = [
 'ADD' => 'إضافة',
 'UPDATE' => 'تعديل',
 'DELETE' => 'حذف',
 'ASSIGN' => 'تعيين',
 'SYSTEM_CHANGE' => 'تغيير النظام',
 'LOGIN' => 'تسجيل دخول',
 'LOGOUT' => 'تسجيل خروج',
 'EXPORT' => 'تصدير',
 'IMPORT' => 'استيراد',
 'BLOCK' => 'حظر',
 'UNBLOCK' => 'رفع الحظر',
 'APPROVE' => 'اعتماد',
 'REJECT' => 'رفض',
 'RESET' => 'إعادة ضبط',
 ];
 return $map[$action] ?? $action;
}

// ترجمة الرتب الوظيفية عشان تظهر بشكل مفهوم
function translateRole(string $role): string {
 $map = [
 'super_admin' => 'المشرف العام',
 'admin' => 'المدير',
 'dean' => 'العميد',
 'affairs' => 'شؤون الطلبة',
 'instructor' => 'عضو هيئة تدريس',
 'student' => 'طالب',
 'system' => 'النظام',
 ];
 return $map[$role] ?? $role;
}

// ترجمة أنواع الأهداف (مستخدم، مادة، نظام، إلخ)
function translateTarget(string $target): string {
 $map = [
 'USER' => 'مستخدم',
 'COURSE' => 'مقرر',
 'EXAM' => 'امتحان',
 'EXAM_SUBMISSION'=> 'إجابة امتحان',
 'ATTENDANCE' => 'حضور',
 'SYSTEM' => 'النظام',
 'GRADE' => 'درجة',
 'ENROLLMENT' => 'تسجيل',
 'SESSION' => 'جلسة',
 'SETTING' => 'إعداد',
 ];
 return $map[$target] ?? $target;
}

// تلوين العملية على حسب نوعها (أخضر للإضافة، أحمر للحذف، إلخ)
function actionBadge(string $action): string {
 $classMap = [
 'ADD' => 'badge-add',
 'UPDATE' => 'badge-update',
 'DELETE' => 'badge-delete',
 'ASSIGN' => 'badge-assign',
 'SYSTEM_CHANGE' => 'badge-system',
 'LOGIN' => 'badge-login',
 'LOGOUT' => 'badge-logout',
 'EXPORT' => 'badge-export',
 'IMPORT' => 'badge-export',
 'BLOCK' => 'badge-delete',
 'UNBLOCK' => 'badge-add',
 'APPROVE' => 'badge-add',
 'REJECT' => 'badge-delete',
 'RESET' => 'badge-update',
 ];
 $cls = $classMap[$action] ?? 'badge-default';
 $arabic = translateAction($action);
 return "<span class=\"action-badge $cls\" title=\"$action\">$arabic</span>";
}

// عرض بيانات الـ JSON بشكل جدول منظم عشان العين ترتاح وهي بتقرأه
function formatJsonDisplay(?string $json): string {
 if ($json === null || $json === '') {
 return '<span style="color:#94a3b8;font-style:italic;font-size:12px">لا توجد بيانات</span>';
 }
 $decoded = json_decode($json, true);
 if (!is_array($decoded)) {
 return '<code style="font-size:11px;word-break:break-all;color:#374151">' . htmlspecialchars($json) . '</code>';
 }
 $html = '<dl class="json-dl">';
 foreach ($decoded as $k => $v) {
 $val = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
 $html .= '<dt>' . htmlspecialchars((string)$k) . '</dt>';
 $html .= '<dd>' . htmlspecialchars((string)$val) . '</dd>';
 }
 $html .= '</dl>';
 return $html;
}

// بناء اللينكات مع الحفاظ على الفلاتر اللي اليوزر اختارها
function queryWith(array $override = []): string {
 $base = $_GET;
 unset($base['page'], $base['export']);
 $merged = array_merge($base, $override);
 return '?' . http_build_query(array_filter($merged, fn($v) => $v !== ''));
}

// بنشوف لو فيه أي فلتر شغال دلوقتي عشان نعرف اليوزر
$filtersActive = ($fActor || $fRole || $fAction || $fTarget || $fDateFrom || $fDateTo || $fSearch);
?>

<style>
/* ═══════════════════════════════════════════════════════════════
 سجل الرقابة الإدارية — أنماط CSS
 ═══════════════════════════════════════════════════════════════ */

/* ── متغيرات الألوان ── */
.audit-page { direction: rtl; text-align: right; }


/* ── بطاقات الإحصائيات ── */
.audit-stats-grid {
 display: grid;
 grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
 gap: 14px;
 margin-bottom: 22px;
}
.audit-stat-card {
 background: #fff;
 border-radius: 14px;
 padding: 18px 20px;
 display: flex;
 align-items: center;
 gap: 14px;
 box-shadow: 0 1px 4px rgba(0,0,0,.07);
 border: 1px solid #f1f5f9;
 transition: transform .18s, box-shadow .18s;
}
.audit-stat-card:hover {
 transform: translateY(-3px);
 box-shadow: 0 8px 20px rgba(0,0,0,.1);
}
.audit-stat-icon {
 width: 46px; height: 46px; border-radius: 12px;
 display: flex; align-items: center; justify-content: center;
 font-size: 19px; flex-shrink: 0;
}
.audit-stat-icon.blue { background: #eff6ff; color: #2563eb; }
.audit-stat-icon.green { background: #f0fdf4; color: #16a34a; }
.audit-stat-icon.red { background: #fef2f2; color: #dc2626; }
.audit-stat-icon.amber { background: #fffbeb; color: #d97706; }
.audit-stat-num { font-size: 24px; font-weight: 800; color: #1e293b; line-height: 1; }
.audit-stat-lbl { font-size: 12px; color: #64748b; margin-top: 3px; font-weight: 500; }

/* ── لوحة الفلاتر ── */
.audit-filter-panel {
 background: #fff;
 border: 1px solid #e2e8f0;
 border-radius: 14px;
 padding: 18px 22px;
 margin-bottom: 18px;
 box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.audit-filter-grid {
 display: grid;
 grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
 gap: 10px;
 align-items: end;
}
.audit-filter-grid .fg { display: flex; flex-direction: column; gap: 4px; }
.audit-filter-grid label {
 font-size: 11px; font-weight: 700; color: #64748b;
 text-transform: uppercase; letter-spacing: .05em;
}
.audit-filter-grid input,
.audit-filter-grid select {
 padding: 8px 10px;
 border: 1px solid #e2e8f0;
 border-radius: 8px;
 font-size: 13px;
 color: #1e293b;
 background: #f8fafc;
 outline: none;
 font-family: inherit;
 direction: rtl;
 transition: border-color .18s, box-shadow .18s;
}
.audit-filter-grid input:focus,
.audit-filter-grid select:focus {
 border-color: #2563eb;
 background: #fff;
 box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
.audit-btn {
 padding: 8px 16px;
 border-radius: 8px;
 font-size: 13px;
 font-weight: 600;
 cursor: pointer;
 border: none;
 display: inline-flex;
 align-items: center;
 gap: 6px;
 transition: all .15s;
 text-decoration: none;
 font-family: inherit;
 direction: rtl;
}
.audit-btn-primary { background: #2563eb; color: #fff; }
.audit-btn-primary:hover { background: #1d4ed8; color: #fff; }
.audit-btn-reset { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.audit-btn-reset:hover { background: #e2e8f0; }
.audit-btn-export { background: #047857; color: #fff; }
.audit-btn-export:hover { background: #065f46; color: #fff; }

/* ── الجدول الرئيسي ── */
.audit-card {
 background: #fff;
 border-radius: 14px;
 border: 1px solid #e2e8f0;
 overflow: hidden;
 box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.audit-table-wrap { overflow-x: auto; }
.audit-table {
 width: 100%;
 border-collapse: collapse;
 font-size: 13px;
 direction: rtl;
}
.audit-table thead tr {
 background: #f8fafc;
 border-bottom: 2px solid #e2e8f0;
}
.audit-table th {
 padding: 11px 14px;
 text-align: right;
 font-size: 11.5px;
 font-weight: 700;
 color: #64748b;
 text-transform: uppercase;
 letter-spacing: .04em;
 white-space: nowrap;
}
.audit-table td {
 padding: 11px 14px;
 border-bottom: 1px solid #f1f5f9;
 vertical-align: top;
 color: #334155;
}
.audit-table tr:last-child td { border-bottom: none; }
.audit-table tr:hover td { background: #fafbfd; }

/* صف العمليات الخطرة */
.row-danger > td:first-child { border-right: 3px solid #dc2626; }
.row-danger { background: #fffafa !important; }
.row-danger:hover td { background: #fff0f0 !important; }

/* ── شارات العمليات ── */
.action-badge {
 display: inline-block;
 padding: 3px 10px;
 border-radius: 20px;
 font-size: 11.5px;
 font-weight: 700;
 white-space: nowrap;
 letter-spacing: .02em;
}
.badge-add { background: #dcfce7; color: #166534; }
.badge-update { background: #dbeafe; color: #1e40af; }
.badge-delete { background: #fee2e2; color: #991b1b; }
.badge-assign { background: #ede9fe; color: #5b21b6; }
.badge-system { background: #fff1f0; color: #b91c1c; border: 1px solid #fca5a5; }
.badge-login { background: #f0fdf4; color: #15803d; }
.badge-logout { background: #fefce8; color: #854d0e; }
.badge-export { background: #f0f9ff; color: #0369a1; }
.badge-default { background: #f1f5f9; color: #475569; }

/* ── شريحة نوع الهدف ── */
.target-chip {
 display: inline-block;
 padding: 2px 9px;
 border-radius: 6px;
 font-size: 11px;
 font-weight: 600;
 background: #f1f5f9;
 color: #334155;
 border: 1px solid #e2e8f0;
}

/* ── صفوف التفاصيل القابلة للتوسيع ── */
.detail-row { display: none; }
.detail-row.open { display: table-row; }
.detail-cell {
 padding: 0 !important;
 background: #f8fafc !important;
 border-bottom: 1px solid #e2e8f0 !important;
}
.detail-inner {
 padding: 14px 18px;
 display: grid;
 grid-template-columns: 1fr 1fr;
 gap: 16px;
 direction: rtl;
}
.detail-section-label {
 font-size: 11px;
 font-weight: 700;
 text-transform: uppercase;
 letter-spacing: .06em;
 margin-bottom: 7px;
 display: flex;
 align-items: center;
 gap: 5px;
}
.detail-section-label.old { color: #dc2626; }
.detail-section-label.new { color: #16a34a; }
.detail-box {
 background: #fff;
 border: 1px solid #e2e8f0;
 border-radius: 8px;
 padding: 10px 12px;
 min-height: 44px;
 direction: rtl;
}

/* قائمة JSON */
.json-dl { margin: 0; }
.json-dl dt {
 font-size: 10px; font-weight: 700; color: #94a3b8;
 margin-top: 4px; font-family: monospace;
}
.json-dl dd {
 font-size: 12px; color: #1e293b;
 margin: 0 0 2px 0; word-break: break-all;
}

/* ── الترقيم ── */
.audit-pagination {
 display: flex;
 align-items: center;
 justify-content: space-between;
 padding: 12px 18px;
 border-top: 1px solid #f1f5f9;
 gap: 10px;
 flex-wrap: wrap;
 direction: rtl;
}
.audit-pagination .pg-info { font-size: 13px; color: #64748b; }
.pag-links { display: flex; gap: 4px; }
.pag-link {
 display: inline-flex; align-items: center; justify-content: center;
 min-width: 34px; height: 34px; padding: 0 8px;
 border-radius: 8px; font-size: 13px; font-weight: 500;
 color: #475569; text-decoration: none;
 background: #f8fafc; border: 1px solid #e2e8f0;
 transition: all .15s;
}
.pag-link:hover { background: #e2e8f0; }
.pag-link.active { background: #2563eb; color: #fff; border-color: #2563eb; }
.pag-link.disabled { opacity: .35; pointer-events: none; }

/* ── الحالة الفارغة ── */
.empty-audit {
 text-align: center;
 padding: 55px 20px;
 color: #94a3b8;
 direction: rtl;
}
.empty-audit i { font-size: 46px; margin-bottom: 14px; opacity: .35; }

/* ── رسالة خطأ قاعدة البيانات ── */
.audit-db-error {
 background: #fef2f2;
 border: 1px solid #fecaca;
 border-radius: 10px;
 padding: 14px 18px;
 color: #991b1b;
 font-size: 13px;
 margin-bottom: 18px;
 display: flex;
 gap: 10px;
 align-items: flex-start;
 direction: rtl;
}

/* ── دعم الوضع الداكن ── */
[data-theme="dark"] .audit-card,
[data-theme="dark"] .audit-filter-panel,
[data-theme="dark"] .audit-stat-card { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .audit-table th { color: #94a3b8; }
[data-theme="dark"] .audit-table thead tr { background: #0f172a; border-color: #334155; }
[data-theme="dark"] .audit-table td { color: #e2e8f0; border-color: #1e293b; }
[data-theme="dark"] .audit-table tr:hover td { background: #253148 !important; }
[data-theme="dark"] .detail-cell { background: #0f172a !important; border-color: #334155 !important; }
[data-theme="dark"] .detail-box { background: #1e293b; border-color: #334155; }
[data-theme="dark"] .json-dl dt { color: #64748b; }
[data-theme="dark"] .json-dl dd { color: #e2e8f0; }
[data-theme="dark"] .audit-filter-grid input,
[data-theme="dark"] .audit-filter-grid select { background: #0f172a; border-color: #334155; color: #e2e8f0; }
[data-theme="dark"] .audit-stat-num { color: #f1f5f9; }
[data-theme="dark"] .audit-stat-lbl { color: #94a3b8; }
[data-theme="dark"] .pag-link { background: #0f172a; border-color: #334155; color: #94a3b8; }
[data-theme="dark"] .pag-link:hover { background: #1e293b; }
[data-theme="dark"] .target-chip { background: #0f172a; border-color: #334155; color: #94a3b8; }
[data-theme="dark"] .row-danger { background: #2a1315 !important; }
[data-theme="dark"] .row-danger:hover td { background: #381515 !important; }
</style>

<?php /* ═══════════ HTML STARTS ════════════ */ ?>
<div class="audit-page" style="width:100%">

 <!-- ── شريط العنوان الرئيسي ────────────────────────────────── -->
 <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
 <div>
 <h1 style="font-size:21px;font-weight:800;color:#1e293b;margin:0;display:flex;align-items:center;gap:10px;direction:rtl">
 <span style="width:38px;height:38px;background:linear-gradient(135deg,#92400e,#d97706);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
 <i class="fas fa-history" style="color:#fff;font-size:16px"></i>
 </span>
 سجل الرقابة الإدارية
 </h1>
 <p style="color:#64748b;font-size:13px;margin:6px 0 0 48px;direction:rtl">
 جميع العمليات الحساسة مُسجَّلة تلقائياً وغير قابلة للتعديل · نظام التدقيق المركزي
 </p>
 </div>
 <a href="export_audit.php<?= $filterQueryString ?? '' ?>" class="audit-btn audit-btn-export" <?= !$auditTableExists ? 'style="opacity:.5;pointer-events:none"' : '' ?>>
 <i class="fas fa-file-csv"></i> تصدير CSV
 </a>

 <?php if ($role === 'super_admin' && $auditTableExists): ?>
 <!-- زر مسح البيانات التجريبية — للمشرف العام فقط -->
 <button type="button"
 onclick="document.getElementById('audit-truncate-modal').style.display='flex'"
 class="audit-btn"
 style="background:#7f1d1d;color:#fff;opacity:.85"
 title="مسح جميع سجلات التجربة وإعادة العداد إلى 1">
 <i class="fas fa-trash-alt"></i> مسح سجلات التجربة
 </button>
 <?php endif; ?>
 </div>

 <!-- ── إشعار نتيجة المسح ──────────────────────────────────── -->
 <?php if ($truncateMsg): ?>
 <div id="truncate-alert"
 style="display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:600;direction:rtl;
 background:<?= $truncateMsg['ok'] ? '#f0fdf4' : '#fef2f2' ?>;
 border:1px solid <?= $truncateMsg['ok'] ? '#86efac' : '#fca5a5' ?>;
 color:<?= $truncateMsg['ok'] ? '#15803d' : '#b91c1c' ?>">
 <i class="fas <?= $truncateMsg['ok'] ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
 <span style="flex:1"><?= $truncateMsg['text'] ?></span>
 <button onclick="this.parentElement.style.display='none'" style="background:none;border:none;cursor:pointer;color:inherit;font-size:16px;line-height:1">&times;</button>
 </div>
 <?php endif; ?>


 <?php if ($dbError): ?>
 <div class="audit-db-error">
 <i class="fas fa-exclamation-triangle" style="font-size:17px;margin-top:2px;flex-shrink:0"></i>
 <div>
 <strong>خطأ في قاعدة البيانات:</strong> <?= htmlspecialchars($dbError) ?>
 <?php if (strpos($dbError, 'audit_logs') !== false): ?>
 <br><a href="../setup.php" style="color:#2563eb;font-weight:700;text-decoration:underline">تشغيل setup.php لإنشاء الجداول</a>
 <?php endif; ?>
 </div>
 </div>
 <?php endif; ?>

 <!-- ── بطاقات الإحصائيات ──────────────────────────────────── -->
 <div class="audit-stats-grid">
 <div class="audit-stat-card">
 <div class="audit-stat-icon blue"><i class="fas fa-list-alt"></i></div>
 <div>
 <div class="audit-stat-num"><?= number_format($stats['total']) ?></div>
 <div class="audit-stat-lbl">إجمالي الأحداث</div>
 </div>
 </div>
 <div class="audit-stat-card">
 <div class="audit-stat-icon green"><i class="fas fa-calendar-day"></i></div>
 <div>
 <div class="audit-stat-num"><?= number_format($stats['today']) ?></div>
 <div class="audit-stat-lbl">أحداث اليوم</div>
 </div>
 </div>
 <div class="audit-stat-card">
 <div class="audit-stat-icon red"><i class="fas fa-shield-exclamation"></i></div>
 <div>
 <div class="audit-stat-num"><?= number_format($stats['danger']) ?></div>
 <div class="audit-stat-lbl">عمليات خطرة</div>
 </div>
 </div>
 <div class="audit-stat-card">
 <div class="audit-stat-icon amber"><i class="fas fa-sign-in-alt"></i></div>
 <div>
 <div class="audit-stat-num"><?= number_format($stats['logins']) ?></div>
 <div class="audit-stat-lbl">عمليات تسجيل دخول</div>
 </div>
 </div>
 </div>

 <!-- ── لوحة الفلاتر ───────────────────────────────────────── -->
 <div class="audit-filter-panel">
 <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;direction:rtl">
 <span style="font-size:13px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:7px">
 <i class="fas fa-filter" style="color:#2563eb"></i> تصفية وبحث
 </span>
 <?php if ($filtersActive): ?>
 <span style="font-size:11px;background:#fef9c3;color:#713f12;padding:2px 10px;border-radius:20px;font-weight:700;border:1px solid #fde68a">
 <i class="fas fa-check-circle"></i> فلاتر نشطة
 </span>
 <?php endif; ?>
 </div>
 <form method="get" action="">
 <div class="audit-filter-grid">
 <div class="fg">
 <label>اسم المسؤول أو رقمه</label>
 <input type="text" name="actor" value="<?= htmlspecialchars($fActor) ?>" placeholder="بحث...">
 </div>
 <div class="fg">
 <label>الدور الوظيفي</label>
 <select name="role">
 <option value="">جميع الأدوار</option>
 <?php foreach ($distinctRoles as $r): ?>
 <option value="<?= htmlspecialchars($r) ?>" <?= $fRole === $r ? 'selected' : '' ?>>
 <?= htmlspecialchars(translateRole($r)) ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="fg">
 <label>نوع العملية</label>
 <select name="action_type">
 <option value="">جميع العمليات</option>
 <?php foreach ($distinctActions as $a): ?>
 <option value="<?= htmlspecialchars($a) ?>" <?= strtoupper($fAction) === $a ? 'selected' : '' ?>>
 <?= htmlspecialchars(translateAction($a)) ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="fg">
 <label>نوع الهدف</label>
 <select name="target_type">
 <option value="">جميع الأهداف</option>
 <?php foreach ($distinctTargets as $t): ?>
 <option value="<?= htmlspecialchars($t) ?>" <?= strtoupper($fTarget) === $t ? 'selected' : '' ?>>
 <?= htmlspecialchars(translateTarget($t)) ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="fg">
 <label>من تاريخ</label>
 <input type="date" name="date_from" value="<?= htmlspecialchars($fDateFrom) ?>">
 </div>
 <div class="fg">
 <label>إلى تاريخ</label>
 <input type="date" name="date_to" value="<?= htmlspecialchars($fDateTo) ?>">
 </div>
 <div class="fg" style="grid-column: span 2">
 <label>بحث نصي في الوصف</label>
 <input type="text" name="search" value="<?= htmlspecialchars($fSearch) ?>" placeholder="ابحث في الوصف، القيم القديمة، الجديدة...">
 </div>
 <div class="fg" style="display:flex;flex-direction:row;gap:8px;align-items:flex-end">
 <button type="submit" class="audit-btn audit-btn-primary" style="width:100%">
 <i class="fas fa-search"></i> بحث
 </button>
 <a href="audit_logs.php" class="audit-btn audit-btn-reset" style="white-space:nowrap">
 <i class="fas fa-times"></i> مسح
 </a>
 </div>
 </div>
 </form>
 </div>

 <!-- ── جدول السجلات ───────────────────────────────────────── -->
 <div class="audit-card">
 <!-- شريط معلومات النتائج -->
 <div style="padding:12px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;direction:rtl">
 <span style="font-size:13px;color:#64748b">
 عرض <strong style="color:#1e293b"><?= number_format(count($logs)) ?></strong>
 من أصل <strong style="color:#1e293b"><?= number_format($totalRows) ?></strong> سجل
 <?php if ($totalPages > 1): ?> — صفحة <?= $page ?> / <?= $totalPages ?><?php endif; ?>
 </span>
 <span style="font-size:11px;color:#64748b;padding:3px 10px;background:#f8fafc;border-radius:20px;border:1px solid #e2e8f0">
 <i class="fas fa-mouse-pointer"></i> انقر على أي صف لعرض تفاصيل التغييرات
 </span>
 </div>

 <div class="audit-table-wrap">
 <table class="audit-table">
 <thead>
 <tr>
 <th style="width:48px">م</th>
 <th>المسؤول</th>
 <th>الدور</th>
 <th>نوع العملية</th>
 <th>الهدف</th>
 <th>التفاصيل</th>
 <th>عنوان IP</th>
 <th>الوقت</th>
 </tr>
 </thead>
 <tbody>
 <?php if (!$auditTableExists): ?>
 <tr><td colspan="8">
 <div class="empty-audit">
 <i class="fas fa-database"></i>
 <div style="font-size:15px;font-weight:700;color:#374151;margin-bottom:6px">جدول التدقيق غير موجود</div>
 <a href="../setup.php" style="color:#2563eb;font-weight:600;text-decoration:underline">تشغيل الإعداد لإنشاء الجداول</a>
 </div>
 </td></tr>

 <?php elseif (empty($logs) && !$dbError): ?>
 <tr><td colspan="8">
 <div class="empty-audit">
 <i class="fas fa-clipboard-list"></i>
 <div style="font-size:15px;font-weight:700;color:#374151;margin-bottom:6px">لا توجد سجلات</div>
 <div style="font-size:13px">
 <?= $filtersActive
 ? 'لا توجد نتائج تطابق الفلاتر المحددة. <a href="audit_logs.php" style="color:#2563eb">مسح الفلاتر</a>'
 : 'لم يتم تسجيل أي أحداث حتى الآن. <a href="../setup.php" style="color:#2563eb">إنشاء بيانات تجريبية</a>'
 ?>
 </div>
 </div>
 </td></tr>

 <?php else: ?>
 <?php foreach ($logs as $log):
 $isDanger = in_array($log['action_type'], ['DELETE', 'SYSTEM_CHANGE', 'BLOCK', 'REJECT']);
 $hasDetail = ($log['old_values'] || $log['new_values']);
 $detailId = 'detail-' . $log['id'];
 $ts = $log['created_at'];
 ?>
 <tr class="<?= $isDanger ? 'row-danger' : '' ?>"
 <?php if ($hasDetail): ?>
 onclick="auditToggleDetail('<?= $detailId ?>', this)"
 style="cursor:pointer"
 <?php endif; ?>>
 <td style="color:#94a3b8;font-size:12px;text-align:center"><?= (int)$log['id'] ?></td>
 <td>
 <div style="font-weight:600;color:#1e293b"><?= htmlspecialchars($log['actor_name'] ?? '—') ?></div>
 <?php if ($log['actor_id']): ?>
 <div style="font-size:11px;color:#94a3b8">ID: <?= (int)$log['actor_id'] ?></div>
 <?php endif; ?>
 </td>
 <td>
 <span style="font-size:12px;font-weight:600;color:#475569">
 <?= htmlspecialchars(translateRole($log['actor_role'] ?? '')) ?>
 </span>
 </td>
 <td><?= actionBadge($log['action_type']) ?></td>
 <td>
 <span class="target-chip"><?= htmlspecialchars(translateTarget($log['target_type'] ?? '')) ?></span>
 <?php if ($log['target_id']): ?>
 <div style="font-size:11px;color:#94a3b8;margin-top:2px">#<?= (int)$log['target_id'] ?></div>
 <?php endif; ?>
 </td>
 <td style="max-width:300px;line-height:1.5">
 <?= htmlspecialchars($log['description'] ?? '') ?>
 <?php if ($hasDetail): ?>
 <div style="margin-top:4px">
 <span style="font-size:10px;color:#2563eb;font-weight:600">
 <i class="fas fa-chevron-down" id="icon-<?= $log['id'] ?>" style="transition:transform .2s"></i>
 عرض التغييرات
 </span>
 </div>
 <?php endif; ?>
 </td>
 <td style="font-family:monospace;font-size:11.5px;color:#64748b;white-space:nowrap">
 <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
 </td>
 <td style="white-space:nowrap;font-size:12px;color:#64748b">
 <div><?= date('Y/m/d', strtotime($ts)) ?></div>
 <div style="color:#94a3b8;font-size:11px"><?= date('H:i:s', strtotime($ts)) ?></div>
 </td>
 </tr>

 <?php if ($hasDetail): ?>
 <tr class="detail-row" id="<?= $detailId ?>">
 <td class="detail-cell" colspan="8">
 <div class="detail-inner">
 <div>
 <div class="detail-section-label old">
 <i class="fas fa-times-circle"></i> القيم قبل التغيير
 </div>
 <div class="detail-box">
 <?= formatJsonDisplay($log['old_values']) ?>
 </div>
 </div>
 <div>
 <div class="detail-section-label new">
 <i class="fas fa-check-circle"></i> القيم بعد التغيير
 </div>
 <div class="detail-box">
 <?= formatJsonDisplay($log['new_values']) ?>
 </div>
 </div>
 </div>
 </td>
 </tr>
 <?php endif; ?>

 <?php endforeach; ?>
 <?php endif; ?>
 </tbody>
 </table>
 </div>

 <!-- ── الترقيم ─────────────────────────────────────────── -->
 <?php if ($totalPages > 1): ?>
 <div class="audit-pagination">
 <span class="pg-info">
 السجلات <?= number_format(($page - 1) * $perPage + 1) ?> – <?= number_format(min($page * $perPage, $totalRows)) ?>
 من <?= number_format($totalRows) ?>
 </span>
 <div class="pag-links">
 <!-- التالي (RTL في اليسار) -->
 <a href="<?= queryWith(['page' => $page + 1]) ?>" class="pag-link <?= $page >= $totalPages ? 'disabled' : '' ?>">
 <i class="fas fa-chevron-right"></i>
 </a>

 <?php
 $start = max(1, $page - 2);
 $end = min($totalPages, $page + 2);
 if ($start > 1) {
 echo '<a href="' . queryWith(['page' => 1]) . '" class="pag-link">1</a>';
 if ($start > 2) echo '<span class="pag-link disabled">…</span>';
 }
 for ($i = $start; $i <= $end; $i++): ?>
 <a href="<?= queryWith(['page' => $i]) ?>" class="pag-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
 <?php endfor; ?>
 <?php
 if ($end < $totalPages) {
 if ($end < $totalPages - 1) echo '<span class="pag-link disabled">…</span>';
 echo '<a href="' . queryWith(['page' => $totalPages]) . '" class="pag-link">' . $totalPages . '</a>';
 }
 ?>

 <!-- السابق -->
 <a href="<?= queryWith(['page' => $page - 1]) ?>" class="pag-link <?= $page <= 1 ? 'disabled' : '' ?>">
 <i class="fas fa-chevron-left"></i>
 </a>
 </div>
 </div>
 <?php endif; ?>

 </div><!-- /.audit-card -->

 <?php if ($role === 'super_admin'): ?>
 <!-- ══ مودال تأكيد مسح سجلات التجربة (Super Admin فقط) ══ -->
 <div id="audit-truncate-modal"
 style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;
 align-items:center;justify-content:center;direction:rtl">
 <div style="background:#fff;border-radius:16px;padding:32px 30px;max-width:440px;width:94%;box-shadow:0 20px 60px rgba(0,0,0,.25);position:relative">
 <!-- أيقونة التحذير -->
 <div style="text-align:center;margin-bottom:18px">
 <span style="display:inline-flex;align-items:center;justify-content:center;
 width:60px;height:60px;border-radius:50%;
 background:#fef2f2;border:2px solid #fca5a5">
 <i class="fas fa-exclamation-triangle" style="color:#dc2626;font-size:26px"></i>
 </span>
 </div>
 <h3 style="text-align:center;font-size:17px;font-weight:800;color:#1e293b;margin:0 0 10px">
 تأكيد مسح سجلات التجربة
 </h3>
 <p style="text-align:center;font-size:13px;color:#64748b;margin:0 0 22px;line-height:1.7">
 سيتم تنفيذ:
 <code style="display:block;margin:8px 0;padding:8px 12px;background:#f8fafc;
 border:1px solid #e2e8f0;border-radius:6px;font-size:12px;color:#7f1d1d">
 TRUNCATE TABLE audit_logs RESTART IDENTITY
 </code>
 <strong style="color:#dc2626">سيُحذف كل السجل نهائياً ولا يمكن التراجع.</strong>
 <br>تأكد أنك تريد حذف البيانات التجريبية فقط.
 </p>
 <form method="GET" action="clear_audit.php" style="display:flex;gap:10px;justify-content:center">
 <input type="hidden" name="token" value="<?= htmlspecialchars(md5(session_id() . '_audit_clear')) ?>">
 <button type="submit"
 style="padding:10px 22px;background:#dc2626;color:#fff;border:none;
 border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;
 font-family:inherit;transition:background .15s"
 onmouseover="this.style.background='#b91c1c'"
 onmouseout="this.style.background='#dc2626'">
 <i class="fas fa-trash-alt"></i> نعم، امسح الآن
 </button>
 <button type="button"
 onclick="document.getElementById('audit-truncate-modal').style.display='none'"
 style="padding:10px 22px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;
 border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;
 font-family:inherit">
 إلغاء
 </button>
 </form>
 </div>
 </div>
 <?php endif; ?>

</div><!-- /.audit-page -->

<script>
/**
 * فتح/إغلاق صفوف التفاصيل (القيم القديمة والجديدة)
 */
function auditToggleDetail(detailId, rowEl) {
 const detail = document.getElementById(detailId);
 if (!detail) return;
 const isOpen = detail.classList.contains('open');
 detail.classList.toggle('open', !isOpen);
 // تدوير أيقونة السهم
 const logId = detailId.replace('detail-', '');
 const icon = document.getElementById('icon-' + logId);
 if (icon) icon.style.transform = isOpen ? '' : 'rotate(180deg)';
}

// إغلاق مودال المسح عند الضغط خارجه
const _truncateModal = document.getElementById('audit-truncate-modal');
if (_truncateModal) {
 _truncateModal.addEventListener('click', function(e) {
 if (e.target === _truncateModal) {
 _truncateModal.style.display = 'none';
 }
 });
 // إغلاق بـ Escape
 document.addEventListener('keydown', function(e) {
 if (e.key === 'Escape') _truncateModal.style.display = 'none';
 });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
