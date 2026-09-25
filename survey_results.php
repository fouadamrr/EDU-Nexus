<?php
require_once 'includes/header.php';
$title = "نتائج وتحليل الاستبيانات";

// For demo purposes, we will mock survey data if table doesn't exist
try {
 $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (id SERIAL PRIMARY KEY, title VARCHAR(255), total_responses INT, created_at TIMESTAMPTZ DEFAULT NOW())");
 $pdo->exec("CREATE TABLE IF NOT EXISTS survey_results (id SERIAL, survey_id INT, question VARCHAR(255), avg_rating FLOAT)");
} catch(Exception $e) {}

$surveys = [];
try {
 $surveys = $pdo->query("SELECT * FROM surveys ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Dummy data if empty
if(empty($surveys)) {
 $surveys = [
 ['id' => 1, 'title' => 'تقييم جودة المقررات الدراسية (الفصل الأول)', 'total_responses' => 450],
 ['id' => 2, 'title' => 'رضا الطلاب عن الخدمات الجامعية', 'total_responses' => 320]
 ];
}
?>

<div class="space-y-6 animate-fade-in-up max-w-7xl mx-auto">
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
 <div class="flex justify-between items-center mb-6 border-b pb-4 border-slate-100">
 <div>
 <h1 class="text-2xl font-bold text-secondary flex items-center gap-2"><i class="fas fa-poll text-primary"></i> <?php echo $title; ?></h1>
 <p class="text-sm text-slate-500 mt-1">متابعة تفاعل الطلاب ورضاهم عن جودة التعليم والمرافق.</p>
 </div>
 <button class="bg-primary text-white px-5 py-2.5 rounded-xl font-bold hover:bg-accent hover:text-white transition"><i class="fas fa-plus ml-2"></i> إنشاء استبيان جديد</button>
 </div>
 
 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
 <?php foreach($surveys as $s): ?>
 <div class="border border-slate-200 rounded-2xl p-6 bg-bg hover:shadow-md transition">
 <div class="flex justify-between items-start mb-4">
 <h3 class="font-bold text-lg text-secondary"><?php echo htmlspecialchars($s['title']); ?></h3>
 <span class="bg-white border border-slate-200 text-slate-500 text-xs px-3 py-1 rounded-full font-bold"><i class="fas fa-users ml-1 text-primary"></i> <?php echo $s['total_responses']; ?> مشاركة</span>
 </div>
 
 <div class="space-y-4">
 <!-- Q1 -->
 <div>
 <div class="flex justify-between text-sm mb-1">
 <span class="text-slate-700 font-bold">مدى وضوح المادة العلمية</span>
 <span class="text-primary font-bold">85%</span>
 </div>
 <div class="w-full bg-slate-200 rounded-full h-2"><div class="bg-primary h-2 rounded-full" style="width: 85%"></div></div>
 </div>
 <!-- Q2 -->
 <div>
 <div class="flex justify-between text-sm mb-1">
 <span class="text-slate-700 font-bold">أداء أعضاء هيئة التدريس</span>
 <span class="text-primary font-bold">92%</span>
 </div>
 <div class="w-full bg-slate-200 rounded-full h-2"><div class="bg-primary h-2 rounded-full" style="width: 92%"></div></div>
 </div>
 <!-- Q3 -->
 <div>
 <div class="flex justify-between text-sm mb-1">
 <span class="text-slate-700 font-bold">توافر المراجع والمصادر</span>
 <span class="text-primary font-bold">65%</span>
 </div>
 <div class="w-full bg-slate-200 rounded-full h-2"><div class="bg-primary h-2 rounded-full" style="width: 65%"></div></div>
 </div>
 </div>
 
 <div class="mt-6 pt-4 border-t border-slate-200 flex justify-between items-center">
 <span class="text-xs text-slate-400 font-bold"><i class="far fa-clock"></i> تم النشر مؤخراً</span>
 <button class="text-accent hover:text-accent-dark font-bold text-sm bg-bg px-4 py-2 rounded-lg transition">عرض التقرير المفصل</button>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
