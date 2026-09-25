<?php
require_once 'includes/header.php';
require_once __DIR__ . '/controllers/MilitaryController.php';

// Access Control
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}

$controller = new MilitaryController();
$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 if (isset($_POST['create_session'])) {
 if ($controller->createSession($_POST)) {
 $message = '<div class="bg-primary text-white p-4 rounded-xl mb-4 font-bold">تم فتح الدورة الجديدة بنجاح.</div>';
 }
 }
 if (isset($_POST['update_status'])) {
 if ($controller->updateRegistrationStatus((int)$_POST['reg_id'], $_POST['status'])) {
 $message = '<div class="bg-accent text-white p-4 rounded-xl mb-4 font-bold">تم تحديث حالة الطالب بنجاح.</div>';
 }
 }
}

$sessions = $controller->getAllSessions();
$registrations = $controller->getRegistrations();
?>

<div class="max-w-7xl mx-auto space-y-8 animate-fade-in">
 
 <!-- Admin Header -->
 <div class="flex flex-wrap items-center justify-between gap-6">
 <div>
 <h1 class="text-3xl font-black text-secondary flex items-center gap-3">
 <i class="fas fa-user-shield text-primary bg-bg p-2.5 rounded-2xl"></i>
 إدارة التربية العسكرية (إدارة)
 </h1>
 <p class="text-slate-500 mt-1">التحكم في الدورات التدريبية ومراجعة طلبات الطلاب.</p>
 </div>
 <div class="flex gap-3">
 <button onclick="document.getElementById('sessionModal').classList.remove('hidden')" class="bg-secondary text-white px-6 py-3 rounded-2xl font-bold hover:bg-opacity-80 hover:text-white transition shadow-lg flex items-center gap-2">
 <i class="fas fa-plus-circle"></i> فتح دورة جديدة
 </button>
 <a href="military_print.php?bulk=1" target="_blank" class="bg-slate-800 text-white px-6 py-3 rounded-2xl font-bold hover:bg-slate-900 transition shadow-lg flex items-center gap-2">
 <i class="fas fa-print"></i> طباعة جميع المستندات
 </a>
 </div>
 </div>

 <?php echo $message; ?>

 <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
 
 <!-- Sessions Sidebar (1/4) -->
 <div class="lg:col-span-1 space-y-6">
 <h3 class="font-bold text-slate-700 flex items-center gap-2 px-2">
 <i class="fas fa-calendar-alt text-primary"></i> الدورات المفتوحة
 </h3>
 <div class="space-y-4">
 <?php foreach ($sessions as $sess): ?>
 <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group">
 <div class="absolute right-0 top-0 h-full w-1 <?php echo $sess['status'] === 'open' ? 'bg-primary' : 'bg-slate-300'; ?>"></div>
 <div class="flex justify-between items-start mb-2">
 <span class="text-[10px] font-bold uppercase py-0.5 px-2 rounded-full <?php echo $sess['status'] === 'open' ? 'bg-primary text-white' : 'bg-bg text-slate-500'; ?>">
 <?php echo $sess['status'] === 'open' ? 'نشطة' : 'مغلقة'; ?>
 </span>
 </div>
 <h4 class="font-bold text-slate-800"><?php echo htmlspecialchars($sess['title']); ?></h4>
 <div class="text-[11px] text-slate-400 mt-2 space-y-1">
 <div class="flex items-center gap-1"><i class="far fa-clock"></i> من: <?php echo $sess['start_date']; ?></div>
 <div class="flex items-center gap-1"><i class="fas fa-history"></i> إلى: <?php echo $sess['end_date']; ?></div>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
 </div>

 <!-- Registrations Table (3/4) -->
 <div class="lg:col-span-3 space-y-6">
 <h3 class="font-bold text-slate-700 flex items-center gap-2 px-2">
 <i class="fas fa-users text-primary"></i> طلبات المتقدمين
 </h3>
 <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-right text-sm">
 <thead class="bg-bg text-slate-500 font-bold uppercase text-[11px] tracking-wider border-b border-slate-100">
 <tr>
 <th class="px-6 py-4">الطالب</th>
 <th class="px-6 py-4">الدورة</th>
 <th class="px-6 py-4 text-center">المستندات</th>
 <th class="px-6 py-4 text-center">الحالة</th>
 <th class="px-6 py-4 text-center">إجراءات</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-slate-50">
 <?php foreach ($registrations as $reg): ?>
 <tr class="hover:bg-bg/50 transition-colors">
 <td class="px-6 py-4">
 <div class="font-bold text-slate-800"><?php echo htmlspecialchars($reg['full_name']); ?></div>
 <div class="text-[11px] text-slate-400 font-mono"><?php echo htmlspecialchars($reg['username']); ?></div>
 </td>
 <td class="px-6 py-4 text-slate-600 font-medium">
 <?php echo htmlspecialchars($reg['session_title']); ?>
 </td>
 <td class="px-6 py-4 text-center">
 <div class="flex justify-center gap-2">
 <button onclick="previewDocs('<?php echo $reg['id_card_path']; ?>', '<?php echo $reg['uni_card_path']; ?>')" class="w-8 h-8 rounded-lg bg-bg text-accent hover:bg-accent hover:text-white transition flex items-center justify-center" title="عرض الصور">
 <i class="fas fa-eye text-xs"></i>
 </button>
 <a href="military_print.php?user_id=<?php echo $reg['user_id']; ?>" target="_blank" class="w-8 h-8 rounded-lg bg-bg text-slate-600 hover:bg-slate-800 hover:text-white transition flex items-center justify-center" title="طباعة الاستمارة">
 <i class="fas fa-print text-xs"></i>
 </a>
 </div>
 </td>
 <td class="px-6 py-4 text-center">
 <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase
 <?php 
 echo $reg['status'] === 'pending' ? 'bg-primary text-white' : 
 ($reg['status'] === 'approved' ? 'bg-primary text-white' : 
 ($reg['status'] === 'passed' ? 'bg-primary text-white' : 'bg-primary text-white')); 
 ?>">
 <?php echo $reg['status']; ?>
 </span>
 </td>
 <td class="px-6 py-4">
 <form method="POST" class="flex justify-center gap-1">
 <input type="hidden" name="update_status" value="1">
 <input type="hidden" name="reg_id" value="<?php echo $reg['id']; ?>">
 
 <?php if ($reg['status'] === 'pending'): ?>
 <button name="status" value="approved" class="text-[10px] font-bold bg-primary text-white px-3 py-1 rounded-lg hover:bg-primary transition">قبول</button>
 <?php endif; ?>

 <?php if ($reg['status'] === 'approved'): ?>
 <button name="status" value="passed" class="text-[10px] font-bold bg-primary text-white px-3 py-1 rounded-lg hover:bg-primary transition">اجتياز</button>
 <button name="status" value="failed" class="text-[10px] font-bold bg-primary text-white px-3 py-1 rounded-lg hover:bg-primary transition">إخفاق</button>
 <?php endif; ?>
 </form>
 </td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 </div>
 </div>
 </div>
</div>

<!-- ══════════════════════════════════════
 SESSION MODAL — Scoped CSS Fix
══════════════════════════════════════ -->
<style>
 /* ── Scoped Modal Overrides (fix global header.php CSS conflicts) ── */
 #sessionModal label {
 font-size: 11px !important;
 font-weight: 700 !important;
 color: #64748b !important;
 text-transform: uppercase !important;
 letter-spacing: 0.07em !important;
 display: flex !important;
 align-items: center !important;
 gap: 6px !important;
 margin-bottom: 6px !important;
 }
 #sessionModal label i {
 color: #2563eb !important;
 font-size: 11px !important;
 }
 #sessionModal input[type="text"],
 #sessionModal input[type="number"],
 #sessionModal input[type="date"] {
 width: 100% !important;
 background: #f8fafc !important;
 border: 1.5px solid #e2e8f0 !important;
 border-radius: 12px !important;
 padding: 11px 14px !important;
 font-size: 14px !important;
 font-family: 'Cairo', sans-serif !important;
 color: #1e293b !important;
 box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
 transition: border-color 0.2s, box-shadow 0.2s !important;
 outline: none !important;
 }
 #sessionModal input:focus {
 border-color: #2563eb !important;
 box-shadow: 0 0 0 3px rgba(37,99,235,0.1) !important;
 background: #fff !important;
 }
 #sessionModal input::placeholder { color: #94a3b8 !important; font-size: 13px !important; }
 #sessionModal .modal-submit-btn {
 width: 100%;
 background: linear-gradient(135deg, #2563eb 0%, #2563eb 100%);
 color: #fff;
 padding: 14px 24px;
 border-radius: 14px;
 font-size: 15px;
 font-weight: 800;
 font-family: 'Cairo', sans-serif;
 border: none;
 cursor: pointer;
 box-shadow: 0 6px 20px rgba(37,99,235,0.25);
 transition: all 0.25s ease;
 display: flex;
 align-items: center;
 justify-content: center;
 gap: 10px;
 margin-top: 8px;
 }
 #sessionModal .modal-submit-btn:hover {
 transform: translateY(-2px);
 box-shadow: 0 10px 28px rgba(37,99,235,0.35);
 }
 #sessionModal .modal-submit-btn i { color: #fff !important; font-size: 14px !important; }
 /* Modal entrance animation */
 #sessionModal > div {
 animation: modalSlideIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
 }
 @keyframes modalSlideIn {
 from { transform: translateY(30px) scale(0.96); opacity: 0; }
 to { transform: translateY(0) scale(1); opacity: 1; }
 }
 .modal-field-group { margin-bottom: 0; }
 .modal-close-btn {
 width: 36px; height: 36px;
 border-radius: 10px;
 background: rgba(255,255,255,0.15);
 border: 1px solid rgba(255,255,255,0.25);
 display: flex; align-items: center; justify-content: center;
 cursor: pointer;
 transition: background 0.2s;
 }
 .modal-close-btn:hover { background: rgba(255,255,255,0.3); }
 .modal-close-btn i { color: #fff !important; font-size: 14px !important; }
</style>

<!-- Add Session Modal -->
<div id="sessionModal" class="hidden fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
 <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">

 <!-- Modal Header -->
 <div style="background: linear-gradient(135deg, #2563eb 0%, #2563eb 100%); padding: 22px 24px;" class="flex justify-between items-center">
 <div class="flex items-center gap-3">
 <div style="width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;">
 <i class="fas fa-plus-circle" style="color:#fff !important;font-size:16px;"></i>
 </div>
 <div>
 <h3 style="font-weight:800;font-size:16px;color:#fff;margin:0;">فتح دورة جديدة</h3>
 <p style="font-size:11px;color:rgba(255,255,255,0.7);margin:2px 0 0;">تربية عسكرية</p>
 </div>
 </div>
 <button onclick="document.getElementById('sessionModal').classList.add('hidden')" class="modal-close-btn">
 <i class="fas fa-times"></i>
 </button>
 </div>

 <!-- Modal Body -->
 <form method="POST" class="p-6 space-y-5">
 <input type="hidden" name="create_session" value="1">

 <!-- Session Title -->
 <div class="modal-field-group">
 <label>
 <i class="fas fa-tag"></i>
 اسم الدورة / التعريف
 </label>
 <input type="text" name="title" required placeholder="مثال: دورة التربية العسكرية — صيف 2026">
 </div>

 <!-- Dates Grid -->
 <div class="grid grid-cols-2 gap-4">
 <div class="modal-field-group">
 <label>
 <i class="fas fa-calendar-plus"></i>
 تاريخ البدء
 </label>
 <input type="date" name="start_date" required>
 </div>
 <div class="modal-field-group">
 <label>
 <i class="fas fa-calendar-check"></i>
 تاريخ الانتهاء
 </label>
 <input type="date" name="end_date" required>
 </div>
 </div>

 <!-- Capacity -->
 <div class="modal-field-group">
 <label>
 <i class="fas fa-users"></i>
 القدرة الاستيعابية
 </label>
 <input type="number" name="capacity" value="100" min="1">
 </div>

 <!-- Divider -->
 <div style="border-top:1px solid #f1f5f9;margin:4px 0;"></div>

 <!-- Submit -->
 <button type="submit" class="modal-submit-btn">
 <i class="fas fa-check-circle"></i>
 تأكيد فتح الدورة
 </button>
 </form>
 </div>
</div>

<!-- Doc Preview Modal -->
<div id="docPreviewModal" class="hidden fixed inset-0 bg-slate-900/90 z-[60] flex items-center justify-center p-4">
 <button onclick="document.getElementById('docPreviewModal').classList.add('hidden')" class="absolute top-6 right-6 text-white text-3xl hover:text-accent transition">
 <i class="fas fa-times"></i>
 </button>
 <div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-2 gap-6 bg-white/5 p-4 rounded-3xl backdrop-blur-lg border border-white/10">
 <div class="space-y-4">
 <h4 class="text-white font-bold text-center">البطاقة الشخصية</h4>
 <img id="id-prev-img" src="" class="w-full h-auto rounded-2xl shadow-2xl border border-white/20">
 </div>
 <div class="space-y-4">
 <h4 class="text-white font-bold text-center">كارنيه الجامعة</h4>
 <img id="uni-prev-img" src="" class="w-full h-auto rounded-2xl shadow-2xl border border-white/20">
 </div>
 </div>
</div>

<script>
function previewDocs(idImg, uniImg) {
 document.getElementById('id-prev-img').src = idImg;
 document.getElementById('uni-prev-img').src = uniImg;
 document.getElementById('docPreviewModal').classList.remove('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?>
