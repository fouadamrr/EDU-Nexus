<?php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/DashboardController.php';

$dashboardController = new DashboardController();
$dashData = $dashboardController->getStudentDashboardData($user_id, $_SESSION['college_id'] ?? 0);

$level = $dashData['level'];
$gpa = $dashData['gpa'];
$announcements = $dashData['announcements'];
$dash_grades = $dashData['recent_grades'];
?>

<style>
  .service-icon-container i {
    color: inherit !important;
  }
</style>

 <!-- البانر بتاع الترحيب -->
 <div class="relative overflow-hidden bg-gradient-to-r from-primary via-primary-light to-bright rounded-2xl shadow-card mb-6 border border-white/10">
  <!-- شوية زخرفة وحركات كدا في الخلفية -->
  <div class="absolute top-0 right-0 -mt-16 -mr-16 w-64 h-50 bg-white opacity-10 rounded-full blur-3xl"></div>
  <div class="absolute bottom-0 left-0 -mb-16 -ml-16 w-48 h-48 bg-white opacity-10 rounded-full blur-2xl"></div>
  
  <div class="relative p-6 md:p-8 flex flex-col lg:flex-row justify-between items-center gap-6 z-10">
    <div class="flex flex-col md:flex-row items-center gap-5 w-full lg:w-auto">
      <!-- صورة البروفايل في البانر -->
      <div class="w-20 h-20 rounded-2xl border-4 border-white/30 shadow-xl overflow-hidden flex-shrink-0 bg-white/10 backdrop-blur-xl group transition-transform hover:scale-105">
        <?php 
        $abs_profile_pic = $_project_root . DIRECTORY_SEPARATOR . $profile_pic;
        if (!empty($profile_pic) && file_exists($abs_profile_pic)): 
        ?>
            <img src="<?php echo $base_path . $profile_pic; ?>" class="w-full h-full object-cover">
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center text-white text-3xl font-black bg-gradient-to-br from-white/20 to-transparent">
                <?php echo mb_substr($full_name, 0, 1, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
      </div>

      <div class="text-white text-center md:text-right">
        <h3 class="text-2xl font-black mb-1 text-white drop-shadow-sm"><?php echo $lang['welcome']; ?>، <?php echo explode(' ', $full_name)[0]; ?> 👋</h3>
        <div class="flex flex-wrap gap-3 items-center justify-center md:justify-start">
          <div class="flex items-center gap-1.5 bg-white/10 backdrop-blur-md px-3 py-1 rounded-lg border border-white/10">
            <i class="fas fa-layer-group text-white/70 text-[10px]"></i>
            <span class="text-white/80 text-xs"><?php echo $lang['level']; ?>:</span>
            <span class="font-bold text-white text-xs"><?php echo $level; ?></span>
          </div>
          <div class="flex items-center gap-1.5 bg-white/10 backdrop-blur-md px-3 py-1 rounded-lg border border-white/10">
            <i class="fas fa-star text-white/70 text-[10px]"></i>
            <span class="text-white/80 text-xs"><?php echo $lang['gpa']; ?>:</span>
            <span class="font-bold text-white text-xs"><?php echo $gpa; ?></span>
          </div>
        </div>
      </div>
    </div>
  
    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
      <a href="profile.php" class="bg-white text-primary px-5 py-2.5 rounded-xl hover:bg-white/90 transition-all text-sm font-bold shadow-soft flex items-center justify-center gap-2 group">
        <span>تحديث البيانات</span>
        <i class="fas fa-sync-alt group-hover:rotate-180 transition-transform duration-500 text-xs"></i>
      </a>
      <a href="results.php" class="bg-white/10 text-white border border-white/20 backdrop-blur-md px-5 py-2.5 rounded-xl hover:bg-white/20 transition-all text-sm font-bold flex items-center justify-center gap-2">
        <span>عرض النتائج</span>
        <i class="fas fa-arrow-left text-xs"></i>
      </a>
    </div>
  </div>
 </div>


<!-- متتبع الساعات والتقدم الأكاديمي -->
<div class="bg-white rounded-2xl shadow-card border border-primary/5 p-4 mb-8 relative z-20 group hover:shadow-lg transition-all duration-500 mt-6">
    <div class="absolute top-0 right-0 w-1.5 h-full bg-gradient-to-b from-primary to-accent opacity-20 group-hover:opacity-100 transition-opacity"></div>
    <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
        <!-- شوية تفاصيل وكلام -->
        <div class="flex items-center gap-4 text-right w-full lg:w-auto">
            <div class="w-14 h-14 bg-primary/5 rounded-2xl flex items-center justify-center text-primary text-xl shadow-inner flex-shrink-0 group-hover:scale-110 group-hover:bg-primary group-hover:text-white transition-all duration-500">
                <i class="fas fa-tasks"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-secondary mb-0.5"><?php echo $lang['academic_progress'] ?? 'متتبع التقدم الأكاديمي'; ?></h3>
                <p class="text-secondary/50 text-xs font-medium">لقد أنجزت <span class="text-primary font-bold"><?php echo $dashData['progress_percent']; ?>%</span> من متطلبات التخرج.</p>
            </div>
        </div>
        
        <!-- الجزء بتاع شريط التحميل -->
        <div class="flex-1 w-full max-w-xl">
            <div class="flex justify-between items-end mb-2 px-1">
                <div class="flex flex-col">
                    <span class="text-[9px] uppercase tracking-wider text-secondary/30 font-black">ساعات مكتملة</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-black text-secondary"><?php echo $dashData['completed_hours']; ?></span>
                        <span class="text-secondary/40 font-bold text-xs">/ <?php echo $dashData['total_required_hours']; ?></span>
                    </div>
                </div>
                <div class="flex flex-col items-end text-left">
                    <span class="text-[9px] uppercase tracking-wider text-primary/30 font-black">المتبقي</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-black text-primary"><?php echo $dashData['total_required_hours'] - $dashData['completed_hours']; ?></span>
                        <span class="text-primary/40 font-bold text-xs">ساعة</span>
                    </div>
                </div>
            </div>
            
            <div class="relative h-4 w-full bg-slate-100 rounded-full overflow-hidden border border-slate-200 p-0.5 shadow-inner">
                <!-- Main Progress Fill -->
                <div id="graduation-progress-fill" 
                     class="h-full rounded-full relative shadow-lg bg-blue-700" 
                     style="width: <?php echo $dashData['progress_percent']; ?>%;">
                    
                    <!-- حركة اللمعة اللي بتجري دي -->
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
// الإعلانات إحنا جايبينها فوق خلاص في الـ Controller
?>

<!-- Two Column Layout for Main Content -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8 relative z-10">
 
 <!-- العمود اللي على الشمال: الإعلانات -->
 <div class="lg:col-span-1 space-y-6">
 <div class="flex items-center justify-between mb-2">
 <h3 class="text-xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-bullhorn text-accent bg-accent/10 p-2 rounded-lg"></i>
 <?php echo $lang['announcements']; ?>
 </h3>
 <a href="#" class="text-sm text-accent font-medium hover:underline">عرض الكل</a>
 </div>

 <?php if (!empty($announcements)): ?>
 <div class="space-y-4">
 <?php foreach ($announcements as $ann): ?>
 <div class="bg-white p-5 rounded-2xl shadow-sm border border-primary/10 hover:shadow-md hover:border-primary transition-all group relative overflow-hidden">
 <div class="absolute left-0 top-0 h-full w-1 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
 <div class="flex justify-between items-start mb-3">
 <h4 class="font-bold text-secondary leading-tight"><?php echo htmlspecialchars($ann['title'] ?? 'بدون عنوان'); ?></h4>
 </div>
 <p class="text-secondary/70 text-sm leading-relaxed mb-4 line-clamp-2">
 <?php echo htmlspecialchars($ann['content'] ?? ''); ?>
 </p>
 <div class="flex items-center text-xs text-secondary/50 font-medium">
 <i class="far fa-calendar-alt ml-1.5 object-center"></i>
 <?php echo htmlspecialchars($ann['date'] ?? ''); ?>
 </div>
 </div>
 <?php
 endforeach; ?>
 </div>
 <?php
else: ?>
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-primary/10 text-center flex flex-col items-center justify-center h-48 text-secondary/50">
 <i class="far fa-bell-slash text-4xl mb-3 opacity-50"></i>
 <p>لا توجد إعلانات جديدة حالياً</p>
 </div>
 <?php
endif; ?>
 </div>

 <!-- العمود اللي على اليمين: النتائج واللينكات السريعة -->
 <div class="lg:col-span-2 space-y-8">
 
 <!-- Quick Services Grid -->
 <div>
 <h3 class="text-xl font-bold text-secondary flex items-center gap-2 mb-4">
 <i class="fas fa-th-large text-primary bg-primary/10 p-2 rounded-lg"></i>
 خدمات سريعة
 </h3>
 <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
  <?php
  $top_services = [
    ['icon' => 'fa-graduation-cap', 'title' => $lang['icon_results'], 'link' => 'results.php', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50'],
    ['icon' => 'fa-calendar-alt', 'title' => $lang['icon_schedule'] ?? 'الجدول الدراسي', 'link' => 'schedule.php', 'color' => 'text-indigo-600', 'bg' => 'bg-indigo-50'],
    ['icon' => 'fa-file-signature', 'title' => $lang['icon_registration'], 'link' => 'registration.php', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
    ['icon' => 'fa-money-check-alt', 'title' => $lang['icon_fees'] ?? 'الرسوم الدراسية', 'link' => 'fees.php', 'color' => 'text-amber-700', 'bg' => 'bg-amber-50'],
  ];

  foreach ($top_services as $service):
  ?>
  <a href="<?php echo $service['link']; ?>"
     class="bg-white p-5 rounded-2xl border border-primary/10 hover:border-primary shadow-sm hover:shadow-md transition-all duration-300 flex flex-col items-center justify-center text-center group">
    <div class="w-16 h-16 rounded-2xl <?php echo $service['bg']; ?> <?php echo $service['color']; ?> flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-500 service-icon-container shadow-sm">
      <i class="fas <?php echo $service['icon']; ?> text-2xl"></i>
    </div>
    <h4 class="font-bold text-sm text-secondary transition-colors group-hover:text-primary">
      <?php echo $service['title']; ?>
    </h4>
  </a>
 <?php
endforeach; ?>
 </div>
 </div>

 <!-- عرض درجات الطالب في آخر ترم -->
 <?php
 // Grades are now fetched via DashboardController
 ?>

 <?php if (!empty($dash_grades)): ?>
 <div>
 <h3 class="text-xl font-bold text-secondary flex items-center gap-2 mb-4">
 <i class="fas fa-chart-line text-accent bg-accent/10 p-2 rounded-lg"></i>
 ملخص الدرجات الأخيرة
 </h3>
 <div class="bg-white rounded-2xl shadow-sm border border-primary/10 overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-right whitespace-nowrap">
 <thead class="bg-primary/5 text-secondary/70 font-bold text-xs uppercase tracking-wider">
 <tr>
 <th class="px-6 py-4">الفصل</th>
 <th class="px-6 py-4">كود المقرر</th>
 <th class="px-6 py-4">اسم المقرر</th>
 <th class="px-6 py-4 text-center">الساعات</th>
 <th class="px-6 py-4 text-center">التقدير</th>
 <th class="px-6 py-4 text-center">النقاط</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-100">
 <?php foreach (array_slice($dash_grades, 0, 5) as $g): ?>
 <tr class="hover:bg-primary/5/50 transition-colors">
 <td class="px-6 py-4 text-sm text-secondary/70">
 <?php echo htmlspecialchars($g['semester'] ?? ''); ?>
 </td>
 <td class="px-6 py-4 font-mono font-bold text-secondary">
 <?php echo htmlspecialchars($g['code'] ?? ''); ?>
 </td>
 <td class="px-6 py-4 text-secondary">
 <?php echo htmlspecialchars($g['name'] ?? ''); ?>
 </td>
 <td class="px-6 py-4 text-center text-sm">
 <?php echo htmlspecialchars((string) ($g['credit_hours'] ?? '')); ?>
 </td>
 <td class="px-6 py-4 text-center font-bold <?php echo in_array($g['grade'] ?? '', ['A', 'A-', 'B+']) ? 'text-emerald-600' : 'text-secondary'; ?>">
 <?php echo htmlspecialchars($g['grade'] ?? ''); ?>
 </td>
 <td class="px-6 py-4 text-center text-sm">
 <?php echo htmlspecialchars((string) ($g['points'] ?? '')); ?>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 </div>
 <?php endif; ?>
 </div>
</div>

<!-- شبكة كل الخدمات الأكاديمية -->
<div class="mb-8">
 <h3 class="text-xl font-bold text-secondary flex items-center gap-2 mb-4">
 <i class="fas fa-layer-group text-primary bg-primary/10 p-2 rounded-lg"></i>
 كل الخدمات الأكاديمية
 </h3>
 <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4">
  <?php
  $services = [
    ['icon' => 'fa-id-card',         'title' => $lang['icon_profile'],                   'link' => 'profile.php',    'color' => 'text-sky-600',    'bg' => 'bg-sky-100/50'],
    ['icon' => 'fa-shield-alt',      'title' => $lang['icon_military'] ?? 'التربية العسكرية', 'link' => 'military.php',   'color' => 'text-slate-600',  'bg' => 'bg-slate-100/50'],
    ['icon' => 'fa-calendar-check',  'title' => $lang['icon_exams'],                     'link' => 'exams.php',      'color' => 'text-indigo-600', 'bg' => 'bg-indigo-100/50'],
    ['icon' => 'fa-clipboard-list',  'title' => $lang['icon_surveys'] ?? 'الاستبيانات', 'link' => 'surveys.php',    'color' => 'text-teal-600',   'bg' => 'bg-teal-100/50'],
    ['icon' => 'fa-book-open',       'title' => 'الكتب الدراسية',                        'link' => 'textbooks.php',  'color' => 'text-amber-600',  'bg' => 'bg-amber-100/50'],
    ['icon' => 'fa-book',            'title' => $lang['icon_books'] ?? 'المكتبة',        'link' => 'books.php',      'color' => 'text-orange-600', 'bg' => 'bg-orange-100/50'],
    ['icon' => 'fa-user-check',      'title' => $lang['icon_attendance'] ?? 'الغياب والدروس', 'link' => 'attendance.php', 'color' => 'text-rose-600',   'bg' => 'bg-rose-100/50'],
  ];

  foreach ($services as $service):
  ?>
  <a href="<?php echo $service['link']; ?>"
     class="bg-white p-3 rounded-xl shadow-sm border border-primary/10 hover:border-primary hover:shadow-md transition-all duration-300 flex flex-col items-center justify-center text-center group">
    <div class="w-12 h-12 rounded-2xl <?php echo $service['bg']; ?> <?php echo $service['color']; ?> flex items-center justify-center mb-2 group-hover:scale-110 transition-transform duration-500 service-icon-container shadow-sm">
      <i class="fas <?php echo $service['icon']; ?> text-lg"></i>
    </div>
    <h4 class="font-bold text-[10px] text-secondary/80 group-hover:text-primary transition-colors">
      <?php echo $service['title']; ?>
    </h4>
  </a>
 <?php
endforeach; ?>
 </div>
</div>

<!-- حتة التنبيهات الأكاديمية اللي تحت -->
<div class="mt-8 mb-4">
 <a href="warnings.php"
 class="bg-primary/5 border border-primary p-6 rounded-2xl flex items-center justify-center gap-4 text-primary hover:bg-primary hover:text-white hover:border-primary transition-all duration-300 w-full md:w-1/2 lg:w-1/3 mx-auto shadow-sm group">
 <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
 <i class="fas fa-exclamation-triangle text-xl text-primary group-hover:text-primary transition-colors"></i>
 </div>
 <div>
 <span class="font-bold text-lg block group-hover:text-white transition-colors"><?php echo $lang['academic_warnings']; ?></span>
 <span class="text-xs text-primary/80 mt-1 block group-hover:text-white/80 transition-colors">اضغط للتأكد من موقفك الأكاديمي</span>
 </div>
 </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const progressBar = document.getElementById('academic-progress-bar');
    if (progressBar) {
        const percent = progressBar.getAttribute('data-percent');
        // Small timeout to ensure transition triggers
        setTimeout(() => {
            progressBar.style.width = percent + '%';
        }, 300);
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>