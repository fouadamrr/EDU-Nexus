<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/header.php';

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($role !== 'student') {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
?>

<div class="max-w-4xl mx-auto space-y-6 animate-fade-in-up">
 <!-- Header -->
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
 <div>
 <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
 <span class="w-12 h-12 bg-bg text-primary rounded-xl flex items-center justify-center">
 <i class="fas fa-qrcode"></i>
 </span>
 تسجيل الحضور التلقائي
 </h2>
 <p class="text-slate-500 mt-2 text-sm">قم بتوجيه الكاميرا نحو رمز الاستجابة السريعة (QR Code) المعروض من قبل الدكتور لتسجيل حضورك فوراً.</p>
 </div>
 <div class="bg-bg text-primary p-3 rounded-xl hidden sm:block">
 <i class="fas fa-camera text-2xl"></i>
 </div>
 </div>

 <!-- Scanner Container -->
 <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 relative overflow-hidden">
 <div id="reader-container" class="w-full max-w-lg mx-auto bg-bg rounded-xl overflow-hidden border-2 border-dashed border-slate-300 relative">
 <div id="reader" style="width: 100%;"></div>
 
 <div id="scanner-overlay" class="absolute inset-0 flex items-center justify-center bg-white/80 z-10 transition-opacity duration-300">
 <div class="text-center">
 <div class="w-16 h-16 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-3 animate-pulse">
 <i class="fas fa-camera text-2xl"></i>
 </div>
 <p class="text-slate-700 font-bold mb-3">جاري تجهيز الكاميرا...</p>
 <button id="start-btn" class="bg-primary text-white px-6 py-2 rounded-xl font-bold hover:bg-primary transition shadow hidden">
 <i class="fas fa-play ml-2"></i> بدء المسح
 </button>
 </div>
 </div>
 </div>

 <div id="scan-result" class="hidden mt-6 bg-bg text-primary border border-primary p-5 rounded-xl text-center">
 <i class="fas fa-check-circle text-4xl mb-2"></i>
 <h3 class="font-bold text-lg">تم قراءة الرمز بنجاح!</h3>
 <p class="text-sm mt-1">جاري توجيهك لتسجيل الحضور...</p>
 <div class="mt-4 text-xs font-mono break-all text-primary/70" id="result-text"></div>
 </div>
 
 <div id="error-message" class="hidden mt-6 bg-bg text-primary border border-primary p-4 rounded-xl text-center text-sm font-bold">
 <i class="fas fa-exclamation-triangle"></i> <span id="error-text">حدث خطأ أثناء فتح الكاميرا.</span>
 </div>
 </div>
</div>

<!-- Include Html5Qrcode Library via CDN -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
 let html5QrcodeScanner = null;
 const scannerOverlay = document.getElementById('scanner-overlay');
 const startBtn = document.getElementById('start-btn');
 const scanResult = document.getElementById('scan-result');
 const resultText = document.getElementById('result-text');
 const errorMsgInfo = document.getElementById('error-message');
 const errorText = document.getElementById('error-text');
 
 // Setup Scanner
 function onScanSuccess(decodedText, decodedResult) {
 // Stop scanning
 if (html5QrcodeScanner) {
 html5QrcodeScanner.clear();
 }
 
 // Show success UI
 document.getElementById('reader-container').style.display = 'none';
 scanResult.classList.remove('hidden');
 resultText.innerText = decodedText;
 
 // Only redirect if it's an attend.php URL to avoid arbitrary redirects
 if (decodedText.includes('attend.php') || decodedText.includes('qr_attendance')) {
 setTimeout(() => {
 window.location.href = decodedText;
 }, 1000);
 } else {
 scanResult.className = "mt-6 bg-bg text-primary border border-primary p-5 rounded-xl text-center";
 scanResult.innerHTML = `
 <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
 <h3 class="font-bold text-lg">رمز غير مدعوم</h3>
 <p class="text-sm mt-1">هذا الرمز ليس لبرنامج الحضور الجامعي.</p>
 <div class="mt-4"><button onclick="location.reload()" class="bg-primary text-white px-4 py-2 rounded font-bold">حاول مرة أخرى</button></div>
 `;
 }
 }

 function onScanFailure(error) {
 // handle scan failure quietly
 }

 function startScanner() {
 scannerOverlay.classList.add('hidden');
 errorMsgInfo.classList.add('hidden');
 
 try {
 html5QrcodeScanner = new Html5QrcodeScanner(
 "reader", { fps: 10, qrbox: {width: 250, height: 250}, aspectRatio: 1.0 }, /* verbose= */ false);
 html5QrcodeScanner.render(onScanSuccess, onScanFailure);
 } catch (e) {
 errorMsgInfo.classList.remove('hidden');
 errorText.innerText = "لم نتمكن من الوصول للكاميرا. يرجى التأكد من إعطاء الصلاحيات.";
 scannerOverlay.classList.remove('hidden');
 startBtn.classList.remove('hidden');
 }
 }

 // Attempt to start automatically after a short delay
 setTimeout(() => {
 startScanner();
 }, 500);
});
</script>

<?php require_once 'includes/footer.php'; ?>
