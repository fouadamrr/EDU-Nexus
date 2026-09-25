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
$current_year = date('Y');
$summer_term = "Summer " . $current_year;

// Handle saving summer enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_summer'], $_POST['course_id']) && $student_id > 0) {
 $course_id = (int)$_POST['course_id'];
 
 if ($course_id > 0) {
 try {
 $stmt = $pdo->prepare("SELECT college_id FROM students WHERE user_id = ?");
 $stmt->execute([$student_id]);
 $college_id = $stmt->fetchColumn() ?: 0;
 
 $s_db = new UniversityDB((int)$college_id);
 
 // Check if already enrolled
 $existing = $s_db->findAll('enrollments', ['user_id' => $student_id, 'course_id' => $course_id, 'semester' => $summer_term]);
 
 if (!empty($existing)) {
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'>
 <i class='fas fa-exclamation-circle'></i> الطالب مسجل بالفعل في هذا المقرر للتدريب الصيفي.
 </div>";
 } else {
 $s_db->insert('enrollments', [
 'user_id' => $student_id,
 'course_id' => $course_id,
 'semester' => $summer_term,
 'status' => 'active'
 ]);
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'>
 <i class='fas fa-check-circle'></i> تم تسجيل التدريب الصيفي للطالب بنجاح.
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
 <i class="fas fa-sun text-primary bg-bg p-3 rounded-xl"></i> 
 تسجيل التدريب الصيفي والميداني
 </h2>
 <form method="GET" action="summer_training.php" class="flex flex-col md:flex-row gap-4 relative">
 <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
 <div class="flex-1">
 <input type="text" name="q" value="<?= htmlspecialchars($search_term) ?>" placeholder="ابحث عن طالب بالاسم أو رقم القيد للتسجيل الصيفي..." class="w-full border border-gray-300 rounded-xl p-4 pr-12 text-lg focus:ring-2 focus:ring-primary outline-none">
 <i class="fas fa-search absolute right-5 top-5 text-gray-400 text-xl"></i>
 </div>
 <button type="submit" class="bg-primary hover:bg-primary text-white font-bold px-8 py-4 rounded-xl shadow-md transition-all">بحث</button>
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
 <a href="summer_training.php?id=<?= $res['id'] ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 hover:border-primary hover:text-primary px-5 py-2 rounded-lg font-bold text-sm shadow-sm transition-all flex items-center gap-2">
 <i class="fas fa-sun"></i> تسجيل صيفي
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

// Load training courses (We assume courses with "تدريب" or "صيفي" or just list all visible to college)
// If possible, filter by name ILIKE '%تدريب%'
$col_id = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? 0) : 0;
$q_courses = "SELECT id, code, name, credit_hours FROM courses";
$p_courses = [];
if ($col_id > 0) {
 $q_courses .= " WHERE college_id = ?";
 $p_courses[] = $col_id;
}
$q_courses .= " ORDER BY name ASC";
$stmtC = $pdo->prepare($q_courses);
$stmtC->execute($p_courses);
$courses = $stmtC->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="max-w-4xl mx-auto space-y-6 animate-fade-in-up">

 <div class="flex items-center justify-between">
 <div>
 <h2 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-sun text-primary"></i>
 التسجيل الصيفي للطالب
 </h2>
 <p class="text-slate-500 text-sm mt-1">إضافة مواد للتدريب الميداني أو الصيفي في فصل الصيف بشكل استثنائي.</p>
 </div>
 <a href="summer_training.php?tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold hover:text-primary hover:border-primary transition-colors">
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
 <span class="ml-3 inline-flex bg-primary text-white font-bold text-xs px-2 py-1 rounded-full border border-primary"><?= $summer_term ?></span>
 </div>
 </div>

 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
 <form method="POST" action="summer_training.php?id=<?= $student_id ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
 <div class="mb-6">
 <label class="block text-sm font-bold text-slate-700 mb-2">اختر مادة التدريب أو المقرر الصيفي</label>
 <select name="course_id" required class="w-full border border-slate-300 rounded-xl p-3 focus:ring-amber-500 focus:border-primary outline-none">
 <option value="">-- يرجى الاختيار --</option>
 <?php foreach($courses as $c): ?>
 <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'] . ' - ' . $c['name']) ?> (<?= $c['credit_hours'] ?> ساعات)</option>
 <?php endforeach; ?>
 </select>
 </div>
 
 <button type="submit" name="enroll_summer" class="w-full bg-primary hover:bg-primary text-white font-bold py-4 rounded-xl shadow-md transition-all flex justify-center items-center gap-2">
 <i class="fas fa-plus-circle"></i> إتمام التسجيل الصيفي
 </button>
 </form>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
