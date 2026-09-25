<?php
require_once 'includes/header.php';

// Only admin, affairs, and super_admin can access
if (!in_array($role, ['super_admin', 'admin', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('registration');


$message = '';

$student_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($student_id <= 0) {
 $search_term = $_GET['q'] ?? '';
 $results = [];
 
 if ($search_term !== '') {
 $q_str = "SELECT id, username, full_name, college_id FROM users WHERE role = 'student' AND (full_name ILIKE :q OR username ILIKE :q)";
 $params = [':q' => "%$search_term%"];
 
 $filter_college = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? 0) : 0;
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
 <i class="fas fa-search text-primary bg-bg p-3 rounded-xl"></i> 
 البحث عن طالب لتعديل المقررات
 </h2>
 <form method="GET" action="edit_student_courses.php" class="flex flex-col md:flex-row gap-4 relative">
 <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
 <div class="flex-1">
 <input type="text" name="q" value="<?= htmlspecialchars($search_term) ?>" placeholder="ابحث بالاسم أو رقم القيد..." class="w-full border border-gray-300 rounded-xl p-4 pr-12 text-lg focus:ring-2 focus:ring-primary outline-none">
 <i class="fas fa-search absolute right-5 top-5 text-gray-400 text-xl"></i>
 </div>
 <button type="submit" class="bg-primary hover:bg-accent hover:text-white text-white font-bold px-8 py-4 rounded-xl shadow-md transition-all">
 بحث الان
 </button>
 </form>
 <p class="text-sm text-slate-500 mt-4 text-center">أو يمكنك اختيار الطالب مباشرة من <a href="manage_users.php?role=student" class="text-primary font-bold hover:underline">قائمة إدارة المستخدمين</a>.</p>
 </div>

 <?php if ($search_term !== ''): ?>
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="p-4 bg-bg border-b border-slate-100 font-bold text-slate-700">نتائج البحث</div>
 <div class="divide-y divide-slate-100">
 <?php if (empty($results)): ?>
 <div class="p-8 text-center text-slate-500 font-medium">لم يتم العثور على أي طلاب مطابقين لبحثك.</div>
 <?php else: ?>
 <?php foreach ($results as $res): ?>
 <div class="flex items-center justify-between p-4 hover:bg-bg transition-colors">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 rounded-full bg-bg text-white flex items-center justify-center font-bold text-lg">
 <?= mb_substr($res['full_name'], 0, 1, 'UTF-8') ?>
 </div>
 <div>
 <h4 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($res['full_name']) ?></h4>
 <span class="text-sm text-slate-500 font-mono"><i class="fas fa-id-card ml-1"></i><?= htmlspecialchars($res['username']) ?></span>
 </div>
 </div>
 <a href="edit_student_courses.php?id=<?= $res['id'] ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 hover:border-primary hover:text-accent px-5 py-2 rounded-lg font-bold text-sm shadow-sm transition-all flex items-center gap-2">
 <i class="fas fa-edit"></i>
 فتح ملف الطالب
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

// Find student directly from PostgreSQL
function findStudentAcrossColleges($student_id) {
 global $pdo;
 $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
 $stmt->execute([$student_id]);
 $student = $stmt->fetch(PDO::FETCH_ASSOC);
 if ($student) {
 $studentCollegeId = isset($student['college_id']) ? (int)$student['college_id'] : null;
 $studentCollegeId = $studentCollegeId ?: 0;
 return [$student, new UniversityDB($studentCollegeId)];
 }
 return [null, null];
}

/** @var UniversityDB $s_db */
list($student, $s_db) = findStudentAcrossColleges($student_id);

if (!$student) {
 echo '<div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow text-center mt-10">
 <p class="text-primary font-bold mb-4">الطالب غير موجود أو ليس من نوع طالب.</p>
 <a href="manage_users.php?role=student" class="text-primary underline">العودة لقائمة الطلاب</a>
 </div>';
 require_once 'includes/footer.php';
 exit;
}

if (in_array($role, ['dean', 'affairs'])) {
 $cid = (int)($_SESSION['college_id'] ?? 0);
 if (($student['college_id'] ?? 0) != $cid) {
 echo '<div class="max-w-4xl mx-auto bg-white p-8 rounded-xl shadow text-center mt-10">
 <p class="text-accent font-bold mb-4">غير مصرح لك بعرض بيانات هذا الطالب.</p>
 <a href="manage_users.php?role=student" class="text-primary underline">العودة لقائمة الطلاب</a>
 </div>';
 require_once 'includes/footer.php';
 exit;
 }
}

// Fetch extended details
$details = $s_db->find('student_details', 'user_id', $student_id);
$major = $details['major'] ?? 'غير محدد';
$level = isset($details['level']) ? (int) $details['level'] : null;
$gpa = $details['gpa'] ?? null;

// Determine semester we are registering for (align with seeded data)
$semester = 'Fall 2025';

// Fetch all courses and filter by level (approximate "قسم الطالب / مستواه")
$all_courses = $db->findAll('courses');
$available_courses = [];
foreach ($all_courses as $c) {
 if ($level && isset($c['level']) && (int) $c['level'] !== $level) {
 continue;
 }
 $available_courses[] = $c;
}

// Current enrollments for this semester
$current_enrollments = $s_db->findAll('enrollments', ['user_id' => $student_id, 'semester' => $semester]);
$current_course_ids = array_map(function ($e) {
 return (int) ($e['course_id'] ?? 0);
}, $current_enrollments);

// Handle save enrollments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_enrollments') {
 $course_ids = $_POST['course_ids'] ?? [];
 if (!is_array($course_ids)) {
 $course_ids = [];
 }
 $course_ids = array_values(array_unique(array_map('intval', $course_ids)));
 $course_ids = array_filter($course_ids, function ($id) {
 return $id > 0;
 });

 if (count($course_ids) === 0) {
 $message = '<div class="bg-bg text-primary p-4 rounded-lg font-bold mb-6">الرجاء اختيار مقرر واحد على الأقل قبل الحفظ.</div>';
 } else {
 // Rebuild mapping of valid course IDs from filtered list
 $valid_map = [];
 foreach ($available_courses as $c) {
 if (isset($c['id'])) {
 $valid_map[(int) $c['id']] = true;
 }
 }
 $valid_course_ids = array_values(array_filter($course_ids, function ($id) use ($valid_map) {
 return isset($valid_map[$id]);
 }));

 if (count($valid_course_ids) === 0) {
 $message = '<div class="bg-bg text-primary p-4 rounded-lg font-bold mb-6">المقررات المختارة غير صالحة لهذا الطالب.</div>';
 } else {
 // Sync enrollments for this semester
 $s_db->deleteWhere('enrollments', ['user_id' => $student_id, 'semester' => $semester]);
 foreach ($valid_course_ids as $cid) {
 $s_db->insert('enrollments', [
 'user_id' => $student_id,
 'course_id' => $cid,
 'semester' => $semester,
 'status' => 'active'
 ]);
 }
 $current_course_ids = $valid_course_ids;
 $message = '<div class="bg-bg text-accent p-4 rounded-lg font-bold mb-6">تم تحديث تعديل المقررات للطالب بنجاح.</div>';
 }
 }
}
?>

<div class="max-w-6xl mx-auto space-y-8">

 <!-- Header: Student summary -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
 <div class="flex items-start gap-4">
 <div class="w-14 h-14 rounded-2xl bg-bg text-white flex items-center justify-center text-2xl font-bold">
 <?php echo mb_substr($student['full_name'] ?? $student['username'], 0, 1, 'UTF-8'); ?>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-secondary mb-1">
 تعديل مقررات الطالب - <?php echo htmlspecialchars($student['full_name']); ?>
 </h2>
 <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
 <span class="inline-flex items-center gap-1 bg-bg px-3 py-1 rounded-full font-mono">
 <i class="fas fa-id-card text-slate-400"></i>
 <?php echo htmlspecialchars($student['username']); ?>
 </span>
 <span class="inline-flex items-center gap-1 bg-bg text-primary px-3 py-1 rounded-full">
 <i class="fas fa-layer-group"></i>
 المستوى الدراسي:
 <strong class="ml-1"><?php echo $level ? $level : 'غير محدد'; ?></strong>
 </span>
 <span class="inline-flex items-center gap-1 bg-bg text-primary px-3 py-1 rounded-full">
 <i class="fas fa-book"></i>
 التخصص:
 <strong class="ml-1"><?php echo htmlspecialchars($major); ?></strong>
 </span>
 </div>
 </div>
 </div>
 <a href="manage_users.php?role=student" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-primary">
 <i class="fas fa-arrow-right"></i>
 العودة لقائمة الطلاب
 </a>
 </div>

 <!-- Enrollment section -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
 <div class="flex items-center justify-between mb-2">
 <h3 class="text-xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-clipboard-check text-primary"></i>
 تعديل مقررات الطالب (مستوى <?php echo $level ? $level : 'غير محدد'; ?>)
 </h3>
 <span class="text-xs text-slate-500 bg-bg px-3 py-1 rounded-full border border-slate-100">
 الفصل الدراسي: <?php echo htmlspecialchars($semester); ?>
 </span>
 </div>

 <?php echo $message; ?>

 <?php if (empty($available_courses)): ?>
 <div class="bg-bg border border-slate-100 rounded-xl p-6 text-center text-slate-500">
 لا توجد مقررات مناسبة مرتبطة بمستوى هذا الطالب حالياً.
 </div>
 <?php else: ?>
 <form method="POST" class="space-y-4">
 <input type="hidden" name="action" value="save_enrollments">

 <div class="overflow-x-auto border border-slate-100 rounded-xl">
 <table class="w-full text-right">
 <thead class="bg-bg text-slate-600 text-sm font-bold">
 <tr>
 <th class="p-3">اختيار</th>
 <th class="p-3">كود المقرر</th>
 <th class="p-3">اسم المقرر</th>
 <th class="p-3 text-center">المستوى</th>
 <th class="p-3 text-center">الساعات</th>
 <th class="p-3 text-center">الفصل</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-100">
 <?php foreach ($available_courses as $c): ?>
 <tr class="hover:bg-bg transition">
 <td class="p-3 text-center">
 <input type="checkbox"
 name="course_ids[]"
 value="<?php echo (int) ($c['id'] ?? 0); ?>"
 <?php echo in_array((int) ($c['id'] ?? 0), $current_course_ids) ? 'checked' : ''; ?>
 class="w-5 h-5 text-primary rounded border-slate-300 focus:ring-primary cursor-pointer">
 </td>
 <td class="p-3 font-mono font-bold text-slate-800">
 <?php echo htmlspecialchars($c['code'] ?? ''); ?>
 </td>
 <td class="p-3 font-medium text-slate-800">
 <?php echo htmlspecialchars($c['name'] ?? ''); ?>
 </td>
 <td class="p-3 text-center text-sm text-slate-600">
 <?php echo htmlspecialchars((string) ($c['level'] ?? '')); ?>
 </td>
 <td class="p-3 text-center text-sm">
 <?php echo htmlspecialchars((string) ($c['credit_hours'] ?? '')); ?>
 </td>
 <td class="p-3 text-center text-sm text-slate-500">
 <?php echo htmlspecialchars($c['semester'] ?? ''); ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>

 <div class="flex justify-end gap-3">
 <button type="submit"
 class="bg-primary text-white px-6 py-2 rounded-lg font-bold shadow hover:bg-accent hover:text-white transition">
 <i class="fas fa-save ml-2"></i>
 حفظ التعديلات
 </button>
 </div>
 </form>
 <?php endif; ?>
 </div>

</div>

<?php require_once 'includes/footer.php'; ?>
