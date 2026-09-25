<?php
require_once 'includes/header.php';

// اتأكد إن اللي داخل دا أدمن أو عميد أو من الشؤون
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}

// بنجيب شوية أرقام وإحصائيات عن السيستم
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_instructors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor'")->fetchColumn();
$total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// لو هو عميد أو من شئون الطلبة، هات الأرقام بتاعة كليته بس
if ($role === 'affairs' || $role === 'dean') {
 $cid = $_SESSION['college_id'] ?? 0;
 
 $stmtS = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND college_id = ?");
 $stmtS->execute([$cid]);
 $total_students = $stmtS->fetchColumn();

 $stmtI = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND college_id = ?");
 $stmtI->execute([$cid]);
 $total_instructors = $stmtI->fetchColumn();

 $stmtC = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE college_id = ?");
 $stmtC->execute([$cid]);
 $total_courses = $stmtC->fetchColumn();
}
?>

<div class="space-y-8">

 <!-- Welcome Banner Matrouh (Modernized) -->
 <div class="relative overflow-hidden bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-2xl p-8 md:p-10 text-white shadow-card mb-8 border border-white/10">
 <div class="absolute -right-20 -top-20 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
 <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white opacity-10 rounded-full blur-2xl"></div>
 <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6 group">
 <div class="text-center md:text-right text-white">
 <h1 class="text-3xl font-bold mb-3 text-white">مرحباً، <?php echo htmlspecialchars($full_name); ?> 👋</h1>
 <p class="text-white text-lg font-bold inline-flex items-center gap-2 bg-white/10 px-5 py-2 rounded-xl backdrop-blur-md border border-white/20 shadow-sm">
 <i class="fas fa-shield-alt text-white text-xl"></i>
 <?php echo $role === 'affairs' ? 'لوحة تحكم شؤون الطلبة' : 'لوحة تحكم المسئول'; ?>
 </p>
 </div>
 <div class="w-20 h-20 bg-white/10 backdrop-blur-md border border-white/20 shadow-sm transition-transform rounded-2xl flex items-center justify-center p-2 overflow-hidden flex-shrink-0">
 <img src="assets/images/logo.png" alt="EDU Nexus Logo" class="w-full h-full object-contain rounded-xl bg-white p-1">
 </div>
 </div>
 </div>

 <!-- Stats Grid -->
 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
 <?php if (has_permission('students')): ?>
 <!-- Student Stat -->
 <a href="manage_users.php?role=student" class="bg-white p-6 rounded-2xl shadow-sm border border-primary/10 flex items-center justify-between group hover:border-primary hover:shadow-md hover:-translate-y-1 transition-all">
 <div>
 <p class="text-secondary/70 text-sm font-bold mb-1">إجمالي الطلاب</p>
 <div class="flex items-baseline gap-2">
 <p class="text-3xl font-bold text-secondary"><?php echo $total_students; ?></p>
 </div>
 </div>
 <div class="w-14 h-14 bg-primary/10 rounded-2xl flex items-center justify-center text-primary shadow-inner group-hover:bg-primary group-hover:text-white transition-all">
 <i class="fas fa-user-graduate text-2xl"></i>
 </div>
 </a>
 <?php endif; ?>

 <?php if ($role !== 'affairs' && has_permission('supervision')): ?>
 <!-- Instructor Stat -->
 <a href="manage_users.php?role=instructor" class="bg-white p-6 rounded-2xl shadow-sm border border-primary/10 flex items-center justify-between group hover:border-primary hover:shadow-md hover:-translate-y-1 transition-all">
 <div>
 <p class="text-secondary/70 text-sm font-bold mb-1">أعضاء هيئة التدريس</p>
 <div class="flex items-baseline gap-2">
 <p class="text-3xl font-bold text-secondary"><?php echo $total_instructors; ?></p>
 </div>
 </div>
 <div class="w-14 h-14 bg-primary/10 rounded-2xl flex items-center justify-center text-primary shadow-inner group-hover:bg-primary group-hover:text-white transition-all">
 <i class="fas fa-chalkboard-teacher text-2xl"></i>
 </div>
 </a>
 <?php endif; ?>

 <?php if ($role !== 'affairs' && has_permission('programs')): ?>
 <!-- Courses Stat -->
 <a href="manage_courses.php" class="bg-white p-6 rounded-2xl shadow-sm border border-primary/10 flex items-center justify-between group hover:border-accent/40 hover:shadow-md hover:-translate-y-1 transition-all">
 <div>
 <p class="text-secondary/70 text-sm font-bold mb-1">المقررات النشطة</p>
 <div class="flex items-baseline gap-2">
 <p class="text-3xl font-bold text-secondary"><?php echo $total_courses; ?></p>
 </div>
 </div>
 <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 shadow-inner group-hover:bg-emerald-500 group-hover:text-white transition-all">
 <i class="fas fa-book-open text-2xl"></i>
 </div>
 </a>
 <?php endif; ?>
 </div>

 <!-- Quick Actions -->
 <div>
 <h3 class="text-xl font-bold text-secondary mb-5 flex items-center gap-2">
 <i class="fas fa-bolt text-amber-500 bg-amber-50 p-2 rounded-xl"></i>
 لوحة التحكم السريعة
 </h3>
 
 <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
 
  <?php if (has_permission('students')): ?>
  <a href="manage_users.php"
     class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-primary flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
    <div class="w-14 h-14 bg-indigo-50/50 text-indigo-600 rounded-full flex items-center justify-center border border-indigo-100 group-hover:scale-110 transition-transform duration-300">
      <i class="fas <?php echo $role === 'affairs' ? 'fa-graduation-cap' : 'fa-users-cog'; ?> text-xl"></i>
    </div>
    <span class="font-bold text-secondary text-sm"><?php echo $role === 'affairs' ? 'درجات الطلاب' : 'إدارة المستخدمين'; ?></span>
  </a>
  <?php endif; ?>

  <?php if (in_array($role, ['super_admin', 'admin']) && has_permission('students')): ?>
  <a href="all_students.php"
     class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-primary flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
    <div class="w-14 h-14 bg-sky-50/50 text-sky-500 rounded-full flex items-center justify-center border border-sky-100 group-hover:scale-110 transition-transform duration-300">
      <i class="fas fa-user-graduate text-xl"></i>
    </div>
    <span class="font-bold text-secondary text-sm">جميع طلاب الكليات</span>
  </a>
  <?php endif; ?>

  <?php if ($role !== 'affairs' && has_permission('programs')): ?>
  <a href="manage_courses.php"
     class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-accent flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
    <div class="w-14 h-14 bg-purple-50/50 text-purple-600 rounded-full flex items-center justify-center border border-purple-100 group-hover:scale-110 transition-transform duration-300">
      <i class="fas fa-book text-xl"></i>
    </div>
    <span class="font-bold text-secondary text-sm">إدارة المقررات</span>
  </a>
  <?php endif; ?>



  <?php if ($role !== 'affairs' && has_permission('results')): ?>
  <a href="submit_grades.php"
     class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-primary flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
    <div class="w-14 h-14 bg-emerald-50/50 text-emerald-600 rounded-full flex items-center justify-center border border-emerald-100 group-hover:scale-110 transition-transform duration-300">
      <i class="fas fa-file-signature text-xl"></i>
    </div>
    <span class="font-bold text-secondary text-sm">اعتماد الدرجات</span>
  </a>
  <?php endif; ?>

  <?php if (has_permission('financial')): ?>
  <a href="manage_fees.php"
     class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-primary flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
    <div class="w-14 h-14 bg-amber-50/50 text-amber-600 rounded-full flex items-center justify-center border border-amber-100 group-hover:scale-110 transition-transform duration-300">
      <i class="fas fa-money-check-alt text-xl"></i>
    </div>
    <span class="font-bold text-secondary text-sm">الرسوم والمصروفات</span>
  </a>
  <?php endif; ?>

  <?php if ($role !== 'affairs' && has_permission('library')): ?>
  <a href="manage_books.php"
     class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-primary flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
    <div class="w-14 h-14 bg-blue-50/50 text-blue-600 rounded-full flex items-center justify-center border border-blue-100 group-hover:scale-110 transition-transform duration-300">
      <i class="fas fa-swatchbook text-xl"></i>
    </div>
    <span class="font-bold text-secondary text-sm">المكتبة الرقمية</span>
  </a>
  <?php endif; ?>

  <?php if (has_permission('supervision')): ?>
  <a href="announcements.php"
     class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-primary flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
    <div class="w-14 h-14 bg-rose-50/50 text-rose-600 rounded-full flex items-center justify-center border border-rose-100 group-hover:scale-110 transition-transform duration-300">
      <i class="fas fa-bullhorn text-xl"></i>
    </div>
    <span class="font-bold text-secondary text-sm">نشر التعميمات</span>
  </a>
  <?php endif; ?>

  <?php if ($role === 'admin'): ?>
  <a href="settings.php"
  class="bg-white p-5 rounded-2xl shadow-sm hover:shadow-md border border-primary/10 hover:border-slate-800 flex flex-col items-center justify-center gap-3 transition-all duration-300 group text-center">
  <div class="w-14 h-14 bg-slate-100 text-slate-600 rounded-full flex items-center justify-center border border-slate-200 group-hover:bg-slate-700 group-hover:text-white transition-all duration-300">
  <i class="fas fa-cogs text-xl"></i>
  </div>
  <span class="font-bold text-secondary text-sm">إعدادات النظام الأساسية</span>
  </a> 
  <?php endif; ?>

 </div>
 </div>

</div>

<?php require_once 'includes/footer.php'; ?>