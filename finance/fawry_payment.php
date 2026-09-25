<?php
require_once 'includes/header.php';
/** @var PDO $pdo */

// استلام بيانات الرسوم
$fee_id = isset($_GET['fee_id']) ? (int)$_GET['fee_id'] : 0;
$fee_info = null;

if ($fee_id > 0) {
    // تصحيح: اسم الجدول هو fees والعمود هو user_id
    $stmt = $pdo->prepare("SELECT f.*, u.full_name, u.username FROM fees f JOIN users u ON f.user_id = u.id WHERE f.id = ?");
    $stmt->execute([$fee_id]);
    $fee_info = $stmt->fetch();
}

if (!$fee_info) {
    echo "<div class='p-20 text-center'><h2 class='text-2xl font-black text-rose-500'>خطأ: لم يتم العثور على بيانات الرسوم</h2></div>";
    require_once 'includes/footer.php';
    exit;
}

// توليد كود دفع عشوائي (Mockup)
$fawry_ref = "9" . rand(100000000, 999999999);
?>

<div class="max-w-3xl mx-auto py-10 animate-fade-in-up">
    <!-- بطاقة الفاتورة -->
    <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-slate-100">
        <div class="bg-gradient-to-r from-orange-500 to-amber-500 p-10 text-white relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
            <div class="flex justify-between items-center relative z-10">
                <div>
                    <h1 class="text-3xl font-black mb-2">بوابة الدفع الإلكتروني</h1>
                    <p class="text-white/80 font-bold">بواسطة شركة فوري (Fawry Pay)</p>
                </div>
                <div class="bg-white p-4 rounded-2xl shadow-xl">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/d/d4/Fawry_logo.png" alt="Fawry" class="h-8">
                </div>
            </div>
        </div>

        <div class="p-10 space-y-8">
            <!-- تفاصيل الطالب -->
            <div class="grid grid-cols-2 gap-6 pb-8 border-b border-dashed border-slate-200">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">اسم الطالب</p>
                    <p class="text-lg font-black text-slate-800"><?php echo htmlspecialchars($fee_info['full_name']); ?></p>
                </div>
                <div class="text-left">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">رقم الفاتورة</p>
                    <p class="text-lg font-black text-slate-800">#<?php echo $fee_info['id']; ?></p>
                </div>
            </div>

            <!-- تفاصيل المبلغ -->
            <div class="bg-slate-50 rounded-3xl p-8 text-center group">
                <p class="text-sm font-bold text-slate-500 mb-2">إجمالي المبلغ المطلوب سداده</p>
                <div class="text-5xl font-black text-primary mb-2 group-hover:scale-110 transition-transform duration-500">
                    <?php echo number_format($fee_info['amount'], 2); ?>
                    <span class="text-xl text-slate-400">ج.م</span>
                </div>
                <p class="text-xs text-slate-400"><?php echo htmlspecialchars($fee_info['description']); ?></p>
            </div>

            <!-- كود فوري (المرجع) -->
            <div class="border-2 border-orange-500 border-dashed rounded-3xl p-8 relative">
                <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-orange-500 text-white px-6 py-1 rounded-full text-[10px] font-black">كود الدفع في منافذ فوري</div>
                <div class="text-center">
                    <p class="text-4xl font-mono font-black text-orange-600 tracking-[0.5em] mb-4"><?php echo $fawry_ref; ?></p>
                    <p class="text-sm text-slate-500 font-bold">يرجى التوجه لأي منفذ فوري واستخدام الكود أعلاه لإتمام الدفع</p>
                </div>
            </div>

            <!-- تعليمات -->
            <div class="space-y-4">
                <div class="flex items-start gap-4">
                    <div class="w-8 h-8 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">
                        بمجرد إتمام الدفع، سيقوم النظام بتحديث حالتك تلقائياً خلال 5 دقائق. يرجى الاحتفاظ بإيصال الدفع الورقي كضمان.
                    </p>
                </div>
            </div>

            <div class="pt-6 grid grid-cols-2 gap-4">
                <button onclick="window.print()" class="bg-slate-100 text-slate-600 py-4 rounded-2xl font-black hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-print"></i> طباعة التعليمات
                </button>
                <a href="fees.php" class="bg-primary text-white py-4 rounded-2xl font-black hover:bg-indigo-700 shadow-xl shadow-primary/20 transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-check-circle"></i> العودة للمالية
                </a>
            </div>
        </div>
    </div>

    <!-- تذييل -->
    <div class="mt-8 flex items-center justify-center gap-6 opacity-40">
        <img src="https://fawry.com/wp-content/uploads/2023/03/fawry-logo.png" class="h-6 grayscale" alt="Fawry">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/2560px-Visa_Inc._logo.svg.png" class="h-3 grayscale" alt="Visa">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Mastercard-logo.svg/1280px-Mastercard-logo.svg.png" class="h-5 grayscale" alt="Mastercard">
    </div>
</div>

<style>
.animate-fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
@media print {
    body * { visibility: hidden; }
    .bg-white.rounded-\[2\.5rem\] { visibility: visible; position: absolute; left: 0; top: 0; width: 100%; border: none; }
    .bg-white.rounded-\[2\.5rem\] * { visibility: visible; }
    button, a { display: none !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
