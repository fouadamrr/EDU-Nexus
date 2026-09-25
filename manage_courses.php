<?php
require_once 'includes/header.php';
// $db is available from db.php

// التأكد إن اللي داخل دا معاه صلاحية الأدمن أو العميد
if (!in_array($role, ['admin', 'dean'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('programs');

$message = '';

// اختيار الكلية - لو داخل بصفة أدمن يقدر يفلتر بالكليات
if (in_array($role, ['admin', 'super_admin'])) {
 $selected_college_id = (int)($_GET['college_id'] ?? $_SESSION['college_id'] ?? 0);
} else {
 $selected_college_id = (int)($_SESSION['college_id'] ?? 0);
}

require_once __DIR__ . '/controllers/CourseController.php';
require_once __DIR__ . '/models/College.php';

$courseController = new CourseController();

// التعامل مع طلبات الإضافة والتعديل والحذف اللي جاية من الفورمة
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete'])) {
 $action = $_POST['action'] ?? null;
 $postData = $_POST;
 if (isset($_GET['delete'])) {
 $action = 'delete';
 $postData['delete'] = $_GET['delete'];
 }
 
 $res = $courseController->handleRequest($postData, $action);
 if ($res) {
 $message = $res;
 }
}

// جلب لستة المواد الدراسية
$courses = $courseController->getCoursesList($selected_college_id);

// جلب لستة الدكاترة النشطين عشان نربطهم بالمواد
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'instructor' AND status = 'active' ORDER BY full_name ASC");
$stmt->execute();
$instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-6xl mx-auto space-y-8 animate-fade-in-up">

 <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
 <h2 class="text-2xl font-bold text-gray-800">إدارة المقررات الدراسية</h2>
 
 <div class="flex items-center gap-3 w-full md:w-auto">
 <?php if (in_array($role, ['admin', 'super_admin'])): ?>
 <form method="GET" class="flex items-center gap-2 w-full md:w-auto">
 <select name="college_id" onchange="this.form.submit()" class="border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-primary text-sm font-bold text-gray-700 bg-white">
 <option value="0">جميع الكليات (عرض الكل)</option>
 <?php 
 $collegeModel = new College();
 $all_colleges = $collegeModel->findAll();
 foreach ($all_colleges as $c): 
 $selected = ($selected_college_id == $c['id']) ? 'selected' : '';
 ?>
 <option value="<?php echo $c['id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($c['name']); ?></option>
 <?php endforeach; ?>
 </select>
 </form>
 <?php endif; ?>

  <button onclick="openAddModal()"
  class="bg-primary text-white px-5 py-2.5 rounded-xl hover:bg-indigo-700 transition-all shadow-md flex items-center gap-2 font-bold whitespace-nowrap">
  <i class="fas fa-plus"></i> إضافة مقرر جديد
  </button>
 </div>
 </div>

 <?php echo $message; ?>

 <!-- جدول المواد الدراسية -->
 <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-right">
 <thead class="bg-bg border-b border-gray-200 text-gray-500 font-bold text-sm">
 <tr>
 <th class="p-4">#</th>
 <th class="p-4">كود المقرر</th>
 <th class="p-4">اسم المقرر</th>
 <th class="p-4 text-center">الفرقة</th>
 <th class="p-4">الساعات المعتمدة</th>
 <th class="p-4 text-center">إجراءات</th>
 </tr>
 </thead>
  <tbody class="divide-y divide-slate-100">
  <?php foreach ($courses as $c): ?>
  <tr class="hover:bg-primary/5 transition-colors">
 <td class="p-4 text-gray-400">
 <?php echo $c['id']; ?>
 </td>
 <td class="p-4 font-bold font-mono text-primary">
 <?php echo htmlspecialchars($c['code']); ?>
 </td>
 <td class="p-4 font-bold">
 <?php echo htmlspecialchars($c['name']); ?>
 </td>
 <td class="p-4 text-center">
 <?php
 $lv = (int)($c['level'] ?? 0);
 $lv_ar = [1=>'الأولى',2=>'الثانية',3=>'الثالثة',4=>'الرابعة'];
 if ($lv > 0):
 ?>
  <span class="text-xs bg-primary/10 text-primary border border-primary/20 font-bold px-3 py-1 rounded-full"><?php echo $lv_ar[$lv] ?? $lv; ?></span>
 <?php else: ?>
 <span class="text-xs text-gray-400">عام</span>
 <?php endif; ?>
 </td>
 <td class="p-4 text-gray-500"><?php echo $c['credit_hours']; ?> ساعات</td>
 <td class="p-4 text-center">
 <?php $college_param = ($selected_college_id > 0) ? "&college_id={$selected_college_id}" : ""; ?>
 <div class="flex items-center justify-center gap-2">
  <a href="course_results_report.php?id=<?php echo $c['id']; ?><?php echo $college_param; ?>"
  class="text-xs bg-primary/10 text-primary border border-primary/20 hover:bg-primary hover:text-white px-3 py-1 rounded-lg transition-all inline-flex items-center gap-1 font-bold" title="تقرير النتائج">
  <i class="fas fa-chart-pie"></i> تقرير
  </a>
  <a href="submit_grades.php?id=<?php echo $c['id']; ?><?php echo $college_param; ?>"
  class="text-xs bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-600 hover:text-white px-3 py-1 rounded-lg transition-all inline-flex items-center gap-1 font-bold">
  <i class="fas fa-check-double"></i> رصد الدرجات
  </a>
  <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8'); ?>)"
  class="text-xs bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-600 hover:text-white px-3 py-1 rounded-lg transition-all">
  <i class="fas fa-edit"></i> تعديل
  </button>
  <a href="?delete=<?php echo $c['id']; ?><?php echo $college_param; ?>"
  class="text-xs bg-rose-50 border border-rose-100 text-rose-600 hover:bg-rose-600 hover:text-white px-3 py-1 rounded-lg transition-all"
  onclick="return confirm('هل أنت متأكد؟')">
  <i class="fas fa-trash"></i> حذف
  </a>
 </div>
 </td>
 </tr>
 <?php
endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
</div>

<!-- مودال إضافة وتعديل المواد -->
<div id="courseModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
 <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden animate-scale-in">
  <div class="bg-gradient-to-r from-primary to-indigo-700 px-6 py-5 flex justify-between items-center text-white">
  <h3 class="text-xl font-bold flex items-center gap-2" id="modalTitle">
  <i class="fas fa-plus-circle"></i> إضافة مقرر جديد
  </h3>
  <button onclick="closeModal()" class="text-white/70 hover:text-white hover:bg-white/10 w-9 h-9 rounded-full flex items-center justify-center transition-all">
  <i class="fas fa-times text-xl"></i>
  </button>
  </div>

  <form method="POST" action="?tab=programs&college_id=<?php echo $selected_college_id; ?>" class="p-6 space-y-5" id="courseForm">
  <input type="hidden" name="action" id="formAction" value="add">
  <input type="hidden" name="id" id="courseId">
  <input type="hidden" name="college_id" id="collegeId" value="<?php echo $selected_college_id; ?>">

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">كود المقرر</label>
 <input type="text" name="code" id="code" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg"
 placeholder="مثال: CS101">
 </div>

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">اسم المقرر</label>
 <input type="text" name="name" id="name" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 </div>

 <?php if (in_array($role, ['admin', 'super_admin'])): ?>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">الكلية</label>
 <select name="college_id_select" id="college_id_select" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 <option value="">-- اختر الكلية --</option>
 <?php foreach ($all_colleges as $c): ?>
 <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
 <?php endforeach; ?>
 </select>
 </div>
 <?php endif; ?>

 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">الفرقة الدراسية</label>
 <select name="level" id="level"
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 <option value="0">عام (كل الفرق)</option>
 <option value="1">الفرقة الأولى</option>
 <option value="2">الفرقة الثانية</option>
 <option value="3">الفرقة الثالثة</option>
 <option value="4">الفرقة الرابعة</option>
 </select>
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">الساعات المعتمدة</label>
 <input type="number" name="credit" id="credit" value="3"
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 </div>
 </div>

 <div class="grid grid-cols-3 gap-3">
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">التحريري (نظري)</label>
 <input type="number" name="theory_marks" id="theory_marks" value="60" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">أعمال السنة</label>
 <input type="number" name="coursework_marks" id="coursework_marks" value="20" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 </div>
 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">العملي</label>
 <input type="number" name="practical_marks" id="practical_marks" value="20" required
 class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 </div>
 </div>

 <div>
 <label class="block text-sm font-bold text-gray-700 mb-1">دكتور المادة (اختياري)</label>
 <select name="instructor_id" id="instructor_id" class="w-full border border-gray-300 rounded p-2 focus:ring-2 focus:ring-accent bg-bg">
 <option value="">-- غير محدد --</option>
 <?php foreach ($instructors as $inst): ?>
 <option value="<?php echo $inst['id']; ?>"><?php echo htmlspecialchars($inst['full_name']); ?></option>
 <?php endforeach; ?>
 </select>
 </div>

  <div class="pt-4 flex gap-4">
  <button type="button" onclick="closeModal()"
  class="flex-1 bg-slate-50 text-slate-600 py-3 rounded-xl font-bold hover:bg-slate-100 transition-all">إلغاء</button>
  <button type="submit"
  class="flex-1 bg-primary text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition-all shadow-md">حفظ
  البيانات</button>
  </div>
 </form>
 </div>
</div>

<script>
 const modal = document.getElementById('courseModal');
 const form = document.getElementById('courseForm');
 const modalTitle = document.getElementById('modalTitle');
 const formAction = document.getElementById('formAction');
 const courseId = document.getElementById('courseId');
 const code = document.getElementById('code');
 const name = document.getElementById('name');
 const credit = document.getElementById('credit');
 const theory_marks = document.getElementById('theory_marks');
 const coursework_marks = document.getElementById('coursework_marks');
 const practical_marks = document.getElementById('practical_marks');

 function openAddModal() {
 modal.classList.remove('hidden');
 modalTitle.innerText = 'إضافة مقرر جديد';
 formAction.value = 'add';
 courseId.value = '';
 form.reset();
 credit.value = 3;
 theory_marks.value = 60;
 coursework_marks.value = 20;
 practical_marks.value = 20;
 document.getElementById('instructor_id').value = '';
 const collegeSelect = document.getElementById('college_id_select');
 if(collegeSelect) collegeSelect.value = document.getElementById('collegeId').value > 0 ? document.getElementById('collegeId').value : '';
 }

 function openEditModal(course) {
 modal.classList.remove('hidden');
 modalTitle.innerText = 'تعديل المقرر';
 formAction.value = 'edit';
 courseId.value = course.id;
 document.getElementById('collegeId').value = course.college_id || 0;
 code.value = course.code;
 name.value = course.name;
 credit.value = course.credit_hours;
 theory_marks.value = course.theory_marks !== undefined ? course.theory_marks : 60;
 coursework_marks.value = course.coursework_marks !== undefined ? course.coursework_marks : 20;
 practical_marks.value = course.practical_marks !== undefined ? course.practical_marks : 20;
 document.getElementById('level').value = course.level || 0;
 document.getElementById('instructor_id').value = course.instructor_id || '';
 const collegeSelect = document.getElementById('college_id_select');
 if(collegeSelect) collegeSelect.value = course.college_id || '';
 }

 function closeModal() {
 modal.classList.add('hidden');
 }
</script>

<?php require_once 'includes/footer.php'; ?>
