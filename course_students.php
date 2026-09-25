<?php
require_once 'includes/header.php';

// التأكد من رتبة اليوزر (لازم يكون دكتور)
if ($role !== 'instructor') {
 header("Location: index.php");
 exit;
}

$course_id = (int)($_GET['id'] ?? 0);

// بنجيب بيانات المادة عشان نعرض اسمها
$stmt = $db->safeQuery("SELECT * FROM courses WHERE id = :id LIMIT 1", [':id' => $course_id]);
$course = $stmt->fetch();

if (!$course) {
 echo "<div class='p-8 text-center text-primary font-bold'>المقرر غير موجود</div>";
 require_once 'includes/footer.php';
 exit;
}

// بنجيب أسماء كل الكليات عشان نبقى عارفين كل طالب منين
$colleges_list = $db->findAll('colleges');
$colleges_map = [];
foreach ($colleges_list as $c) {
 $colleges_map[$c['id']] = $c['name'];
}

// بنجيب كل الطلاب اللي مسجلين في المادة دي دلوقتي
$sql = "SELECT u.id, u.username, u.full_name, u.college_id, e.status AS enrollment_status
 FROM enrollments e
 INNER JOIN users u ON u.id = e.user_id
 WHERE e.course_id = :course_id";
$stmt = $db->safeQuery($sql, [':course_id' => $course_id]);
$students = $stmt->fetchAll();
?>

<div class="max-w-5xl mx-auto space-y-8 animate-fade-in-up">

 <div class="flex items-center justify-between border-b border-gray-200 pb-4">
 <div>
 <h2 class="text-2xl font-bold text-gray-800">كشف الطلاب</h2>
 <p class="text-gray-500 mt-1">الطلاب المسجلين في مقرر: <span class="font-bold text-primary">
 <?php echo htmlspecialchars($course['name']); ?> (
 <?php echo htmlspecialchars($course['code']); ?>)
 </span></p>
 </div>
 <a href="instructor_dashboard.php"
 class="text-gray-500 hover:text-primary transition flex items-center gap-2">
 <i class="fas fa-arrow-right"></i> عودة
 </a>
 </div>

 <!-- Students Table -->
 <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
 <?php if (empty($students)): ?>
 <div class="py-16 text-center text-gray-400">
 <i class="fas fa-users text-4xl mb-3 block"></i>
 <p class="font-bold">لا يوجد طلاب مسجلون في هذا المقرر بعد.</p>
 </div>
 <?php else: ?>
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-primary text-primary font-bold text-sm">
 <tr>
 <th class="p-4">#</th>
 <th class="p-4">رقم القيد</th>
 <th class="p-4">الاسم الكامل</th>
 <th class="p-4">الكلية</th>
 <th class="p-4 text-center">الحالة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php foreach ($students as $idx => $s):
 $college_label = $colleges_map[$s['college_id']] ?? '—';
 $status_label = $s['enrollment_status'] === 'dropped' ? 'محذوف'
 : ($s['enrollment_status'] === 'completed' ? 'مكتمل' : 'مقيد');
 $status_class = $s['enrollment_status'] === 'dropped' ? 'bg-primary text-white'
 : ($s['enrollment_status'] === 'completed' ? 'bg-primary text-white'
 : 'bg-primary text-white');
 ?>
 <tr class="hover:bg-bg transition">
 <td class="p-4 text-gray-400"><?php echo $idx + 1; ?></td>
 <td class="p-4 font-bold font-mono text-gray-700">
 <?php echo htmlspecialchars($s['username']); ?>
 </td>
 <td class="p-4 font-bold">
 <?php echo htmlspecialchars($s['full_name']); ?>
 </td>
 <td class="p-4 text-gray-500"><?php echo htmlspecialchars($college_label); ?></td>
 <td class="p-4 text-center">
 <span class="text-xs px-2 py-1 rounded font-bold <?php echo $status_class; ?>">
 <?php echo $status_label; ?>
 </span>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 <?php endif; ?>
 </div>

</div>

<?php require_once 'includes/footer.php'; ?>