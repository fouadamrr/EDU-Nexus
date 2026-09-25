<?php
require_once 'includes/header.php';
require_once __DIR__ . '/models/Survey.php';

$surveyModel = new Survey();
$surveys = $surveyModel->findAll();
?>

<div class="max-w-4xl mx-auto space-y-6 animate-fade-in-up">
 <!-- Intro -->
 <div class="bg-bg border-r-4 border-primary p-6 rounded-xl">
 <h2 class="font-bold text-primary text-lg mb-2">شاركنا رأيك!</h2>
 <p class="text-primary">تعتبر آراؤكم ومقترحاتكم أساساً لتطوير العملية التعليمية والخدمات بالجامعة. جميع
 الاستبيانات سرية وتستخدم لأغراض التحسين والتطوير.</p>
 </div>

 <!-- Surveys List -->
 <div class="space-y-4">
 <?php if (empty($surveys)): ?>
 <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center text-gray-500">
 لا توجد استبيانات متاحة حالياً.
 </div>
 <?php else: ?>
 <?php foreach ($surveys as $s): ?>
 <div
 class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition flex justify-between items-center group">
 <div class="flex items-center gap-4">
 <div
 class="w-12 h-12 bg-primary rounded-full flex items-center justify-center text-primary group-hover:scale-110 transition">
 <i class="fas fa-poll-h text-xl"></i>
 </div>
 <div>
 <h3 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($s['title']); ?></h3>
 <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($s['description']); ?></p>
 <span class="text-xs text-gray-400 mt-1 block"><i class="far fa-clock ml-1"></i> تاريخ النشر:
 <?php echo $s['created_at']; ?></span>
 </div>
 </div>
 <a href="<?php echo htmlspecialchars($s['link']); ?>" target="_blank"
 class="bg-primary text-white px-6 py-2 rounded-lg font-bold hover:bg-primary transition shadow-sm flex items-center gap-2">
 ابدأ
 <i class="fas fa-arrow-left text-sm"></i>
 </a>
 </div>
 <?php endforeach; ?>
 <?php endif; ?>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>