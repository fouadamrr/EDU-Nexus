<?php
require_once 'includes/header.php';

require_once __DIR__ . '/controllers/ScheduleController.php';

if (in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='lecture_schedules.php?tab=schedules';</script>";
 exit;
}

$scheduleController = new ScheduleController();
$scheduleData = $scheduleController->getStudentSchedule($user_id);

$days = $scheduleData['days'];
$timetable = $scheduleData['timetable'];

$ar_days = [
 'Saturday' => 'السبت',
 'Sunday' => 'الأحد',
 'Monday' => 'الاثنين',
 'Tuesday' => 'الثلاثاء',
 'Wednesday' => 'الأربعاء',
 'Thursday' => 'الخميس',
];

// المواعيد من 8 الصبح لحد 5 العصر، كل ساعة لوحدها
$slots = [];
for ($h = 8; $h < 17; $h++) {
 $slots[] = sprintf('%02d:00', $h);
}

// بنجهز شكل الجدول عشان نحط فيه المحاضرات باليوم والساعة
$grid = [];
foreach ($days as $day) {
 $grid[$day] = array_fill_keys($slots, null);
}

foreach ($days as $day) {
 if (empty($timetable[$day])) continue;
 foreach ($timetable[$day] as $class) {
 $start = substr($class['start_time'] ?? '00:00', 0, 5);
 $end = substr($class['end_time'] ?? '00:00', 0, 5);
 // Find which slot this belongs to
 foreach ($slots as $slot) {
 $slot_end = sprintf('%02d:00', (int)$slot + 1);
 if ($start >= $slot && $start < $slot_end) {
 $grid[$day][$slot] = $class;
 break;
 }
 }
 }
}

// بنحدد لون لكل مادة عشان نفرق بينهم في الجدول
function getCourseColor(string $name): string {
 $colors = [
 'bg-bg border-primary text-primary',
 'bg-bg border-primary text-primary',
 'bg-bg border-primary text-primary',
 'bg-bg border-primary text-primary',
 'bg-bg border-primary text-primary',
 'bg-bg border-sky-400 text-sky-800',
 'bg-bg border-primary text-primary',
 ];
 return $colors[crc32($name) % count($colors)];
}
?>

<div class="max-w-full mx-auto px-2">
 <div class="flex items-center justify-between mb-6">
 <h2 class="text-2xl font-bold text-primary">
 <i class="fas fa-calendar-alt text-accent ml-2"></i>
 الجدول الدراسي
 </h2>
 <div class="flex items-center gap-3 text-xs text-gray-500">
 <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-primary border border-primary"></span> محاضرة</span>
 <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-primary border border-primary"></span> سكشن / عملي</span>
 </div>
 </div>

 <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-x-auto">
 <table class="w-full text-sm border-collapse" style="min-width:750px;">
 <!-- عناوين الجدول (الأيام والمواعيد) -->
 <thead>
 <tr>
 <th class="bg-gray-800 text-white p-3 text-center font-bold border border-gray-700 rounded-tl-2xl" style="min-width:70px;">
 <i class="fas fa-clock text-gray-300 text-xs block mb-1"></i>الوقت
 </th>
 <?php foreach ($days as $day): ?>
 <th class="bg-primary text-white p-3 text-center font-bold border border-primary/30" style="min-width:120px;">
 <?php echo $ar_days[$day] ?? $day; ?>
 </th>
 <?php endforeach; ?>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($slots as $i => $slot):
 $hour = (int)$slot;
 $next = sprintf('%02d:00', $hour + 1);
 $is_break = ($hour === 12); // استراحة الظهر
 $label = date('g:i A', strtotime($slot)) . ' – ' . date('g:i A', strtotime($next));
 ?>
 <tr class="<?php echo $is_break ? 'bg-bg' : ($i % 2 === 0 ? 'bg-white' : 'bg-bg/50'); ?> hover:bg-bg/30 transition">
 <!-- عمود الوقت الصباحي والمسائي -->
 <td class="border border-gray-100 p-2 text-center font-mono text-xs text-gray-500 font-bold bg-bg <?php echo $is_break ? 'bg-primary text-white' : ''; ?>">
 <?php echo $label; ?>
 <?php if ($is_break): ?><div class="text-primary text-[10px]">استراحة</div><?php endif; ?>
 </td>

 <!-- أعمدة أيام الأسبوع -->
 <?php foreach ($days as $day):
 $class = $grid[$day][$slot] ?? null;
 ?>
 <td class="border border-gray-100 p-2 align-top" style="height:72px;">
 <?php if ($class): 
 $name = $class['name'] ?? '';
 $colorCls = getCourseColor($name);
 $isLab = stripos($name, 'Lab') !== false || stripos($name, 'عملي') !== false;
 ?>
 <div class="h-full rounded-lg border-r-4 px-2 py-1.5 <?php echo $colorCls; ?> select-none">
 <div class="font-bold text-xs leading-tight mb-1 truncate" title="<?php echo htmlspecialchars($name); ?>">
 <?php echo htmlspecialchars($name); ?>
 </div>
 <div class="text-[11px] opacity-70 font-mono"><?php echo htmlspecialchars($class['code'] ?? ''); ?></div>
 <?php if (!empty($class['location'])): ?>
 <div class="text-[11px] opacity-60 mt-0.5">
 <i class="fas fa-map-marker-alt text-[9px]"></i>
 <?php echo htmlspecialchars($class['location']); ?>
 </div>
 <?php endif; ?>
 </div>
 <?php else: ?>
 <div class="h-full flex items-center justify-center text-gray-200 text-[10px]">—</div>
 <?php endif; ?>
 </td>
 <?php endforeach; ?>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>

 <?php
 $total_classes = 0;
 foreach ($days as $day) {
 foreach ($slots as $slot) {
 if (!empty($grid[$day][$slot])) $total_classes++;
 }
 }
 ?>
 <div class="mt-4 flex items-center gap-4 text-sm text-gray-500">
 <span><i class="fas fa-info-circle text-accent ml-1"></i>إجمالي المحاضرات الأسبوعية: <strong class="text-accent"><?php echo $total_classes; ?></strong></span>
 <span class="text-gray-300">|</span>
 <span>الجدول من <strong>8:00 ص</strong> إلى <strong>5:00 م</strong></span>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>