<?php
require_once __DIR__ . '/includes/header.php';

// Check role
if (!in_array($role, ['super_admin', 'admin', 'dean'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('registration');

$message = '';

// Handle Warning Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_warnings'])) {
 $student_ids = $_POST['student_ids'] ?? [];
 
 if (empty($student_ids)) {
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'><i class='fas fa-exclamation-triangle'></i> يرجى تحديد طالب واحد على الأقل.</div>";
 } else {
 // Here we insert notifications for each student
 $success_count = 0;
 try {
 $stmt = $pdo->prepare("
 INSERT INTO messages (sender_id, receiver_id, subject, body, created_at) 
 VALUES (:sender, (SELECT user_id FROM students WHERE id = :std_id), :sub, :body, NOW())
 ");
 
 $pdo->beginTransaction();
 foreach ($student_ids as $sid) {
 // Ensure table structure. In case messages does not exist yet (or has different format in nexus),
 // we'll try catching the error and displaying it. But assuming the system has a notification/messaging structure.
 try {
 $stmt->execute([
 ':sender' => $_SESSION['user_id'],
 ':std_id' => $sid,
 ':sub' => 'إنذار أكاديمي: تأخر في تسجيل المقررات',
 ':body' => 'عزيزي الطالب، نود إبلاغك بأنك لم تقم بتسجيل مقرراتك الدراسية للفصل الحالي وتم تطبيق إنذار أكاديمي عليك. يرجى مراجعة شؤون الطلبة فوراً.'
 ]);
 $success_count++;
 } catch(Exception $e) {
 // ignore individual failures to try all
 }
 }
 $pdo->commit();
 
 if ($success_count > 0) {
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'>
 <i class='fas fa-check-circle'></i> تم إرسال الإنذار لعدد $success_count طالب بنجاح.
 </div>";
 }
 
 } catch (PDOException $e) {
 $pdo->rollBack();
 $message = "<div class='bg-primary text-white p-4 rounded-lg mb-6 font-bold flex items-center gap-2'><i class='fas fa-exclamation-triangle'></i> خطأ في النظام: " . $e->getMessage() . "</div>";
 }
 }
}

// Fetch Students NOT registered for any courses
// Based on current schema: enrollments link student_id to course_id.
// So we find students not in enrollments.
$query = "
 SELECT s.id as student_id, u.full_name, u.username as academic_number, c.name as college_name, s.level
 FROM students s
 JOIN users u ON s.user_id = u.id
 LEFT JOIN colleges c ON s.college_id = c.id
 WHERE s.user_id NOT IN (
 SELECT DISTINCT user_id FROM enrollments
 )
 AND s.enrollment_status = 'enrolled'
 ORDER BY c.name ASC, s.level ASC, u.full_name ASC
";
$students_list = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="space-y-6 animate-fade-in-up">

 <div class="flex flex-col md:flex-row items-center justify-between gap-4">
 <div>
 <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
 <i class="fas fa-exclamation-triangle text-primary"></i>
 إنذار الطلاب غير المسجلين
 </h2>
 <p class="text-gray-500 text-sm mt-1">قائمة بالطلاب الذين لم يقوموا بتسجيل أي مقررات دراسية حتى الآن.</p>
 </div>
 </div>

 <?= $message ?>

 <!-- Results Table & Form -->
 <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
 <form method="POST" action="warning_unregistered.php?tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
 
 <div class="p-4 bg-bg border-b border-gray-100 flex justify-between items-center">
 <div class="text-sm font-bold text-gray-600">
 العدد الإجمالي للطاب غير المسجلين: <span class="text-primary text-lg ml-1"><?= count($students_list) ?></span>
 </div>
 <button type="submit" name="send_warnings" class="bg-primary hover:bg-primary text-white font-bold py-2 px-6 rounded-lg transition-colors shadow-sm flex items-center gap-2">
 <i class="fas fa-paper-plane"></i> إرسال إنذار للمحددين
 </button>
 </div>

 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-200">
 <tr>
 <th class="p-4 text-center" style="width: 50px;">
 <input type="checkbox" id="selectAll" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
 </th>
 <th class="p-4 text-sm font-bold text-gray-600">رقم القيد</th>
 <th class="p-4 text-sm font-bold text-gray-600">اسم الطالب</th>
 <th class="p-4 text-sm font-bold text-gray-600">الكلية</th>
 <th class="p-4 text-sm font-bold text-gray-600 text-center">الفرقة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php if (empty($students_list)): ?>
 <tr>
 <td colspan="5" class="p-8 text-center text-gray-500 font-bold">
 <i class="fas fa-check-circle text-primary text-3xl mb-2 block"></i>
 جميع الطلاب المقيدين قاموا بتسجيل مقرراتهم.
 </td>
 </tr>
 <?php else: ?>
 <?php foreach ($students_list as $s): ?>
 <tr class="hover:bg-bg transition-colors">
 <td class="p-4 text-center">
 <input type="checkbox" name="student_ids[]" value="<?= $s['student_id'] ?>" class="student-checkbox w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
 </td>
 <td class="p-4 font-mono font-bold text-primary">
 <?= htmlspecialchars($s['academic_number']) ?>
 </td>
 <td class="p-4 font-bold text-gray-800">
 <?= htmlspecialchars($s['full_name']) ?>
 </td>
 <td class="p-4 text-sm text-gray-600">
 <?= htmlspecialchars($s['college_name'] ?? 'بدون كلية') ?>
 </td>
 <td class="p-4 text-center">
 <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold">
 <?= $s['level'] ?>
 </span>
 </td>
 </tr>
 <?php endforeach; ?>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
 </form>
 </div>
</div>

<script>
 // Select All functionality
 document.getElementById('selectAll').addEventListener('change', function(e) {
 const checkboxes = document.querySelectorAll('.student-checkbox');
 checkboxes.forEach(cb => cb.checked = e.target.checked);
 });
</script>

<?php require_once 'includes/footer.php'; ?>
