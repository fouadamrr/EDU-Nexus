<?php
// generate_form.php
require_once 'includes/header.php'; // For session and DB
require_once __DIR__ . '/models/StudentDetail.php';
require_once __DIR__ . '/models/College.php';

$student_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'enrollment';

$studentModel = new StudentDetail();
$student = $studentModel->findOneBy('user_id', $student_id);

// Get User info
$stmt = $pdo->prepare("SELECT u.*, c.name as college_name FROM users u LEFT JOIN colleges c ON u.college_id = c.id WHERE u.id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();

if (!$user || !$student) {
 die("تعذر العثور على بيانات الطالب.");
}

$date = date('Y/m/d');
?>

<div class="max-w-4xl mx-auto p-4 md:p-8">
 
 <!-- Print Actions (Hidden during print) -->
 <div class="mb-4 flex justify-between items-center print:hidden bg-bg p-4 rounded-xl border border-primary">
 <div class="flex items-center gap-3">
 <i class="fas fa-print text-primary"></i>
 <span class="font-bold text-primary">معاينة الاستمارة</span>
 </div>
 <button onclick="window.print()" class="bg-primary text-white px-6 py-2 rounded-lg font-bold hover:bg-primary transition flex items-center gap-2">
 <span>طباعة الآن</span>
 <i class="fas fa-file-pdf"></i>
 </button>
 </div>

 <!-- Paper Mockup -->
 <div class="bg-white border-2 border-slate-200 p-8 shadow-sm relative font-serif text-slate-900 leading-tight" id="printableArea">
 
 <!-- Header -->
 <div class="flex justify-between items-start mb-8 border-b-2 border-slate-900 pb-4">
 <div class="text-right space-y-1">
 <h1 class="text-xl font-bold">جامعة EDU Nexus</h1>
 <h2 class="text-lg font-bold"><?php echo htmlspecialchars($user['college_name']); ?></h2>
 <h2 class="text-md">شؤون الطلاب</h2>
 </div>
 <div class="flex flex-col items-center">
 <div class="mb-2">
 <img src="assets/images/logo.png" alt="University Logo" class="h-24 w-auto object-contain">
 </div>
 <span class="text-[10px] font-mono text-slate-400">EDU NEXUS SYSTEM</span>
 </div>
 <div class="text-left space-y-1">
 <p class="text-sm italic">Date: <?php echo date('d-m-Y'); ?></p>
 <p class="text-sm italic">Serial: <?php echo strtoupper(substr(md5($student_id . time()), 0, 8)); ?></p>
 </div>
 </div>

 <?php if ($type === 'enrollment'): ?>
 <!-- Enrollment Certificate -->
 <div class="text-center mb-8">
 <h2 class="text-2xl font-extrabold underline underline-offset-8 mb-2">إفادة قيد أكاديمي</h2>
 </div>

 <div class="space-y-6 text-md">
 <p>تشهد عمادة شؤون الطلاب بـ <strong class="text-lg"><?php echo htmlspecialchars($user['college_name']); ?></strong> بأن الطالب المذكور أدناه مقيد بالكلية ومن الطلاب المنتظمين:</p>
 
 <div class="grid grid-cols-2 gap-y-4 bg-bg/50 p-6 rounded-2xl border border-slate-100">
 <div>
 <span class="text-slate-500 block text-sm">اسم الطالب:</span>
 <strong class="text-xl leading-none"><?php echo htmlspecialchars($user['full_name']); ?></strong>
 </div>
 <div>
 <span class="text-slate-500 block text-sm">الرقم الجامعي (ID):</span>
 <strong class="font-mono"><?php echo htmlspecialchars($user['username']); ?></strong>
 </div>
 <div>
 <span class="text-slate-500 block text-sm">التخصص / القسم:</span>
 <strong><?php echo htmlspecialchars($student['major'] ?: 'العام (غير متخصص)'); ?></strong>
 </div>
 <div>
 <span class="text-slate-500 block text-sm">المستوى الدراسي:</span>
 <strong>المستوى <?php echo (int)$student['level']; ?></strong>
 </div>
 <div>
 <span class="text-slate-500 block text-sm">الحالة الأكاديمية:</span>
 <span class="px-2 py-0.5 border border-primary text-primary bg-white rounded-md font-bold text-sm">مقيد ومنتظم</span>
 </div>
 <div>
 <span class="text-slate-500 block text-sm">العام الجامعي:</span>
 <strong>2023 / 2024</strong>
 </div>
 </div>

 <p class="mt-12">وقد أعطيت له هذه الإفادة لتقديمها إلى الجهات المختصة، دون أدنى مسؤولية على الجامعة تجاه حقوق الغير.</p>
 </div>

 <?php elseif ($type === 'data_summary'): ?>
 <!-- Student Data Form -->
 <div class="text-center mb-6">
 <h2 class="text-2xl font-extrabold underline underline-offset-8 mb-2">استمارة بيانات طالب</h2>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4 border-b border-slate-100 pb-4">
 <div class="space-y-4">
 <h4 class="font-bold border-r-4 border-primary padding px-3 mb-4">البيانات الشخصية</h4>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">الاسم الكامل:</span>
 <span class="font-bold"><?php echo htmlspecialchars($user['full_name']); ?></span>
 </div>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">الرقم القومي:</span>
 <span class="font-bold font-mono"><?php echo htmlspecialchars($student['national_id'] ?: '—'); ?></span>
 </div>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">تاريخ الميلاد:</span>
 <span class="font-bold"><?php echo htmlspecialchars($student['birth_date'] ?: '—'); ?></span>
 </div>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">رقم الهاتف:</span>
 <span class="font-bold font-mono"><?php echo htmlspecialchars($student['phone'] ?: '—'); ?></span>
 </div>
 </div>

 <div class="space-y-4">
 <h4 class="font-bold border-r-4 border-primary padding px-3 mb-4">البيانات الأكاديمية</h4>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">الكلية:</span>
 <span class="font-bold"><?php echo htmlspecialchars($user['college_name']); ?></span>
 </div>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">القسم:</span>
 <span class="font-bold"><?php echo htmlspecialchars($student['major'] ?: 'عام'); ?></span>
 </div>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">سنة الالتحاق:</span>
 <span class="font-bold"><?php echo htmlspecialchars((string)$student['enrollment_year'] ?: '—'); ?></span>
 </div>
 <div class="flex justify-between border-b border-slate-50 py-2">
 <span class="text-slate-500">تنبيهات أكاديمية:</span>
 <span class="text-primary font-bold whitespace-nowrap">لا يوجد موانع تسجيل</span>
 </div>
 </div>
 </div>
 
 <p class="text-[10px] text-slate-400 italic mb-6">أقر أنا الطالب المذكور أعلاه بصحة البيانات الواردة في هذه الاستمارة تحت مسئوليتي الشخصية.</p>

 <?php endif; ?>

 <!-- Footer / Signature -->
 <div class="absolute bottom-44 left-12 right-12 flex justify-between items-center bg-bg p-4 rounded-2xl border border-slate-100">
 <div class="text-center w-1/3">
 <p class="text-[10px] text-slate-400 mb-6">يعتمد / مدير الشؤون</p>
 <div class="h-8 border-b border-slate-300 w-32 mx-auto"></div>
 </div>
 <div class="text-center w-1/3">
 <!-- Watermark Logo Placeholder -->
 <i class="fas fa-certificate text-4xl text-secondary"></i>
 </div>
 <div class="text-center w-1/3">
 <p class="text-[10px] text-slate-400 mb-6">يعتمد / عميد الكلية</p>
 <div class="h-8 border-b border-slate-300 w-32 mx-auto"></div>
 </div>
 </div>

 <!-- Final Note -->
 <p class="absolute bottom-24 left-12 right-12 text-center text-slate-400 text-[10px] flex items-center justify-center gap-2 opacity-50">
 <i class="fas fa-shield-alt"></i>
 <span>هذا المستند صادر إلكترونياً ولا يعتد به بدون ختم الجامعة الرسمي.</span>
 </p>

 </div>

</div>

<style>
@page {
 size: A4;
 margin: 0;
}
@media print {
 html, body {
 height: 297mm;
 width: 210mm;
 margin: 0 !important;
 padding: 0 !important;
 overflow: hidden;
 }
 .print\:hidden { display: none !important; }
 #printableArea {
 border: none !important;
 box-shadow: none !important;
 padding: 0.8cm !important;
 height: 275mm !important; /* Reduced to leave safe room at bottom */
 max-height: 275mm !important;
 width: 100% !important;
 margin: 0 !important;
 overflow: hidden;
 position: relative;
 box-sizing: border-box !important;
 }
 .text-slate-500 { color: #64748b !important; }
 
 /* Ensure colors are printed */
 * {
 -webkit-print-color-adjust: exact !important;
 print-color-adjust: exact !important;
 }
}

#printableArea {
 width: 210mm;
 min-height: 297mm;
 margin: 0 auto;
}

#printableArea::before {
 content: "EDU NEXUS UNIVERSITY";
 position: absolute;
 top: 50%;
 left: 50%;
 transform: translate(-50%, -50%) rotate(-45deg);
 font-size: 5rem;
 color: rgba(226, 232, 240, 0.2);
 z-index: 0;
 pointer-events: none;
 white-space: nowrap;
 font-weight: 900;
}
</style>

<?php require_once 'includes/footer.php'; ?>

