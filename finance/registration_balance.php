<?php
require_once __DIR__ . '/includes/header.php';

// Check role
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('registration');


$message = '';
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_semester = "Equivalency " . date('Y');

// Handle saving balanced registration (Equivalency)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_balance'], $_POST['course_id'], $_POST['grade']) && $student_id > 0) {
 $course_id = (int)$_POST['course_id'];
 $grade = $_POST['grade'];
 $points = (float)($_POST['points'] ?? 0.0);
 
 if ($course_id > 0) {
 try {
 $stmt = $pdo->prepare("SELECT college_id FROM students WHERE user_id = ?");
 $stmt->execute([$student_id]);
 $college_id = $stmt->fetchColumn() ?: 0;
 
 $s_db = new UniversityDB((int)$college_id);
 
 // 1. Insert into enrollments as 'completed'
 // check if exists
 $existing = $s_db->findAll('enrollments', ['user_id' => $student_id, 'course_id' => $course_id]);
 if (!empty($existing)) {
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'>
 <i class='fas fa-exclamation-circle'></i> المقرر مسجل مسبقاً لهذا الطالب. يرجى تعديل حالته بدلاً من الموازنة.
 </div>";
 } else {
 $s_db->insert('enrollments', [
 'user_id' => $student_id,
 'course_id' => $course_id,
 'semester' => $current_semester,
 'status' => 'completed'
 ]);
 
 // 2. Insert into grades directly so it counts towards GPA
 $s_db->insert('grades', [
 'user_id' => $student_id,
 'course_id' => $course_id,
 'semester' => $current_semester,
 'grade' => $grade,
 'points' => $points,
 'total_marks' => 100
 ]);
 
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'>
 <i class='fas fa-check-circle'></i> تم تنفيذ معادلة المقرر (تسجيل موازنة) للطالب واعتماد الدرجة بنجاح.
 </div>";
 }
 } catch (Exception $e) {
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'>
 <i class='fas fa-exclamation-triangle'></i> خطأ أثناء التحديث: " . htmlspecialchars($e->getMessage()) . "
 </div>";
 }
 }
}

// Render Search Interface if no student is selected
if ($student_id <= 0) {
 $search_term = $_GET['q'] ?? '';
 $results = [];
 
 if ($search_term !== '') {
 $col_id = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? 0) : 0;
 $q_str = "SELECT id, username, full_name, college_id FROM users WHERE role = 'student' AND (full_name ILIKE :q OR username ILIKE :q)";
 $params = [':q' => "%$search_term%"];
 if ($col_id > 0) {
 $q_str .= " AND college_id = :cid";
 $params[':cid'] = $col_id;
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
 <i class="fas fa-balance-scale text-primary bg-bg p-3 rounded-xl"></i> 
 تسجيل موازنة (معادلة مقررات مقاصة)
 </h2>
 <form method="GET" action="registration_balance.php" class="flex flex-col md:flex-row gap-4 relative">
 <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
 <div class="flex-1">
 <input type="text" name="q" value="<?= htmlspecialchars($search_term) ?>" placeholder="ابحث برقم القيد أو الاسم لطالب استجد/محول..." class="w-full border border-gray-300 rounded-xl p-4 pr-12 text-lg focus:ring-2 focus:ring-primary outline-none">
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
 <a href="registration_balance.php?id=<?= $res['id'] ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 hover:border-primary hover:text-primary px-5 py-2 rounded-lg font-bold text-sm shadow-sm transition-all flex items-center gap-2">
 <i class="fas fa-plus"></i> إضافة مواد معادلة
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

// Load student
$stmt = $pdo->prepare("SELECT u.full_name, u.username as academic_number, s.college_id FROM users u JOIN students s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
 echo "<div class='p-8 text-center text-primary font-bold'>الطالب غير موجود.</div>";
 require_once 'includes/footer.php';
 exit;
}

if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id'])) {
 if ($student['college_id'] != $_SESSION['college_id']) {
 echo "<div class='p-8 text-center text-accent font-bold'>غير مصرح لك بعرض هذا الطالب.</div>";
 require_once 'includes/footer.php';
 exit;
 }
}

// Get history of balanced items
$s_db = new UniversityDB((int)($student['college_id']??0));
$history = $s_db->findAll('enrollments', ['user_id' => $student_id, 'semester' => $current_semester]);
$history_courses = [];
if (!empty($history)) {
 // fetch courses info
 $stmtCInfo = $pdo->prepare("SELECT id, code, name FROM courses");
 $stmtCInfo->execute();
 $allc = $stmtCInfo->fetchAll(PDO::FETCH_ASSOC);
 $cmap = [];
 foreach($allc as $ct) $cmap[$ct['id']] = $ct;
 
 foreach($history as $h) {
 $cid = $h['course_id'] ?? 0;
 if (isset($cmap[$cid])) {
 $history_courses[] = $cmap[$cid]['code'] . ' - ' . $cmap[$cid]['name'];
 }
 }
}

// Available courses to balance
$col_id = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? 0) : 0;
$q_courses = "SELECT id, code, name, credit_hours FROM courses";
$p_courses = [];
if ($col_id > 0) {
 $q_courses .= " WHERE college_id = ?";
 $p_courses[] = $col_id;
}
$q_courses .= " ORDER BY code ASC";
$stmtC = $pdo->prepare($q_courses);
$stmtC->execute($p_courses);
$courses = $stmtC->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="max-w-4xl mx-auto space-y-6 animate-fade-in-up">

 <div class="flex items-center justify-between">
 <div>
 <h2 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-balance-scale text-primary"></i>
 تسجيل موازنة (مقاصة علمية)
 </h2>
 <p class="text-slate-500 text-sm mt-1">يُستخدم هذا التبويب لمعادلة مقررات درسها الطالب المحول مسبقاً واعتماد درجتها في معدله التراكمي.</p>
 </div>
 <a href="registration_balance.php?tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold hover:text-primary hover:bg-bg transition-colors">
 <i class="fas fa-arrow-right"></i> طالب آخر
 </a>
 </div>

 <?= $message ?>

 <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
 <div class="w-16 h-16 rounded-2xl bg-bg text-white flex items-center justify-center text-2xl font-bold">
 <?= mb_substr($student['full_name'], 0, 1, 'UTF-8') ?>
 </div>
 <div>
 <h3 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($student['full_name']) ?></h3>
 <span class="text-slate-500 font-mono"><i class="fas fa-id-card ml-1"></i><?= htmlspecialchars($student['academic_number']) ?></span>
 
 <?php if (!empty($history_courses)): ?>
 <div class="mt-2 text-xs text-primary bg-bg inline-block px-3 py-1 rounded border border-primary">
 <strong><i class="fas fa-check-circle"></i> المقررات المعادلة مسبقاً:</strong> 
 <?= htmlspecialchars(implode(" ، ", $history_courses)) ?>
 </div>
 <?php endif; ?>
 </div>
 </div>

 <div class="bg-white rounded-2xl shadow-sm border border-slate-100">
 <form method="POST" action="registration_balance.php?id=<?= $student_id ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="p-8">
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
 <!-- Course -->
 <div class="md:col-span-2">
 <label class="block text-sm font-bold text-slate-700 mb-2">المقرر المراد معادلته (الموجود في اللائحة)</label>
 <select name="course_id" required class="w-full border border-slate-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none">
 <option value="">-- يرجى اختيار المقرر المعادل --</option>
 <?php foreach($courses as $c): ?>
 <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'] . ' - ' . $c['name']) ?> (<?= $c['credit_hours'] ?> ساعات)</option>
 <?php endforeach; ?>
 </select>
 </div>
 
 <!-- Grade -->
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">التقدير المعتمد (مثال: A, B+, Pass)</label>
 <input type="text" name="grade" required placeholder="Ex: B+" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none uppercase">
 </div>
 
 <!-- Points -->
 <div>
 <label class="block text-sm font-bold text-slate-700 mb-2">النقاط (للتأثير على GPA) اختياري</label>
 <input type="number" step="0.1" name="points" placeholder="Ex: 3.5" class="w-full border border-slate-300 rounded-xl p-3 focus:ring-primary focus:border-primary outline-none">
 </div>
 </div>
 
 <div class="flex justify-end pt-4 border-t border-slate-100">
 <button type="submit" name="save_balance" class="bg-primary hover:bg-accent hover:text-white text-white font-bold py-3 px-8 rounded-xl shadow-md transition-all flex items-center gap-2">
 <i class="fas fa-save"></i> حفظ معادلة المقرر
 </button>
 </div>
 </form>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>

