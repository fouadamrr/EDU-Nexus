<?php
require_once 'includes/header.php';

// اتأكد إن اللي داخل دا دكتور فعلاً
if ($role !== 'instructor') {
 header("Location: index.php");
 exit;
}

require_once __DIR__ . '/controllers/InstructorDashboardController.php';
require_once __DIR__ . '/controllers/TextbookController.php';
$dashController   = new InstructorDashboardController();
$textbookCtrl     = new TextbookController();
$instructor_id    = $_SESSION['user_id'];

$dashboardData = $dashController->getDashboardData($instructor_id, $_SESSION['user'] ?? null);
$display_name            = $dashboardData['display_name'];
$display_dept            = $dashboardData['display_dept'];
$display_subject         = $dashboardData['display_subject'];
$my_students             = $dashboardData['my_students'];
$my_course_id_for_grades = $dashboardData['my_course_id'];

$upload_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['book_file'])) {
    // بنستخدم الكنترولر الجديد بتاع الكتب عشان نرفع الكتاب بكل بياناته
    if (isset($_POST['textbook_upload'])) {
        $result     = $textbookCtrl->uploadTextbook($_POST, $_FILES, $instructor_id);
        $upload_msg = $result['success']
            ? "<div class='bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl flex items-center gap-3 mt-4'><i class='fas fa-check-circle text-lg'></i><span class='font-bold'>" . htmlspecialchars($result['message']) . "</span></div>"
            : "<div class='bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl flex items-center gap-3 mt-4'><i class='fas fa-times-circle text-lg'></i><span class='font-bold'>" . htmlspecialchars($result['message']) . "</span></div>";
    } else {
        $upload_msg = $dashController->handleBookUpload($_FILES, $_POST, $instructor_id);
    }
}

// قائمة الكليات عشان يختار منها
$all_colleges_list = $textbookCtrl->getAllColleges();
$instructor_college_id = (int)($_SESSION['college_id'] ?? 0);

// المواد اللي الدكتور دا بيديها
$my_courses_list = $textbookCtrl->getInstructorCourses($instructor_id);
?>

<div class="max-w-7xl mx-auto space-y-8 pb-12">

 <!-- الهيدر بتاع الترحيب والشكل العام -->
 <div class="relative overflow-hidden bg-gradient-to-r from-primary to-accent rounded-2xl p-8 md:p-10 text-white shadow-card mb-8 border border-white/10">
 <div class="absolute -right-20 -top-20 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
 <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white opacity-10 rounded-full blur-2xl"></div>
 <div class="relative z-10 flex flex-col md:flex-row justify-between items-center md:items-start gap-6 group">
 <div class="flex flex-col md:flex-row items-center gap-6">
 <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/20 shadow-sm transition-transform group-hover:scale-105">
 <i class="fas fa-chalkboard-teacher text-4xl text-white"></i>
 </div>
 <div class="text-center md:text-right text-white">
 <h1 class="text-3xl font-bold mb-2 text-white">مرحباً، د. <?php echo htmlspecialchars($display_name); ?> 👋</h1>
 <div class="flex items-center justify-center md:justify-start gap-2 text-white/90">
 <i class="fas fa-university text-sm text-white"></i>
 <span class="text-lg font-bold text-white"><?php echo htmlspecialchars($display_dept); ?></span>
 </div>
 </div>
 </div>
 
 <div class="bg-white/10 px-8 py-4 rounded-2xl backdrop-blur-md border border-white/20 text-center min-w-[220px] shadow-sm">
 <p class="text-xs text-white/80 uppercase tracking-widest mb-1 font-bold">المادة التدريسية</p>
 <p class="text-xl font-bold tracking-wide text-white"><?php echo htmlspecialchars($display_subject); ?></p>
 </div>
 </div>
 </div>

 <!-- الإحصائيات وسجل الحضور -->
 <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-8">
 <!-- منطقة الرسم البياني -->
 <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-primary/10 p-6 flex flex-col items-center relative overflow-hidden group hover:shadow-md transition-shadow">
 <div class="absolute inset-0 bg-primary/5 skew-y-12 transform origin-bottom-left group-hover:bg-primary/10 transition-colors"></div>
  <h3 class="text-secondary font-bold mb-6 relative z-10 w-full flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
      <i class="fas fa-chart-bar"></i>
    </div>
    تحليل الحضور (تجريبي)
  </h3>
 <!-- شكل بياني بسيط كدا للمنظر -->
 <div class="flex items-end space-x-2 space-x-reverse h-32 w-full justify-center relative z-10">
 <div class="w-8 bg-primary rounded-t-lg transition hover:bg-primary relative group/bar h-3/4">
 <span class="absolute -top-7 left-1/2 transform -translate-x-1/2 text-xs font-bold text-secondary/70 opacity-0 group-hover/bar:opacity-100 transition-opacity bg-white shadow-sm px-2 py-1 rounded">75%</span>
 </div>
 <div class="w-8 bg-primary rounded-t-lg transition hover:bg-primary relative group/bar h-1/2">
 <span class="absolute -top-7 left-1/2 transform -translate-x-1/2 text-xs font-bold text-secondary/70 opacity-0 group-hover/bar:opacity-100 transition-opacity bg-white shadow-sm px-2 py-1 rounded">50%</span>
 </div>
 <div class="w-8 bg-primary rounded-t-lg transition hover:bg-accent hover:text-white relative group/bar h-full drop-shadow-sm">
 <span class="absolute -top-7 left-1/2 transform -translate-x-1/2 text-xs font-bold text-secondary/70 opacity-0 group-hover/bar:opacity-100 transition-opacity bg-white shadow-sm px-2 py-1 rounded">100%</span>
 </div>
 </div>
 <p class="text-xs text-center text-secondary/50 mt-4 font-mono font-medium">احصائيات القسم</p>
 
 <?php
 $my_course_id = $my_course_id_for_grades;
 ?>
 <a href="submit_grades.php?id=<?php echo $my_course_id; ?>" class="mt-6 w-full py-2.5 bg-primary/5 text-primary border-primary text-sm font-bold border rounded-xl hover:bg-primary hover:text-white transition-colors flex items-center justify-center gap-2 shadow-sm">
 <i class="fas fa-file-signature"></i> رصد درجات أعمال السنة
 </a>
 </div>

 <!-- تسجيل الغياب (جوه قائمة منسدلة) -->
 <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-primary/10 overflow-hidden flex flex-col group hover:shadow-md transition-shadow">
 <div class="p-6 border-b border-primary/10 bg-primary/5/50 flex flex-wrap justify-between items-center gap-4">
 <button onclick="toggleStudentList()" class="flex items-center gap-4 focus:outline-none flex-1 text-right">
  <div class="w-12 h-12 rounded-full bg-indigo-50/50 flex items-center justify-center text-indigo-600 border border-indigo-100 group-hover:scale-105 transition-transform">
    <i class="fas fa-users-cog text-xl"></i>
  </div>
 <div>
 <h3 class="text-xl font-bold text-secondary">سجل الحضور والطلاب</h3>
 <p class="text-sm text-secondary/70 mt-0.5"><span class="font-bold text-accent"><?php echo count($my_students); ?></span> طالب مسجل بالمقرر</p>
 </div>
 <i class="fas fa-chevron-down mr-auto text-secondary/50 transition-transform transform" id="toggleIcon"></i>
 </button>
 
 <?php
if (isset($_GET['attendance']) && $_GET['attendance'] == 'saved') {
 echo "<span class='bg-primary/5 text-primary border border-primary px-4 py-1.5 rounded-full text-sm font-bold animate-pulse flex items-center gap-2'><i class='fas fa-check'></i> تم الحفظ بنجاح!</span>";
}
?>
 </div>
 
 <div id="studentList" class="hidden border-t border-primary/10 bg-white flex-1 flex flex-col">
 <form action="save_attendance.php" method="POST" class="flex flex-col h-full">
 <div class="p-4 bg-primary/5 flex flex-wrap justify-between items-center border-b border-primary/10 gap-4">
 <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-xl border border-primary/10 shadow-sm">
 <label class="text-sm font-bold text-secondary">تاريخ المحاضرة:</label>
 <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" class="bg-transparent border-none p-0 text-sm font-mono text-primary focus:ring-0 cursor-pointer">
 </div>
 <button type="submit" class="bg-primary hover:bg-accent hover:text-white text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-sm transition-all transform active:scale-95 flex items-center gap-2">
 <i class="fas fa-save"></i> حفظ الغياب
 </button>
 </div>

 <div class="overflow-x-auto flex-1">
 <table class="w-full text-right table-fixed">
 <thead class="bg-white text-secondary/50 font-bold text-xs uppercase tracking-wider border-b border-primary/10 sticky top-0 shadow-sm z-10">
 <tr>
 <th class="px-6 py-4 w-2/5">الطالب</th>
 <th class="px-6 py-4 w-1/5 text-center">المستوى / GPA</th>
 <th class="px-6 py-4 w-1/5 text-center">مرات الغياب</th>
 <th class="px-6 py-4 w-1/5 text-center">تسجيل الحضور</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50 text-secondary">
 <?php if (empty($my_students)): ?>
 <tr><td colspan="4" class="p-12 text-center text-secondary/50 italic font-medium">لا يوجد طلاب مسجلين.</td></tr>
 <?php
else: ?>
 <?php foreach ($my_students as $idx => $s):
 $uname = $s['username'] ?? '';
 $fname = $s['full_name'] ?? 'Unknown';
 $lvl = $s['level'] ?? '-';
 $sgpa = $s['gpa'] ?? '0.00';
 $sid = $s['id'];
 $is_trial_row = ($uname === 'trial_student');

 $absent_count = $dashController->getStudentAbsences($sid, $instructor_id);
 $is_warned = ($absent_count >= 3);

 $row_class = $is_warned ? 'bg-primary/5/30' : 'hover:bg-primary/5/50';
 if ($is_trial_row)
 $row_class = 'bg-primary/5/50';
?>
 <tr class="transition-colors <?php echo $row_class; ?>">
 <td class="px-6 py-4">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-full bg-primary/5 text-secondary/70 flex items-center justify-center font-bold text-sm">
 <?php echo mb_substr($fname, 0, 1, 'UTF-8'); ?>
 </div>
 <div>
 <div class="font-bold text-secondary">
 <?php echo htmlspecialchars($fname); ?>
 </div>
 <div class="text-xs text-secondary/50 font-mono mt-0.5 flex items-center gap-2">
 <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($uname); ?>
 </div>
 </div>
 <?php if ($is_trial_row): ?>
 <span class="mr-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary text-white border border-primary">TRIAL</span>
 <?php
 endif; ?>
 <?php if ($is_warned): ?>
 <span class="mr-auto px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary text-white border border-primary shadow-sm animate-pulse">إنذار غياب</span>
 <?php
 endif; ?>
 </div>
 </td>
 <td class="px-6 py-4 text-center">
 <div class="text-sm font-bold text-secondary"><?php echo htmlspecialchars($lvl); ?></div>
 <div class="text-xs text-accent font-mono mt-0.5 font-bold"><i class="fas fa-star text-[10px]"></i> <?php echo htmlspecialchars($sgpa); ?></div>
 </td>
 <td class="px-6 py-4 text-center">
 <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg font-mono font-bold <?php echo $is_warned ? 'bg-primary text-white' : 'bg-primary/5 text-secondary/80'; ?>">
 <?php echo $absent_count; ?>
 </span>
 </td>
 <td class="px-6 py-4 text-center">
  <!-- اختيارات الحضور والغياب -->
 <div class="flex justify-center items-center gap-2 bg-primary/5 p-1 rounded-lg inline-flex">
 <label class="cursor-pointer relative">
 <input type="radio" name="attendance[<?php echo $sid; ?>]" value="Present" class="peer sr-only" checked>
 <span class="px-3 py-1.5 rounded-md text-xs font-bold text-secondary/70 peer-checked:bg-white peer-checked:text-primary peer-checked:shadow-sm transition-all block">حضور</span>
 </label>
 <label class="cursor-pointer relative">
 <input type="radio" name="attendance[<?php echo $sid; ?>]" value="Absent" class="peer sr-only">
 <span class="px-3 py-1.5 rounded-md text-xs font-bold text-secondary/70 peer-checked:bg-white peer-checked:text-primary peer-checked:shadow-sm transition-all block">غياب</span>
 </label>
 </div>
 </td>
 </tr>
 <?php
 endforeach; ?>
 <?php
endif; ?>
 </tbody>
 </table>
 </div>
 </form>
 </div>
 </div>
 </div>

 <script>
 function toggleStudentList() {
 const list = document.getElementById('studentList');
 const icon = document.getElementById('toggleIcon');
 
 if (list.classList.contains('hidden')) {
 list.classList.remove('hidden');
 icon.style.transform = 'rotate(180deg)';
 } else {
 list.classList.add('hidden');
 icon.style.transform = 'rotate(0deg)';
 }
 }
 </script>

  <!-- إدارة الامتحانات -->
 <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
  <!-- كارت إضافة امتحان جديد -->
 <div class="lg:col-span-1">
 <div class="bg-white rounded-2xl shadow-sm border border-primary/10 p-6 h-full relative overflow-hidden group hover:shadow-md transition-shadow">
 <div class="absolute top-0 left-0 w-32 h-32 bg-primary/5/50 rounded-br-full -ml-8 -mt-8 z-0 group-hover:bg-primary/5 transition-colors"></div>
 <h2 class="text-lg font-bold text-secondary mb-6 flex items-center gap-3 relative z-10 w-full border-b border-primary/10 pb-4">
    <div class="w-10 h-10 rounded-full bg-emerald-50/50 flex items-center justify-center text-emerald-600 border border-emerald-100">
      <i class="fas fa-plus-circle text-lg"></i>
    </div>
 إضافة اختبار جديد
 </h2>
 
 <?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
 echo $dashController->handleExamCreation($_POST, $instructor_id);
}
?>

 <form method="POST" class="space-y-5 relative z-10">
 <input type="hidden" name="create_exam" value="1">
 <div>
 <label class="block text-sm font-bold text-secondary mb-2">اسم الاختبار</label>
 <input type="text" name="exam_name" class="w-full bg-primary/5 border border-primary/10 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-all font-medium text-secondary" placeholder="مثال: اختبار منتصف الفصل" required>
 </div>
 
 <div>
 <label class="block text-sm font-bold text-secondary mb-2">الدرجة الكلية</label>
 <input type="number" name="total_marks" class="w-full bg-primary/5 border border-primary/10 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-all font-mono font-bold text-secondary" placeholder="100" required>
 </div>
 
 <button type="submit" class="w-full bg-primary hover:bg-accent hover:text-white text-white font-bold py-3.5 rounded-xl transition-all shadow-md shadow-sm flex items-center justify-center gap-2">
 <i class="fas fa-save"></i> حفظ الاختبار
 </button>
 </form>
 </div>
 </div>

  <!-- لستة الامتحانات بتاعتي -->
 <div class="lg:col-span-2">
 <div class="bg-white rounded-2xl shadow-sm border border-primary/10 overflow-hidden h-full flex flex-col group hover:shadow-md transition-shadow">
 <div class="p-6 border-b border-primary/10 bg-primary/5/50 flex items-center justify-between">
 <h2 class="text-lg font-bold text-secondary flex items-center gap-3 w-full border-b border-primary/10 pb-4 mb-0">
    <div class="w-10 h-10 rounded-full bg-sky-50/50 flex items-center justify-center text-sky-600 border border-sky-100">
      <i class="fas fa-clipboard-list text-lg"></i>
    </div>
 اختباراتي المسجلة
 </h2>
 </div>

 <div class="flex-grow overflow-x-auto p-2">
 <table class="w-full text-right table-fixed">
 <thead class="bg-white text-secondary/50 font-bold text-xs border-b border-primary/10 uppercase tracking-wider">
 <tr>
 <th class="px-6 py-4 w-2/5">اسم الاختبار</th>
 <th class="px-6 py-4 w-1/5 text-center">الدرجة</th>
 <th class="px-6 py-4 w-2/5 text-center">الإجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50 text-secondary">
 <?php
$my_exams = $dashController->getInstructorExams($instructor_id);
if (empty($my_exams)): ?>
 <tr><td colspan="3" class="p-12 text-center text-secondary/50 italic font-medium">لم تقم بإنشاء أي اختبارات بعد.</td></tr>
 <?php
else: ?>
 <?php foreach ($my_exams as $exam): ?>
 <tr class="hover:bg-primary/5/50 transition-colors">
 <td class="px-6 py-4">
 <div class="font-bold text-secondary"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
 <div class="text-xs text-secondary/50 mt-1">
 <i class="far fa-calendar-alt text-[10px]"></i> <?php echo htmlspecialchars($exam['date'] ?? date('Y-m-d')); ?>
 </div>
 </td>
 <td class="px-6 py-4 text-center">
 <span class="inline-flex items-center justify-center w-12 h-8 rounded-lg font-mono font-bold bg-primary/5 text-secondary/80 border border-primary/10 shadow-sm">
 <?php echo $exam['total_marks']; ?>
 </span>
 </td>
 <td class="px-6 py-4 text-center">
 <div class="flex items-center justify-center gap-2">
 <a href="grade_exam.php?exam_id=<?php echo $exam['id']; ?>" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-accent text-white text-sm font-bold hover:bg-sky-600 transition-colors shadow-sm shadow-sky-200">
 <i class="fas fa-edit ml-2"></i> رصد الدرجات
 </a>
 <a href="delete_exam.php?id=<?php echo $exam['id']; ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary/5 text-white text-sm font-bold hover:bg-secondary hover:text-white transition-colors" onclick="return confirm('هل أنت متأكد من مسح هذا الاختبار نهائياً؟');" title="مسح الاختبار">
 <i class="fas fa-trash-alt"></i>
 </a>
 </div>
 </td>
 </tr>
 <?php
 endforeach; ?>
 <?php
endif; ?>
 </tbody>
 </table>
 </div>
 </div>
 </div>
 </div>

  <!-- رفع الكتب والتحكم فيها -->
 <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

   <!-- كارت الرفع -->
  <div class="lg:col-span-1">
   <div class="bg-white rounded-2xl shadow-sm border border-primary/10 p-6 h-full relative overflow-hidden group hover:shadow-md transition-shadow">
    <div class="absolute top-0 right-0 w-32 h-32 bg-amber-50/30 rounded-bl-full -mr-8 -mt-8 z-0 group-hover:bg-amber-50/60 transition-colors"></div>
    <h2 class="text-lg font-bold text-secondary mb-5 flex items-center gap-3 relative z-10 border-b border-primary/10 pb-4">
     <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 border border-amber-100">
      <i class="fas fa-cloud-upload-alt text-lg"></i>
     </div>
     رفع كتاب دراسي جديد
    </h2>

    <?php echo $upload_msg; ?>

    <form action="instructor_dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4 relative z-10">
     <input type="hidden" name="textbook_upload" value="1">

     <!-- اسم الكتاب -->
     <div>
      <label class="block text-xs font-black text-secondary/60 mb-1.5 uppercase tracking-widest">عنوان الكتاب</label>
      <input type="text" name="book_title"
             class="w-full bg-primary/5 border border-primary/10 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent focus:bg-white transition-all font-bold text-secondary text-sm"
             placeholder="مثال: مبادئ علم النفس التربوي" required>
     </div>

     <!-- اختار الكلية -->
     <div>
      <label class="block text-xs font-black text-secondary/60 mb-1.5 uppercase tracking-widest">الكلية</label>
      <select name="college_id"
              class="w-full bg-primary/5 border border-primary/10 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent focus:bg-white transition-all font-bold text-secondary text-sm" required>
       <option value="">— اختر الكلية —</option>
       <?php foreach ($all_colleges_list as $col): ?>
       <option value="<?php echo $col['id']; ?>" <?php echo $col['id'] == $instructor_college_id ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($col['name']); ?>
       </option>
       <?php endforeach; ?>
      </select>
     </div>

     <!-- اختار المادة -->
     <div>
      <label class="block text-xs font-black text-secondary/60 mb-1.5 uppercase tracking-widest">المادة الدراسية (اختياري)</label>
      <select name="course_id"
              class="w-full bg-primary/5 border border-primary/10 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent focus:bg-white transition-all font-bold text-secondary text-sm">
       <option value="0">— لجميع المواد أو مادة أخرى —</option>
       <?php foreach ($my_courses_list as $mc): ?>
       <option value="<?php echo $mc['id']; ?>">
        <?php echo htmlspecialchars($mc['name'] . ' (' . $mc['code'] . ')'); ?>
       </option>
       <?php endforeach; ?>
      </select>
      <p class="text-[10px] text-secondary/50 mt-1">إذا اخترت مادة، سيظهر الكتاب لطلاب هذه المادة تحديداً.</p>
     </div>

     <!-- الفرقة والسعر -->
     <div class="grid grid-cols-2 gap-3">
      <div>
       <label class="block text-xs font-black text-secondary/60 mb-1.5 uppercase tracking-widest">الفرقة المستهدفة</label>
       <select name="level"
               class="w-full bg-primary/5 border border-primary/10 rounded-xl px-3 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent focus:bg-white transition-all font-bold text-secondary text-sm">
        <option value="0">الكل</option>
        <option value="1">الفرقة الأولى</option>
        <option value="2">الفرقة الثانية</option>
        <option value="3">الفرقة الثالثة</option>
        <option value="4">الفرقة الرابعة</option>
       </select>
      </div>
      <div>
       <label class="block text-xs font-black text-secondary/60 mb-1.5 uppercase tracking-widest">السعر (ج.م)</label>
       <input type="number" name="price" min="0" step="0.5" value="0"
              class="w-full bg-primary/5 border border-primary/10 rounded-xl px-3 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent focus:bg-white transition-all font-black text-secondary text-base">
      </div>
     </div>

     <!-- ملف الكتاب نفسه -->
     <div>
      <label class="block text-xs font-black text-secondary/60 mb-1.5 uppercase tracking-widest">ملف الكتاب</label>
      <div class="relative w-full border-2 border-dashed border-primary/10 rounded-xl bg-primary/5 hover:border-accent hover:bg-accent/5 transition-colors p-1">
       <input type="file" name="book_file"
              class="w-full text-sm text-secondary/70 file:mr-0 file:ml-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-white file:text-accent hover:file:bg-accent hover:file:text-white file:cursor-pointer file:shadow-sm file:transition-colors cursor-pointer" required>
      </div>
      <p class="text-[10px] text-secondary/40 mt-1.5 flex items-center gap-1">
       <i class="fas fa-info-circle text-accent"></i> PDF, DOC, PPT — بحد أقصى 70 MB
      </p>
     </div>

     <button type="submit"
             class="w-full bg-gradient-to-r from-primary to-accent text-white font-black py-3 rounded-xl transition-all shadow-md hover:opacity-90 flex items-center justify-center gap-2 text-sm">
      <i class="fas fa-upload"></i> رفع الكتاب الدراسي
     </button>
    </form>
   </div>
  </div>

   <!-- قائمة الكتب اللي اترفعنت -->
  <div class="lg:col-span-2">
   <div class="bg-white rounded-2xl shadow-sm border border-primary/10 overflow-hidden h-full flex flex-col group hover:shadow-md transition-shadow">
    <div class="p-5 border-b border-primary/10 bg-primary/5/50 flex items-center justify-between">
     <h2 class="text-lg font-bold text-secondary flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 border border-blue-100">
       <i class="fas fa-book-reader text-lg"></i>
      </div>
      كتبي الدراسية المرفوعة
     </h2>
     <a href="textbook_payments.php" class="text-xs font-black text-primary bg-primary/10 px-3 py-1.5 rounded-lg hover:bg-primary hover:text-white transition hidden lg:inline-flex items-center gap-1.5">
      <i class="fas fa-list-check"></i> طلبات الدفع
     </a>
    </div>

    <div class="flex-grow overflow-x-auto">
     <table class="w-full text-right text-sm">
      <thead class="bg-slate-50 text-slate-500 font-black text-[11px] border-b border-slate-100 uppercase tracking-wider">
       <tr>
        <th class="px-5 py-3.5">الكتاب</th>
        <th class="px-5 py-3.5 text-center">الكلية / الفرقة</th>
        <th class="px-5 py-3.5 text-center">مدفوعات</th>
        <th class="px-5 py-3.5 text-center">إجراءات</th>
       </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
      <?php
      $my_books = $textbookCtrl->getInstructorBooks($instructor_id);
      if (empty($my_books)):
      ?>
       <tr><td colspan="4" class="p-12 text-center text-secondary/40 italic font-medium">
        لم تقم برفع أي كتب دراسية بعد. استخدم النموذج على اليسار.
       </td></tr>
      <?php else: ?>
      <?php foreach ($my_books as $book):
       $bTitle      = $book['book_title'] ?? $book['title'] ?? 'Untitled';
       $bFile       = $book['file_name'] ?? basename($book['file_path'] ?? '');
       $bDate       = date('Y-m-d', strtotime($book['upload_date'] ?? $book['created_at'] ?? 'now'));
       $bId         = (int)($book['id'] ?? 0);
       $bCollege    = $book['college_name'] ?? '—';
       $bLevel      = (int)($book['level'] ?? 0);
       $bPrice      = (float)($book['price'] ?? 0);
       $paidCount   = (int)($book['paid_count'] ?? 0);
       $pendingCount= (int)($book['pending_count'] ?? 0);
       $lvlNames    = [0=>'الكل',1=>'الأولى',2=>'الثانية',3=>'الثالثة',4=>'الرابعة'];
       $bLevelLabel = $lvlNames[$bLevel] ?? '—';
       $fileUrl     = 'uploads/books/' . htmlspecialchars($bFile);
      ?>
       <tr class="hover:bg-slate-50 transition-colors">
        <td class="px-5 py-4">
         <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
           <i class="fas fa-book text-sm"></i>
          </div>
          <div class="min-w-0">
           <div class="font-black text-slate-800 text-sm truncate" title="<?php echo htmlspecialchars($bTitle); ?>">
            <?php echo htmlspecialchars($bTitle); ?>
           </div>
           <div class="text-[10px] text-slate-400 font-mono mt-0.5">
            <?php echo $bDate; ?>
            <?php if ($bPrice > 0): ?> &bull; <span class="text-amber-600 font-bold"><?php echo number_format($bPrice, 0); ?> ج.م</span><?php endif; ?>
           </div>
          </div>
         </div>
        </td>
        <td class="px-5 py-4 text-center">
         <div class="text-xs font-bold text-slate-600 truncate max-w-[100px] mx-auto" title="<?php echo htmlspecialchars($bCollege); ?>">
          <?php echo htmlspecialchars($bCollege); ?>
         </div>
         <span class="inline-block mt-1 text-[10px] font-black bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full border border-indigo-100">
          <?php echo $bLevelLabel; ?>
         </span>
        </td>
        <td class="px-5 py-4 text-center">
         <div class="flex items-center justify-center gap-2">
          <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-100 text-[10px] font-black px-2 py-0.5 rounded-full" title="دفعوا">
           <i class="fas fa-check-circle"></i><?php echo $paidCount; ?>
          </span>
          <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-100 text-[10px] font-black px-2 py-0.5 rounded-full" title="بانتظار">
           <i class="fas fa-clock"></i><?php echo $pendingCount; ?>
          </span>
         </div>
        </td>
        <td class="px-5 py-4 text-center">
         <div class="flex items-center justify-center gap-1.5">
          <?php if ($bFile): ?>
          <a href="<?php echo $fileUrl; ?>" download
             class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-primary hover:text-white transition text-xs" title="تحميل">
           <i class="fas fa-download"></i>
          </a>
          <?php endif; ?>
          <a href="delete_book.php?id=<?php echo $bId; ?>"
             class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-rose-600 hover:text-white transition text-xs"
             onclick="return confirm('هل أنت متأكد من مسح هذا الكتاب نهائياً؟');" title="حذف">
           <i class="fas fa-trash-alt"></i>
          </a>
         </div>
        </td>
       </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
     </table>
    </div>
   </div>
  </div>
 </div>

</div>

<?php require_once 'includes/footer.php'; ?>