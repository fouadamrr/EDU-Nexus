<?php
// forms.php
require_once 'includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-8 animate-fade-in">
 
 <!-- Page Header -->
 <div class="bg-white p-8 rounded-2xl shadow-card border border-slate-100 flex flex-col md:flex-row justify-between items-center gap-6">
 <div class="flex items-center gap-5">
 <div class="w-16 h-16 rounded-2xl bg-bg flex items-center justify-center text-white shadow-lg shadow-sm">
 <i class="fas fa-file-invoice text-2xl"></i>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-slate-800">النماذج والاستمارات الإلكترونية</h2>
 <p class="text-slate-500 mt-1">توليد وطباعة الاستمارات والطلبات الرسمية ببياناتك الجامعية</p>
 </div>
 </div>
 <div class="flex items-center gap-2 bg-bg px-4 py-2 rounded-xl border border-slate-100">
 <i class="fas fa-info-circle text-accent"></i>
 <span class="text-xs font-bold text-slate-600">سيتم ملء البيانات تلقائياً</span>
 </div>
 </div>

 <!-- Forms Grid -->
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
 
 <!-- Form Item 1: Enrollment Certificate -->
 <a href="generate_form.php?type=enrollment"
 target="_blank"
 class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:border-primary hover:shadow-xl hover:-translate-y-1 transition-all group">
 <div class="w-12 h-12 rounded-xl bg-bg text-primary flex items-center justify-center mb-4 group-hover:bg-primary group-hover:text-white transition-colors">
 <i class="fas fa-id-badge text-xl"></i>
 </div>
 <h4 class="font-bold text-slate-800 mb-2">إفادة قيد أكاديمي</h4>
 <p class="text-xs text-slate-500 leading-relaxed mb-6">استخراج شهادة قيد رسمية لتقديمها لأي جهة تطلب إثبات قيدك بالجامعة.</p>
 <div class="flex items-center justify-between text-primary font-bold text-xs">
 <span>عرض النموذج</span>
 <i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
 </div>
 </a>

 <!-- Form Item 2: Student Data Summary -->
 <a href="generate_form.php?type=data_summary"
 target="_blank"
 class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover:border-primary hover:shadow-xl hover:-translate-y-1 transition-all group">
 <div class="w-12 h-12 rounded-xl bg-bg text-primary flex items-center justify-center mb-4 group-hover:bg-primary group-hover:text-white transition-colors">
 <i class="fas fa-user-edit text-xl"></i>
 </div>
 <h4 class="font-bold text-slate-800 mb-2">استمارة بيانات طالب</h4>
 <p class="text-xs text-slate-500 leading-relaxed mb-6">تقرير شامل يحتوي على كافة بياناتك الشخصية والأكاديمية المسجلة في النظام.</p>
 <div class="flex items-center justify-between text-primary font-bold text-xs">
 <span>عرض النموذج</span>
 <i class="fas fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
 </div>
 </a>

 <!-- Form Item 3: Military Education (Coming Soon) -->
 <div class="bg-bg p-6 rounded-2xl border border-slate-100 opacity-60 relative group overflow-hidden cursor-not-allowed">
 <div class="absolute top-0 right-0 bg-slate-200 text-slate-500 text-[10px] font-bold px-3 py-1 rounded-bl-xl">قريباً</div>
 <div class="w-12 h-12 rounded-xl bg-slate-200 text-slate-400 flex items-center justify-center mb-4">
 <i class="fas fa-shield-alt text-xl"></i>
 </div>
 <h4 class="font-bold text-slate-800 mb-2">استمارة التربية العسكرية</h4>
 <p class="text-xs text-slate-400 leading-relaxed mb-6">طلب الالتحاق بدورة التربية العسكرية (سيتم ربطه بموديول التربية العسكرية).</p>
 </div>

 <!-- Form Item 4: Housing (Coming Soon) -->
 <div class="bg-bg p-6 rounded-2xl border border-slate-100 opacity-60 relative group overflow-hidden cursor-not-allowed">
 <div class="absolute top-0 right-0 bg-slate-200 text-slate-500 text-[10px] font-bold px-3 py-1 rounded-bl-xl">قريباً</div>
 <div class="w-12 h-12 rounded-xl bg-slate-200 text-slate-400 flex items-center justify-center mb-4">
 <i class="fas fa-hotel text-xl"></i>
 </div>
 <h4 class="font-bold text-slate-800 mb-2">استمارة المدينة الجامعية</h4>
 <p class="text-xs text-slate-400 leading-relaxed mb-6">نموذج طلب التسكين بالمدينة الجامعية للعام الدراسي الحالي.</p>
 </div>

 </div>

 <!-- Alert / Footer -->
 <div class="bg-bg border border-primary p-6 rounded-2xl flex items-start gap-4">
 <div class="bg-white p-3 rounded-xl text-primary shadow-sm">
 <i class="fas fa-exclamation-triangle text-xl"></i>
 </div>
 <div>
 <h4 class="font-bold text-primary mb-1">تنبيه هام:</h4>
 <p class="text-sm text-primary opacity-90 leading-relaxed">
 هذه النماذج يتم توليدها بناءً على البيانات المسجلة بملفك الشخصي. في حال وجود أي خطأ في البيانات، يرجى مراجعة إدارة شؤون الطلاب لتصحيحها قبل طباعة الاستمارة.
 </p>
 </div>
 </div>

</div>

<style>
.animate-fade-in {
 animation: fadeIn 0.5s ease-out;
}
@keyframes fadeIn {
 from { opacity: 0; transform: translateY(10px); }
 to { opacity: 1; transform: translateY(0); }
}
</style>

<?php require_once 'includes/footer.php'; ?>
