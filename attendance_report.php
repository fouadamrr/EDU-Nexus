<?php
require_once 'includes/header.php';
/** @var PDO $pdo */
require_permission('absence');

$title = "تقرير الغياب الشهري";
$session_college_id = (int)($_SESSION['college_id'] ?? 0);
$is_scoped = in_array($role, ['dean', 'affairs']);

// Filters
$filter_college = isset($_GET['college_id']) ? (int)$_GET['college_id'] : 0;
$filter_level = isset($_GET['level']) ? (int)$_GET['level'] : 0;
$filter_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_status = $_GET['status'] ?? '';

// Effective college logic (strict scoping)
$effective_college = $is_scoped ? $session_college_id : $filter_college;

$all_colleges = [];
if (!$is_scoped) {
    try { $all_colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
}

$courses_for_filter = [];
try {
    $q_cs = "SELECT id, name, code FROM courses";
    $c_params = [];
    if ($effective_college > 0) { 
        $q_cs .= " WHERE college_id = ?"; 
        $c_params[] = $effective_college; 
    }
    $q_cs .= " ORDER BY level, name";
    $stmt = $pdo->prepare($q_cs);
    $stmt->execute($c_params);
    $courses_for_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Build Query
$params = [];
$where_parts = ["u.role = 'student'"];
if ($effective_college > 0) { $where_parts[] = "u.college_id = ?"; $params[] = $effective_college; }
if ($filter_level > 0) { $where_parts[] = "st.level = ?"; $params[] = $filter_level; }
if ($filter_course > 0) { $where_parts[] = "a.course_id = ?"; $params[] = $filter_course; }
if ($filter_month) { $where_parts[] = "TO_CHAR(a.date, 'YYYY-MM') = ?"; $params[] = $filter_month; }

$where = 'WHERE ' . implode(' AND ', $where_parts);
$sql = "SELECT u.id AS student_id, u.full_name, u.username, c2.name AS college_name, st.level, c.id AS course_id, c.name AS course_name, c.code AS course_code,
COUNT(a.id) AS total_sessions, SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
ROUND(CASE WHEN COUNT(a.id) > 0 THEN (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)) ELSE 100 END) AS attendance_pct
FROM attendance a JOIN users u ON u.id = a.user_id JOIN courses c ON c.id = a.course_id LEFT JOIN students st ON st.user_id = u.id LEFT JOIN colleges c2 ON c2.id = u.college_id
{$where} GROUP BY u.id, u.full_name, u.username, c2.name, st.level, c.id, c.name, c.code HAVING COUNT(a.id) > 0 ORDER BY attendance_pct ASC, u.full_name";

$records = [];
try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $records = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}

if ($filter_status === 'danger') { $records = array_filter($records, fn($r) => $r['attendance_pct'] < 75); }
elseif ($filter_status === 'warning') { $records = array_filter($records, fn($r) => $r['attendance_pct'] >= 75 && $r['attendance_pct'] < 85); }
elseif ($filter_status === 'ok') { $records = array_filter($records, fn($r) => $r['attendance_pct'] >= 85); }
$records = array_values($records);

$report_title = "تقرير متابعة الحضور والغياب الشهري";
$sub_title = "شهر: " . $filter_month;
$count_label = "إجمالي السجلات";
$count_value = count($records);
$logo_path = 'assets/images/logo.png';
$logo_b64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>

<style>
@media print {
    header, .no-print, .sidebar, #sidebar, footer { display: none !important; }
    body, html { background: #fff !important; margin: 0; padding: 0; }
    .print-container { width: 100% !important; max-width: none !important; box-shadow: none !important; border: none !important; }
    
    body::before {
        content: ""; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 400px; height: 400px; background: url('<?= $logo_b64 ?>') no-repeat center;
        background-size: contain; opacity: 0.05; z-index: -1;
    }
    table { width: 100% !important; border: 1.5px solid #000 !important; border-collapse: collapse !important; font-size: 10px !important; }
    thead { display: table-header-group !important; }
    th, td { border: 1px solid #000 !important; padding: 6px !important; }
    th { background: #f8fafc !important; color: #000 !important; font-weight: 900 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    .report-official-header {
        width: 100%; border-bottom: 3.5px solid #000; padding-bottom: 12px; margin-bottom: 20px;
        display: flex !important; align-items: center; justify-content: space-between;
    }
    .header-logo-box {
        width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px;
    }
}
.report-official-header {
    width: 100%; border-bottom: 3.5px solid #334155; padding-bottom: 15px; margin-bottom: 25px;
    display: flex; align-items: center; justify-content: space-between;
}
.header-logo-box {
    width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px;
}
</style>

<div class="max-w-7xl mx-auto space-y-6 mt-6 print-container">
    <div class="flex flex-wrap items-center justify-between gap-4 no-print bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
        <div>
            <h1 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-chart-bar text-primary"></i> <?= $title ?>
            </h1>
            <p class="text-slate-500 text-sm mt-1">إحصائيات وقوائم الغياب الرسمية للطلاب والفرق.</p>
        </div>
        <button onclick="window.print()" class="bg-primary text-white px-7 py-3.5 rounded-2xl font-black shadow-lg hover:bg-indigo-700 transition flex items-center gap-2">
            <i class="fas fa-print"></i> طباعة التقرير الرسمي
        </button>
    </div>

    <!-- Filters Bar (no-print) -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 no-print">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <?php if (!$is_scoped): ?>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">الكلية</label>
                <select name="college_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold">
                    <option value="0">جميع الكليات</option>
                    <?php foreach ($all_colleges as $col): ?>
                    <option value="<?= $col['id'] ?>" <?= $filter_college == $col['id'] ? 'selected' : '' ?>><?= htmlspecialchars($col['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">الفرقة</label>
                <select name="level" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold">
                    <option value="0">جميع الفرق</option>
                    <option value="1">الفرقة الأولى</option><option value="2">الفرقة الثانية</option><option value="3">الفرقة الثالثة</option><option value="4">الفرقة الرابعة</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">المقرر</label>
                <select name="course_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold">
                    <option value="0">جميع المقررات</option>
                    <?php foreach ($courses_for_filter as $cf): ?>
                    <option value="<?= $cf['id'] ?>" <?= $filter_course == $cf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cf['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">الشهر</label>
                <input type="month" name="month" value="<?= htmlspecialchars($filter_month) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold">
            </div>
            <button type="submit" class="bg-primary text-white py-3 rounded-xl font-black hover:bg-indigo-700 transition">تطبيق</button>
        </form>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-right text-sm">
            <thead>
                <tr><th colspan="7" class="p-0 border-none"><?php require_once 'includes/report_header_print.php'; ?></th></tr>
                <tr class="bg-slate-50">
                    <th class="p-4">الطالب</th>
                    <th class="p-4">الكلية - المادة</th>
                    <th class="p-4 text-center">المحاضرات</th>
                    <th class="p-4 text-center">الغياب</th>
                    <th class="p-4 text-center">نسبة الحضور</th>
                    <th class="p-4 text-center">الحالة</th>
                    <th class="p-4 text-center no-print">إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): $pct = (int)$r['attendance_pct']; $color = $pct >= 85 ? 'emerald' : ($pct >= 75 ? 'amber' : 'rose'); ?>
                <tr>
                    <td class="p-4">
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($r['full_name']) ?></div>
                        <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($r['username']) ?></div>
                    </td>
                    <td class="p-4">
                        <div class="text-xs font-bold"><?= htmlspecialchars($r['college_name']) ?></div>
                        <div class="text-[10px] text-primary"><?= htmlspecialchars($r['course_name']) ?></div>
                    </td>
                    <td class="p-4 text-center font-bold text-slate-600"><?= $r['total_sessions'] ?></td>
                    <td class="p-4 text-center text-rose-600 font-black"><?= $r['absent_count'] ?></td>
                    <td class="p-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                           <span class="font-black text-<?= $color ?>-600"><?= $pct ?>%</span>
                        </div>
                    </td>
                    <td class="p-4 text-center">
                        <span class="text-[10px] font-black px-2 py-1 rounded bg-<?= $color ?>-50 text-<?= $color ?>-700 border border-<?= $color ?>-100 uppercase">
                            <?= $pct >= 85 ? 'منتظم' : ($pct >= 75 ? 'متابعة' : 'إنذار') ?>
                        </span>
                    </td>
                    <td class="p-4 text-center no-print"><i class="fas fa-eye text-slate-200"></i></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
