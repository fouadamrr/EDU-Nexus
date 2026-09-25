<?php
require_once 'includes/header.php';

require_once __DIR__ . '/controllers/ExamsController.php';
$examsController = new ExamsController();

$courses = $examsController->getStudentMockExams($user_id);
?>

<div class="max-w-4xl mx-auto">
 <div class="flex items-center justify-between mb-6">
 <h2 class="text-2xl font-bold text-primary"><i class="fas fa-calendar-check text-accent ml-2"></i> جدول
 الإمتحانات النهائية</h2>
 </div>

 <div class="bg-bg border border-primary text-primary p-4 rounded-lg mb-6 flex items-center">
 <i class="fas fa-info-circle text-xl margin-left-3 ml-3"></i>
 <div>
 <p class="font-bold">تنبيه هام</p>
 <p class="text-sm">يرجى الحضور قبل موعد الإمتحان بـ 30 دقيقة. ممنوع اصطحاب الهاتف المحمول.</p>
 </div>
 </div>

 <div class="bg-white rounded-xl shadow-lg run-in overflow-hidden">
 <table class="w-full text-right">
 <thead class="bg-primary text-white">
 <tr>
 <th class="p-4">اليوم والتاريخ</th>
 <th class="p-4">المادة</th>
 <th class="p-4 text-center">الوقت</th>
 <th class="p-4 text-center">القاعة / اللجنة</th>
 <th class="p-4 text-center">رقم الجلوس</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-100">
 <?php if (empty($courses)): ?>
 <tr>
 <td colspan="5" class="p-8 text-center text-gray-500">لا توجد إمتحانات مسجلة.</td>
 </tr>
 <?php else: ?>
 <?php
 // Mock dates for display
 $dates = ['السبت 20/05/2026', 'الإثنين 22/05/2026', 'الأربعاء 24/05/2026', 'السبت 27/05/2026'];
 $i = 0;
 foreach ($courses as $course):
 if ($i >= count($dates))
 $i = 0;
 ?>
 <tr class="hover:bg-bg">
 <td class="p-4 font-bold"><?php echo $dates[$i++]; ?></td>
 <td class="p-4">
 <?php echo htmlspecialchars($course['code'] ?? '') . ' - ' . htmlspecialchars($course['name'] ?? ''); ?>
 </td>
 <td class="p-4 text-center">09:00 - 12:00</td>
 <td class="p-4 text-center">مدرج (<?php echo rand(1, 4) == 1 ? 'أ' : 'ب'; ?>) - لجنة
 <?php echo rand(1, 10); ?>
 </td>
 <td class="p-4 text-center font-mono font-bold">4521</td>
 </tr>
 <?php endforeach; ?>
 <?php endif; ?>
 </tbody>
 </table>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>