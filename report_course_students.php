<?php
require_once __DIR__ . '/includes/header.php';

// Check role
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
require_permission('students');

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$semester = $_GET['semester'] ?? 'Fall 2025';

$col_id = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? 0) : 0;

// Render Selection Interface
if ($course_id <= 0) {
    $q_courses = "SELECT id, code, name FROM courses";
    $p_courses = [];
    if ($col_id > 0) {
        $q_courses .= " WHERE college_id = ?";
        $p_courses[] = $col_id;
    }
    $q_courses .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($q_courses);
    $stmt->execute($p_courses);
    $all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtSem = $pdo->query("SELECT DISTINCT semester FROM enrollments WHERE semester IS NOT NULL ORDER BY semester DESC");
    $semesters = $stmtSem->fetchAll(PDO::FETCH_COLUMN);
    if(empty($semesters)) $semesters = ['Fall 2025'];
    ?>
    <div class="max-w-4xl mx-auto animate-fade-in-up mt-8 no-print">
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 mb-6 flex flex-col items-center">
            <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center text-3xl mb-4 shadow-inner">
                <i class="fas fa-users"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-800 mb-2">استخراج كشوف المادة</h2>
            <p class="text-slate-500 text-center mb-8">اختر المقرر الدراسي لإنشاء كشوف الحضور والتوقيع الرسمية.</p>
            
            <form method="GET" class="w-full max-w-2xl space-y-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">المقرر الدراسي</label>
                    <select name="course_id" required class="w-full border border-slate-200 bg-slate-50 rounded-2xl p-4 font-bold outline-none focus:ring-4 focus:ring-primary/10 transition-all">
                        <option value="">-- اختر المادة --</option>
                        <?php foreach($all_courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'] . ' - ' . $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">الفصل الدراسي</label>
                    <select name="semester" class="w-full border border-slate-200 bg-slate-50 rounded-2xl p-4 font-bold outline-none focus:ring-4 focus:ring-primary/10 transition-all">
                        <?php foreach($semesters as $sem): ?>
                        <option value="<?= htmlspecialchars($sem) ?>"><?= htmlspecialchars($sem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full bg-primary text-white font-black px-8 py-5 rounded-3xl shadow-xl shadow-primary/20 hover:bg-indigo-700 transition-all flex justify-center items-center gap-3">
                    <i class="fas fa-file-invoice text-xl"></i> استخراج الكشف الرسمي
                </button>
            </form>
        </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// Fetch Course Data
$stmt = $pdo->prepare("SELECT id, code, name, credit_hours, college_id FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course || ($col_id > 0 && $course['college_id'] != $col_id)) {
    echo "<div class='p-8 text-center text-rose-600 font-bold'>عذراً، غير مصرح لك بعرض بيانات هذا المقرر أو المقرر غير موجود.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Fetch Students
$query = "SELECT u.full_name, u.username as academic_number, s.level, c.name as college_name, e.status
FROM enrollments e JOIN users u ON e.user_id = u.id JOIN students s ON u.id = s.user_id LEFT JOIN colleges c ON s.college_id = c.id
WHERE e.course_id = :cid AND e.semester = :sem ORDER BY u.full_name ASC";
$stmtEn = $pdo->prepare($query);
$stmtEn->execute([':cid' => $course_id, ':sem' => $semester]);
$studentsList = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

$report_title = "كشف حضور وتوزيع درجات الطلاب";
$sub_title = htmlspecialchars($course['code'] . ' - ' . $course['name'] . ' (' . $semester . ')');
$count_label = "إجمالي المسجلين";
$count_value = count($studentsList);

$logo_path = 'assets/images/logo.png';
$logo_b64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>

<style>
@media print {
    header, .no-print, .sidebar, #sidebar, footer { display: none !important; }
    body, html { height: auto !important; overflow: visible !important; background: #fff !important; margin: 0; padding: 0; }
    main { display: block !important; overflow: visible !important; padding: 0 !important; margin: 0 !important; width: 100% !important; }
    
    body::before {
        content: ""; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 400px; height: 400px; background: url('<?= $logo_b64 ?>') no-repeat center;
        background-size: contain; opacity: 0.05; z-index: -1;
    }

    table { width: 100% !important; border: 1.5px solid #000 !important; border-collapse: collapse !important; font-size: 11px !important; }
    thead { display: table-header-group !important; }
    th, td { border: 1px solid #000 !important; padding: 8px !important; }
    th { background: #f8fafc !important; color: #000 !important; font-weight: 900 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    
    .report-official-header {
        width: 100%; border-bottom: 3.5px solid #000; padding-bottom: 12px; margin-bottom: 20px;
        display: flex !important; align-items: center; justify-content: space-between;
    }
    .header-logo-box {
        width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px;
    }
    .print-break { page-break-before: always; }
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

<div class="max-w-6xl mx-auto space-y-6 animate-fade-in-up mt-6">
    <div class="flex items-center justify-between no-print bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-file-invoice text-primary"></i> <?= htmlspecialchars($course['name']) ?>
            </h2>
            <p class="text-slate-500 text-sm mt-1">معاينة الكشف الرسمي للمادة قبل الطباعة.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="bg-primary text-white px-7 py-3.5 rounded-2xl font-black shadow-lg hover:bg-indigo-700 transition-all flex items-center gap-2 text-sm">
                <i class="fas fa-print"></i> طباعة الكشف
            </button>
            <a href="report_course_students.php" class="bg-slate-50 text-slate-600 px-5 py-3.5 rounded-2xl font-bold border border-slate-200 hover:bg-white transition-all text-sm">مادة أخرى</a>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-0">
        <table class="w-full text-right text-sm">
            <thead>
                <tr>
                    <th colspan="6" class="p-0 border-none">
                        <?php require_once 'includes/report_header_print.php'; ?>
                    </th>
                </tr>
                <tr class="bg-slate-50">
                    <th class="p-4 text-center w-12">#</th>
                    <th class="p-4 w-32">رقم القيد</th>
                    <th class="p-4">اسم الطالب الرباعي</th>
                    <th class="p-4 text-center w-24">الفرقة</th>
                    <th class="p-4 text-center w-48">توقيع الطالب</th>
                    <th class="p-4 text-center w-20">الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($studentsList)): ?>
                <tr><td colspan="6" class="p-16 text-center text-slate-400 font-bold">لا يوجد طلاب مسجلون حالياً.</td></tr>
                <?php else: ?>
                <?php $i=1; foreach ($studentsList as $s): ?>
                <tr class="hover:bg-slate-50/50">
                    <td class="p-4 text-center text-slate-400 font-mono"><?= $i++ ?></td>
                    <td class="p-4 font-black font-mono text-primary"><?= htmlspecialchars($s['academic_number']) ?></td>
                    <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($s['full_name']) ?></td>
                    <td class="p-4 text-center font-bold text-slate-500"><?= $s['level'] ?></td>
                    <td class="p-4"><div style="border-bottom: 1.5px dotted #000; height: 15px; width: 100%; opacity: 0.6;"></div></td>
                    <td class="p-4 text-center">
                        <span class="text-[10px] font-black uppercase tracking-widest <?= $s['status']==='dropped' ? 'text-rose-500':'text-emerald-500' ?>">
                            <?= $s['status']==='dropped' ? 'منسحب':'مسجل' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="hidden print:flex justify-between items-center mt-12 px-12 pb-12">
            <div class="text-center font-black">
                <p class="mb-8">أستاذ المادة</p>
                <div class="w-48 border-b-2 border-black border-dotted mx-auto"></div>
            </div>
            <div class="text-center font-black">
                <p class="mb-8">شؤون الطلاب</p>
                <div class="w-48 border-b-2 border-black border-dotted mx-auto"></div>
            </div>
            <div class="text-center font-black">
                <p class="mb-8">يعتمد / عميد الكلية</p>
                <div class="w-48 border-b-2 border-black border-dotted mx-auto"></div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
