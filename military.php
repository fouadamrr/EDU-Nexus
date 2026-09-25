<?php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/MilitaryController.php';

$controller = new MilitaryController();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
 $message = $controller->handleRegistration($user_id, $_POST, $_FILES);
}

$registration = $controller->getStudentRegistration($user_id);
$available_sessions = $controller->getAvailableSessions();
?>

<div class="max-w-4xl mx-auto space-y-8 animate-fade-in-up">
 <!-- عنوان الصفحة والترحيب بالطلبة -->
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden">
 <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-bl-full"></div>
 <div class="relative z-10">
 <h2 class="text-2xl font-black text-secondary flex items-center gap-3">
 <i class="fas fa-shield-alt text-primary"></i>
 إدارة التربية العسكرية
 </h2>
 <p class="text-slate-500 mt-2">نظام التسجيل في دورات التربية العسكرية ومتابعة النتائج.</p>
 </div>
 </div>

 <?php echo $message; ?>

 <?php if ($registration && $registration['status'] === 'passed'): ?>
 <!-- الحالة الأولى: لو الطالب نجح واجتاز الدورة خلاص -->
 <div class="bg-bg border border-primary p-10 rounded-3xl text-center">
 <div class="w-20 h-20 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-sm">
 <i class="fas fa-check text-3xl"></i>
 </div>
 <h3 class="text-2xl font-black text-primary mb-2">تهانينا! تم الاجتياز بنجاح</h3>
 <p class="text-primary font-medium">لقد أتممت دورة التربية العسكرية (<?php echo htmlspecialchars($registration['title']); ?>) بنجاح.</p>
 <div class="mt-6 inline-flex items-center gap-2 bg-white border border-primary px-6 py-2 rounded-xl text-primary font-bold text-sm">
 <i class="fas fa-calendar-check opacity-70"></i>
 تاريخ الانتهاء: <?php echo $registration['end_date']; ?>
 </div>
 </div>

 <?php elseif ($registration): ?>
 <!-- الحالة التانية: لو الطالب مسجل (سواء لسه مستني رد، أو اتقبل، أو لا قدر الله سقط) -->
 <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
 <div class="flex flex-col md:flex-row items-center justify-between gap-6">
 <div class="flex items-center gap-5">
 <div class="w-16 h-16 bg-bg text-primary rounded-2xl flex items-center justify-center text-2xl shadow-sm">
 <i class="fas fa-id-card-alt"></i>
 </div>
 <div>
 <h4 class="text-lg font-bold text-slate-800">بيانات التسجيل الحالي</h4>
 <p class="text-slate-500 text-sm"><?php echo htmlspecialchars($registration['title']); ?></p>
 <div class="mt-1 flex items-center gap-3">
 <span class="text-xs font-mono text-slate-400">من: <?php echo $registration['start_date']; ?></span>
 <span class="text-xs font-mono text-slate-400">إلى: <?php echo $registration['end_date']; ?></span>
 </div>
 </div>
 </div>
 
 <div class="text-center md:text-left shrink-0">
 <div class="mb-3">
 <span class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider
 <?php 
 echo $registration['status'] === 'pending' ? 'bg-primary text-white' : 
 ($registration['status'] === 'approved' ? 'bg-primary text-white' : 'bg-primary text-white'); 
 ?>">
 حالة الطلب: <?php 
 echo $registration['status'] === 'pending' ? 'قيد المراجعة' : 
 ($registration['status'] === 'approved' ? 'تم القبول - بانتظار الدورة' : 'لم يوفق - يمكنك المحاولة لاحقاً'); 
 ?>
 </span>
 </div>

 <div class="flex flex-col gap-2">
 <a href="military_print.php" target="_blank" class="bg-slate-800 text-white px-6 py-2 rounded-xl font-bold hover:bg-slate-900 transition flex items-center justify-center gap-2 text-sm shadow-md">
 <i class="fas fa-file-pdf"></i>
 طباعة ملف التقديم
 </a>
 <?php if ($registration['status'] === 'failed'): ?>
 <button onclick="document.getElementById('retryNote').classList.toggle('hidden')" class="text-primary text-xs font-bold hover:underline">لماذا لم يتم الاجتياز؟</button>
 <div id="retryNote" class="hidden text-xs text-slate-500 mt-2 max-w-[200px]">يجب إعادة التسجيل في دورة جديدة بعد ظهور النتيجة النهائية رسمياً.</div>
 <?php endif; ?>
 </div>
 </div>
 </div>

 <!-- شريط التقدم في الخطوات -->
 <div class="mt-10 border-t border-slate-50 pt-8">
 <div class="flex items-center justify-between px-4 max-w-2xl mx-auto">
 <div class="flex flex-col items-center gap-2">
 <div class="w-8 h-8 rounded-full flex items-center justify-center bg-primary text-white text-xs"><i class="fas fa-check"></i></div>
 <span class="text-[10px] font-bold text-slate-400">التقديم</span>
 </div>
 <div class="flex-1 h-0.5 bg-primary mx-2"></div>
 <div class="flex flex-col items-center gap-2">
 <div class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $registration['status'] !== 'pending' ? 'bg-primary text-white' : 'bg-bg text-slate-400'; ?> text-xs">
 <?php echo $registration['status'] !== 'pending' ? '<i class="fas fa-check"></i>' : '2'; ?>
 </div>
 <span class="text-[10px] font-bold text-slate-400">مراجعة الملف</span>
 </div>
 <div class="flex-1 h-0.5 <?php echo $registration['status'] === 'passed' ? 'bg-primary' : 'bg-bg'; ?> mx-2"></div>
 <div class="flex flex-col items-center gap-2">
 <div class="w-8 h-8 rounded-full flex items-center justify-center bg-bg text-slate-400 text-xs">3</div>
 <span class="text-[10px] font-bold text-slate-400">النتيجة</span>
 </div>
 </div>
 </div>
 </div>

 <?php else: ?>
 <!-- الحالة التالتة: لو الطالب لسه معملش أي تسجيل خالص -->
 <?php if (empty($available_sessions)): ?>
 <div class="bg-white p-16 rounded-2xl shadow-sm border border-slate-100 text-center">
 <div class="w-20 h-20 bg-bg rounded-full flex items-center justify-center mx-auto mb-6 text-slate-200">
 <i class="fas fa-calendar-times text-4xl"></i>
 </div>
 <h3 class="text-xl font-bold text-slate-700">لا توجد دورات متاحة للتسجيل حالياً</h3>
 <p class="text-slate-400 mt-2">يتم فتح باب التسجيل دورياً، يرجى مراجعة إدارة التربية العسكرية.</p>
 </div>
 <?php else: ?>
 <!-- فورم التسجيل في دورة جديدة -->
 <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
 <div class="bg-bg p-6 text-white">
 <h3 class="text-xl font-bold flex items-center gap-2">
 <i class="fas fa-edit"></i>
 طلب التحاق بدورة التربية العسكرية
 </h3>
 <p class="text-primary text-xs mt-1">يرجى ملء البيانات التالية بدقة مع إرفاق المستندات المطلوبة.</p>
 </div>
 
 <form method="POST" enctype="multipart/form-data" class="p-8 space-y-6">
 <input type="hidden" name="register" value="1">
 
 <!-- اختيار الدورة اللي الطالب عايز يدخل فيها -->
 <div class="space-y-3">
 <label class="block text-sm font-bold text-slate-700" style="display:flex;align-items:center;gap:8px;">
 <i class="fas fa-list-ul" style="color:#2563eb;"></i> اختر الدورة المتاحة
 </label>

 <style>
 .session-card {
 display: flex;
 align-items: center;
 gap: 14px;
 padding: 16px 18px;
 border: 2px solid #e2e8f0;
 border-radius: 16px;
 cursor: pointer;
 transition: all 0.2s ease;
 background: #f8fafc;
 user-select: none;
 }
 .session-card:hover { border-color: #93c5fd; background: #eff6ff; }
 .session-card.selected {
 border-color: #2563eb !important;
 background: linear-gradient(135deg, #eff6ff 0%, #f0f4ff 100%) !important;
 box-shadow: 0 4px 14px rgba(37,99,235,0.12);
 }
 .session-radio-dot {
 width: 22px; height: 22px;
 border-radius: 50%;
 border: 2px solid #cbd5e1;
 display: flex; align-items: center; justify-content: center;
 flex-shrink: 0;
 transition: all 0.2s;
 background: #fff;
 }
 .session-card.selected .session-radio-dot {
 border-color: #2563eb;
 background: #2563eb;
 }
 .session-radio-dot::after {
 content: '';
 width: 8px; height: 8px;
 border-radius: 50%;
 background: #fff;
 opacity: 0;
 transition: opacity 0.2s;
 }
 .session-card.selected .session-radio-dot::after { opacity: 1; }
 </style>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-3" id="sessions-grid">
 <?php foreach ($available_sessions as $sess): ?>
 <div class="session-card" onclick="selectSession(this, <?php echo $sess['id']; ?>)">
 <div class="session-radio-dot"></div>
 <div class="flex-1">
 <div style="font-weight:700;color:#1e293b;font-size:14px;"><?php echo htmlspecialchars($sess['title']); ?></div>
 <div style="font-size:11px;color:#64748b;margin-top:4px;">
 <i class="fas fa-calendar-alt" style="color:#2563eb;font-size:10px;"></i>
 <?php echo $sess['start_date']; ?> → <?php echo $sess['end_date']; ?>
 </div>
 <?php if (!empty($sess['capacity'])): ?>
 <div style="font-size:10px;color:#94a3b8;margin-top:2px;">
 <i class="fas fa-users" style="font-size:9px;"></i>
 السعة: <?php echo $sess['capacity']; ?> طالب
 </div>
 <?php endif; ?>
 </div>
 </div>
 <?php endforeach; ?>
 </div>

 <!-- الجزء الخاص ببعت البيانات المخفية للسيرفر -->
 <input type="radio" id="selected_session_id" name="session_id" value="" required style="display:none;">
 <div id="session-error" style="display:none;color:#ef4444;font-size:12px;font-weight:600;margin-top:6px;">
 ⚠️ يرجى اختيار دورة أولاً
 </div>
 </div>

 <script>
 function selectSession(card, sessionId) {
 // Remove selected from all cards
 document.querySelectorAll('.session-card').forEach(c => c.classList.remove('selected'));
 // Mark this card as selected
 card.classList.add('selected');
 // Update the hidden radio input
 const radio = document.getElementById('selected_session_id');
 radio.value = sessionId;
 radio.checked = true;
 // Hide error if shown
 document.getElementById('session-error').style.display = 'none';
 }
 // Validate before submit
 document.addEventListener('DOMContentLoaded', function() {
 const form = document.querySelector('form[method="POST"]');
 if (form) {
 form.addEventListener('submit', function(e) {
 const radio = document.getElementById('selected_session_id');
 if (!radio.value) {
 e.preventDefault();
 document.getElementById('session-error').style.display = 'block';
 document.getElementById('sessions-grid').scrollIntoView({behavior:'smooth', block:'center'});
 }
 });
 }
 });
 </script>


 <hr class="border-slate-50">

 <!-- رفع المستندات والأوراق المطلوبة -->
 <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
 <!-- صورة البطاقة الشخصية -->
 <div class="space-y-3">
 <label class="block text-sm font-bold text-slate-700">صورة البطاقة الشخصية (وجه)</label>
 <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-primary transition-colors relative group">
 <input type="file" name="id_card" accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewImage(this, 'id-preview')">
 <div id="id-preview" class="space-y-2">
 <i class="fas fa-address-card text-3xl text-slate-300 group-hover:text-primary transition-colors"></i>
 <p class="text-xs text-slate-400 font-medium">اسحب الصورة أو اضغط للرفع</p>
 </div>
 </div>
 </div>

 <!-- صورة كارنيه الجامعة -->
 <div class="space-y-3">
 <label class="block text-sm font-bold text-slate-700">صورة كارنيه الجامعة</label>
 <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-primary transition-colors relative group">
 <input type="file" name="uni_card" accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewImage(this, 'uni-preview')">
 <div id="uni-preview" class="space-y-2">
 <i class="fas fa-id-badge text-3xl text-slate-300 group-hover:text-primary transition-colors"></i>
 <p class="text-xs text-slate-400 font-medium">اسحب الصورة أو اضغط للرفع</p>
 </div>
 </div>
 </div>
 </div>

 <div class="bg-bg border border-primary p-4 rounded-xl flex items-start gap-3">
 <i class="fas fa-exclamation-triangle text-primary mt-1"></i>
 <p class="text-[11px] text-primary leading-relaxed font-medium">
 تنبيه: يجب أن تكون الصور واضحة وبحجم لا يتعدى 5 ميجابايت. سيتم دمج الصور آلياً لتسهيل عملية المراجعة والطباعة من قبل إدارة التربية العسكرية.
 </p>
 </div>

 <button type="submit" class="w-full bg-primary text-white py-4 rounded-2xl font-black shadow-lg shadow-sm hover:bg-accent hover:text-white transition-all transform hover:-translate-y-1 flex items-center justify-center gap-3">
 <i class="fas fa-paper-plane"></i>
 تمكين طلب التسجيل
 </button>
 </form>
 </div>
 <?php endif; ?>
 <?php endif; ?>

 <!-- شوية معلومات وشروط تهم الطالب -->
 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
 <div class="bg-white p-6 rounded-2xl border border-slate-100">
 <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-3">
 <i class="fas fa-info-circle text-accent"></i> شروط الالتحاق
 </h4>
 <ul class="text-xs text-slate-500 space-y-2 leading-relaxed list-disc pr-4">
 <li>أن يكون الطالب مقيداً بالجامعة في إحدى الفرق الدراسية.</li>
 <li>إرفاق صورة بطاقة الرقم القومي سارية.</li>
 <li>إرفاق صورة كارنيه الجامعة للعام الدراسي الحالي.</li>
 <li>الالتزام بالزي المحدد والمواعيد المقررة للدورة.</li>
 </ul>
 </div>
 <div class="bg-white p-6 rounded-2xl border border-slate-100">
 <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-3">
 <i class="fas fa-print text-primary"></i> تعليمات الطباعة
 </h4>
 <p class="text-xs text-slate-500 leading-relaxed">
 بعد القبول المبدئي، يمكنك طباعة "ملف التقديم" الذي يحتوي على صور مستنداتك مدمجة في صفحة واحدة. يرجى تقديم هذا الملف ورقياً لمكتب التربية العسكرية عند الحضور.
 </p>
 </div>
 </div>
</div>

<script>
function previewImage(input, previewId) {
 const preview = document.getElementById(previewId);
 if (input.files && input.files[0]) {
 const reader = new FileReader();
 reader.onload = function(e) {
 preview.innerHTML = `
 <div class="relative w-full h-24 rounded-lg overflow-hidden">
 <img src="${e.target.result}" class="w-full h-full object-cover">
 <div class="absolute inset-0 bg-black/20 flex items-center justify-center">
 <i class="fas fa-check text-white text-xl"></i>
 </div>
 </div>
 <p class="text-[10px] text-primary font-bold mt-2">تم اختيار الملف بنجاح</p>
 `;
 }
 reader.readAsDataURL(input.files[0]);
 }
}
</script>

<?php require_once 'includes/footer.php'; ?>