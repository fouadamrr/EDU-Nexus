<?php
require_once 'includes/header.php';

$type = $_GET['type'] ?? 'all';
$type_titles = [
    'registered' => 'تقرير الطلاب المسجلين بالفصل الحالي',
    'distribution' => 'تقرير توزيع الطلاب',
    'all' => 'إحصائيات التسجيل الأكاديمي الشاملة'
];
$title = $type_titles[$type] ?? $type_titles['all'];

// بنفلتر البيانات على حسب صلاحيات اليوزر (لو عميد يشوف كليته بس)
$is_scoped = in_array($role, ['dean', 'affairs']);
$session_college_id = (int)($_SESSION['college_id'] ?? 0);
$college_id = $is_scoped ? $session_college_id : (int)($_GET['college_id'] ?? 0);
$semester = 'Spring 2026';

// بنجيب لستة الطلاب وعدد المواد اللي سجلوا فيها الترم ده
$query = "SELECT u.id, u.username, u.full_name, s.level, s.enrollment_status, c.name as college_name,
(SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id AND e.semester = :sem) as courses_count
FROM users u JOIN students s ON u.id = s.user_id LEFT JOIN colleges c ON u.college_id = c.id
WHERE u.role = 'student'";

$params = [':sem' => $semester];
if ($college_id > 0) { $query .= " AND u.college_id = :cid"; $params[':cid'] = $college_id; }
if ($type === 'registered') { $query .= " AND EXISTS (SELECT 1 FROM enrollments e WHERE e.user_id = u.id AND e.semester = :sem)"; }

$query .= " ORDER BY u.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$report_title = $title;
$sub_title = "الفصل الدراسي: " . $semester . ($college_id ? " | كلية " . $students[0]['college_name'] : "");
$count_label = "إجمالي الطلاب";
$count_value = count($students);
$logo_path = 'assets/images/logo.png';
$logo_b64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>

<style>
@media print {
    header, .no-print, .sidebar, #sidebar, footer { display: none !important; }
    body, html { background: #fff !important; margin: 0; padding: 0; }
    
    body::before {
        content: ""; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 400px; height: 400px; background: url('<?= $logo_b64 ?>') no-repeat center;
        background-size: contain; opacity: 0.05; z-index: -1;
    }
    table { width: 100% !important; border: 1.5px solid #000 !important; border-collapse: collapse !important; font-size: 11px !important; }
    thead { display: table-header-group !important; }
    th, td { border: 1px solid #000 !important; padding: 10px !important; }
}
.report-official-header { width: 100%; border-bottom: 3.5px solid #334155; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; }
.header-logo-box { width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px; }
</style>

<div class="max-w-7xl mx-auto space-y-6 mt-6">
    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-8 no-print">
            <div>
                <h1 class="text-2xl font-black text-slate-800"><?php echo $title; ?></h1>
                <p class="text-slate-500 mt-1">تقارير إحصائية رسمية لحالة التسجيل الأكاديمي.</p>
            </div>
            <button onclick="window.print()" class="bg-primary text-white px-8 py-3.5 rounded-2xl font-black shadow-lg hover:bg-slate-800 transition-all flex items-center gap-2">
                <i class="fas fa-print"></i> طباعة التقرير
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr><th colspan="5" class="p-0 border-none"><?php require_once 'includes/report_header_print.php'; ?></th></tr>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-4 font-black text-slate-400 text-xs uppercase tracking-widest">رقم القيد</th>
                        <th class="px-6 py-4 font-black text-slate-800">اسم الطالب</th>
                        <th class="px-6 py-4 font-black text-slate-600 text-center">الفرقة</th>
                        <th class="px-6 py-4 font-black text-slate-600 text-center">المقررات</th>
                        <th class="px-6 py-4 font-black text-slate-600 text-center">حالة القيد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($students as $s): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 text-primary font-mono font-bold"><?= htmlspecialchars($s['username']) ?></td>
                        <td class="px-6 py-4 font-bold text-slate-800"><?= htmlspecialchars($s['full_name']) ?></td>
                        <td class="px-6 py-4 text-slate-600 text-center font-bold">المستوى <?= $s['level'] ?></td>
                        <td class="px-6 py-4 text-center font-black <?= $s['courses_count'] > 0 ? 'text-primary' : 'text-slate-300' ?>">
                            <?= $s['courses_count'] ?> مقررات
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-[10px] font-black uppercase border border-slate-200">
                                <?= $s['enrollment_status'] === 'enrolled' ? 'منتظم' : 'موقوف' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
