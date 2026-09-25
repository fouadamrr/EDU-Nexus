<?php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/AttendanceController.php';

// الصفحة دي للطلبة بس عشان يشوفوا غيابهم
// الأدمن والمديرين بيستخدموا صفحة التقارير (attendance_report.php)
if ($role !== 'student') {
 echo "<script>window.location.href='attendance_report.php?tab=absence';</script>";
 exit;
}

$attendanceController = new AttendanceController();
$courses = $attendanceController->getStudentAttendance((int)$user_id);

// بنحسب الإجماليات عشان نعرضها في الكروت
$total_sessions_all = 0;
$present_all = 0;
$warning_courses = 0;
$danger_courses = 0;
foreach ($courses as $c) {
 $total_sessions_all += $c['total_sessions'];
 $present_all += ($c['total_sessions'] - $c['absent_count']);
 if ($c['attendance_pct'] < 75) $danger_courses++;
 elseif ($c['attendance_pct'] < 85) $warning_courses++;
}
$overall_pct = $total_sessions_all > 0 ? round(($present_all / $total_sessions_all) * 100) : 100;
$absent_total = $total_sessions_all - $present_all;
?>

<div class="max-w-5xl mx-auto space-y-6 animate-fade-in-up">

 <!-- عنوان الصفحة والترحيب -->
 <div>
 <h1 class="text-2xl font-bold text-secondary flex items-center gap-2">
 <i class="fas fa-user-clock text-primary bg-bg p-2 rounded-xl"></i>
 سجل الحضور والغياب
 </h1>
 <p class="text-sm text-slate-500 mt-1">متابعة نسب حضورك في المقررات الدراسية المسجلة</p>
 </div>

 <!-- ملخص الغياب والحضور في كروت -->
 <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
 <!-- نسبة الحضور الكلية -->
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 relative overflow-hidden">
 <div class="absolute top-0 left-0 w-full h-1 <?php echo $overall_pct >= 85 ? 'bg-primary' : ($overall_pct >= 75 ? 'bg-primary' : 'bg-primary'); ?>"></div>
 <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1">نسبة الحضور الكلية</p>
 <p class="text-3xl font-black <?php echo $overall_pct >= 85 ? 'text-primary' : ($overall_pct >= 75 ? 'text-primary' : 'text-primary'); ?>">
 <?php echo $overall_pct; ?>%
 </p>
 <div class="mt-2 bg-bg rounded-full h-1.5 overflow-hidden">
 <div class="h-1.5 rounded-full <?php echo $overall_pct >= 85 ? 'bg-primary' : ($overall_pct >= 75 ? 'bg-primary' : 'bg-primary'); ?>"
 style="width: <?php echo $overall_pct; ?>%"></div>
 </div>
 </div>

 <!-- إجمالي المحاضرات وقابلية الغياب -->
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
 <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1">مرات الغياب</p>
 <p class="text-3xl font-black text-primary"><?php echo $absent_total; ?></p>
 <p class="text-xs text-slate-400 mt-1">من إجمالي <?php echo $total_sessions_all; ?> محاضرة</p>
 </div>

 <!-- المواد اللي فيها تنبيه -->
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
 <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1">مقررات تحت المتابعة</p>
 <p class="text-3xl font-black text-primary"><?php echo $warning_courses; ?></p>
 <p class="text-xs text-slate-400 mt-1">نسبة حضور 75-85%</p>
 </div>

 <!-- المواد اللي الطالب مهدد فيها بالحرمان -->
 <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
 <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1">مقررات في خطر</p>
 <p class="text-3xl font-black text-primary"><?php echo $danger_courses; ?></p>
 <p class="text-xs text-slate-400 mt-1">نسبة حضور أقل من 75%</p>
 </div>
 </div>

 <?php if ($danger_courses > 0): ?>
 <!-- تنبيه في حالة وجود خطر حرمان -->
 <div class="bg-bg border border-primary rounded-2xl p-4 flex items-start gap-3">
 <i class="fas fa-exclamation-triangle text-primary text-xl mt-0.5 shrink-0"></i>
 <div>
 <p class="font-bold text-primary">تحذير: أنت في خطر الحرمان من الامتحانات!</p>
 <p class="text-sm text-primary mt-0.5">لديك <?php echo $danger_courses; ?> مقرر(ات) نسبة حضورك فيها أقل من 75%. تواصل مع مرشدك الأكاديمي فوراً.</p>
 </div>
 </div>
 <?php elseif ($warning_courses > 0): ?>
 <div class="bg-bg border border-primary rounded-2xl p-4 flex items-start gap-3">
 <i class="fas fa-exclamation-circle text-primary text-xl mt-0.5 shrink-0"></i>
 <div>
 <p class="font-bold text-primary">تنبيه: نسبة حضورك تحتاج لمتابعة</p>
 <p class="text-sm text-primary mt-0.5">لديك <?php echo $warning_courses; ?> مقرر(ات) نسبة حضورك فيها بين 75-85%. احرص على الانتظام.</p>
 </div>
 </div>
 <?php endif; ?>

 <!-- جدول تفاصيل المواد والنسب -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="px-6 py-4 border-b border-slate-100">
 <h2 class="font-bold text-slate-700 flex items-center gap-2">
 <i class="fas fa-book-open text-primary"></i>
 تفاصيل الحضور في المقررات
 </h2>
 </div>

 <?php if (empty($courses)): ?>
 <div class="p-12 text-center text-slate-400 font-bold">
 <i class="fas fa-book text-4xl mb-3 block text-slate-200"></i>
 لا توجد مقررات مسجلة أو لم يتم تسجيل أي غياب حتى الآن.
 </div>
 <?php else: ?>
 <div class="overflow-x-auto">
 <table class="w-full text-right text-sm">
 <thead class="bg-bg text-slate-500 font-bold uppercase text-xs border-b border-slate-100">
 <tr>
 <th class="px-5 py-3">المقرر الدراسي</th>
 <th class="px-5 py-3 text-center">المحاضرات</th>
 <th class="px-5 py-3 text-center">مرات الغياب</th>
 <th class="px-5 py-3 text-center">الغياب المسموح (25%)</th>
 <th class="px-5 py-3 text-center">نسبة الحضور</th>
 <th class="px-5 py-3 text-center">الحالة</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50">
 <?php foreach ($courses as $c):
 $pct = (int)$c['attendance_pct'];
 $total = (int)$c['total_sessions'];
 $absent = (int)$c['absent_count'];
 $max_allowed = $total > 0 ? floor($total * 0.25) : 0;
 $remaining = max(0, $max_allowed - $absent);
 $color = $pct >= 85 ? 'emerald' : ($pct >= 75 ? 'amber' : 'red');
 $label = $pct >= 85 ? 'منتظم' : ($pct >= 75 ? 'تحت المتابعة' : 'منذر بالحرمان');
 $row_bg = $pct < 75 ? 'bg-bg/40' : ($pct < 85 ? 'bg-bg/30' : '');
 ?>
 <tr class="hover:bg-bg transition <?php echo $row_bg; ?>">
 <td class="px-5 py-3">
 <div class="font-bold text-slate-800"><?php echo htmlspecialchars($c['name']); ?></div>
 <div class="text-xs text-slate-400 font-mono mt-0.5"><?php echo htmlspecialchars($c['code'] ?? ''); ?></div>
 </td>
 <td class="px-5 py-3 text-center font-mono font-bold text-slate-600"><?php echo $total; ?></td>
 <td class="px-5 py-3 text-center">
 <span class="font-bold text-primary text-base"><?php echo $absent; ?></span>
 </td>
 <td class="px-5 py-3 text-center">
 <?php if ($total > 0): ?>
 <span class="text-xs font-bold <?php echo $remaining > 0 ? 'text-primary' : 'text-primary'; ?>">
 متبقي: <?php echo $remaining; ?> محاضرة
 </span>
 <?php else: ?>
 <span class="text-slate-300 text-xs">—</span>
 <?php endif; ?>
 </td>
 <td class="px-5 py-3">
 <div class="flex items-center justify-center gap-2">
 <span class="font-bold text-<?php echo $color; ?>-600 text-sm w-10"><?php echo $pct; ?>%</span>
 <div class="w-24 bg-bg rounded-full h-2 overflow-hidden">
 <div class="bg-<?php echo $color; ?>-500 h-2 rounded-full" style="width:<?php echo $pct; ?>%"></div>
 </div>
 </div>
 </td>
 <td class="px-5 py-3 text-center">
 <span class="bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-700 px-3 py-1 rounded-full text-xs font-bold">
 <?php echo $label; ?>
 </span>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>

 <!-- ملاحظات تحت الجدول -->
 <div class="px-5 py-3 bg-bg border-t border-slate-100 text-xs text-slate-400 font-medium flex items-center gap-2">
 <i class="fas fa-info-circle"></i>
 الحد الأدنى للحضور 75% من إجمالي المحاضرات — الغياب فوق 25% يعرضك للحرمان من الامتحانات.
 </div>
 <?php endif; ?>
 </div>

</div>

<?php require_once 'includes/footer.php'; ?>