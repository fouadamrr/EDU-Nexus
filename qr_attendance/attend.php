<?php
require_once '../includes/header.php';

// Only students can attend via QR; redirect others
if ($role !== 'student') {
 echo "<script>window.location.href='../index.php';</script>"; exit;
}

$token = trim($_GET['token'] ?? '');
$error = '';
$already_attended = false;
$session_data = null;

// ── Auto-expire any timed-out sessions ───────────────────────────────────────
try {
 $pdo->prepare("UPDATE lecture_sessions SET status='expired' WHERE status='active' AND expires_at < NOW()")->execute();
} catch (Exception $e) {}

// ── Validate token ────────────────────────────────────────────────────────────
if (!$token) {
 $error = 'رمز الحضور غير موجود. يرجى مسح QR Code الصحيح.';
} else {
 try {
 $stmt = $pdo->prepare("
 SELECT ls.id, ls.status, ls.expires_at, ls.college_id,
 c.name AS course_name, c.code AS course_code,
 col.name AS college_name, col.latitude, col.longitude
 FROM lecture_sessions ls
 JOIN courses c ON c.id = ls.course_id
 LEFT JOIN colleges col ON col.id = ls.college_id
 WHERE ls.token = ?
 ");
 $stmt->execute([$token]);
 $session_data = $stmt->fetch(PDO::FETCH_ASSOC);

 if (!$session_data) {
 $error = 'رمز الحضور غير صالح.';
 } elseif ($session_data['status'] !== 'active') {
 $status_ar = $session_data['status'] === 'ended' ? 'تم إنهاء الجلسة من قِبل المدرس.' : 'انتهت صلاحية QR Code لهذه المحاضرة.';
 $error = $status_ar;
 } else {
 // Check if student already attended
 $chk = $pdo->prepare("SELECT id FROM qr_attendance WHERE session_id=? AND student_id=?");
 $chk->execute([$session_data['id'], $user_id]);
 if ($chk->fetch()) {
 $already_attended = true;
 }
 }
 } catch (Exception $e) {
 $error = 'حدث خطأ أثناء التحقق. يرجى المحاولة مجدداً.';
 }
}

$has_college_location = $session_data
 && !empty($session_data['latitude'])
 && !empty($session_data['longitude']);
?>

<div class="max-w-lg mx-auto mt-6 space-y-5 animate-fade-in-up">

 <!-- Logo + Title -->
 <div class="text-center">
 <div class="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-3 shadow-lg">
 <i class="fas fa-qrcode"></i>
 </div>
 <h1 class="text-2xl font-bold text-secondary">تسجيل الحضور</h1>
 <p class="text-sm text-slate-500 mt-1">EDU Nexus — نظام الحضور الذكي</p>
 </div>

 <?php if ($error): ?>
 <!-- ── ERROR ── -->
 <div class="bg-bg border border-primary rounded-2xl p-6 text-center">
 <i class="fas fa-times-circle text-5xl text-primary mb-3 block"></i>
 <h2 class="font-bold text-primary text-lg"><?php echo htmlspecialchars($error); ?></h2>
 <p class="text-sm text-primary mt-2">تواصل مع مدرسك للحصول على رمز الحضور الصحيح.</p>
 </div>

 <?php elseif ($already_attended): ?>
 <!-- ── ALREADY ATTENDED ── -->
 <div class="bg-bg border border-primary rounded-2xl p-6 text-center">
 <i class="fas fa-check-double text-5xl text-primary mb-3 block"></i>
 <h2 class="font-bold text-primary text-lg">تم تسجيل حضورك مسبقاً</h2>
 <p class="text-sm text-primary mt-2">
 تم تسجيل حضورك في محاضرة
 <strong><?php echo htmlspecialchars($session_data['course_name']); ?></strong>.
 لا يمكن التسجيل مرتين.
 </p>
 </div>

 <?php else: ?>
 <!-- ── ATTENDANCE FORM ── -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <!-- Course Info -->
 <div class="bg-bg px-6 py-5 text-white">
 <p class="text-primary text-xs font-bold uppercase tracking-wider mb-1">المحاضرة</p>
 <h2 class="text-xl font-bold"><?php echo htmlspecialchars($session_data['course_name']); ?></h2>
 <p class="text-primary text-sm font-mono mt-0.5">
 <?php echo htmlspecialchars($session_data['course_code'] ?? ''); ?>
 <?php if ($session_data['college_name']): ?>
 — <?php echo htmlspecialchars($session_data['college_name']); ?>
 <?php endif; ?>
 </p>
 <div class="mt-3 text-xs text-primary">
 <i class="fas fa-clock ml-1"></i>
 تنتهي الصلاحية: <?php echo date('H:i', strtotime($session_data['expires_at'])); ?>
 </div>
 </div>

 <div class="p-6 space-y-5">
 <!-- Student info -->
 <div class="flex items-center gap-3 bg-bg rounded-xl px-4 py-3">
 <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center text-white font-bold shrink-0">
 <?php echo mb_substr($_SESSION['full_name'] ?? $username, 0, 1, 'UTF-8'); ?>
 </div>
 <div>
 <p class="font-bold text-slate-800"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $username); ?></p>
 <p class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($username); ?></p>
 </div>
 </div>

 <!-- Location Status -->
 <div id="location-status" class="bg-bg border border-primary rounded-xl p-4 text-center">
 <i class="fas fa-map-marker-alt text-primary text-2xl mb-2 block"></i>
 <p class="font-bold text-primary text-sm">يتم تحديد موقعك...</p>
 <p class="text-xs text-primary mt-1">يرجى السماح للمتصفح بالوصول إلى موقعك</p>
 </div>

 <!-- Hidden form -->
 <form id="attendanceForm" method="POST" action="verify_location.php" class="hidden space-y-2">
 <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
 <input type="hidden" id="lat_input" name="lat" value="">
 <input type="hidden" id="lng_input" name="lng" value="">
 <input type="hidden" id="acc_input" name="accuracy" value="">
 <button type="submit" id="submit-btn"
 class="w-full bg-primary hover:bg-accent hover:text-white text-white py-3.5 rounded-xl font-bold transition shadow-lg shadow-sm flex items-center justify-center gap-2 text-base">
 <i class="fas fa-check-circle text-xl"></i> تأكيد تسجيل الحضور
 </button>
 </form>

 <!-- No location support fallback -->
 <form id="noLocationForm" method="POST" action="verify_location.php" class="hidden">
 <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
 <input type="hidden" name="lat" value="">
 <input type="hidden" name="lng" value="">
 <button type="submit"
 class="w-full bg-slate-500 hover:bg-slate-600 text-white py-3.5 rounded-xl font-bold transition flex items-center justify-center gap-2">
 <i class="fas fa-exclamation-triangle"></i> تسجيل بدون موقع (مشبوه)
 </button>
 <p class="text-xs text-center text-slate-400 mt-2">سيُسجَّل حضورك كـ "مشبوه" نظراً لعدم تحديد الموقع</p>
 </form>

 </div>
 </div>
 <?php endif; ?>

</div>

<script>
<?php if (!$error && !$already_attended): ?>
const hasCollegeLocation = <?php echo $has_college_location ? 'true' : 'false'; ?>;

function onLocationSuccess(position) {
 const lat = position.coords.latitude;
 const lng = position.coords.longitude;
 const acc = Math.round(position.coords.accuracy);

 document.getElementById('lat_input').value = lat;
 document.getElementById('lng_input').value = lng;
 document.getElementById('acc_input').value = acc;

 // Update status UI
 const statusDiv = document.getElementById('location-status');
 statusDiv.className = 'bg-bg border border-primary rounded-xl p-4 text-center';
 statusDiv.innerHTML = `
 <i class="fas fa-map-marker-alt text-primary text-2xl mb-2 block"></i>
 <p class="font-bold text-primary text-sm">تم تحديد موقعك بنجاح</p>
 <p class="text-xs text-primary mt-1">الدقة: ±${acc} متر</p>
 ${!hasCollegeLocation ? '<p class="text-xs text-accent mt-1 font-medium"><i class="fas fa-info-circle ml-1"></i>موقع الكلية غير مسجل — سيُسجَّل بدون تحقق المسافة</p>' : ''}
 `;

 // Show submit form
 document.getElementById('attendanceForm').classList.remove('hidden');
}

function onLocationError(err) {
 let msg = 'تعذّر تحديد موقعك.';
 if (err.code === 1) msg = 'رفضت السماح بالوصول إلى الموقع. سيُسجَّل حضورك كمشبوه.';
 if (err.code === 2) msg = 'الموقع غير متاح. تأكد من تفعيل GPS.';
 if (err.code === 3) msg = 'انتهت المهلة. يرجى المحاولة مجدداً.';

 const statusDiv = document.getElementById('location-status');
 statusDiv.className = 'bg-bg border border-primary rounded-xl p-4 text-center';
 statusDiv.innerHTML = `
 <i class="fas fa-exclamation-triangle text-primary text-2xl mb-2 block"></i>
 <p class="font-bold text-primary text-sm">${msg}</p>
 `;
 document.getElementById('noLocationForm').classList.remove('hidden');
}

// Request geolocation immediately on page load
if (navigator.geolocation) {
 navigator.geolocation.getCurrentPosition(onLocationSuccess, onLocationError, {
 enableHighAccuracy: true,
 timeout: 15000,
 maximumAge: 0
 });
} else {
 document.getElementById('location-status').innerHTML = `
 <i class="fas fa-exclamation-triangle text-primary text-2xl mb-2 block"></i>
 <p class="font-bold text-primary text-sm">جهازك لا يدعم تحديد الموقع</p>
 `;
 document.getElementById('noLocationForm').classList.remove('hidden');
}
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
