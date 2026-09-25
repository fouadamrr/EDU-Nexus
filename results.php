<?php
require_once 'includes/header.php';

require_once __DIR__ . '/controllers/ResultsController.php';
$resultsController = new ResultsController();

$requested_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$targetData = $resultsController->getTargetStudent($role, (int)$user_id, $full_name, $username, $requested_id);

$target_user_id = $targetData['id'];
$target_full_name = $targetData['full_name'];
$target_username = $targetData['username'];

$semesters = $resultsController->getStudentResults($target_user_id, $role);

// بنحسب المعدل التراكمي الإجمالي والساعات المكتملة
$total_all_points = 0.0;
$total_all_hours = 0;
foreach ($semesters as $semester => $courses) {
 foreach ($courses as $c) {
 if (!empty($c['grade'])) { // بنحسب بس المواد اللي ليها درجات فعلاً
 $p = isset($c['points']) && $c['points'] !== '' ? (float) $c['points'] : 0.0;
 $h = isset($c['credit_hours']) && $c['credit_hours'] !== '' ? (int) $c['credit_hours'] : 0;
 $total_all_points += $p * $h;
 $total_all_hours += $h;
 }
 }
}
$cumulative_gpa = $total_all_hours > 0 ? round($total_all_points / $total_all_hours, 2) : 0.00;
$academic_standing = $cumulative_gpa >= 2.0 ? 'منتظم' : ($total_all_hours > 0 ? 'إنذار أكاديمي' : 'جديد');
?>
<style>
 .result-card { animation: slideUp 0.6s ease forwards; opacity: 0; }
 @keyframes slideUp {
 from { opacity: 0; transform: translateY(20px); }
 to { opacity: 1; transform: translateY(0); }
 }
 .badge-grade {
 padding: 4px 12px;
 border-radius: 99px;
 font-weight: 800;
 font-size: 0.75rem;
 display: inline-flex;
 align-items: center;
 justify-content: center;
 min-width: 45px;
 }
  .grade-a { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
  .grade-b { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
  .grade-c { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
  .grade-f { background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; }
 
 [data-theme="dark"] .grade-a { background: rgba(16, 185, 129, 0.2); color: #34d399; }
 [data-theme="dark"] .grade-b { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
 [data-theme="dark"] .grade-c { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
 [data-theme="dark"] .grade-f { background: rgba(239, 68, 68, 0.2); color: #f87171; }
</style>

<div class="max-w-5xl mx-auto space-y-8 print:py-4 print:px-8 print:max-w-none relative">
 
 <!-- النسخة المطبوعة: خلفية العلامة المائية -->
 <div class="hidden print:flex fixed inset-0 items-center justify-center opacity-[0.05] pointer-events-none z-0 mt-40" style="print-color-adjust: exact; -webkit-print-color-adjust: exact;">
 <img src="assets/images/logo.png" alt="Watermark" class="w-[800px] h-[800px] object-contain grayscale mix-blend-multiply">
 </div>

 <!-- النسخة المطبوعة: هيدر المستند (اللوجو ع اليمين والعنوان ع الشمال) -->
 <div class="hidden print:flex flex-row justify-between items-start border-b-[2px] border-slate-300 pb-4 mb-6 mt-1">
 
 <!-- الجزء اليمين: اللوجو وبيانات الجامعة -->
 <div class="flex flex-col items-start gap-4">
 <!-- University Name & Logo -->
 <div class="flex items-center gap-3">
 <img src="assets/images/logo.png" alt="Logo" class="w-20 h-20 object-contain">
 <div class="space-y-0.5 text-right">
 <h1 class="text-2xl font-black text-slate-900 leading-tight">EDU Nexus</h1>
 <p class="text-slate-600 font-bold text-sm">منظومة التعليم الآمن</p>
 <p class="text-slate-500 font-medium text-xs">إدارة شئون الطلاب والامتحانات</p>
 </div>
 </div>

 <!-- بيانات الطالب في النسخة المطبوعة -->
 <div class="flex flex-col gap-1 text-xs px-3 border-r-[3px] border-slate-200 text-right pr-4">
 <p>
 <span class="text-slate-500 font-medium ml-1">اسم الطالب:</span>
 <span class="font-bold text-slate-800"><?php echo htmlspecialchars($target_full_name); ?></span>
 </p>
 <p>
 <span class="text-slate-500 font-medium ml-1">رقم الجلوس:</span>
 <span class="font-bold text-slate-800 font-mono"><?php echo htmlspecialchars($target_username); ?></span>
 </p>
 <p>
 <span class="text-slate-500 font-medium ml-1">العام الجامعي:</span>
 <span class="font-bold text-slate-800 font-mono">2025/2026</span>
 </p>
 </div>
 </div>

  <!-- الجزء الشمال: عنوان المستند -->
  <div class="mt-2 bg-indigo-50/50 p-4 rounded-xl border border-primary min-w-[200px]">
  <h2 class="text-xl font-black text-primary flex items-center justify-center gap-2">
  بيان نجاح / كشف درجات
  <i class="fas fa-graduation-cap text-primary mr-1"></i>
  </h2>
  </div>

 </div>

 <div class="flex items-center justify-between print:hidden">
 <div>
 <h2 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-graduation-cap text-accent"></i> 
 النتائج الدراسية
 </h2>
 <p class="text-slate-500 text-sm mt-1">عرض السجل الأكاديمي التفصيلي والمعدلات الفصلية والتراكمية.</p>
 </div>
 <button onclick="window.print()"
 class="no-print bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-primary hover:text-primary text-slate-600 dark:text-slate-300 px-5 py-2.5 rounded-xl shadow-sm transition-all flex items-center gap-2 text-sm font-bold">
 <i class="fas fa-print"></i> طباعة البيان الرسمية
 </button>
 </div>

 <!-- كروت الملخص الأكاديمي -->
 <div class="grid grid-cols-1 md:grid-cols-3 gap-6 print:hidden">
 <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center gap-5 result-card" style="animation-delay: 0.1s">
 <div class="w-14 h-14 rounded-2xl bg-bg dark:bg-primary/30 flex items-center justify-center text-primary dark:text-primary text-2xl">
 <i class="fas fa-chart-line"></i>
 </div>
 <div>
 <p class="text-slate-500 dark:text-slate-400 text-xs font-bold mb-1">المعدل التراكمي (GPA)</p>
 <h4 class="text-3xl font-black text-slate-800 dark:text-white font-mono"><?php echo $cumulative_gpa; ?></h4>
 </div>
 </div>
 
 <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center gap-5 result-card" style="animation-delay: 0.2s">
 <div class="w-14 h-14 rounded-2xl bg-bg dark:bg-primary/30 flex items-center justify-center text-primary dark:text-primary text-2xl">
 <i class="fas fa-clock"></i>
 </div>
 <div>
 <p class="text-slate-500 dark:text-slate-400 text-xs font-bold mb-1">إجمالي الساعات المكتسبة</p>
 <h4 class="text-3xl font-black text-slate-800 dark:text-white font-mono"><?php echo $total_all_hours; ?></h4>
 </div>
 </div>

 <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 flex items-center gap-5 result-card" style="animation-delay: 0.3s">
 <div class="w-14 h-14 rounded-2xl bg-bg dark:bg-primary/30 flex items-center justify-center text-primary dark:text-primary text-2xl">
 <i class="fas fa-user-shield"></i>
 </div>
 <div>
 <p class="text-slate-500 dark:text-slate-400 text-xs font-bold mb-1">الحالة الأكاديمية</p>
 <h4 class="text-xl font-bold text-slate-800 dark:text-white"><?php echo $academic_standing; ?></h4>
 </div>
 </div>
 </div>

 <?php if (empty($semesters)): ?>
 <div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 text-center">
 <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
 <p class="text-gray-500">لا توجد نتائج مسجلة حتى الآن.</p>
 </div>
 <?php else: ?>
 <?php 
 $idx = 0;
 foreach ($semesters as $semester => $courses):
 $semester_points = 0.0;
 $semester_hours = 0;
 foreach ($courses as $c) {
 if (!empty($c['grade'])) {
 $p = isset($c['points']) && $c['points'] !== '' ? (float) $c['points'] : 0.0;
 $h = isset($c['credit_hours']) && $c['credit_hours'] !== '' ? (int) $c['credit_hours'] : 0;
 $semester_points += $p * $h;
 $semester_hours += $h;
 }
 }
 $semester_gpa = $semester_hours > 0 ? round($semester_points / $semester_hours, 2) : 0.00;
 ?>
 <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden result-card md:hover:shadow-lg transition-shadow" style="animation-delay: <?php echo 0.4 + ($idx * 0.1); ?>s">
 <div class="bg-bg dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-700 p-5 flex justify-between items-center">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
 <i class="fas fa-calendar-check text-lg"></i>
 </div>
 <h3 class="font-bold text-lg text-slate-800 dark:text-white"><?php echo htmlspecialchars($semester); ?></h3>
 </div>
 <div class="flex items-center gap-4 text-sm font-bold">
 <span class="text-slate-500 dark:text-slate-400 hidden sm:inline">عدد المواد: <?php echo count($courses); ?></span>
 <span class="bg-primary text-white px-4 py-1.5 rounded-full shadow-sm">GPA: <?php echo $semester_gpa; ?></span>
 </div>
 </div>

 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-bg dark:bg-slate-900/30 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider">
 <tr>
 <th class="p-5 font-bold">المقرر الدراسي</th>
 <th class="p-5 text-center font-bold">الساعات</th>
 <th class="p-5 text-center font-bold">التقدير</th>
 <th class="p-5 text-center font-bold">النقاط</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-sm">
 <?php foreach ($courses as $course): 
 $grade = $course['grade'] ?? '';
 $clean_grade = trim(str_replace('(قيد الاعتماد)', '', $grade));
 $grade_class = 'grade-c';
 if (in_array($clean_grade, ['A', 'A-', 'B+'])) $grade_class = 'grade-a';
 elseif (in_array($clean_grade, ['B', 'C+'])) $grade_class = 'grade-b';
 elseif (in_array($clean_grade, ['F', 'D', 'D+'])) $grade_class = 'grade-f';
 ?>
 <tr class="hover:bg-bg dark:hover:bg-slate-900/20 transition-colors">
 <td class="p-5">
 <div class="font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($course['name'] ?? ''); ?></div>
 <div class="text-xs text-slate-400 font-mono mt-0.5"><?php echo htmlspecialchars($course['code'] ?? ''); ?></div>
 </td>
 <td class="p-5 text-center font-semibold text-slate-600 dark:text-slate-400"><?php echo $course['credit_hours'] ?? 0; ?></td>
 <td class="p-5 text-center">
 <span class="badge-grade <?php echo $grade_class; ?>">
 <?php echo htmlspecialchars($grade); ?>
 </span>
 </td>
 <td class="p-5 text-center font-mono font-bold text-slate-700 dark:text-slate-300"><?php echo number_format((float)($course['points'] ?? 0), 2); ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 <?php $idx++; ?>
 <?php endforeach; ?>
 <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>