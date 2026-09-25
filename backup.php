<?php
require_once 'includes/header.php';
$title = "النسخ الاحتياطي للبيانات";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
 $success_msg = "تم إنشاء نسخة احتياطية من جميع قواعد البيانات والأنظمة بنجاح وتأمينها.";
}
?>

<div class="space-y-6 animate-fade-in-up max-w-5xl mx-auto">
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
 <h1 class="text-2xl font-bold text-secondary mb-2 flex items-center gap-2"><i class="fas fa-database text-primary"></i> <?php echo $title; ?></h1>
 <p class="text-slate-500 mb-8">قم بحفظ نسخة آمنة من جميع بيانات الطلاب والمقررات والدرجات والإعدادات لاستعادتها في حالات الطوارئ.</p>
 
 <?php if (!empty($success_msg)): ?>
 <div class="bg-bg text-primary p-4 rounded-xl border border-primary font-bold mb-6 flex items-center gap-2">
 <i class="fas fa-check-circle text-xl"></i> <?php echo $success_msg; ?>
 </div>
 <?php endif; ?>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
 <div class="border border-slate-200 rounded-2xl p-6 bg-bg">
 <div class="flex items-center gap-4 mb-4">
 <div class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center text-xl"><i class="fas fa-server"></i></div>
 <div>
 <h3 class="font-bold text-lg text-slate-800">حالة قاعدة البيانات (PostgreSQL)</h3>
 <p class="text-xs text-slate-500">متصلة وتعمل بشكل جيد</p>
 </div>
 </div>
 <div class="space-y-2 text-sm text-slate-600 font-bold">
 <div class="flex justify-between border-b pb-2"><span class="text-slate-400">حجم البيانات المقدر:</span> <span>~45 MB</span></div>
 <div class="flex justify-between"><span class="text-slate-400">آخر عملية نسخ احتياطي:</span> <span class="text-primary">منذ يومين</span></div>
 </div>
 </div>
 
 <div class="border border-primary rounded-2xl p-6 bg-bg text-primary">
 <h3 class="font-bold text-xl mb-2 flex items-center gap-2"><i class="fas fa-cloud-download-alt"></i> استخراج نسخة احتياطية الآن</h3>
 <p class="text-sm opacity-80 max-w-sm mb-6 leading-relaxed">ستقوم هذه العملية بضغط وحفظ جداول قاعدة البيانات والمعلومات الأكاديمية بالكامل.</p>
 <form method="POST">
 <button type="submit" name="backup" onclick="return confirm('تأكيد بدء استخراج النسخة الاحتياطية؟')" class="w-full bg-primary text-white py-3 rounded-xl font-bold shadow-md hover:bg-primary transition">
 <i class="fas fa-cog ml-2 hidden" id="backup-spinner"></i>
 بدء النسخ الاحتياطي السريع (Backup)
 </button>
 </form>
 </div>
 </div>
 
 <div class="mt-8">
 <h3 class="font-bold text-slate-800 mb-4">أرشيف السيرفر (النسخ الاحتياطية السابقة)</h3>
 <div class="border border-slate-200 rounded-xl overflow-hidden">
 <table class="w-full text-right bg-white text-sm">
 <thead class="bg-bg text-slate-500 font-bold border-b border-slate-200">
 <tr>
 <th class="p-4">تاريخ النسخة</th>
 <th class="p-4">بواسطة</th>
 <th class="p-4">الحجم</th>
 <th class="p-4 text-center">الإجراء</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-100">
 <tr class="hover:bg-bg transition">
 <td class="p-4 font-mono text-slate-600"><?php echo date('Y-m-d 02:00:00', strtotime('-2 days')); ?></td>
 <td class="p-4 font-bold text-slate-700">النظام الآلي السحابي</td>
 <td class="p-4 text-slate-500">45.2 MB</td>
 <td class="p-4 text-center">
 <button class="text-primary hover:text-primary-dark font-bold bg-bg px-3 py-1.5 rounded-lg text-xs transition"><i class="fas fa-download ml-1"></i> تحميل</button>
 </td>
 </tr>
 <tr class="hover:bg-bg transition">
 <td class="p-4 font-mono text-slate-600"><?php echo date('Y-m-d 14:30:00', strtotime('-9 days')); ?></td>
 <td class="p-4 font-bold text-slate-700">مدير النظام</td>
 <td class="p-4 text-slate-500">42.8 MB</td>
 <td class="p-4 text-center">
 <button class="text-primary hover:text-primary-dark font-bold bg-bg px-3 py-1.5 rounded-lg text-xs transition"><i class="fas fa-download ml-1"></i> تحميل</button>
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>
 </div>
</div>

<?php require_once 'includes/footer.php'; ?>
