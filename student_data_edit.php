<?php
require_once 'includes/header.php';
require_once __DIR__ . '/models/StudentDetail.php';
$studentDetailModel = new StudentDetail();

// التأكد إن اللي داخل دا معاه صلاحية تعديل بيانات الطلبة
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('students');

$message = '';
$active_tab = $_GET['tab'] ?? 'bulk_gpa';

// بنجيب كل الطلبة من الداتا بيز على حسب صلاحية اللي داخل
// الأدمن بيشوف الكل، والعميد بيشوف كليته بس
$college_scope = $_SESSION['college_id'] ?? null;

$stuSql = 'SELECT u.id, u.username, u.full_name, u.college_id,
 c.name AS _college_name
 FROM users u
 LEFT JOIN colleges c ON u.college_id = c.id
 WHERE u.role = \'student\'';
$stuParams = [];
if (in_array($role, ['dean', 'affairs']) && $college_scope) {
 $stuSql .= ' AND u.college_id = :cid';
 $stuParams[':cid'] = $college_scope;
}
$stuSql .= ' ORDER BY c.name, u.full_name';
$stuStmt = $pdo->prepare($stuSql);
$stuStmt->execute($stuParams);
$all_students = $stuStmt->fetchAll();

// بنتعامل مع طلبات الحفظ والتعديل اللي جاية من الفورمات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $action = $_POST['action'] ?? '';

 // Refresh helper — re-runs the SQL student query
 $refreshStudents = function() use ($pdo, $role, $college_scope) {
 $sql = "SELECT u.id, u.username, u.full_name, u.college_id, c.name AS _college_name
 FROM users u LEFT JOIN colleges c ON u.college_id = c.id WHERE u.role = 'student'";
 $params = [];
 if (in_array($role, ['dean', 'affairs']) && $college_scope) { $sql .= ' AND u.college_id = :cid'; $params[':cid'] = $college_scope; }
 $sql .= ' ORDER BY c.name, u.full_name';
 $st = $pdo->prepare($sql); $st->execute($params);
 return $st->fetchAll();
 };

 // --- 1. تعديل المعدل التراكمي لكل الطلبة مرة واحدة ---
 if ($action === 'bulk_update_gpa') {
 $increment = (float)($_POST['gpa_increment'] ?? 0);
 $max_gpa = (float)($_POST['max_gpa'] ?? 4.0);
 $updated = 0;
 foreach ($all_students as $student) {
 $sid = $student['id'];
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($details) {
 $new_gpa = min($max_gpa, round((float)($details['gpa'] ?? 0) + $increment, 2));
 $studentDetailModel->update( $details['id'], ['gpa' => $new_gpa]);
 } else {
 $studentDetailModel->insert( ['user_id' => $sid, 'gpa' => max(0, min($max_gpa, $increment))]);
 }
 $updated++;
 }
 $message = "<div class='msg-success'>✅ تم تحديث المعدل التراكمي لـ {$updated} طالب بنجاح.</div>";
 $all_students = $refreshStudents();
 }

 // --- 2. تعديل المستويات لكل الطلبة مرة واحدة ---
 elseif ($action === 'bulk_update_levels') {
 $level_action = $_POST['level_action'] ?? 'increment';
 $updated = 0;
 foreach ($all_students as $student) {
 $sid = $student['id'];
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 $current = (int)($details['level'] ?? 1);
 if ($level_action === 'increment') { $new_level = min(4, $current + 1); }
 elseif ($level_action === 'decrement') { $new_level = max(1, $current - 1); }
 else { $new_level = max(1, min(4, (int)($_POST['set_level'] ?? $current))); }
 if ($details) { $studentDetailModel->update( $details['id'], ['level' => $new_level]); }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'level' => $new_level]); }
 $updated++;
 }
 $message = "<div class='msg-success'>✅ تم تحديث المستوى الدراسي لـ {$updated} طالب بنجاح.</div>";
 $all_students = $refreshStudents();
 }

 // --- 3. تعديل النسب المئوية لكل الطلبة مرة واحدة ---
 elseif ($action === 'bulk_update_percentages') {
 $base_percentage = (float)($_POST['base_percentage'] ?? 0);
 $updated = 0;
 foreach ($all_students as $student) {
 $sid = $student['id'];
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($base_percentage > 0) { $pct = min(100, max(0, $base_percentage)); }
 else { $gpa = (float)($details['gpa'] ?? 0); $pct = round($gpa / 4.0 * 100, 2); }
 if ($details) { $studentDetailModel->update( $details['id'], ['percentage' => $pct]); }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'percentage' => $pct]); }
 $updated++;
 }
 $message = "<div class='msg-success'>✅ تم تحديث النسبة المئوية لـ {$updated} طالب بنجاح.</div>";
 $all_students = $refreshStudents();
 }

 // --- 4. تعديل معدل طالب واحد بس ---
 elseif ($action === 'single_update_gpa') {
 $sid = (int)($_POST['student_id'] ?? 0);
 $new_gpa = min(4.0, max(0, (float)($_POST['new_gpa'] ?? 0)));
 if ($sid > 0) {
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($details) { $studentDetailModel->update( $details['id'], ['gpa' => $new_gpa]); $message = "<div class='msg-success'>✅ تم تحديث المعدل التراكمي للطالب بنجاح.</div>"; }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'gpa' => $new_gpa]); $message = "<div class='msg-success'>✅ تم إنشاء وتحديث المعدل التراكمي للطالب بنجاح.</div>"; }
 } else { $message = "<div class='msg-error'>❌ يرجى اختيار طالب صحيح.</div>"; }
 }

 // --- 5. تعديل مستوى طالب واحد بس ---
 elseif ($action === 'single_update_level') {
 $sid = (int)($_POST['student_id'] ?? 0);
 $new_level = max(1, min(4, (int)($_POST['new_level'] ?? 1)));
 if ($sid > 0) {
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($details) { $studentDetailModel->update( $details['id'], ['level' => $new_level]); }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'level' => $new_level]); }
 $message = "<div class='msg-success'>✅ تم تحديث المستوى الدراسي للطالب بنجاح.</div>";
 } else { $message = "<div class='msg-error'>❌ يرجى اختيار طالب صحيح.</div>"; }
 }

 // --- 6. تعديل نسبة طالب واحد بس ---
 elseif ($action === 'single_update_percentage') {
 $sid = (int)($_POST['student_id'] ?? 0);
 $pct = min(100, max(0, (float)($_POST['new_percentage'] ?? 0)));
 if ($sid > 0) {
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($details) { $studentDetailModel->update( $details['id'], ['percentage' => $pct]); }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'percentage' => $pct]); }
 $message = "<div class='msg-success'>✅ تم تحديث النسبة المئوية للطالب بنجاح.</div>";
 } else { $message = "<div class='msg-error'>❌ يرجى اختيار طالب صحيح.</div>"; }
 }

 // --- 7. إضافة سنة التخرج وتغيير حالة الطلبة لـ "متخرج" ---
 elseif ($action === 'add_graduation_year') {
 $student_ids = $_POST['student_ids'] ?? [];
 $grad_year = (int)($_POST['graduation_year'] ?? date('Y'));
 $updated = 0;
 if (!is_array($student_ids)) $student_ids = [];
 foreach ($student_ids as $sid) {
 $sid = (int)$sid;
 if ($sid <= 0) continue;
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($details) { $studentDetailModel->update( $details['id'], ['graduation_year' => $grad_year, 'enrollment_status' => 'graduated']); }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'graduation_year' => $grad_year, 'enrollment_status' => 'graduated']); }
 $updated++;
 }
 $message = "<div class='msg-success'>✅ تم إضافة عام التخرج {$grad_year} لـ {$updated} طالب بنجاح.</div>";
 $all_students = $refreshStudents();
 }

 // --- 8. تعديل المعدل السنوي لطالب واحد بس ---
 elseif ($action === 'single_update_annual_gpa') {
 $sid = (int)($_POST['student_id'] ?? 0);
 $annual_gpa = min(4.0, max(0, (float)($_POST['annual_gpa'] ?? 0)));
 if ($sid > 0) {
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($details) { $studentDetailModel->update( $details['id'], ['annual_gpa' => $annual_gpa]); }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'annual_gpa' => $annual_gpa]); }
 $message = "<div class='msg-success'>✅ تم تحديث المعدل السنوي للطالب بنجاح.</div>";
 } else { $message = "<div class='msg-error'>❌ يرجى اختيار طالب صحيح.</div>"; }
 }

 // --- 9. تعديل المعدل السنوي لكل الطلبة مرة واحدة ---
 elseif ($action === 'bulk_update_annual_gpa') {
 $method = $_POST['annual_gpa_method'] ?? 'from_gpa';
 $updated = 0;
 foreach ($all_students as $student) {
 $sid = $student['id'];
 $details = $studentDetailModel->findOneBy('user_id', $sid);
 if ($method === 'from_gpa') { $annual = (float)($details['gpa'] ?? 0); }
 else { $annual = min(4.0, max(0, (float)($_POST['fixed_annual_gpa'] ?? 0))); }
 if ($details) { $studentDetailModel->update( $details['id'], ['annual_gpa' => $annual]); }
 else { $studentDetailModel->insert( ['user_id' => $sid, 'annual_gpa' => $annual]); }
 $updated++;
 }
 $message = "<div class='msg-success'>✅ تم تحديث المعدل السنوي لـ {$updated} طالب بنجاح.</div>";
 $all_students = $refreshStudents();
 }

 // Refresh after any POST
 $all_students = $refreshStudents();
}

$total_students = count($all_students);
?>

<style>
.msg-success {
 background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;
 padding: 14px 18px; border-radius: 10px; font-weight: bold; margin-bottom: 20px;
}
.msg-error {
 background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;
 padding: 14px 18px; border-radius: 10px; font-weight: bold; margin-bottom: 20px;
}
.tab-btn {
 padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.82rem;
 cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569;
 transition: all 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 6px;
}
.tab-btn:hover { background: #f1f5f9; color: #2563eb; border-color: #2563eb; }
.tab-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
.section-card {
 background: white; border-radius: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
 border: 1px solid #f1f5f9; padding: 16px;
}
@media(min-width: 768px) {
 .section-card { padding: 24px; }
}
.form-label { font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
.form-input {
 width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;
 font-size: 0.9rem; font-family: 'Cairo', sans-serif; color: #1f2937;
 transition: border-color 0.2s;
}
.form-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-select {
 width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;
 font-size: 0.9rem; font-family: 'Cairo', sans-serif; color: #1f2937; background: white;
 transition: border-color 0.2s;
}
.form-select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.btn-primary {
 background: #2563eb; color: white; padding: 8px 20px; border-radius: 8px;
 font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; font-family: 'Cairo', sans-serif;
 transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px;
}
.btn-primary:hover { background: #1e40af; }
.btn-danger {
 background: #dc2626; color: white; padding: 8px 20px; border-radius: 8px;
 font-weight: 700; font-size: 0.85rem; border: none; cursor: pointer; font-family: 'Cairo', sans-serif;
 transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px;
}
.btn-danger:hover { background: #b91c1c; }
.warning-badge {
 background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
 padding: 8px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 600;
 display: flex; align-items: center; gap: 8px; margin-bottom: 16px;
}
.tab-content { display: none; }
.tab-content.active { display: block; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media(max-width:640px){ .grid-2 { grid-template-columns: 1fr; } }
.info-pill {
 display: inline-flex; align-items: center; gap: 6px;
 background: #eff6ff; color: #1d4ed8; border-radius: 999px;
 padding: 4px 12px; font-size: 0.8rem; font-weight: 600;
}
.section-title {
 font-size: 1.05rem; font-weight: 700; color: #1e293b;
 display: flex; align-items: center; gap: 10px; margin-bottom: 16px;
 padding-bottom: 12px; border-bottom: 2px solid #f1f5f9;
}
</style>

<div style="max-width:1200px; margin:0 auto;">

 <!-- هيدر الصفحة -->
 <div class="section-card" style="margin-bottom:24px;">
 <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
 <div style="display:flex; align-items:center; gap:16px;">
 <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#2563eb,#3b82f6);display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-user-edit" style="color:white;font-size:1.4rem;"></i>
 </div>
 <div>
 <h2 style="font-size:1.4rem;font-weight:800;color:#0f172a;margin:0;">تعديل البيانات الدراسية للطلاب</h2>
 <p style="color:#64748b;font-size:0.85rem;margin:4px 0 0;">إدارة المعدلات والمستويات والنسب لجميع الطلاب أو طالب محدد</p>
 </div>
 </div>
 <span class="info-pill">
 <i class="fas fa-users"></i>
 <?php echo $total_students; ?> طالب مسجل
 </span>
 </div>
 </div>

 <!-- رسائل النجاح أو الفشل -->
 <?php if ($message): ?>
 <div style="margin-bottom:16px;"><?php echo $message; ?></div>
 <?php endif; ?>

 <!-- تابات أنواع التعديلات -->
 <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;overflow-x:auto;padding-bottom:4px;">
 <a href="?tab=bulk_gpa" class="tab-btn <?php echo $active_tab==='bulk_gpa'?'active':''; ?>">
 <i class="fas fa-star-half-alt"></i> تعديل المعدل التراكمي للجميع **
 </a>
 <a href="?tab=bulk_levels" class="tab-btn <?php echo $active_tab==='bulk_levels'?'active':''; ?>">
 <i class="fas fa-layer-group"></i> تعديل مستويات الطلاب **
 </a>
 <a href="?tab=bulk_percentages" class="tab-btn <?php echo $active_tab==='bulk_percentages'?'active':''; ?>">
 <i class="fas fa-percent"></i> تعديل نسب الطلاب **
 </a>
 <a href="?tab=single_gpa" class="tab-btn <?php echo $active_tab==='single_gpa'?'active':''; ?>">
 <i class="fas fa-user-check"></i> تعديل معدل طالب
 </a>
 <a href="?tab=single_level" class="tab-btn <?php echo $active_tab==='single_level'?'active':''; ?>">
 <i class="fas fa-sort-numeric-up"></i> تعديل مستوى طالب
 </a>
 <a href="?tab=single_percentage" class="tab-btn <?php echo $active_tab==='single_percentage'?'active':''; ?>">
 <i class="fas fa-chart-pie"></i> تعديل نسبة طالب
 </a>
 <a href="?tab=graduation" class="tab-btn <?php echo $active_tab==='graduation'?'active':''; ?>">
 <i class="fas fa-graduation-cap"></i> إضافة عام تخرج
 </a>
 <a href="?tab=single_annual" class="tab-btn <?php echo $active_tab==='single_annual'?'active':''; ?>">
 <i class="fas fa-calendar-check"></i> معدل سنوي لطالب
 </a>
 <a href="?tab=bulk_annual" class="tab-btn <?php echo $active_tab==='bulk_annual'?'active':''; ?>">
 <i class="fas fa-calendar-alt"></i> تعديل المعدل السنوي للجميع **
 </a>
 </div>

 <!-- ══════════════════════════════ TAB 1: Bulk GPA ══════════════════════════════ -->
 <?php if ($active_tab === 'bulk_gpa'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-star-half-alt" style="color:#d97706;"></i>
 </div>
 تعديل المعدل التراكمي لجميع الطلاب **
 </div>
 <div class="warning-badge">
 <i class="fas fa-exclamation-triangle"></i>
 هذا الإجراء يؤثر على <?php echo $total_students; ?> طالب دفعة واحدة. تأكد من صحة القيم قبل الحفظ.
 </div>
 <form method="POST" onsubmit="return confirm('هل أنت متأكد من تعديل المعدل التراكمي لجميع الطلاب؟');">
 <input type="hidden" name="action" value="bulk_update_gpa">
 <div class="grid-2">
 <div>
 <label class="form-label">مقدار التعديل على المعدل <span style="color:#64748b;">(موجب = زيادة / سالب = تقليل)</span></label>
 <input type="number" name="gpa_increment" step="0.01" min="-4" max="4" value="0" class="form-input" required>
 <p style="color:#64748b;font-size:0.78rem;margin-top:5px;">مثال: 0.10 يعني رفع المعدل 0.10 لكل طالب</p>
 </div>
 <div>
 <label class="form-label">الحد الأقصى للمعدل التراكمي</label>
 <input type="number" name="max_gpa" step="0.01" min="1" max="4" value="4.00" class="form-input" required>
 </div>
 </div>
 <!-- Preview Table -->
 <div style="margin-top:24px;">
 <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
 <p style="font-weight:700;color:#1e293b;margin:0;font-size:0.9rem;">معدلات الطلاب الحالية:</p>
 <input type="text" class="form-input search-filter" placeholder="بحث بالاسم أو الرقم..." style="max-width:250px; padding:6px 12px; font-size:0.85rem;" onkeyup="filterTable(this)">
 </div>
 <div style="overflow-x:auto;border:1px solid #f1f5f9;border-radius:10px;max-height:300px;overflow-y:auto;">
 <table style="width:100%;border-collapse:collapse;text-align:right;font-size:0.85rem;">
 <thead style="background:#f8fafc;position:sticky;top:0;">
 <tr>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">#</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">اسم الطالب</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">رقم الطالب</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;text-align:center;">المعدل الحالي</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($all_students as $i => $s):
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $gpa = isset($det['gpa']) ? number_format((float)$det['gpa'], 2) : 'غير محدد';
 ?>
 <tr style="border-top:1px solid #f1f5f9;">
 <td style="padding:10px 14px;color:#94a3b8;"><?php echo $i + 1; ?></td>
 <td style="padding:10px 14px;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?></td>
 <td style="padding:10px 14px;font-family:monospace;color:#475569;"><?php echo htmlspecialchars($s['username']); ?></td>
 <td style="padding:10px 14px;text-align:center;">
 <span style="background:#eff6ff;color:#1d4ed8;padding:2px 10px;border-radius:999px;font-weight:700;"><?php echo $gpa; ?></span>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 <div style="margin-top:20px;display:flex;gap:12px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> تطبيق التعديل على الجميع
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 2: Bulk Levels ══════════════════════════════ -->
 <?php elseif ($active_tab === 'bulk_levels'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-layer-group" style="color:#16a34a;"></i>
 </div>
 تعديل المستوى الدراسي لجميع الطلاب **
 </div>
 <div class="warning-badge">
 <i class="fas fa-exclamation-triangle"></i>
 هذا الإجراء يؤثر على <?php echo $total_students; ?> طالب دفعة واحدة.
 </div>
 <form method="POST" onsubmit="return confirm('هل أنت متأكد من تعديل مستويات جميع الطلاب؟');">
 <input type="hidden" name="action" value="bulk_update_levels">
 <div class="grid-2">
 <div>
 <label class="form-label">نوع التعديل</label>
 <select name="level_action" class="form-select" onchange="toggleFixedLevel(this.value)">
 <option value="increment">رفع مستوى واحد (الترقية)</option>
 <option value="decrement">خفض مستوى واحد</option>
 <option value="set">تحديد مستوى ثابت للجميع</option>
 </select>
 </div>
 <div id="fixed_level_div" style="display:none;">
 <label class="form-label">المستوى الجديد (1 - 4)</label>
 <input type="number" name="set_level" min="1" max="4" value="1" class="form-input">
 </div>
 </div>
 <div style="margin-top:20px;">
 <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
 <p style="font-weight:700;color:#1e293b;margin:0;font-size:0.9rem;">مستويات الطلاب الحالية:</p>
 <input type="text" class="form-input search-filter" placeholder="بحث بالاسم أو الرقم..." style="max-width:250px; padding:6px 12px; font-size:0.85rem;" onkeyup="filterTable(this)">
 </div>
 <div style="overflow-x:auto;border:1px solid #f1f5f9;border-radius:10px;max-height:300px;overflow-y:auto;">
 <table style="width:100%;border-collapse:collapse;text-align:right;font-size:0.85rem;">
 <thead style="background:#f8fafc;position:sticky;top:0;">
 <tr>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">#</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">اسم الطالب</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;text-align:center;">المستوى الحالي</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($all_students as $i => $s):
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $lvl = $det['level'] ?? 'غير محدد';
 ?>
 <tr style="border-top:1px solid #f1f5f9;">
 <td style="padding:10px 14px;color:#94a3b8;"><?php echo $i + 1; ?></td>
 <td style="padding:10px 14px;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?></td>
 <td style="padding:10px 14px;text-align:center;">
 <span style="background:#f0fdf4;color:#166534;padding:2px 10px;border-radius:999px;font-weight:700;">المستوى <?php echo $lvl; ?></span>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> تطبيق التعديل
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 3: Bulk Percentages ══════════════════════════════ -->
 <?php elseif ($active_tab === 'bulk_percentages'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#fdf4ff;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-percent" style="color:#9333ea;"></i>
 </div>
 تعديل النسبة المئوية لجميع الطلاب **
 </div>
 <div class="warning-badge">
 <i class="fas fa-exclamation-triangle"></i>
 هذا الإجراء يؤثر على <?php echo $total_students; ?> طالب دفعة واحدة.
 </div>
 <form method="POST" onsubmit="return confirm('هل أنت متأكد من تعديل نسب جميع الطلاب؟');">
 <input type="hidden" name="action" value="bulk_update_percentages">
 <div style="max-width:420px;">
 <label class="form-label">النسبة المئوية الثابتة (0 = احتساب تلقائي من المعدل)</label>
 <input type="number" name="base_percentage" step="0.01" min="0" max="100" value="0" class="form-input">
 <p style="color:#64748b;font-size:0.78rem;margin-top:5px;">إذا كانت القيمة 0، سيتم احتساب النسبة تلقائياً من المعدل التراكمي (GPA / 4.0 × 100)</p>
 </div>
 <div style="margin-top:24px;">
 <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
 <p style="font-weight:700;color:#1e293b;margin:0;font-size:0.9rem;">نسب الطلاب الحالية:</p>
 <input type="text" class="form-input search-filter" placeholder="بحث بالاسم أو الرقم..." style="max-width:250px; padding:6px 12px; font-size:0.85rem;" onkeyup="filterTable(this)">
 </div>
 <div style="overflow-x:auto;border:1px solid #f1f5f9;border-radius:10px;max-height:280px;overflow-y:auto;">
 <table style="width:100%;border-collapse:collapse;text-align:right;font-size:0.85rem;">
 <thead style="background:#f8fafc;position:sticky;top:0;">
 <tr>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">#</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">اسم الطالب</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;text-align:center;">النسبة الحالية</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;text-align:center;">المعدل التراكمي</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($all_students as $i => $s):
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $pct = isset($det['percentage']) ? $det['percentage'] . '%' : 'غير محدد';
 $gpa = isset($det['gpa']) ? number_format((float)$det['gpa'], 2) : '—';
 ?>
 <tr style="border-top:1px solid #f1f5f9;">
 <td style="padding:10px 14px;color:#94a3b8;"><?php echo $i + 1; ?></td>
 <td style="padding:10px 14px;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?></td>
 <td style="padding:10px 14px;text-align:center;">
 <span style="background:#fdf4ff;color:#9333ea;padding:2px 10px;border-radius:999px;font-weight:700;"><?php echo $pct; ?></span>
 </td>
 <td style="padding:10px 14px;text-align:center;color:#475569;font-weight:600;"><?php echo $gpa; ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> تطبيق التعديل
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 4: Single GPA ══════════════════════════════ -->
 <?php elseif ($active_tab === 'single_gpa'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#fff7ed;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-user-check" style="color:#ea580c;"></i>
 </div>
 تعديل المعدل التراكمي لطالب محدد
 </div>
 <form method="POST">
 <input type="hidden" name="action" value="single_update_gpa">
 <div class="grid-2">
 <div>
 <label class="form-label">اختر الطالب</label>
 <select name="student_id" class="form-select" required onchange="loadStudentGpa(this.value)">
 <option value="">-- اختر الطالب --</option>
 <?php foreach ($all_students as $s): 
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $current_gpa = $det['gpa'] ?? 0;
 ?>
 <option value="<?php echo $s['id']; ?>" data-gpa="<?php echo $current_gpa; ?>">
 <?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?> (<?php echo htmlspecialchars($s['username']); ?>)
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="form-label">المعدل التراكمي الجديد (0.00 – 4.00)</label>
 <input type="number" id="single_gpa_input" name="new_gpa" step="0.01" min="0" max="4" placeholder="e.g. 3.75" class="form-input" required>
 <p style="color:#64748b;font-size:0.78rem;margin-top:5px;" id="current_gpa_hint">اختر الطالب أولاً لعرض معدله الحالي</p>
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> حفظ المعدل الجديد
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 5: Single Level ══════════════════════════════ -->
 <?php elseif ($active_tab === 'single_level'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-sort-numeric-up" style="color:#16a34a;"></i>
 </div>
 تعديل المستوى الدراسي لطالب محدد
 </div>
 <form method="POST">
 <input type="hidden" name="action" value="single_update_level">
 <div class="grid-2">
 <div>
 <label class="form-label">اختر الطالب</label>
 <select name="student_id" class="form-select" required onchange="loadStudentLevel(this.value)">
 <option value="">-- اختر الطالب --</option>
 <?php foreach ($all_students as $s): 
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $current_level = $det['level'] ?? 1;
 ?>
 <option value="<?php echo $s['id']; ?>" data-level="<?php echo $current_level; ?>">
 <?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?> (<?php echo htmlspecialchars($s['username']); ?>)
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="form-label">المستوى الدراسي الجديد</label>
 <select name="new_level" id="single_level_select" class="form-select" required>
 <?php for ($l = 1; $l <= 4; $l++): ?>
 <option value="<?php echo $l; ?>">المستوى <?php echo $l; ?></option>
 <?php endfor; ?>
 </select>
 <p style="color:#64748b;font-size:0.78rem;margin-top:5px;" id="current_level_hint">اختر الطالب أولاً لعرض مستواه الحالي</p>
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> حفظ المستوى الجديد
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 6: Single Percentage ══════════════════════════════ -->
 <?php elseif ($active_tab === 'single_percentage'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#fdf4ff;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-chart-pie" style="color:#9333ea;"></i>
 </div>
 تعديل النسبة المئوية لطالب محدد
 </div>
 <form method="POST">
 <input type="hidden" name="action" value="single_update_percentage">
 <div class="grid-2">
 <div>
 <label class="form-label">اختر الطالب</label>
 <select name="student_id" class="form-select" required onchange="loadStudentPercentage(this.value)">
 <option value="">-- اختر الطالب --</option>
 <?php foreach ($all_students as $s): 
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $current_pct = $det['percentage'] ?? '';
 ?>
 <option value="<?php echo $s['id']; ?>" data-pct="<?php echo $current_pct; ?>">
 <?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?> (<?php echo htmlspecialchars($s['username']); ?>)
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="form-label">النسبة المئوية الجديدة (0 – 100)</label>
 <input type="number" id="single_pct_input" name="new_percentage" step="0.01" min="0" max="100" placeholder="e.g. 88.50" class="form-input" required>
 <p style="color:#64748b;font-size:0.78rem;margin-top:5px;" id="current_pct_hint">اختر الطالب أولاً لعرض نسبته الحالية</p>
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> حفظ النسبة الجديدة
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 7: Graduation Year ══════════════════════════════ -->
 <?php elseif ($active_tab === 'graduation'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#fffbeb;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-graduation-cap" style="color:#d97706;"></i>
 </div>
 إضافة عام التخرج للطلاب
 </div>
 <div class="warning-badge">
 <i class="fas fa-info-circle"></i>
 سيتم تحديث حالة الطلاب المختارين إلى "متخرج" وإضافة عام التخرج.
 </div>
 <form method="POST" onsubmit="return confirm('هل أنت متأكد من تخريج الطلاب المختارين؟');">
 <input type="hidden" name="action" value="add_graduation_year">
 <div style="max-width:300px;margin-bottom:20px;">
 <label class="form-label">عام التخرج</label>
 <input type="number" name="graduation_year" min="2000" max="2100" value="<?php echo date('Y'); ?>" class="form-input" required>
 </div>
 <div>
 <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
 <label class="form-label" style="margin:0;">اختر الطلاب المراد تخريجهم:</label>
 <div style="display:flex;gap:8px;align-items:center;">
 <input type="text" class="form-input search-filter" placeholder="بحث..." style="max-width:150px; padding:4px 8px; font-size:0.8rem;" onkeyup="filterTable(this)">
 <button type="button" onclick="selectAllStudents(true)" style="font-size:0.78rem;padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;font-family:'Cairo',sans-serif;">تحديد الكل</button>
 <button type="button" onclick="selectAllStudents(false)" style="font-size:0.78rem;padding:4px 10px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;font-family:'Cairo',sans-serif;">إلغاء الكل</button>
 </div>
 </div>
 <div style="border:1px solid #f1f5f9;border-radius:10px;overflow-x:auto;max-height:320px;overflow-y:auto;">
 <table style="width:100%;border-collapse:collapse;text-align:right;font-size:0.85rem;">
 <thead style="background:#f8fafc;position:sticky;top:0;">
 <tr>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;width:50px;">اختيار</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">اسم الطالب</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;">رقم الطالب</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;text-align:center;">المستوى</th>
 <th style="padding:10px 14px;color:#64748b;font-weight:600;text-align:center;">الحالة</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($all_students as $s):
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $lvl = $det['level'] ?? '—';
 $status = $det['enrollment_status'] ?? 'enrolled';
 $grad_year = $det['graduation_year'] ?? null;
 $status_ar = ['enrolled' => 'مسجل', 'graduated' => 'متخرج', 'suspended' => 'موقوف'][$status] ?? $status;
 $status_color = $status === 'graduated' ? '#22c55e' : ($status === 'suspended' ? '#ef4444' : '#3b82f6');
 ?>
 <tr style="border-top:1px solid #f1f5f9;">
 <td style="padding:10px 14px;text-align:center;">
 <input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" class="student-checkbox" style="width:18px;height:18px;cursor:pointer;" <?php echo $status==='graduated'?'checked':''; ?>>
 </td>
 <td style="padding:10px 14px;font-weight:600;color:#1e293b;"><?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?></td>
 <td style="padding:10px 14px;font-family:monospace;color:#475569;"><?php echo htmlspecialchars($s['username']); ?></td>
 <td style="padding:10px 14px;text-align:center;color:#475569;"><?php echo $lvl; ?></td>
 <td style="padding:10px 14px;text-align:center;">
 <span style="background:<?php echo $status_color; ?>22;color:<?php echo $status_color; ?>;padding:2px 10px;border-radius:999px;font-weight:700;font-size:0.78rem;"><?php echo $status_ar; ?><?php echo $grad_year ? ' ' . $grad_year : ''; ?></span>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-danger">
 <i class="fas fa-graduation-cap"></i> تخريج الطلاب المختارين
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 8: Single Annual GPA ══════════════════════════════ -->
 <?php elseif ($active_tab === 'single_annual'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#f0f9ff;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-calendar-check" style="color:#0284c7;"></i>
 </div>
 تعديل المعدل السنوي لطالب محدد
 </div>
 <form method="POST">
 <input type="hidden" name="action" value="single_update_annual_gpa">
 <div class="grid-2">
 <div>
 <label class="form-label">اختر الطالب</label>
 <select name="student_id" class="form-select" required onchange="loadStudentAnnualGpa(this.value)">
 <option value="">-- اختر الطالب --</option>
 <?php foreach ($all_students as $s): 
 $det = $studentDetailModel->findOneBy('user_id', $s['id']);
 $current_annual = $det['annual_gpa'] ?? '';
 ?>
 <option value="<?php echo $s['id']; ?>" data-annual="<?php echo $current_annual; ?>">
 <?php echo htmlspecialchars($s['full_name'] ?? $s['username']); ?> (<?php echo htmlspecialchars($s['username']); ?>)
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div>
 <label class="form-label">المعدل السنوي الجديد (0.00 – 4.00)</label>
 <input type="number" id="single_annual_input" name="annual_gpa" step="0.01" min="0" max="4" placeholder="e.g. 3.50" class="form-input" required>
 <p style="color:#64748b;font-size:0.78rem;margin-top:5px;" id="current_annual_hint">اختر الطالب أولاً لعرض معدله السنوي الحالي</p>
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> حفظ المعدل السنوي
 </button>
 </div>
 </form>
 </div>

 <!-- ══════════════════════════════ TAB 9: Bulk Annual GPA ══════════════════════════════ -->
 <?php elseif ($active_tab === 'bulk_annual'): ?>
 <div class="section-card">
 <div class="section-title">
 <div style="width:36px;height:36px;border-radius:10px;background:#f0f9ff;display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-calendar-alt" style="color:#0284c7;"></i>
 </div>
 تعديل المعدل السنوي لجميع الطلاب **
 </div>
 <div class="warning-badge">
 <i class="fas fa-exclamation-triangle"></i>
 هذا الإجراء يؤثر على <?php echo $total_students; ?> طالب دفعة واحدة.
 </div>
 <form method="POST" onsubmit="return confirm('هل أنت متأكد من تحديث المعدل السنوي لجميع الطلاب؟');">
 <input type="hidden" name="action" value="bulk_update_annual_gpa">
 <div class="grid-2">
 <div>
 <label class="form-label">طريقة الاحتساب</label>
 <select name="annual_gpa_method" class="form-select" onchange="toggleFixedAnnualGpa(this.value)">
 <option value="from_gpa">نسخ من المعدل التراكمي الحالي</option>
 <option value="fixed">تحديد قيمة ثابتة للجميع</option>
 </select>
 </div>
 <div id="fixed_annual_div" style="display:none;">
 <label class="form-label">قيمة المعدل السنوي الثابتة</label>
 <input type="number" name="fixed_annual_gpa" step="0.01" min="0" max="4" value="0" class="form-input">
 </div>
 </div>
 <div style="margin-top:20px;">
 <button type="submit" class="btn-primary">
 <i class="fas fa-save"></i> تطبيق التعديل
 </button>
 </div>
 </form>
 </div>
 <?php endif; ?>

</div>

<script>
function toggleFixedLevel(val) {
 document.getElementById('fixed_level_div').style.display = (val === 'set') ? 'block' : 'none';
}
function toggleFixedAnnualGpa(val) {
 document.getElementById('fixed_annual_div').style.display = (val === 'fixed') ? 'block' : 'none';
}
function selectAllStudents(checked) {
 document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = checked);
}
function loadStudentGpa(sid) {
 const sel = document.querySelector('select[name="student_id"]');
 const opt = sel ? sel.querySelector('option[value="' + sid + '"]') : null;
 if (opt) {
 const gpa = opt.getAttribute('data-gpa');
 document.getElementById('single_gpa_input').value = gpa || '';
 document.getElementById('current_gpa_hint').textContent = 'المعدل الحالي: ' + (gpa || 'غير محدد');
 }
}
function filterTable(inputEl) {
 const term = inputEl.value.toLowerCase();
 const table = inputEl.closest('.section-card').querySelector('table');
 if (!table) return;
 const rows = table.querySelectorAll('tbody tr');
 rows.forEach(row => {
     // Assuming name is in col 1, ID is in col 2
     const text = row.innerText.toLowerCase();
     row.style.display = text.includes(term) ? '' : 'none';
 });
}

function loadStudentLevel(sid) {
 const sel = document.querySelector('select[name="student_id"]');
 const opt = sel ? sel.querySelector('option[value="' + sid + '"]') : null;
 if (opt) {
 const level = opt.getAttribute('data-level') || '1';
 document.getElementById('single_level_select').value = level;
 document.getElementById('current_level_hint').textContent = 'المستوى الحالي: ' + level;
 }
}
function loadStudentPercentage(sid) {
 const sel = document.querySelector('select[name="student_id"]');
 const opt = sel ? sel.querySelector('option[value="' + sid + '"]') : null;
 if (opt) {
 const pct = opt.getAttribute('data-pct');
 document.getElementById('single_pct_input').value = pct || '';
 document.getElementById('current_pct_hint').textContent = 'النسبة الحالية: ' + (pct ? pct + '%' : 'غير محددة');
 }
}
function loadStudentAnnualGpa(sid) {
 const sel = document.querySelector('select[name="student_id"]');
 const opt = sel ? sel.querySelector('option[value="' + sid + '"]') : null;
 if (opt) {
 const annual = opt.getAttribute('data-annual');
 document.getElementById('single_annual_input').value = annual || '';
 document.getElementById('current_annual_hint').textContent = 'المعدل السنوي الحالي: ' + (annual || 'غير محدد');
 }
}
</script>

<?php require_once 'includes/footer.php'; ?>
