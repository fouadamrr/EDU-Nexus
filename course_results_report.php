<?php
require_once 'includes/header.php';

// Role Check
if (!in_array($role, ['super_admin', 'admin', 'dean'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('results');


$course_id = (int)($_GET['id'] ?? 0);
require_once __DIR__ . '/controllers/GradeController.php';
$gradeController = new GradeController();

$course = $gradeController->getCourseDetails($course_id);
if (!$course) {
 echo "<div class='p-8 text-center text-accent font-bold'>المقرر غير موجود أو غير مصرح لك بعرضه</div>";
 require_once 'includes/footer.php';
 exit;
}

$students = $gradeController->getEnrolledStudents($course_id);
$grades_map = $gradeController->getExistingGrades($course_id);

$total_m = (int)($course['theory_marks'] ?? 60) + (int)($course['coursework_marks'] ?? 20) + (int)($course['practical_marks'] ?? 20);

$total_students = count($students);
$passed_students = 0;
$failed_students = 0;
$pending_students = 0;
$grades_distribution = ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'F'=>0];

$student_results = [];

foreach ($students as $s) {
 $g = $grades_map[$s['id']] ?? null;
 if ($g) {
 $cw = (float)($g['coursework_score'] ?? $g['work_score'] ?? 0);
 $pr = (float)($g['practical_score'] ?? 0);
 $th = (float)($g['theory_score'] ?? $g['written_score'] ?? 0);
 $total_val = $cw + $pr + $th;
 
 $percent = $total_m > 0 ? round(($total_val / $total_m) * 100, 2) : 0;
 $passed = $percent >= 50;
 
 if ($passed) $passed_students++;
 else $failed_students++;
 
 $l = $g['grade'] ?? '';
 if (strpos($l, 'A') !== false) $grades_distribution['A']++;
 elseif (strpos($l, 'B') !== false) $grades_distribution['B']++;
 elseif (strpos($l, 'C') !== false) $grades_distribution['C']++;
 elseif (strpos($l, 'D') !== false) $grades_distribution['D']++;
 elseif ($l === 'F') $grades_distribution['F']++;
 } else {
 $pending_students++;
 $percent = 0;
 $passed = false;
 $l = '-';
 }
 
 $student_results[] = [
 'name' => $s['full_name'],
 'username' => $s['username'],
 'percent' => $percent,
 'passed' => $passed,
 'grade' => $l
 ];
}

$pass_rate = $total_students > 0 ? round(($passed_students / $total_students) * 100, 1) : 0;
$fail_rate = $total_students > 0 ? round(($failed_students / $total_students) * 100, 1) : 0;

?>

<div class="max-w-6xl mx-auto space-y-6 animate-fade-in-up print:m-0 print:max-w-none">

 <div class="flex items-center justify-between border-b border-gray-200 pb-4 print:hidden">
 <div>
 <h2 class="text-2xl font-bold text-gray-800">تقرير نتائج المقرر</h2>
 <p class="text-gray-500 mt-1">المقرر: <span class="font-bold text-primary"><?php echo htmlspecialchars($course['name']); ?></span> (<?php echo htmlspecialchars($course['code']); ?>)</p>
 </div>
 <div class="flex gap-3">
 <button onclick="window.print()" class="text-white bg-primary hover:bg-primary px-4 py-2 rounded-lg transition flex items-center gap-2 font-bold focus:ring-2 focus:ring-blue-300">
 <i class="fas fa-print"></i> طباعة التقرير
 </button>
 <a href="manage_courses.php" class="text-gray-500 bg-bg hover:bg-gray-200 hover:text-gray-800 px-4 py-2 rounded-lg transition flex items-center gap-2">
 <i class="fas fa-arrow-right"></i> عودة
 </a>
 </div>
 </div>

 <!-- Print Header -->
 <div class="hidden print:block text-center mb-8 pb-4 border-b-2 border-black">
 <h1 class="text-2xl font-bold mb-2">تعليم نكسس - التقرير الشامل لنتائج المقرر</h1>
 <h2 class="text-xl">مقرر: <?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['code']); ?>)</h2>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
 <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 text-center">
 <h3 class="text-slate-500 text-sm font-bold mb-2">إجمالي الطلاب</h3>
 <p class="text-3xl font-bold text-slate-800"><?php echo $total_students; ?></p>
 </div>
 <div class="bg-bg p-6 rounded-xl shadow-sm border border-primary text-center">
 <h3 class="text-primary text-sm font-bold mb-2">النجاح</h3>
 <p class="text-3xl font-bold text-primary"><?php echo $pass_rate; ?>% <span class="text-sm font-normal text-primary">(<?php echo $passed_students; ?> ط)</span></p>
 </div>
 <div class="bg-bg p-6 rounded-xl shadow-sm border border-primary text-center">
 <h3 class="text-primary text-sm font-bold mb-2">الرسوب</h3>
 <p class="text-3xl font-bold text-primary"><?php echo $fail_rate; ?>% <span class="text-sm font-normal text-primary">(<?php echo $failed_students; ?> ط)</span></p>
 </div>
 <div class="bg-bg p-6 rounded-xl shadow-sm border border-slate-200 text-center">
 <h3 class="text-slate-600 text-sm font-bold mb-2">في انتظار الرصد</h3>
 <p class="text-3xl font-bold text-slate-800"><?php echo $pending_students; ?></p>
 </div>
 </div>

 <!-- Grades Distribution -->
 <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 mb-8">
 <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">توزيع التقديرات</h3>
 <div class="flex flex-wrap gap-4 justify-between items-end h-32 pt-4">
 <?php foreach ($grades_distribution as $grade_key => $count): 
 $height = $total_students > 0 ? ($count / $total_students) * 100 : 0;
 ?>
 <div class="flex-1 flex flex-col items-center gap-2">
 <div class="w-12 bg-primary rounded-t-md transition-all relative group" style="height: <?php echo max($height, 5); ?>%">
 <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-xs font-bold text-gray-500"><?php echo $count; ?></span>
 </div>
 <span class="font-bold text-slate-700"><?php echo $grade_key; ?></span>
 </div>
 <?php endforeach; ?>
 </div>
 </div>

 <!-- Detailed List -->
 <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
 <div class="p-4 border-b border-gray-200 bg-bg">
 <h3 class="font-bold text-gray-800">بيان تفصيلي بدرجات الطلاب</h3>
 </div>
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-200 text-gray-600 font-bold text-sm">
 <tr>
 <th class="p-4 w-16">#</th>
 <th class="p-4 text-right">رقم القيد (الجامعي)</th>
 <th class="p-4 w-1/3 text-right">اسم الطالب</th>
 <th class="p-4 text-center">النسبة المئوية</th>
 <th class="p-4 text-center">التقدير</th>
 <th class="p-4 text-center">النتيجة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php foreach ($student_results as $i => $sr): ?>
 <tr class="hover:bg-bg transition">
 <td class="p-4 text-gray-500"><?php echo $i + 1; ?></td>
 <td class="p-4 font-mono text-gray-600"><?php echo htmlspecialchars($sr['username']); ?></td>
 <td class="p-4 font-bold text-gray-800"><?php echo htmlspecialchars($sr['name']); ?></td>
 <td class="p-4 text-center text-primary font-bold" dir="ltr"><?php echo $sr['percent']; ?>%</td>
 <td class="p-4 text-center font-bold text-primary"><?php echo $sr['grade']; ?></td>
 <td class="p-4 text-center">
 <?php if ($sr['grade'] === '-'): ?>
 <span class="text-gray-400">غير مرصود</span>
 <?php elseif ($sr['passed']): ?>
 <span class="text-primary font-bold bg-bg px-2 py-1 rounded">ناجح</span>
 <?php else: ?>
 <span class="text-primary font-bold bg-bg px-2 py-1 rounded">راسب</span>
 <?php endif; ?>
 </td>
 </tr>
 <?php endforeach; ?>
 
 <?php if (empty($student_results)): ?>
 <tr><td colspan="6" class="p-8 text-center text-gray-500 font-bold">لا يوجد طلاب مسجلون في هذا المقرر</td></tr>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
</div>

<style>
@media print {
 body { font-size: 14pt; background: #fff !important; }
 .shadow, .shadow-sm { box-shadow: none !important; }
 .border { border-color: #000 !important; }
 table th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
 * { background: transparent !important; color: #000 !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
