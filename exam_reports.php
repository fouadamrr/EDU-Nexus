<?php
require_once 'includes/header.php';
/** @var PDO $pdo */

if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
require_permission('exams');

// Auto-create exam tables if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_committees (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location VARCHAR(255),
        capacity INT NOT NULL DEFAULT 30,
        current_count INT DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_distributions (
        id SERIAL PRIMARY KEY,
        student_id INT NOT NULL UNIQUE,
        committee_id INT NOT NULL,
        seat_number VARCHAR(50) NOT NULL UNIQUE,
        exam_number VARCHAR(20),
        FOREIGN KEY (committee_id) REFERENCES exam_committees(id) ON DELETE CASCADE
    )");
    try { $pdo->exec("ALTER TABLE exam_distributions ALTER COLUMN seat_number TYPE VARCHAR(50)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE exam_distributions ADD CONSTRAINT unique_seat_number UNIQUE (seat_number)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE exam_distributions ADD COLUMN IF NOT EXISTS exam_number VARCHAR(20)"); } catch(Exception $e){}
} catch (Exception $e) {}

$type = $_GET['type'] ?? 'all';
$college_scope = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? null) : null;

$report_titles = [
    'seats' => 'كشف مقاعد الطلاب بالفصل الامتحاني',
    'distribution_sheet' => 'كشف توزيع الطلاب على اللجان',
    'no_seats' => 'طلاب بلا مقاعد امتحانية',
    'all' => 'تقارير الامتحانات',
];
$title = $report_titles[$type] ?? $report_titles['all'];

$logo_path = 'assets/images/logo.png';
$logo_b64 = '';
if (file_exists($logo_path)) {
    $logo_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
}
?>

<style>
@media print {
    header, .no-print, .sidebar, aside, #sidebar, .horizontal-tabs, .fixed, .print-hidden, #notif-btn, footer {
        display: none !important;
    }
    body, html { height: auto !important; overflow: visible !important; background: #fff !important; margin: 0; padding: 0; }
    main { display: block !important; overflow: visible !important; padding: 0 !important; margin: 0 !important; width: 100% !important; background: transparent !important; }
    .print-container { width: 100% !important; max-width: none !important; box-shadow: none !important; border: none !important; padding: 0 !important; margin: 0 !important; }
    
    /* Watermark Effect */
    body::before {
        content: "";
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 400px;
        height: 400px;
        background: url('<?php echo $logo_b64; ?>') no-repeat center;
        background-size: contain;
        opacity: 0.05;
        z-index: -1;
    }

    table { width: 100% !important; border: 1.5px solid #000 !important; border-collapse: collapse !important; font-size: 11px !important; }
    thead { display: table-header-group !important; } /* This ensures thead repeats on every page */
    th { border: 1px solid #000 !important; padding: 8px !important; }
    td { border: 1px solid #000 !important; padding: 6px 8px !important; }
    
    .print-break { page-break-before: always; }

    /* Custom Official Header Style */
    .report-official-header {
        width: 100%;
        border-bottom: 3.5px solid #000;
        padding-bottom: 15px;
        margin-bottom: 25px;
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        background: white;
    }
}

.report-official-header {
    width: 100%;
    border-bottom: 3.5px solid #334155;
    padding-bottom: 15px;
    margin-bottom: 25px;
    display: flex !important;
    align-items: center;
    justify-content: space-between;
}
.header-logo-box {
    width: 60px;
    height: 60px;
    border: 2px solid #000;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 5px;
    margin-bottom: 5px;
}
</style>

<div class="space-y-6 animate-fade-in-up max-w-7xl mx-auto print-container">

    <!-- Dashboard UI (no-print) -->
    <div class="relative overflow-hidden bg-primary rounded-3xl p-8 text-white shadow-card mb-8 no-print">
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div>
                <h1 class="text-3xl font-bold mb-2 flex items-center gap-3">
                    <i class="fas fa-file-invoice text-4xl"></i> <?php echo $title; ?>
                </h1>
                <p class="text-white/80 font-medium">التقارير والكشوفات الرسمية المعتمدة للمؤسسة التعليمية.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="exam_reports.php?type=all" class="bg-white/20 px-5 py-2.5 rounded-xl font-bold hover:bg-white/30 text-xs">جميع التقارير</a>
                <?php if ($type !== 'all'): ?>
                    <button onclick="window.print()" class="bg-white text-primary px-7 py-3.5 rounded-xl font-black shadow-lg flex items-center gap-2">
                        <i class="fas fa-print"></i> طباعة كشوفات اللجان
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($type === 'all'): ?>
    <!-- List of Reports (no-print) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 no-print">
        <a href="exam_reports.php?type=seats" class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 hover:border-primary transition-all flex flex-col gap-4">
            <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-3xl"><i class="fas fa-chair"></i></div>
            <h3 class="font-bold text-xl">كشف مقاعد الطلاب</h3>
            <p class="text-slate-400 text-sm">كشف حضور الطلاب موزَّعين حسب اللجان مع مكان مخصص لتوقيع الطالب.</p>
        </a>
        <a href="exam_reports.php?type=distribution_sheet" class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 hover:border-success transition-all flex flex-col gap-4">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-3xl"><i class="fas fa-tasks"></i></div>
            <h3 class="font-bold text-xl">ملخص توزيع اللجان</h3>
            <p class="text-slate-400 text-sm">تقرير إحصائي يوضح نسب الإشغال والسعة في كل لجنة.</p>
        </a>
        <a href="exam_reports.php?type=no_seats" class="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 hover:border-rose-500 transition-all flex flex-col gap-4">
            <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-3xl"><i class="fas fa-user-times"></i></div>
            <h3 class="font-bold text-xl">طلاب بلا لجان</h3>
            <p class="text-slate-400 text-sm">كشف بالطلاب الذين لم يتم تخصيص مقاعد امتحانية لهم بعد.</p>
        </a>
    </div>

    <?php elseif ($type === 'seats'): ?>
    <?php
    $params = [];
    $join_college = "";
    if ($college_scope) {
        $join_college = "JOIN students st ON u.id = st.user_id AND st.college_id = ?";
        $params[] = $college_scope;
    }
    $sql = "SELECT ec.name AS committee_name, ec.location AS committee_location, d.seat_number, 
    u.full_name, s.level, c.name AS college_name
    FROM exam_distributions d
    JOIN exam_committees ec ON d.committee_id = ec.id
    JOIN users u ON d.student_id = u.id
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN colleges c ON s.college_id = c.id
    $join_college
    ORDER BY ec.name, d.seat_number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groups = [];
    foreach ($rows as $r) { $groups[$r['committee_name']][] = $r; }
    ?>

    <?php 
    $idx = 0;
    foreach ($groups as $cname => $students): 
        if ($idx > 0) echo '<div class="print-break"></div>';
        $idx++;
        $student_count = count($students);
    ?>
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden mb-8">
        <table class="w-full text-right text-sm">
            <thead>
                <!-- Official Repeating Header in thead -->
                <tr>
                    <th colspan="6" class="p-0 border-none">
                        <div class="report-official-header no-print-background">
                            <div style="text-align: right; min-width: 180px;">
                                <div class="text-[14px] font-black uppercase">جامعة EDU Nexus</div>
                                <div class="text-[12px] font-bold">شئون الطلاب</div>
                            </div>
                            <div style="text-align: center; flex: 1;">
                                <div class="header-logo-box mx-auto">
                                    <img src="<?php echo $logo_b64; ?>" alt="Logo" style="width:100%; height:100%; object-fit:contain;">
                                </div>
                                <h2 class="text-[16px] font-black"><?php echo $title; ?></h2>
                                <div class="text-[11px] font-bold text-slate-600"><?php echo htmlspecialchars($cname); ?></div>
                            </div>
                            <div style="text-align: left; min-width: 180px; font-size: 10px;">
                                <div>تاريخ الاستخراج: <?php echo date('Y/m/d'); ?></div>
                                <div class="mt-1 font-black">إجمالي الطلاب باللجنة: <?php echo $student_count; ?></div>
                            </div>
                        </div>
                    </th>
                </tr>
                <!-- Table Columns Header -->
                <tr class="bg-slate-50">
                    <th class="w-10 text-center">#</th>
                    <th class="w-28 text-center text-primary">رقم الجلوس</th>
                    <th>اسم الطالب الثلاثي</th>
                    <th class="w-20 text-center">الفرقة</th>
                    <th class="w-44 text-center">توقيع الطالب</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $i => $s): ?>
                <tr>
                    <td class="text-center text-slate-400 font-mono text-[10px]"><?php echo $i+1; ?></td>
                    <td class="text-center font-black text-primary text-[14px]"><?php echo htmlspecialchars($s['seat_number']); ?></td>
                    <td class="font-black text-slate-800"><?php echo htmlspecialchars($s['full_name']); ?></td>
                    <td class="text-center"><span class="bg-slate-100 px-3 py-1 rounded text-[10px] font-black"><?php echo htmlspecialchars($s['level'] ?: '—'); ?></span></td>
                    <td class="text-center"><div style="border-bottom: 1.5px dotted #000; height: 14px; width: 140px; margin: 0 auto; opacity: 0.6;"></div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <?php elseif ($type === 'distribution_sheet'): ?>
    <!-- Distribution Sheet logic remains similar but with headers -->
    <?php
    $committees = $pdo->query("SELECT *, (capacity - current_count) AS vacant FROM exam_committees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-right text-sm">
            <thead>
                <tr>
                    <th colspan="6" class="p-0 border-none">
                        <div class="report-official-header">
                            <div style="text-align: right; min-width: 180px;"><div class="text-lg font-black">جامعة EDU Nexus</div><div class="text-sm font-bold">شئون الطلاب</div></div>
                            <div style="text-align: center; flex: 1;">
                                <div class="header-logo-box mx-auto"><img src="<?php echo $logo_b64; ?>" alt="Logo" style="width:100%; height:100%; object-fit:contain;"></div>
                                <h1 class="text-xl font-black"><?php echo $title; ?></h1>
                            </div>
                            <div style="text-align: left; min-width: 180px; font-size: 10px;"><div>تاريخ: <?php echo date('Y/m/d'); ?></div></div>
                        </div>
                    </th>
                </tr>
                <tr class="bg-slate-50">
                    <th class="w-12 text-center">#</th>
                    <th>اسم اللجنة</th>
                    <th>الموقع</th>
                    <th class="text-center">السعة</th>
                    <th class="text-center">المشغول</th>
                    <th class="text-center">المتبقي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($committees as $i => $c): ?>
                <tr><td class="text-center"><?php echo $i+1; ?></td><td class="font-bold"><?php echo htmlspecialchars($c['name']); ?></td><td><?php echo htmlspecialchars($c['location'] ?: '—'); ?></td><td class="text-center"><?php echo $c['capacity']; ?></td><td class="text-center font-bold text-primary"><?php echo $c['current_count']; ?></td><td class="text-center"><?php echo $c['vacant']; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($type === 'no_seats'): ?>
    <!-- Similar logic for no_seats -->
    <?php
    $ns_sql = "SELECT u.full_name, u.username, s.level, c.name AS college_name FROM users u LEFT JOIN students s ON u.id = s.user_id LEFT JOIN colleges c ON s.college_id = c.id WHERE u.role = 'student' AND u.id NOT IN (SELECT student_id FROM exam_distributions)";
    $no_seats = $pdo->query($ns_sql)->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-right text-sm">
            <thead>
                <tr>
                    <th colspan="5" class="p-0 border-none">
                        <div class="report-official-header" style="border-bottom-color: #be123c;">
                            <div style="text-align: right; min-width: 180px;"><div class="text-lg font-black text-rose-700">جامعة EDU Nexus</div></div>
                            <div style="text-align: center; flex: 1;">
                                <div class="header-logo-box mx-auto" style="border-color:#be123c;"><img src="<?php echo $logo_b64; ?>" alt="Logo" style="width:100%; height:100%; object-fit:contain;"></div>
                                <h1 class="text-xl font-black text-rose-700"><?php echo $title; ?></h1>
                            </div>
                            <div style="text-align: left; min-width: 180px; font-size: 10px;"><div>تاريخ: <?php echo date('Y/m/d'); ?></div><div class="font-bold text-rose-600">الإجمالي: <?php echo count($no_seats); ?></div></div>
                        </div>
                    </th>
                </tr>
                <tr class="bg-slate-50">
                    <th class="w-12 text-center">#</th><th>الاسم</th><th class="text-center">رقم القيد</th><th class="text-center">الفرقة</th><th>الكلية</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($no_seats as $i => $s): ?>
                <tr><td><?php echo $i+1; ?></td><td class="font-bold text-rose-900"><?php echo htmlspecialchars($s['full_name']); ?></td><td class="text-center"><?php echo htmlspecialchars($s['username']); ?></td><td class="text-center"><?php echo htmlspecialchars($s['level'] ?: '—'); ?></td><td><?php echo htmlspecialchars($s['college_name'] ?? '—'); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<style>
.animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once 'includes/footer.php'; ?>
