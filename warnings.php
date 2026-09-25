<?php
require_once 'includes/header.php';

// بنجيب لستة الإنذارات اللي اتسجلت للطالب ده من الداتا بيز
$stmt = $pdo->prepare("SELECT * FROM academic_warnings WHERE user_id = ?");
$stmt->execute([$user_id]);
$warnings = $stmt->fetchAll();
?>

<div class="max-w-4xl mx-auto">
 <div class="flex items-center justify-between mb-6">
 <h2 class="text-2xl font-bold text-primary"><i class="fas fa-exclamation-triangle ml-2"></i> الإنذارات
 الأكاديمية</h2>
 </div>

 <?php if (empty($warnings)): ?>
 <div class="bg-bg border border-primary text-primary p-8 rounded-xl text-center">
 <i class="fas fa-check-circle text-4xl mb-4"></i>
 <p class="font-bold text-lg">ممتاز! لا يوجد لديك أي إنذارات أكاديمية.</p>
 <p class="text-sm mt-2">حافظ على مستواك الدراسي.</p>
 </div>
 <?php else: ?>
 <div class="space-y-4">
 <?php foreach ($warnings as $w): ?>
 <div class="bg-bg border-r-4 border-primary p-6 rounded-lg shadow-sm flex justify-between items-start">
 <div>
 <h4 class="font-bold text-primary text-lg mb-2">إنذار أكاديمي</h4>
 <p class="text-gray-700"><?php echo htmlspecialchars($w['reason']); ?></p>
 </div>
 <div class="text-sm text-gray-500 bg-white px-3 py-1 rounded border border-gray-200">
 <?php echo htmlspecialchars($w['issued_at'] ?? $w['created_at'] ?? ''); ?>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>