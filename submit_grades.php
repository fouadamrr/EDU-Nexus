<?php
require_once 'includes/header.php';
// المتغير $db متاح من ملف db.php اللي فوق

// التأكد من رتبة اليوزر اللي داخل
if (!in_array($role, ['super_admin', 'instructor', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
// لو دكتور، لازم يكون معاه صلاحية النتائج (results)
if ($role === 'instructor') {
 require_permission('results');
}

require_once __DIR__ . '/controllers/GradeController.php';
$gradeController = new GradeController();

$course_id = (int)($_GET['id'] ?? 0);
$selected_college_id = (int)($_GET['college_id'] ?? $_SESSION['college_id'] ?? 0);

// بنجيب بيانات المادة اللي هنشتغل عليها
$course = null;
if ($course_id > 0) {
 $course = $gradeController->getCourseDetails($course_id);
}

if (!$course) {
 // لو مفيش مادة مختارة، بنعرض اللستة كلها عشان اليوزر يختار مادة
 $q_list = "SELECT * FROM courses";
 $params = [];
 if (in_array($role, ['dean', 'affairs'])) {
 $q_list .= " WHERE college_id = :cid";
 $params[':cid'] = $_SESSION['college_id'];
 }
 $q_list .= " ORDER BY name ASC";
 $stmtL = $pdo->prepare($q_list);
 $stmtL->execute($params);
 $all_courses = $stmtL->fetchAll(PDO::FETCH_ASSOC);
 ?>
 <div class="max-w-4xl mx-auto space-y-6">
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
 <h2 class="text-2xl font-bold text-secondary mb-4 flex items-center gap-3">
 <i class="fas fa-check-double text-primary"></i> اعتماد نتائج المقررات الدراسية
 </h2>
 <p class="text-slate-500 mb-6">يرجى اختيار المقرر الدراسي لمراجعة درجات الطلاب واعتمادها نهائياً.</p>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
 <?php foreach ($all_courses as $c): ?>
 <a href="submit_grades.php?id=<?= $c['id'] ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'results') ?>" 
 class="p-4 border border-slate-100 rounded-xl hover:border-primary hover:bg-bg/30 transition-all flex items-center justify-between group">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-lg bg-bg flex items-center justify-center text-slate-400 group-hover:bg-primary group-hover:text-white transition-colors">
 <i class="fas fa-book text-sm"></i>
 </div>
 <div>
 <h4 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($c['name']) ?></h4>
 <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($c['code']) ?></span>
 </div>
 </div>
 <i class="fas fa-chevron-left text-slate-300 group-hover:text-primary transition-colors"></i>
 </a>
 <?php endforeach; ?>
 </div>
 </div>
 </div>
 <?php
 require_once 'includes/footer.php';
 exit;
}

$message = '';

// بننفذ عملية الحفظ أو الاعتماد لما اليوزر يدوس على الزرار
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $message = $gradeController->handleRequest($_POST, $course_id, $role);
}

// بنجيب لستة الطلاب والدرجات اللي اتسجلت ليهم في المادة دي
$students = $gradeController->getEnrolledStudents($course_id);
$grades_map = $gradeController->getExistingGrades($course_id);

$theory_m = (int)($course['theory_marks'] ?? 60);
$coursework_m = (int)($course['coursework_marks'] ?? 20);
$practical_m = (int)($course['practical_marks'] ?? 20);
$total_m = $theory_m + $coursework_m + $practical_m;

$results_locked = ($db->getSetting('results_locked') === '1');
// الأدمن بس هو اللي يقدر يفتح النتيجة لو كانت مقفولة
$can_edit = !($results_locked && !in_array($role, ['admin', 'super_admin']));
?>

<div class="max-w-5xl mx-auto space-y-6 animate-fade-in-up">

 <div class="flex items-center justify-between border-b border-gray-200 pb-4">
 <div>
 <h2 class="text-2xl font-bold text-gray-800">رصد الدرجات</h2>
 <p class="text-gray-500 mt-1">مقرر: <span class="font-bold text-primary">
 <?php echo htmlspecialchars($course['name']); ?>
 </span></p>
 </div>
 <div class="flex items-center gap-3">
 <?php if ($role !== 'instructor' && $can_edit): ?>
 <form method="POST" class="inline">
 <button type="submit" name="batch_approve" value="1"
 onclick="return confirm('هل أنت متأكد من حفظ واعتماد جميع الدرجات الظاهرة بالجدول؟');"
 class="bg-primary text-white px-5 py-2 rounded-xl font-bold hover:bg-primary shadow-md transition flex items-center gap-2 text-sm">
 <i class="fas fa-check-double"></i> اعتماد الكل
 </button>
 </form>
 <?php endif; ?>
 
 <a href="instructor_dashboard.php"
 class="bg-white border border-slate-200 text-gray-500 hover:text-primary px-4 py-2 rounded-xl transition flex items-center gap-2 text-sm">
 <i class="fas fa-arrow-right"></i> عودة
 </a>
 </div>
 </div>

 <?php if ($results_locked && !in_array($role, ['admin', 'super_admin'])): ?>
 <div class="bg-bg text-primary p-4 rounded-xl shadow-sm border border-primary flex items-center gap-3">
 <i class="fas fa-lock"></i> تم إغلاق رصد الدرجات من قبل الإدارة. لا يمكنك تعديل الدرجات.
 </div>
 <?php endif; ?>

 <?php echo $message; ?>

 <form method="POST" class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-200 text-gray-600 font-bold text-sm">
 <tr>
 <th class="p-4 w-16">#</th>
 <th class="p-4 w-1/4">اسم الطالب</th>
 <?php if($coursework_m > 0): ?><th class="p-4 text-center">أعمال السنة (<?php echo $coursework_m; ?>)</th><?php endif; ?>
 <?php if($practical_m > 0): ?><th class="p-4 text-center">عملي (<?php echo $practical_m; ?>)</th><?php endif; ?>
 <?php if($theory_m > 0): ?><th class="p-4 text-center">تحريري (<?php echo $theory_m; ?>)</th><?php endif; ?>
 <th class="p-4 text-center">المجموع (<?php echo $total_m; ?>)</th>
 <th class="p-4 text-center">نسبة مئوية</th>
 <th class="p-4 text-center">التقدير</th>
 <th class="p-4 text-center">الحالة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php foreach ($students as $index => $s):
 $g = $grades_map[$s['id']] ?? [];
 // لو فيه درجات متسجلة بالطريقة القديمة، بنجيبها عشان مفيش حاجة تضيع
 $cw_score = $g['coursework_score'] ?? $g['work_score'] ?? '';
 $pr_score = $g['practical_score'] ?? '';
 $th_score = $g['theory_score'] ?? $g['written_score'] ?? '';
 
 $grade = $g['grade'] ?? '';
 
 $total_val = ($cw_score !== '' || $pr_score !== '' || $th_score !== '') ? 
 ((float)$cw_score + (float)$pr_score + (float)$th_score) : '-';
 
 if ($total_val !== '-') {
 $percent = $total_m > 0 ? round(($total_val / $total_m) * 100, 2) : 0;
 } else {
 $percent = '-';
 }
 
 $readonly_attr = $can_edit ? '' : 'readonly';
 ?>
 <tr class="hover:bg-bg transition group">
 <td class="p-4 text-gray-400">
 <?php echo $index + 1; ?>
 </td>
 <td class="p-4 font-bold text-gray-700">
 <?php echo htmlspecialchars($s['full_name']); ?>
 <div class="text-xs text-gray-400 font-mono font-normal mt-1">
 <?php echo $s['username']; ?>
 </div>
 </td>
 
 <?php if($coursework_m > 0): ?>
 <td class="p-4 text-center">
 <input type="number" step="0.5" name="grades[<?php echo $s['id']; ?>][coursework]" min="0" max="<?php echo $coursework_m; ?>"
 class="w-20 text-center border border-gray-200 rounded p-1 focus:ring-2 focus:ring-primary focus:border-primary transition inputs-<?php echo $s['id']; ?>"
 placeholder="-" value="<?php echo $cw_score; ?>" <?php echo $readonly_attr; ?>
 onchange="calcTotal(<?php echo $s['id']; ?>, <?php echo $total_m; ?>)">
 </td>
 <?php endif; ?>
 
 <?php if($practical_m > 0): ?>
 <td class="p-4 text-center">
 <input type="number" step="0.5" name="grades[<?php echo $s['id']; ?>][practical]" min="0" max="<?php echo $practical_m; ?>"
 class="w-20 text-center border border-gray-200 rounded p-1 focus:ring-2 focus:ring-primary focus:border-primary transition inputs-<?php echo $s['id']; ?>"
 placeholder="-" value="<?php echo $pr_score; ?>" <?php echo $readonly_attr; ?>
 onchange="calcTotal(<?php echo $s['id']; ?>, <?php echo $total_m; ?>)">
 </td>
 <?php endif; ?>
 
 <?php if($theory_m > 0): ?>
 <td class="p-4 text-center">
 <input type="number" step="0.5" name="grades[<?php echo $s['id']; ?>][theory]" min="0" max="<?php echo $theory_m; ?>"
 class="w-20 text-center border border-gray-200 rounded p-1 focus:ring-2 focus:ring-primary focus:border-primary transition inputs-<?php echo $s['id']; ?>"
 placeholder="-" value="<?php echo $th_score; ?>" <?php echo $readonly_attr; ?>
 onchange="calcTotal(<?php echo $s['id']; ?>, <?php echo $total_m; ?>)">
 </td>
 <?php endif; ?>
 
 <td class="p-4 text-center font-bold text-gray-800">
 <span id="total-<?php echo $s['id']; ?>"><?php echo $total_val; ?></span>
 </td>
 <td class="p-4 text-center font-bold text-primary">
 <span id="percent-<?php echo $s['id']; ?>"><?php echo $percent; ?>%</span>
 </td>
 <td class="p-4 text-center">
 <span id="grade-<?php echo $s['id']; ?>" class="font-bold text-primary"><?php echo htmlspecialchars($grade); ?></span>
 <!-- التقدير بيتحسب لوحده أوتوماتيك واليوزر بيكتب الدرجات -->
 </td>
 <td class="p-4 text-center">
 <?php 
 $status = $g['status'] ?? 'pending';
 if ($status === 'approved'): ?>
 <span class="bg-primary text-white px-3 py-1 rounded-full text-xs font-bold flex items-center justify-center gap-1 mx-auto w-fit">
 <i class="fas fa-check"></i> معتمد
 </span>
 <input type="hidden" name="grades[<?php echo $s['id']; ?>][status]" value="approved">
 <?php else: ?>
 <span class="bg-primary text-white px-3 py-1 rounded-full text-xs font-bold flex items-center justify-center gap-1 mx-auto w-fit">
 <i class="fas fa-clock"></i> معلق
 </span>
 <?php if ($role !== 'instructor' && $can_edit): ?>
 <div class="mt-2">
 <input type="checkbox" name="grades[<?php echo $s['id']; ?>][status]" value="approved" id="app-<?php echo $s['id']; ?>" class="rounded text-primary focus:ring-primary">
 <label for="app-<?php echo $s['id']; ?>" class="text-[10px] text-slate-500 block">اعتماد الآن</label>
 </div>
 <?php elseif($can_edit): ?>
 <input type="hidden" name="grades[<?php echo $s['id']; ?>][status]" value="pending">
 <?php endif; ?>
 <?php endif; ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>

 <?php if($can_edit): ?>
 <div class="p-4 bg-bg border-t border-gray-200 flex justify-between items-center">
 <?php if ($role !== 'instructor'): ?>
 <button type="submit" name="batch_approve" value="1"
 class="bg-primary text-white px-6 py-3 rounded-lg font-bold hover:bg-primary shadow-md transition flex items-center gap-2">
 <i class="fas fa-check-double"></i> اعتماد جميع الدرجات
 </button>
 <?php else: ?>
 <div></div>
 <?php endif; ?>
 
 <button type="submit" name="save_grades" value="1"
 class="bg-primary text-white px-8 py-3 rounded-lg font-bold hover:bg-primary shadow-md transition flex items-center gap-2">
 <i class="fas fa-save"></i> حفظ البيانات
 </button>
 </div>
 <?php endif; ?>
 </form>

</div>

<script>
 function calcTotal(id, total_m) {
 const inputs = document.querySelectorAll(`.inputs-${id}`);
 let sum = 0;
 let valid = false;
 inputs.forEach(inp => {
 if (inp.value !== '') {
 valid = true;
 sum += parseFloat(inp.value);
 }
 });

 if (valid) {
 document.getElementById(`total-${id}`).innerText = sum;
 if (total_m > 0) {
 let percent = (sum / total_m) * 100;
 document.getElementById(`percent-${id}`).innerText = percent.toFixed(2) + '%';
 
 // حساب التقدير أوتوماتيك بناءً على النسبة المئوية
 let grade = 'F';
 if (percent >= 90) grade = 'A';
 else if (percent >= 85) grade = 'A-';
 else if (percent >= 80) grade = 'B+';
 else if (percent >= 75) grade = 'B';
 else if (percent >= 70) grade = 'B-';
 else if (percent >= 65) grade = 'C+';
 else if (percent >= 60) grade = 'C';
 else if (percent >= 56) grade = 'C-';
 else if (percent >= 53) grade = 'D+';
 else if (percent >= 50) grade = 'D';

 document.getElementById(`grade-${id}`).innerText = grade;
 }
 } else {
 document.getElementById(`total-${id}`).innerText = '-';
 document.getElementById(`percent-${id}`).innerText = '-';
 document.getElementById(`grade-${id}`).innerText = '-';
 }
 }
</script>

<?php require_once 'includes/footer.php'; ?>