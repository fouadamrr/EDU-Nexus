<?php
require_once __DIR__ . '/includes/header.php';

// Check role
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('students');


$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Render Search Interface if no student is selected
if ($student_id <= 0) {
 $search_term = $_GET['q'] ?? '';
 $results = [];
 
 if ($search_term !== '') {
 $filter_college = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? 0) : 0;
 $q_str = "SELECT id, username, full_name FROM users WHERE role = 'student' AND (full_name ILIKE :q OR username ILIKE :q)";
 $params = [':q' => "%$search_term%"];
 if ($filter_college > 0) {
 $q_str .= " AND college_id = :cid";
 $params[':cid'] = $filter_college;
 }
 $q_str .= " LIMIT 20";
 $stmt = $pdo->prepare($q_str);
 $stmt->execute($params);
 $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
 }
 
 ?>
 <div class="max-w-4xl mx-auto animate-fade-in-up mt-8">
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 mb-6">
 <h2 class="text-2xl font-bold text-secondary mb-6 flex items-center gap-3">
 <i class="fas fa-chart-pie text-primary bg-bg p-3 rounded-xl"></i> 
 تقرير توزيعات تسجيل طالب
 </h2>
 <form method="GET" action="report_distribution.php" class="flex flex-col md:flex-row gap-4 relative">
 <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'programs') ?>">
 <div class="flex-1">
 <input type="text" name="q" value="<?= htmlspecialchars($search_term) ?>" placeholder="ابحث برقم القيد أو اسم الطالب لاستخراج التقرير..." class="w-full border border-gray-300 rounded-xl p-4 pr-12 text-lg focus:ring-2 focus:ring-primary outline-none">
 <i class="fas fa-search absolute right-5 top-5 text-gray-400 text-xl"></i>
 </div>
 <button type="submit" class="bg-primary hover:bg-accent hover:text-white text-white font-bold px-8 py-4 rounded-xl shadow-md transition-all">بحث</button>
 </form>
 </div>

 <?php if ($search_term !== ''): ?>
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="p-4 bg-bg border-b border-slate-100 font-bold text-slate-700">نتائج البحث</div>
 <div class="divide-y divide-slate-100">
 <?php if (empty($results)): ?>
 <div class="p-8 text-center text-slate-500 font-medium">لم يتم العثور على أي طلاب مطابقين لبحثك.</div>
 <?php else: ?>
 <?php foreach ($results as $res): ?>
 <div class="flex items-center justify-between p-4 hover:bg-bg">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 rounded-full bg-bg text-white flex items-center justify-center font-bold text-lg">
 <?= mb_substr($res['full_name'], 0, 1, 'UTF-8') ?>
 </div>
 <div>
 <h4 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($res['full_name']) ?></h4>
 <span class="text-sm text-slate-500"><i class="fas fa-id-card ml-1"></i><?= htmlspecialchars($res['username']) ?></span>
 </div>
 </div>
 <a href="report_distribution.php?id=<?= $res['id'] ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'programs') ?>" class="bg-white border border-slate-200 hover:border-primary hover:text-primary px-5 py-2 rounded-lg font-bold text-sm shadow-sm transition-all flex items-center gap-2">
 <i class="fas fa-chart-pie"></i> عرض التقرير
 </a>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>
 </div>
 <?php endif; ?>
 </div>
 <?php
 require_once 'includes/footer.php';
 exit;
}

// Ensure Chart.js is included in header or add it here
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

// Load student info
$stmt = $pdo->prepare("
 SELECT u.full_name, u.username as academic_number, s.college_id, s.level, c.name as college_name 
 FROM users u 
 JOIN students s ON u.id = s.user_id 
 LEFT JOIN colleges c ON s.college_id = c.id
 WHERE u.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
 echo "<div class='p-8 text-center text-primary font-bold'>الطالب غير موجود.</div>";
 require_once 'includes/footer.php';
 exit;
}

if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id'])) {
 if ($student['college_id'] != $_SESSION['college_id']) {
 echo "<div class='p-8 text-center text-accent font-bold'>غير مصرح لك بعرض بيانات هذا الطالب لكونه في كلية أخرى.</div>";
 require_once 'includes/footer.php';
 exit;
 }
}

// Fetch all enrollments + credit hours
$query = "
 SELECT e.status, e.semester, c.credit_hours 
 FROM enrollments e 
 JOIN courses c ON e.course_id = c.id 
 WHERE e.user_id = :uid
";
$stmtEn = $pdo->prepare($query);
$stmtEn->execute([':uid' => $student_id]);
$enrollments = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

$stats = [
 'active_courses' => 0,
 'dropped_courses' => 0,
 'completed_courses' => 0,
 'total_credits_completed' => 0,
 'total_credits_active' => 0
];

foreach ($enrollments as $e) {
 $status = $e['status'] ?? 'enrolled';
 $credits = (int)$e['credit_hours'];
 
 // In our system 'enrolled' is the active status
 if ($status === 'enrolled' || $status === 'active') {
 $stats['active_courses']++;
 $stats['total_credits_active'] += $credits;
 } elseif ($status === 'dropped') {
 $stats['dropped_courses']++;
 } elseif ($status === 'completed' || $status === 'finished') {
 $stats['completed_courses']++;
 $stats['total_credits_completed'] += $credits;
 }
}

// Mock total requirement based on level x 15 (Assume 140 is total for graduation)
$total_required_credits = 140; 
$completion_percentage = $total_required_credits > 0 ? round(($stats['total_credits_completed'] / $total_required_credits) * 100, 1) : 0;
if ($completion_percentage > 100) $completion_percentage = 100;

?>

<div class="max-w-6xl mx-auto space-y-6 animate-fade-in-up">

 <div class="flex items-center justify-between no-print">
 <div>
 <h2 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-chart-pie text-primary"></i>
 تقرير توزيعات التسجيل لطالب
 </h2>
 <p class="text-slate-500 text-sm mt-1">يعرض ملخصاً تحليلياً شاملاً لسجل الطالب والمقررات المكتملة والمتبقية.</p>
 </div>
 <div class="flex gap-2">
 <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold hover:text-primary transition-colors flex items-center gap-2">
 <i class="fas fa-print"></i> طباعة
 </button>
 <a href="report_distribution.php?tab=<?= htmlspecialchars($_GET['tab'] ?? 'programs') ?>" class="bg-bg text-slate-600 px-4 py-2 rounded-lg font-bold hover:bg-slate-200 transition-colors">
 طالب آخر
 </a>
 </div>
 </div>

 <!-- Printable Header Card -->
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col md:flex-row items-center gap-6">
 <div class="w-20 h-20 rounded-full bg-bg border border-slate-200 text-slate-400 flex items-center justify-center text-3xl print:border-black print:text-black">
 <i class="fas fa-user-graduate"></i>
 </div>
 <div class="flex-1 text-center md:text-right">
 <h3 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($student['full_name']) ?></h3>
 <div class="flex flex-wrap justify-center md:justify-start gap-4 mt-3 text-sm text-slate-600 font-bold">
 <span class="bg-bg px-3 py-1 text-slate-600 rounded-md border border-slate-200 print:border-none print:p-0"><i class="fas fa-id-card ml-1 text-slate-400"></i> القيد: <?= htmlspecialchars($student['academic_number']) ?></span>
 <span class="bg-bg px-3 py-1 text-slate-600 rounded-md border border-slate-200 print:border-none print:p-0"><i class="fas fa-university ml-1 text-slate-400"></i> <?= htmlspecialchars($student['college_name'] ?? 'بدون كلية') ?></span>
 <span class="bg-bg px-3 py-1 text-slate-600 rounded-md border border-slate-200 print:border-none print:p-0"><i class="fas fa-layer-group ml-1 text-slate-400"></i> الفرقة: <?= $student['level'] ?></span>
 </div>
 </div>
 </div>

 <!-- Stats Grid -->
 <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
 <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 text-center">
 <div class="w-12 h-12 bg-bg text-primary rounded-full flex items-center justify-center mx-auto mb-3 text-xl"><i class="fas fa-book-open"></i></div>
 <p class="text-slate-500 text-sm font-bold">مواد تُدرس حالياً</p>
 <h4 class="text-2xl font-black text-slate-800 mt-1"><?= $stats['active_courses'] ?></h4>
 <span class="text-xs text-accent font-bold"><?= $stats['total_credits_active'] ?> ساعات معتمدة</span>
 </div>
 <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 text-center">
 <div class="w-12 h-12 bg-bg text-primary rounded-full flex items-center justify-center mx-auto mb-3 text-xl"><i class="fas fa-check-double"></i></div>
 <p class="text-slate-500 text-sm font-bold">مواد مجتازة (مكتملة)</p>
 <h4 class="text-2xl font-black text-slate-800 mt-1"><?= $stats['completed_courses'] ?></h4>
 <span class="text-xs text-accent font-bold"><?= $stats['total_credits_completed'] ?> ساعات معتمدة</span>
 </div>
 <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 text-center">
 <div class="w-12 h-12 bg-bg text-primary rounded-full flex items-center justify-center mx-auto mb-3 text-xl"><i class="fas fa-ban"></i></div>
 <p class="text-slate-500 text-sm font-bold">مواد منسحب منها (W)</p>
 <h4 class="text-2xl font-black text-slate-800 mt-1"><?= $stats['dropped_courses'] ?></h4>
 </div>
 <div class="bg-white p-6 rounded-xl shadow-sm border-slate-100 border text-center flex flex-col justify-center relative overflow-hidden">
 <div class="absolute inset-0 bg-bg opacity-5"></div>
 <p class="text-slate-500 text-sm font-bold relative z-10 block mb-2">نسبة إنجاز المتطلبات (تقريبي)</p>
 <div class="relative w-24 h-24 mx-auto z-10 flex items-center justify-center rounded-full border-4 border-slate-100 overflow-hidden" 
 style="background: conic-gradient(#4f46e5 <?= $completion_percentage ?>%, transparent 0);">
 <div class="absolute inset-2 bg-white rounded-full flex items-center justify-center text-lg font-black text-primary">
 <?= $completion_percentage ?>%
 </div>
 </div>
 <p class="text-xs text-slate-400 mt-2 font-bold relative z-10">متبقي <?= ($total_required_credits - $stats['total_credits_completed']) ?> ساعة للتخرج</p>
 </div>
 </div>

 <!-- Charts area - hidden on print for brevity or adjust as needed -->
 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 no-print">
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 h-80 flex flex-col">
 <h3 class="font-bold text-slate-700 mb-4 text-sm flex items-center gap-2"><i class="fas fa-chart-bar text-slate-400"></i> توزيع حالة المقررات</h3>
 <div class="flex-1 relative">
 <canvas id="statusChart"></canvas>
 </div>
 </div>
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 h-80 flex flex-col">
 <h3 class="font-bold text-slate-700 mb-4 text-sm flex items-center gap-2"><i class="fas fa-chart-pie text-slate-400"></i> الساعات المعتمدة المكتسبة</h3>
 <div class="flex-1 relative">
 <canvas id="creditsChart"></canvas>
 </div>
 </div>
 </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
 const ctxStatus = document.getElementById('statusChart').getContext('2d');
 new Chart(ctxStatus, {
 type: 'doughnut',
 data: {
 labels: ['تدرس حالياً', 'مكتملة', 'تم الانسحاب منها'],
 datasets: [{
 data: [<?= $stats['active_courses'] ?>, <?= $stats['completed_courses'] ?>, <?= $stats['dropped_courses'] ?>],
 backgroundColor: ['#6366f1', '#10b981', '#f87171'],
 borderWidth: 0
 }]
 },
 options: {
 responsive: true,
 maintainAspectRatio: false,
 cutout: '70%',
 plugins: {
 legend: { position: 'bottom', labels: { font: { family: 'Tajawal, sans-serif' } } }
 }
 }
 });

 const ctxCredits = document.getElementById('creditsChart').getContext('2d');
 new Chart(ctxCredits, {
 type: 'bar',
 data: {
 labels: ['المكتسبة', 'المتبقية'],
 datasets: [{
 label: 'ساعات معتمدة',
 data: [<?= $stats['total_credits_completed'] ?>, <?= max(0, $total_required_credits - $stats['total_credits_completed']) ?>],
 backgroundColor: ['#10b981', '#e2e8f0'],
 borderRadius: 8
 }]
 },
 options: {
 responsive: true,
 maintainAspectRatio: false,
 scales: {
 y: { beginAtZero: true, grid: { borderDash: [5,5] } }
 },
 plugins: {
 legend: { display: false }
 }
 }
 });
});
</script>

<style>
@media print {
 body { background-color: white !important; }
 .no-print { display: none !important; }
 .shadow-sm { box-shadow: none !important; }
 .border { border: 1px solid #ddd !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
