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

// Handle Status Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['enrollment_id'], $_POST['new_status'])) {
 $enrollment_id = (int)$_POST['enrollment_id'];
 $new_status = $_POST['new_status'];
 $valid_statuses = ['active', 'dropped', 'completed'];

 if (in_array($new_status, $valid_statuses) && $student_id > 0) {
 try {
 // we use the main PDO connection, but remember enrollments are per-college if distributed. 
 // In a centralized approach (like $pdo), we just run the update if the table exists.
 // Let's find the student's college to connect to the right schema if needed.
 $stmt = $pdo->prepare("SELECT college_id FROM students WHERE user_id = ?");
 $stmt->execute([$student_id]);
 $college_id = $stmt->fetchColumn() ?: 0;
 
 $s_db = new UniversityDB((int)$college_id);
 $stmtUpdate = $pdo->prepare("UPDATE enrollments SET status = :st WHERE id = :eid AND user_id = :uid");
 $stmtUpdate->execute([':st' => $new_status, ':eid' => $enrollment_id, ':uid' => $student_id]);
 
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'>
 <i class='fas fa-check-circle'></i> تم تحديث حالة المادة بنجاح.
 </div>";
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
 $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE role = 'student' AND (full_name ILIKE :q OR username ILIKE :q) LIMIT 20");
 $stmt->execute([':q' => "%$search_term%"]);
 $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
 }
 
 ?>
 <div class="max-w-4xl mx-auto animate-fade-in-up mt-8">
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 mb-6">
 <h2 class="text-2xl font-bold text-secondary mb-6 flex items-center gap-3">
 <i class="fas fa-edit text-accent bg-bg p-3 rounded-xl"></i> 
 تعديل حالة المادة (حذف/انسحاب)
 </h2>
 <form method="GET" action="edit_course_status.php" class="flex flex-col md:flex-row gap-4 relative">
 <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
 <div class="flex-1">
 <input type="text" name="q" value="<?= htmlspecialchars($search_term) ?>" placeholder="ابحث مسجل الطالب بالاسم أو رقم القيد..." class="w-full border border-gray-300 rounded-xl p-4 pr-12 text-lg focus:ring-2 focus:ring-primary outline-none">
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
 <a href="edit_course_status.php?id=<?= $res['id'] ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 hover:border-primary hover:text-accent px-5 py-2 rounded-lg font-bold text-sm shadow-sm transition-all flex items-center gap-2">
 <i class="fas fa-tasks"></i> تعديل المواد
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

// If student is selected, load their data
$stmt = $pdo->prepare("SELECT u.full_name, u.username as academic_number, s.college_id FROM users u JOIN students s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
 echo "<div class='p-8 text-center text-primary font-bold'>الطالب غير موجود.</div>";
 require_once 'includes/footer.php';
 exit;
}

$s_db = new UniversityDB((int)($student['college_id'] ?? 0));
// Fetch enrollments with course details
// manual query since we need course names
$query = "SELECT e.id as enrollment_id, e.course_id, e.semester, e.status, c.name, c.code, c.credit_hours 
 FROM enrollments e 
 JOIN courses c ON e.course_id = c.id 
 WHERE e.user_id = :uid 
 ORDER BY e.semester DESC, c.code ASC";
$stmtEn = $pdo->prepare($query);
$stmtEn->execute([':uid' => $student_id]);
$enrollments = $stmtEn->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="max-w-6xl mx-auto space-y-6 animate-fade-in-up">

 <div class="flex items-center justify-between">
 <div>
 <h2 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-edit text-accent"></i>
 تعديل حالة المادة لطالب
 </h2>
 <p class="text-slate-500 text-sm mt-1">تعديل حالة قيد الطالب في مقرر معين (مسجل/منسحب/مكتمل).</p>
 </div>
 <a href="edit_course_status.php?tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg font-bold hover:text-accent hover:bg-bg transition-colors">
 <i class="fas fa-arrow-right"></i> بحث عن طالب آخر
 </a>
 </div>

 <?= $message ?>

 <!-- Student Info -->
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
 <div class="w-16 h-16 rounded-2xl bg-bg text-white flex items-center justify-center text-2xl font-bold">
 <?= mb_substr($student['full_name'], 0, 1, 'UTF-8') ?>
 </div>
 <div>
 <h3 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($student['full_name']) ?></h3>
 <span class="text-slate-500 font-mono"><i class="fas fa-id-card ml-1"></i><?= htmlspecialchars($student['academic_number']) ?></span>
 </div>
 </div>

 <!-- Enrollments Table -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="p-4 bg-bg border-b border-slate-100 text-sm font-bold text-slate-700">
 <i class="fas fa-list-ul ml-2"></i> المقررات المسجلة
 </div>
 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-white border-b border-slate-200">
 <tr>
 <th class="p-4 text-sm text-slate-500">الفصل الدراسي</th>
 <th class="p-4 text-sm text-slate-500">كود المقرر</th>
 <th class="p-4 text-sm text-slate-500">اسم المقرر</th>
 <th class="p-4 text-sm text-slate-500 text-center">حالة التسجيل</th>
 <th class="p-4 text-sm text-slate-500 text-center">تحديث الحالة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-100">
 <?php if (empty($enrollments)): ?>
 <tr><td colspan="5" class="p-8 text-center text-slate-500 font-bold">لم يقم الطالب بتسجيل أي مقررات بعد.</td></tr>
 <?php else: ?>
 <?php foreach ($enrollments as $e): 
 $status = $e['status'] ?? 'active';
 if ($status === 'active') {
 $badge = '<span class="bg-primary text-white border border-primary px-3 py-1 rounded-full text-xs font-bold w-24 inline-block text-center">نشط</span>';
 } elseif ($status === 'dropped') {
 $badge = '<span class="bg-primary text-white border border-primary px-3 py-1 rounded-full text-xs font-bold w-24 inline-block text-center">منسحب (W)</span>';
 } else {
 $badge = '<span class="bg-primary text-white border border-primary px-3 py-1 rounded-full text-xs font-bold w-24 inline-block text-center">مكتمل</span>';
 }
 ?>
 <tr class="hover:bg-bg transition-colors">
 <td class="p-4 text-sm font-medium text-slate-600"><?= htmlspecialchars($e['semester']) ?></td>
 <td class="p-4 text-sm font-bold font-mono text-primary"><?= htmlspecialchars($e['code']) ?></td>
 <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($e['name']) ?></td>
 <td class="p-4 text-center"><?= $badge ?></td>
 <td class="p-4 text-center">
 <form method="POST" action="edit_course_status.php?id=<?= $student_id ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="flex items-center justify-center gap-2">
 <input type="hidden" name="enrollment_id" value="<?= $e['enrollment_id'] ?>">
 <select name="new_status" class="border border-slate-300 rounded-lg p-2 text-sm focus:ring-primary focus:border-primary outline-none">
 <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>تسجيل نشط</option>
 <option value="dropped" <?= $status === 'dropped' ? 'selected' : '' ?>>منسحب من المادة</option>
 <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>مكتمل دراسياً</option>
 </select>
 <button type="submit" name="update_status" class="bg-accent text-white hover:bg-sky-500 hover:text-white px-3 py-2 rounded-lg text-sm font-bold shadow transition-colors">
 حفظ
 </button>
 </form>
 </td>
 </tr>
 <?php endforeach; ?>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
