<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin', 'dean', 'affairs', 'student'])) {
    header('Location: index.php');
    exit;
}
if ($_SESSION['role'] === 'student' && !isset($_GET['student_id'])) {
    $doc = $_GET['doc_type'] ?? 'enrollment_proof';
    header('Location: ?doc_type=' . urlencode($doc) . '&student_id=' . intval($_SESSION['user_id']));
    exit;
}

require_once 'includes/header.php';
/** @var PDO $pdo */
/** @var string $role */
/** @var UniversityDB $db */

// المسموح لهم بدخول الصفحة دي: الإدارة (أدمن، عميد، شؤون) والطالب نفسه
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs', 'student'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}

// بنجيب لستة الكليات عشان نحتاج أسماءها في الوثائق
$colleges_list = $db->findAll('colleges');
$colleges_map = [];
foreach ($colleges_list as $c) {
 $colleges_map[$c['id']] = $c['name'];
}

// أنواع الوثائق والمستندات اللي السيستم بيقدر يطلعها
$doc_types = [
 'enrollment_proof'      => 'إثبات قيد',
 'status_statement'      => 'بيان حالة',
 'clearance'             => 'إخلاء طرف',
 'good_conduct'          => 'حسن سير وسلوك',
 'graduation_certificate'=> 'شهادة التخرج',
];

// بنجيب بيانات الطالب اللي عليه الدور في استخراج الشهادة
$selected_student = null;
$student_details = null;
$selected_doc = $_GET['doc_type'] ?? 'enrollment_proof';
$student_grades = [];
$enrolled_courses = [];

if ($role === 'student') {
 // Students can only generate their own documents
 // Auto-redirect to self-document URL so preview renders immediately
 // Redirect is now handled at the top of the file.
 $selected_student = $db->find('users', 'id', $user_id);
 $student_details = $db->find('student_details', 'user_id', $user_id);
 $student_grades = $db->findAll('grades', ['user_id' => $user_id]);
} elseif (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
 $sid = (int)$_GET['student_id'];
 $selected_student = $db->find('users', 'id', $sid);
 if ($selected_student && $selected_student['role'] === 'student') {
 if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id']) && $selected_student['college_id'] != $_SESSION['college_id']) {
 $selected_student = null;
 } else {
 $student_details = $db->find('student_details', 'user_id', $sid);
 $student_grades = $db->findAll('grades', ['user_id' => $sid]);
 }
 } else {
 $selected_student = null;
 }
}

// Build enrolled courses with names
if (!empty($student_grades)) {
 $all_courses = $db->findAll('courses');
 $courses_map = [];
 foreach ($all_courses as $c) { $courses_map[$c['id']] = $c; }
 foreach ($student_grades as $g) {
 if (isset($courses_map[$g['course_id']])) {
 $enrolled_courses[] = array_merge($g, $courses_map[$g['course_id']]);
 }
 }
}

// لو أدمن، بنجيب له لستة الطلبة عشان يقدر يبحث عنهم ويطلع لهم شهادات
$students_search_query = $_GET['search'] ?? '';
$students_list = [];
if (in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 $filters = ['role' => 'student'];
 if (in_array($role, ['dean', 'affairs']) && isset($_SESSION['college_id'])) {
 $filters['college_id'] = $_SESSION['college_id'];
 }
 $all_students = $db->findAll('users', $filters);
 if (!empty($students_search_query)) {
 foreach ($all_students as $s) {
 if (
 mb_stripos($s['full_name'] ?? '', $students_search_query) !== false ||
 mb_stripos($s['username'] ?? '', $students_search_query) !== false
 ) {
 $students_list[] = $s;
 }
 }
 } else {
 $students_list = $all_students;
 }
}

// University info helpers
$university_name = $db->getSetting('university_name', null) ?? $db->getSetting('system_name', null) ?? 'EDU Nexus';

$college_name = '';
if ($selected_student && isset($selected_student['college_id']) && isset($colleges_map[$selected_student['college_id']])) {
 $college_name = $colleges_map[$selected_student['college_id']];
}

$level_map = [1 => 'الأول', 2 => 'الثاني', 3 => 'الثالث', 4 => 'الرابع'];
$student_level = isset($student_details['level']) ? ($level_map[$student_details['level']] ?? $student_details['level']) : 'غير محدد';
$student_major = $student_details['major'] ?? 'غير محدد';
$student_gpa = $student_details['gpa'] ?? '0.00';
$enrollment_year = $student_details['enrollment_year'] ?? date('Y');
$national_id = $student_details['national_id'] ?? 'غير مسجل';
$today = date('d / m / Y');

$doc_serial = $selected_student
 ? strtoupper(substr(md5($selected_student['id'] . $selected_doc . date('Y')), 0, 8))
 : '';
?>

<?php if ($selected_student): ?>
<!-- Print handled by JS popup — no @media print CSS needed here -->
<?php endif; ?>

<!-- عرض واجهة الصفحة وشكلها الأساسي -->
<div class="max-w-7xl mx-auto space-y-6 no-print">

 <!-- Page Header -->
 <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
 <div class="flex items-center gap-4">
 <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center text-primary">
 <i class="fas fa-file-stamp text-xl"></i>
 </div>
 <div>
 <h2 class="text-2xl font-bold text-slate-800">استخراج الوثائق الرسمية</h2>
 <p class="text-sm text-slate-500 mt-0.5">إصدار الشهادات والمستندات الرسمية للطلاب</p>
 </div>
 </div>
 </div>

 <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

 <!-- الجزء الخاص بالبحث والاختيارت -->
 <div class="lg:col-span-1 space-y-5">

 <!-- منيو اختيار نوع المستند -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
 <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
 <i class="fas fa-scroll text-primary"></i> نوع الوثيقة
 </h3>
 <div class="space-y-2">
  <?php foreach ($doc_types as $key => $label):
  $is_active = ($selected_doc === $key);
  // شهادة التخرج لها صفحة خاصة للطلب
  if ($key === 'graduation_certificate' && $role === 'student') {
    ?>
    <a href="graduation_certificate.php"
     class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 border-indigo-200 bg-indigo-50 text-indigo-700 font-bold hover:bg-indigo-100">
     <i class="fas fa-graduation-cap w-5 text-center text-indigo-500"></i>
     <?php echo $label; ?>
     <span class="mr-auto text-xs bg-indigo-600 text-white px-2 py-0.5 rounded-full">طلب</span>
    </a>
    <?php continue;
  }
  $href = '?doc_type=' . $key . (isset($_GET['student_id']) ? '&student_id='.$_GET['student_id'] : '') . (!empty($students_search_query) ? '&search='.urlencode($students_search_query) : '');
  $icons_map = ['enrollment_proof'=>'id-card','status_statement'=>'file-medical-alt','clearance'=>'check-double','good_conduct'=>'award','graduation_certificate'=>'graduation-cap'];
  ?>
  <a href="<?php echo $href; ?>"
   class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 <?php echo $is_active ? 'bg-bg border-primary text-primary font-bold' : 'border-slate-100 text-slate-600 hover:border-primary hover:bg-bg/50'; ?>">
  <i class="fas fa-<?php echo $icons_map[$key] ?? 'file'; ?> w-5 text-center <?php echo $is_active ? 'text-primary' : 'text-slate-400'; ?>"></i>
  <?php echo $label; ?>
  <?php if ($is_active): ?><i class="fas fa-chevron-left mr-auto text-primary text-xs"></i><?php endif; ?>
  </a>
  <?php endforeach; ?>
 </div>
 </div>

 <!-- منيو اختيار الطالب (دي بتظهر للموظفين بس) -->
 <?php if (in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])): ?>
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
 <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
 <i class="fas fa-user-graduate text-primary"></i> اختيار الطالب
 </h3>
 <form method="GET" class="space-y-3" id="studentSearchForm" onsubmit="return false;">
 <input type="hidden" name="doc_type" id="doc_type_input" value="<?php echo htmlspecialchars($selected_doc); ?>">
 <div class="relative">
 <input type="text" name="search" id="liveSearchInput" value="<?php echo htmlspecialchars($students_search_query); ?>"
 placeholder="���� ������ �� �����..." autocomplete="off"
 class="w-full border border-slate-200 rounded-xl px-4 py-2.5 pl-10 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-primary bg-bg transition-shadow">
 <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
 </div>
 </form>

 <div id="liveSearchResults" class="mt-4 space-y-1.5 max-h-64 overflow-y-auto hidden">
 </div>
 <p id="noResultsMsg" class="text-slate-400 text-sm text-center mt-4 py-4 hidden">�� ���� �����</p>

 </div>
 <?php endif; ?>

 </div>

 <!-- الجزء الخاص بالمعاينة والطباعة -->
 <div class="lg:col-span-2">
 <?php if ($selected_student): ?>
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
 <!-- رأس المعاينة -->
 <div class="bg-bg px-6 py-4 flex items-center justify-between">
 <div class="text-white">
 <p class="text-sm font-medium text-primary">معاينة الوثيقة</p>
 <h3 class="font-bold text-lg"><?php echo $doc_types[$selected_doc] ?? 'وثيقة'; ?></h3>
 </div>
 <button onclick="printDoc()" class="bg-white text-primary hover:bg-bg font-bold px-5 py-2 rounded-xl transition-colors flex items-center gap-2 text-sm shadow-md">
 <i class="fas fa-print"></i> طباعة
 </button>
 </div>

 <!-- محتوى الوثيقة الفعلي -->
 <div class="p-6" id="doc-preview">
 <?php include __DIR__ . '/includes/doc_templates.php'; ?>
 </div>
 </div>
 <?php else: ?>
 <!-- لما لسه ميكنش فيه طالب مختارينه -->
 <div class="bg-white rounded-2xl shadow-sm border border-slate-100 h-full flex flex-col items-center justify-center py-24 px-6 text-center">
 <div class="w-20 h-20 bg-bg rounded-full flex items-center justify-center mb-5">
 <i class="fas fa-file-signature text-4xl text-slate-300"></i>
 </div>
 <h3 class="text-xl font-bold text-slate-600 mb-2">اختر نوع الوثيقة والطالب</h3>
 <p class="text-slate-400 text-sm max-w-xs">
 <?php if ($role === 'student'): ?>
 سيتم عرض الوثيقة الخاصة بك هنا للمعاينة والطباعة.
 <?php else: ?>
 ابحث عن الطالب المراد إصدار الوثيقة له من القائمة على اليسار.
 <?php endif; ?>
 </p>
 <?php if ($role === 'student'):
 $self_doc_link = '?doc_type=' . htmlspecialchars($selected_doc) . '&student_id=' . intval($user_id);
 ?>
 <a href="<?php echo $self_doc_link; ?>"
 class="mt-6 bg-primary text-white px-6 py-2.5 rounded-xl font-bold hover:bg-primary transition-colors">
 <i class="fas fa-eye mr-2"></i> عرض وثيقتي
 </a>
 <?php endif; ?>
 </div>
 <?php endif; ?>
 </div>
 </div>
</div>

<?php if ($selected_student): ?>
<!-- Hidden div used as source for the print popup -->
<div id="doc-print-area" style="display:none;">
 <?php include __DIR__ . '/includes/doc_templates.php'; ?>
</div>

<script>
function printDoc() {
 var content = document.getElementById('doc-print-area');
 if (!content) { window.print(); return; }

 // Collect all doc-specific stylesheets embedded in the page
 var styles = '';
 document.querySelectorAll('style').forEach(function(s) {
 styles += s.outerHTML;
 });
 // Add Google Fonts + Font Awesome
 styles += '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap">';
 styles += '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">';

 var win = window.open('', '_blank', 'width=900,height=700');
 win.document.write('<html dir="rtl" lang="ar"><head>');
 win.document.write('<meta charset="UTF-8">');
 win.document.write('<title><?php echo htmlspecialchars($doc_types[$selected_doc] ?? "وثيقة", ENT_QUOTES); ?></title>');
 win.document.write(styles);
 win.document.write('<style>body{font-family:"Cairo",Arial,sans-serif;margin:0;padding:20px;background:#fff;}</style>');
 win.document.write('</head><body>');
 win.document.write(content.innerHTML);
 win.document.write('</body></html>');
 win.document.close();
 // Wait briefly for fonts/images to load, then print
 win.onload = function() { win.focus(); win.print(); };
 setTimeout(function() { win.focus(); win.print(); }, 800);
}
</script>
<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearchInput');
    const resultsContainer = document.getElementById('liveSearchResults');
    const noResultsMsg = document.getElementById('noResultsMsg');
    const docType = document.getElementById('doc_type_input') ? document.getElementById('doc_type_input').value : 'enrollment_proof';
    
    let debounceTimer;

    if (searchInput && searchInput.value.trim() !== '') {
        performSearch(searchInput.value);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const q = this.value.trim();
            
            if (q.length === 0) {
                resultsContainer.innerHTML = '';
                resultsContainer.classList.add('hidden');
                noResultsMsg.classList.add('hidden');
                return;
            }
            
            debounceTimer = setTimeout(() => {
                performSearch(q);
            }, 300);
        });
    }

    function performSearch(query) {
        fetch('ajax_search_students.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(res => {
                resultsContainer.innerHTML = '';
                
                if (res.status === 'success' && res.data && res.data.length > 0) {
                    noResultsMsg.classList.add('hidden');
                    resultsContainer.classList.remove('hidden');
                    
                    res.data.forEach(student => {
                        const initial = student.full_name ? student.full_name.charAt(0) : '?';
                        const link = '?doc_type=' + encodeURIComponent(docType) + '&student_id=' + encodeURIComponent(student.id) + '&search=' + encodeURIComponent(query);
                        
                        const a = document.createElement('a');
                        a.href = link;
                        a.className = 'flex items-center gap-3 p-2.5 rounded-xl border border-slate-100 hover:bg-bg transition-all text-sm';
                        
                        a.innerHTML = `
                            <div class="w-8 h-8 rounded-full bg-bg flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                ${initial}
                            </div>
                            <div class="overflow-hidden">
                                <div class="truncate text-slate-700">${student.full_name}</div>
                                <div class="text-xs text-slate-400 font-mono">${student.username}</div>
                            </div>
                        `;
                        resultsContainer.appendChild(a);
                    });
                } else {
                    resultsContainer.classList.add('hidden');
                    noResultsMsg.classList.remove('hidden');
                }
            })
            .catch(err => console.error('Search error:', err));
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
