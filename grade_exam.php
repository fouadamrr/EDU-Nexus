<?php
require_once 'includes/header.php';

/** @var UniversityDB $db */

$test = $db->safeQuery("SELECT * FROM public.exam_grades LIMIT 1");

// Role Check
if ($role !== 'instructor') {
 header("Location: index.php");
 exit;
}

$instructor_id = (int)$_SESSION['user_id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);

// Fetch Exam & Verify Ownership
$examStmt = $db->safeQuery("SELECT * FROM exams WHERE id = :id LIMIT 1", [':id' => $exam_id]);
$exam = $examStmt ? $examStmt->fetch() : null;
if (!$exam || (int)$exam['doctor_id'] !== $instructor_id) {
 die("لم يتم العثور على الامتحان أو ليس لديك صلاحية الوصول.");
}

// Handle Grading Submission
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grades'])) {
 foreach ($_POST['grades'] as $student_id => $grade_val) {
 $student_id = (int)$student_id;
 $grade_val = ($grade_val === '' || $grade_val === null)
 ? null
 : min((float)$grade_val, (float)$exam['total_marks']);

 // Skip blank entries
 if ($grade_val === null) continue;

 // Upsert: update if exists, insert otherwise
 $sql = "SELECT id FROM exam_grades WHERE exam_id = :exam_id AND student_id = :student_id";
 $stmt = $db->safeQuery($sql, [':exam_id' => $exam_id, ':student_id' => $student_id]);
 $existing = $stmt ? $stmt->fetch() : null;

 if ($existing) {
 $db->update('exam_grades', $existing['id'], ['grade_value' => $grade_val]);
 } else {
 $db->insert('exam_grades', [
 'exam_id' => $exam_id,
 'student_id' => $student_id,
 'grade_value' => $grade_val,
 ]);
 }
 }
 $msg = "<div class='bg-primary text-white p-4 rounded-xl mb-6 font-bold text-center shadow-sm border border-primary'>تم رصد الدرجات بنجاح!</div>";
}

// Fetch Assigned Students using JOIN for efficiency
$sql = "SELECT u.id, u.username, u.full_name, s.doctor_id,
 eg.grade_value AS current_grade
 FROM users u
 INNER JOIN students s ON u.id = s.user_id
 LEFT JOIN exam_grades eg ON (eg.student_id = u.id AND eg.exam_id = :exam_id)
 WHERE s.doctor_id = :instructor_id 
 OR u.username = 'trial_student'";

$stmt = $db->safeQuery($sql, [':exam_id' => $exam_id, ':instructor_id' => $instructor_id]);
$my_students = $stmt->fetchAll();

// Sort by username
usort($my_students, function($a, $b) { return strcmp($a['username'], $b['username']); });

?>

<div class="max-w-5xl mx-auto space-y-8 animate-fade-in-up pb-12">

 <div class="flex justify-between items-center">
 <div>
 <h1 class="text-3xl font-bold text-gray-800 mb-1">رصد الدرجات</h1>
 <p class="text-gray-500 font-bold text-lg">
 <span class="text-primary"><?php echo htmlspecialchars($exam['exam_name']); ?></span> 
 <span class="text-gray-300 mx-2">|</span> 
 الدرجة الكلية: <?php echo $exam['total_marks']; ?>
 </p>
 </div>
 <a href="instructor_dashboard.php" class="bg-bg text-gray-600 px-4 py-2 rounded-lg font-bold hover:bg-gray-200 transition">
 <i class="fas fa-arrow-left ml-2"></i> عودة
 </a>
 </div>

 <?php echo $msg; ?>

 <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
 <form method="POST">
 <div class="p-6 bg-bg border-b border-gray-100 flex justify-between items-center">
 <h3 class="font-bold text-gray-700">قائمة الطلاب</h3>
 <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg font-bold shadow hover:bg-primary transition transform active:scale-95">
 <i class="fas fa-save ml-2"></i> حفظ كشف الدرجات
 </button>
 </div>
 
 <table class="w-full text-right">
 <thead class="bg-bg/50 text-gray-500 font-bold text-sm border-b border-gray-100">
 <tr>
 <th class="p-4 w-16">#</th>
 <th class="p-4">رقم الطالب</th>
 <th class="p-4">اسم الطالب</th>
 <th class="p-4 w-40 text-center">الدرجة المستحقة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-50">
 <?php foreach ($my_students as $idx => $s): 
 $is_trial = ($s['username'] === 'trial_student');
 ?>
 <tr class="hover:bg-bg/50 transition <?php echo $is_trial ? 'bg-bg/50' : ''; ?>">
 <td class="p-4 text-gray-400 font-mono"><?php echo $idx + 1; ?></td>
 <td class="p-4 font-bold text-gray-700 font-mono"><?php echo htmlspecialchars($s['username']); ?></td>
 <td class="p-4">
 <span class="font-bold text-gray-800"><?php echo htmlspecialchars($s['full_name']); ?></span>
 <?php if($is_trial): ?><span class="mr-2 text-xs bg-primary text-white px-2 py-0.5 rounded-full font-bold">TRIAL</span><?php endif; ?>
 </td>
 <td class="p-4 text-center">
 <input type="number" 
 name="grades[<?php echo $s['id']; ?>]" 
 value="<?php echo htmlspecialchars($s['current_grade']); ?>" 
 class="w-24 text-center font-bold font-mono border-2 border-gray-200 rounded-lg py-2 focus:border-primary focus:ring-4 focus:ring-purple-200 transition outline-none text-primary bg-white"
 max="<?php echo $exam['total_marks']; ?>"
 placeholder="-">
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 
 <div class="p-6 bg-bg border-t border-gray-100 flex justify-end">
 <button type="submit" class="bg-primary text-white px-8 py-3 rounded-xl font-bold shadow-lg hover:bg-primary transition transform active:scale-95 text-lg">
 <i class="fas fa-save ml-2"></i> حفظ التغييرات
 </button>
 </div>
 </form>
 </div>

</div>

<?php require_once 'includes/footer.php'; ?>
