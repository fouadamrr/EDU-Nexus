<?php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/ResultsController.php';

// Only admin-type roles
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
require_permission('results');

$resultsController = new ResultsController();
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$search_term = trim($_GET['q'] ?? '');
$results = [];

$session_college_id = (int)($_SESSION['college_id'] ?? 0);
$is_scoped = in_array($role, ['dean', 'affairs']);

// ── Search Students ───────────────
if ($search_term !== '' && $student_id === 0) {
    $college_scope = '';
    $params = [];
    if ($is_scoped && $session_college_id > 0) {
        $college_scope = " AND u.college_id = :cid";
        $params[':cid'] = $session_college_id;
    }
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, c.name AS college_name FROM users u LEFT JOIN colleges c ON u.college_id = c.id WHERE u.role = 'student' AND (u.full_name ILIKE :q OR u.username ILIKE :q) $college_scope ORDER BY u.full_name LIMIT 30");
    $params[':q'] = "%$search_term%";
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Load One Student ──────────────
$student = null; $semesters = []; $cumulative_gpa = 0; $total_all_hours = 0; $total_all_points = 0;

if ($student_id > 0) {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.email, u.status, c.name AS college_name, sd.level, u.college_id FROM users u LEFT JOIN colleges c ON u.college_id = c.id LEFT JOIN students sd ON sd.user_id = u.id WHERE u.id = :id AND u.role = 'student' LIMIT 1");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        // Strict Scope Check
        if ($is_scoped && $student['college_id'] != $session_college_id) {
            $student = null;
        } else {
            $semesters = $resultsController->getStudentResults($student_id, $role);
            foreach ($semesters as $sem => $courses) {
                foreach ($courses as $c) {
                    if (!empty($c['grade'])) {
                        $p = (float)($c['points'] ?? 0); $h = (int)($c['credit_hours'] ?? 0);
                        $total_all_points += $p * $h; $total_all_hours += $h;
                    }
                }
            }
            $cumulative_gpa = $total_all_hours > 0 ? round($total_all_points / $total_all_hours, 2) : 0.00;
        }
    }
}

$logo_path = 'assets/images/logo.png';
$logo_b64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
$report_title = "بيان درجات الطالب (كشف أكاديمي رسمي)";
$sub_title = $student ? $student['full_name'] : "طلب استخراج بيان درجات";
?>

<style>
@media print {
    header, .no-print, .sidebar, #sidebar, footer, .horizontal-tabs { display: none !important; }
    body, html { height: auto !important; overflow: visible !important; background: #fff !important; margin: 0; padding: 0; }
    main { display: block !important; padding: 0 !important; margin: 0 !important; width: 100% !important; }
    
    body::before {
        content: ""; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 400px; height: 400px; background: url('<?= $logo_b64 ?>') no-repeat center;
        background-size: contain; opacity: 0.05; z-index: -1;
    }
    
    .report-official-header {
        width: 100%; border-bottom: 3.5px solid #000; padding-bottom: 12px; margin-bottom: 20px;
        display: flex !important; align-items: center; justify-content: space-between;
    }
    .header-logo-box { width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px; }

    .transcript-table { width: 100% !important; border: 1.5px solid #000 !important; border-collapse: collapse !important; font-size: 11px !important; }
    .transcript-table th, .transcript-table td { border: 1px solid #000 !important; padding: 6px !important; }
    .transcript-table th { background: #f8fafc !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page-break { page-break-after: always; }
}
.report-official-header { width: 100%; border-bottom: 3.5px solid #334155; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; }
.header-logo-box { width: 60px; height: 60px; border: 2px solid #000; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 5px; margin-bottom: 5px; }
.grade-pill { display:inline-flex; align-items:center; justify-content:center; padding:3px 12px; border-radius:99px; font-weight:800; font-size:.72rem; min-width:44px; border: 1px solid #e2e8f0; }
</style>

<div class="max-w-5xl mx-auto space-y-7 no-print mt-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-file-signature text-primary"></i> بيان الدرجات الأكاديمي
            </h2>
            <p class="text-sm text-slate-500 mt-1">عرض وطباعة السجل الأكاديمي الرسمي المعتمد للطالب.</p>
        </div>
        <?php if ($student): ?>
        <button onclick="window.print()" class="bg-primary text-white px-8 py-4 rounded-3xl font-black shadow-xl shadow-primary/20 hover:bg-slate-800 transition flex items-center gap-3">
            <i class="fas fa-print"></i> طباعة البيان الرسمي
        </button>
        <?php endif; ?>
    </div>

    <!-- Search Box -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 no-print">
        <form method="GET" class="flex gap-3 items-center flex-wrap">
            <div class="relative flex-1 min-w-[250px]">
                <input type="text" name="q" value="<?= htmlspecialchars($search_term) ?>" placeholder="ابحث بالاسم أو رقم القيد..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 pr-12 text-sm font-bold focus:ring-4 focus:ring-primary/5 outline-none">
                <i class="fas fa-search absolute right-5 top-5 text-slate-300"></i>
            </div>
            <button type="submit" class="bg-slate-800 text-white px-8 py-4 rounded-2xl text-sm font-black hover:bg-primary transition">بحث</button>
            <?php if ($search_term || $student_id): ?>
            <a href="student_grade_report.php" class="bg-slate-50 text-slate-500 px-6 py-4 rounded-2xl text-sm font-bold border border-slate-200 hover:bg-white transition flex items-center gap-2">مسح</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($results) && $student_id === 0): ?>
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-100 font-black text-slate-500 text-[10px] uppercase tracking-widest">نتائج البحث (<?= count($results) ?>)</div>
        <div class="divide-y divide-slate-50">
            <?php foreach ($results as $r): ?>
            <div class="flex items-center justify-between p-5 hover:bg-slate-50/50 transition-all">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-black text-xl shadow-inner"><?= mb_substr($r['full_name'], 0, 1, 'UTF-8') ?></div>
                    <div>
                        <div class="font-black text-slate-800"><?= htmlspecialchars($r['full_name']) ?></div>
                        <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($r['username']) ?> | <?= htmlspecialchars($r['college_name']) ?></div>
                    </div>
                </div>
                <a href="student_grade_report.php?id=<?= $r['id'] ?>&q=<?= urlencode($search_term) ?>" class="bg-primary text-white px-5 py-2.5 rounded-xl text-[11px] font-black hover:bg-slate-800 shadow-lg shadow-primary/10">عرض البيان</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($student): ?>
<div class="max-w-5xl mx-auto space-y-6 mt-12 pb-12">
    <!-- Official Header (hidden on screen by CSS, but I simplified it to ALWAYS show now per user's earlier request) -->
    <?php require_once 'includes/report_header_print.php'; ?>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 flex flex-wrap gap-8 items-center relative overflow-hidden">
        <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-bl-[100px] no-print"></div>
        <div class="w-24 h-24 rounded-3xl bg-slate-100 text-slate-400 flex items-center justify-center text-4xl font-black shadow-inner flex-shrink-0">
             <i class="fas fa-user-circle"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-2xl font-black text-slate-800 mb-2"><?= htmlspecialchars($student['full_name']) ?></h3>
            <div class="flex flex-wrap gap-6 text-[11px] font-black uppercase tracking-wider text-slate-400 font-mono">
                <span>رقم القيد: <span class="text-primary"><?= htmlspecialchars($student['username']) ?></span></span>
                <span>الكلية: <span class="text-slate-600"><?= htmlspecialchars($student['college_name']) ?></span></span>
                <span>المعدل: <span class="text-emerald-500"><?= number_format($cumulative_gpa, 2) ?></span></span>
            </div>
        </div>
    </div>

    <!-- Per-Semester Tables -->
    <?php foreach ($semesters as $semester => $courses): 
        $sem_points = 0; $sem_hours = 0; $graded_count = 0;
        foreach ($courses as $c) { if (!empty($c['grade'])) { $p = (float)($c['points'] ?? 0); $h = (int)($c['credit_hours'] ?? 0); $sem_points += $p * $h; $sem_hours += $h; $graded_count++; } }
        $sem_gpa = $sem_hours > 0 ? round($sem_points / $sem_hours, 2) : 0.00;
    ?>
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-black text-slate-800"><?= htmlspecialchars($semester) ?></h3>
            <div class="flex items-center gap-6">
                 <div class="text-center"><p class="text-[9px] font-black text-slate-400 uppercase">ساعات الفصل</p><p class="text-xl font-bold font-mono"><?= $sem_hours ?></p></div>
                 <div class="text-center"><p class="text-[9px] font-black text-slate-400 uppercase">معدل الفصل</p><p class="text-2xl font-black font-mono text-primary"><?= number_format($sem_gpa, 2) ?></p></div>
            </div>
        </div>
        <table class="w-full text-right text-sm transcript-table">
            <thead>
                <tr class="bg-slate-50/30 text-[10px] font-black uppercase tracking-widest text-slate-400">
                    <th class="p-4 w-12 text-center text-slate-300">#</th>
                    <th class="p-4">المقرر الدراسي</th>
                    <th class="p-4 text-center">الساعات</th>
                    <th class="p-4 text-center">التقدير</th>
                    <th class="p-4 text-center">النقاط</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($courses as $i => $c): 
                    $grade = $c['grade'] ?? ''; $points = (float)($c['points'] ?? 0); $credit_h = (int)($c['credit_hours'] ?? 0);
                ?>
                <tr class="hover:bg-slate-50/30 transition-all">
                    <td class="p-4 text-center text-slate-300 font-mono"><?= $i + 1 ?></td>
                    <td class="p-4"><div class="font-bold text-slate-800"><?= htmlspecialchars($c['name']) ?></div><div class="text-[9px] text-slate-400 font-mono"><?= htmlspecialchars($c['code']) ?></div></td>
                    <td class="p-4 text-center font-bold text-slate-600"><?= $credit_h ?></td>
                    <td class="p-4 text-center font-black"><span class="grade-pill"><?= htmlspecialchars($grade ?: '—') ?></span></td>
                    <td class="p-4 text-center font-mono font-bold"><?= $grade !== '' ? number_format($points, 2) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <div class="hidden print:flex justify-between items-end mt-16 px-12 border-t pt-8">
        <div class="text-center"><p class="font-black mb-12">رئيس الكنترول</p><div class="w-40 border-b border-black"></div></div>
        <div class="text-center flex flex-col items-center">
             <div class="w-24 h-24 border-2 border-slate-300 rounded-full flex items-center justify-center text-[8px] text-slate-400 text-center px-2 mb-4">ختم الكلية الرسمي</div>
        </div>
        <div class="text-center"><p class="font-black mb-12">عميد الكلية</p><div class="w-40 border-b border-black"></div></div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
