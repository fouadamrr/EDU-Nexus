<?php
require_once 'includes/header.php';

// Only admin, affairs, and super_admin can access
if (!in_array($role, ['super_admin', 'admin', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$message = '';

$student_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($student_id <= 0) {
    $search_term = $_GET['q'] ?? '';
    $results = [];
    
    if ($search_term !== '') {
        $q_str = "SELECT id, username, full_name, college_id FROM users WHERE role = 'student' AND (full_name ILIKE :q OR username ILIKE :q)";
        $params = [':q' => "%$search_term%"];
        
        $filter_college = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? 0) : 0;
        if ($filter_college > 0) {
            $q_str .= " AND college_id = :cid";
            $params[':cid'] = $filter_college;
        }
        $q_str .= " LIMIT 20";
        
        $stmt = $pdo->prepare($q_str);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    ?>
    <div class="max-w-4xl mx-auto animate-fade-in-up md:mt-12">
        <div class="bg-white p-8 md:p-12 rounded-[3rem] shadow-sm border border-slate-100 mb-8 relative overflow-hidden group">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:scale-110 transition-transform"></div>
            <div class="relative z-10">
                <h2 class="text-3xl font-black text-slate-800 mb-8 flex items-center gap-4">
                    <div class="w-14 h-14 bg-primary/10 rounded-2xl flex items-center justify-center text-primary shadow-inner">
                        <i class="fas fa-search"></i>
                    </div>
                    البحث عن سجل أكاديمي
                </h2>
                <form method="GET" action="admin_student_academic.php" class="relative group/form">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>">
                    <div class="relative">
                        <input type="text" name="q" value="<?= htmlspecialchars($search_term) ?>" 
                               placeholder="أدخل اسم الطالب أو الرقم الجامعي..." 
                               class="w-full bg-slate-50 border border-slate-200 rounded-[2rem] p-6 pr-16 text-xl font-bold focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all shadow-inner">
                        <i class="fas fa-user-graduate absolute right-6 top-1/2 -translate-y-1/2 text-slate-300 text-2xl group-focus-within/form:text-primary transition-colors"></i>
                    </div>
                    <button type="submit" class="mt-6 w-full md:w-auto md:absolute md:left-4 md:top-1/2 md:-translate-y-1/2 bg-primary text-white font-black px-10 py-4 rounded-2xl shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
                        بدء البحث
                    </button>
                </form>
                <p class="text-sm text-slate-400 mt-6 text-center font-medium italic">أو اختر الطالب مباشرة من <a href="manage_users.php?role=student" class="text-primary font-bold hover:underline">قائمة المستخدمين</a></p>
            </div>
        </div>

        <?php if ($search_term !== ''): ?>
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden animate-fade-in-up">
            <div class="p-6 bg-slate-50/50 border-b border-slate-100 font-black text-slate-500 text-xs uppercase tracking-widest px-8">نتائج البحث المباشر</div>
            <div class="divide-y divide-slate-50">
                <?php if (empty($results)): ?>
                <div class="p-16 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                        <i class="fas fa-user-slash text-3xl"></i>
                    </div>
                    <p class="text-slate-400 font-bold">عذراً، لم يتم العثور على نتائج مطابقة لـ "<?php echo htmlspecialchars($search_term); ?>"</p>
                </div>
                <?php else: ?>
                <?php foreach ($results as $res): ?>
                <div class="flex items-center justify-between p-6 hover:bg-primary/5 transition-all group px-8">
                    <div class="flex items-center gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 text-primary flex items-center justify-center font-black text-lg shadow-sm group-hover:scale-110 transition-transform">
                            <?= mb_substr($res['full_name'], 0, 1, 'UTF-8') ?>
                        </div>
                        <div class="flex flex-col">
                            <h4 class="font-black text-slate-800 text-lg tracking-tight"><?= htmlspecialchars($res['full_name']) ?></h4>
                            <span class="text-xs text-slate-400 font-mono tracking-wider"><i class="fas fa-id-card ml-2 opacity-50"></i><?= htmlspecialchars($res['username']) ?></span>
                        </div>
                    </div>
                    <a href="admin_student_academic.php?id=<?= $res['id'] ?>&tab=<?= htmlspecialchars($_GET['tab'] ?? 'registration') ?>" class="bg-white border border-slate-200 text-slate-600 hover:bg-primary hover:text-white hover:border-primary px-6 py-2.5 rounded-xl font-black text-sm shadow-sm transition-all flex items-center gap-2">
                        <i class="fas fa-folder-open text-xs"></i>
                        عرض الملف
                    </a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

// Find student directly from PostgreSQL
function findStudentAcrossColleges($student_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $studentCollegeId = isset($student['college_id']) ? (int)$student['college_id'] : null;
        $studentCollegeId = $studentCollegeId ?: 0;
        return [$student, new UniversityDB($studentCollegeId)];
    }
    return [null, null];
}

/** @var UniversityDB $s_db */
list($student, $s_db) = findStudentAcrossColleges($student_id);

if (!$student) {
    echo '<div class="max-w-4xl mx-auto bg-white p-12 rounded-[2.5rem] shadow-sm text-center mt-20 border border-slate-100">
            <div class="w-20 h-20 bg-rose-50 rounded-full flex items-center justify-center mx-auto mb-6 text-rose-500">
                <i class="fas fa-exclamation-triangle text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800 mb-2">الطالب غير موجود</h3>
            <p class="text-slate-500 mb-8 font-medium">عذراً، هذا الحساب غير مسجل كطالب في قاعدة البيانات الحالية.</p>
            <a href="manage_users.php?role=student" class="bg-primary text-white px-8 py-3 rounded-xl font-black shadow-lg shadow-primary/20 active:scale-95 transition-all">العودة للبحث</a>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

if (in_array($role, ['dean', 'affairs'])) {
    $cid = (int)($_SESSION['college_id'] ?? 0);
    if (($student['college_id'] ?? 0) != $cid) {
        echo '<div class="max-w-4xl mx-auto bg-white p-12 rounded-[2.5rem] shadow-sm text-center mt-20 border border-slate-100">
                <div class="w-20 h-20 bg-rose-50 rounded-full flex items-center justify-center mx-auto mb-6 text-rose-500">
                    <i class="fas fa-lock text-3xl"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-2">غير مصرح بالدخول</h3>
                <p class="text-slate-500 mb-8 font-medium">لا تملك صلاحية عرض السجلات الأكاديمية لطلاب من كليات أخرى.</p>
                <a href="manage_users.php?role=student" class="bg-primary text-white px-8 py-3 rounded-xl font-black shadow-lg shadow-primary/20">العودة للبحث</a>
              </div>';
        require_once 'includes/footer.php';
        exit;
    }
}

// Fetch extended details
$details = $s_db->find('student_details', 'user_id', $student_id);
$major = $details['major'] ?? 'عام / غير محدد';
$level = isset($details['level']) ? (int) $details['level'] : null;
$gpa = $details['gpa'] ?? null;

// Determine semester we are registering for (align with current app state)
$semester = 'Spring 2026';

// Fetch all courses specifically for the student's college
$q_courses = "SELECT * FROM courses WHERE college_id = :cid ORDER BY code ASC";
$stmtC = $pdo->prepare($q_courses);
$stmtC->execute([':cid' => (int)($student['college_id'] ?? 0)]);
$available_courses = $stmtC->fetchAll(PDO::FETCH_ASSOC);

// Current enrollments for this semester
$q_enroll = "SELECT course_id FROM enrollments WHERE user_id = :uid AND semester = :sem";
$stmtE = $pdo->prepare($q_enroll);
$stmtE->execute([':uid' => $student_id, ':sem' => $semester]);
$current_course_ids = $stmtE->fetchAll(PDO::FETCH_COLUMN);
$current_course_ids = array_map('intval', $current_course_ids);

// Handle save enrollments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_enrollments') {
    $course_ids = $_POST['course_ids'] ?? [];
    if (!is_array($course_ids)) {
        $course_ids = [];
    }
    $course_ids = array_values(array_unique(array_map('intval', $course_ids)));
    $course_ids = array_filter($course_ids, function ($id) {
        return $id > 0;
    });

    if (count($course_ids) === 0) {
        $message = '<div class="bg-rose-50 text-rose-600 p-4 rounded-xl font-black mb-6 border border-rose-100 flex items-center gap-3 animate-shake"><i class="fas fa-exclamation-circle"></i> يرجى اختيار مادة واحدة على الأقل قبل الحفظ.</div>';
    } else {
        $valid_map = [];
        foreach ($available_courses as $c) {
            if (isset($c['id'])) { $valid_map[(int) $c['id']] = true; }
        }
        $valid_course_ids = array_values(array_filter($course_ids, function ($id) use ($valid_map) {
            return isset($valid_map[$id]);
        }));

        if (count($valid_course_ids) === 0) {
            $message = '<div class="bg-rose-50 text-rose-600 p-4 rounded-xl font-black mb-6 border border-rose-100 flex items-center gap-3"><i class="fas fa-times-circle"></i> المقررات المختارة غير صالحة.</div>';
        } else {
            $s_db->deleteWhere('enrollments', ['user_id' => $student_id, 'semester' => $semester]);
            foreach ($valid_course_ids as $cid) {
                $s_db->insert('enrollments', [
                    'user_id' => $student_id,
                    'course_id' => $cid,
                    'semester' => $semester,
                    'status' => 'active'
                ]);
            }
            $current_course_ids = $valid_course_ids;
            $message = '<div class="bg-emerald-50 text-emerald-600 p-4 rounded-xl font-black mb-6 border border-emerald-100 flex items-center gap-3 animate-fade-in-up shadow-sm"><i class="fas fa-check-circle"></i> تمت عملية تحديث المقررات المسجلة بنجاح.</div>';
        }
    }
}

// Build results
$enrollments_for_results = $s_db->findAll('enrollments', ['user_id' => $student_id]);
$all_grades_for_results = $s_db->findAll('grades', ['user_id' => $student_id]);

$grades_index_res = [];
foreach ($all_grades_for_results as $g) {
    $key = ($g['course_id'] ?? '') . '|' . ($g['semester'] ?? '');
    $grades_index_res[$key] = $g;
}

$semesters = [];
if (!empty($enrollments_for_results)) {
    foreach ($enrollments_for_results as $en) {
        $cid = $en['course_id'] ?? null;
        if (!$cid) continue;
        $course = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $course->execute([$cid]);
        $c_data = $course->fetch(PDO::FETCH_ASSOC);
        if (!$c_data) continue;
        $sem = $en['semester'] ?? 'غير محدد';
        $key = $cid . '|' . $sem;
        $g = $grades_index_res[$key] ?? null;

        $semesters[$sem][] = [
            'code' => $c_data['code'] ?? '',
            'name' => $c_data['name'] ?? '',
            'credit_hours' => $c_data['credit_hours'] ?? 0,
            'grade' => $g['grade'] ?? '—',
            'points' => $g['points'] ?? '0.00'
        ];
    }
}
?>

<div class="space-y-8 animate-fade-in-up max-w-7xl mx-auto mb-12">

    <!-- Premium Profile Header -->
    <div class="bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-[3rem] p-8 md:p-12 text-white shadow-2xl relative overflow-hidden group border border-white/10">
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="flex items-center gap-6">
                <div class="w-24 h-24 rounded-[2rem] bg-white/20 backdrop-blur-xl border border-white/30 flex items-center justify-center text-5xl font-black text-white shadow-2xl">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h1 class="text-3xl md:text-4xl font-black mb-3"><?php echo htmlspecialchars($student['full_name']); ?></h1>
                    <div class="flex flex-wrap items-center gap-4 text-white/80 text-sm font-bold">
                        <span class="bg-indigo-900/30 px-3 py-1 rounded-lg border border-white/10 font-mono tracking-widest"><i class="fas fa-id-badge ml-2 opacity-50"></i><?php echo htmlspecialchars($student['username']); ?></span>
                        <span class="opacity-80"><i class="fas fa-university ml-2 opacity-50"></i><?php echo htmlspecialchars($student['college_name'] ?? 'جامعة EDU Nexus'); ?></span>
                        <span class="bg-emerald-400/20 text-emerald-100 px-4 py-1 rounded-lg border border-emerald-400/20"><i class="fas fa-layer-group ml-2"></i>المستوى: <?php echo $level ?: '1'; ?></span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-center md:items-end gap-3 min-w-[200px]">
                <div class="text-center p-6 bg-white/10 backdrop-blur-md rounded-[1.5rem] border border-white/20 shadow-xl w-full">
                    <div class="text-[10px] font-black text-white/50 uppercase tracking-widest mb-1">المعدل التراكمي (GPA)</div>
                    <div class="text-4xl font-black text-white"><?php echo $gpa ? number_format((float) $gpa, 2) : '0.00'; ?></div>
                </div>
                <a href="manage_users.php?role=student" class="text-white/60 hover:text-white transition-colors text-xs font-black flex items-center gap-2">
                    <i class="fas fa-arrow-right"></i> قفز لجميع الطلاب
                </a>
            </div>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Courses Registration Column -->
        <div class="lg:col-span-2 space-y-8 no-print">
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-8 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-black text-slate-800 text-lg flex items-center gap-3">
                        <i class="fas fa-edit text-primary p-2 bg-primary/10 rounded-xl"></i> 
                        تسجيل المقررات (<?php echo htmlspecialchars($semester); ?>)
                    </h3>
                </div>
                <form method="POST" class="p-8 space-y-6">
                    <input type="hidden" name="action" value="save_enrollments">
                    <div class="overflow-x-auto rounded-[1.5rem] border border-slate-100">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-slate-50 text-slate-500 font-black px-6">
                                <tr>
                                    <th class="px-6 py-4">تحديد</th>
                                    <th class="px-6 py-4">الكود</th>
                                    <th class="px-6 py-4">اسم المقرر</th>
                                    <th class="px-6 py-4 text-center">الساعات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($available_courses as $c): ?>
                                <tr class="hover:bg-primary/5 transition-all group">
                                    <td class="px-6 py-4 text-center">
                                        <label class="relative flex items-center justify-center cursor-pointer">
                                            <input type="checkbox" name="course_ids[]" value="<?php echo (int)$c['id']; ?>" <?php echo in_array((int)($c['id']), $current_course_ids) ? 'checked' : ''; ?>
                                                   class="w-6 h-6 rounded-lg border-2 border-slate-200 text-primary focus:ring-primary/20 appearance-none checked:bg-primary checked:border-primary transition-all">
                                            <i class="fas fa-check absolute text-white text-[10px] scale-0 checked-icon"></i>
                                        </label>
                                    </td>
                                    <td class="px-6 py-4 font-black text-slate-700 font-mono"><?php echo $c['code']; ?></td>
                                    <td class="px-6 py-4 font-bold text-slate-800"><?php echo $c['name']; ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-500 font-black text-xs"><?php echo $c['credit_hours']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-primary text-white px-10 py-4 rounded-2xl font-black shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all flex items-center gap-3">
                            <i class="fas fa-save shadow-sm"></i> حفظ استمارة التسجيل
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Academic Performance / Results Column -->
        <div class="lg:col-span-1 space-y-8">
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden h-fit sticky top-24">
                <div class="p-8 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-black text-slate-800 text-lg flex items-center gap-3">
                        <i class="fas fa-certificate text-amber-500 p-2 bg-amber-50 rounded-xl"></i>
                        النتائج الرسمية
                    </h3>
                    <button onclick="window.print()" class="no-print bg-slate-50 text-slate-500 hover:bg-slate-800 hover:text-white px-4 py-2 rounded-xl text-[10px] font-black transition-all border border-slate-100 lg:flex hidden items-center gap-1">
                        <i class="fas fa-print"></i> طباعة كشف
                    </button>
                </div>
                <div class="p-4 space-y-4">
                    <?php if (empty($semesters)): ?>
                    <div class="text-center py-16 text-slate-300 font-black italic text-xs">لا يوجد رصد دراسي حالي</div>
                    <?php else: ?>
                    <?php foreach ($semesters as $sem => $courses): ?>
                    <div class="border border-slate-100 rounded-[2rem] overflow-hidden group transition-all hover:border-primary/20">
                        <div class="bg-slate-50/80 p-4 border-b border-slate-100 flex justify-between items-center group-hover:bg-primary/5">
                            <span class="font-black text-slate-700 text-xs"><?php echo htmlspecialchars($sem); ?></span>
                            <?php 
                                $s_points = 0.0; $s_hrs = 0;
                                foreach($courses as $cs) { $s_points += (float)$cs['points'] * (int)$cs['credit_hours']; $s_hrs += (int)$cs['credit_hours']; }
                                $s_gpa = $s_hrs > 0 ? round($s_points / $s_hrs, 2) : 0.00;
                            ?>
                            <span class="text-[10px] font-black bg-indigo-600 text-white px-3 py-1 rounded-full shadow-sm">GPA: <?php echo $s_gpa; ?></span>
                        </div>
                        <div class="p-4 space-y-3">
                            <?php foreach ($courses as $cs): ?>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-black text-slate-600 truncate max-w-[140px]" title="<?php echo $cs['name']; ?>"><?php echo $cs['name']; ?></span>
                                <span class="font-black px-2 py-0.5 rounded-lg border <?php echo (($cs['grade']??'')==='F') ? 'bg-rose-50 text-rose-500 border-rose-100':'bg-emerald-50 text-emerald-600 border-emerald-100'; ?>"><?php echo $cs['grade']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    // Minimal logic for the checkbox icon
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', function() {
            const icon = this.nextElementSibling;
            if (this.checked) icon.classList.add('scale-100'); else icon.classList.remove('scale-100');
        });
        // Initial state
        if (cb.checked) cb.nextElementSibling.classList.add('scale-100');
    });
</script>

<style>
.animate-shake { animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both; transform: translate3d(0, 0, 0); }
@keyframes shake { 10%, 90% { transform: translate3d(-1px, 0, 0); } 20%, 80% { transform: translate3d(2px, 0, 0); } 30%, 50%, 70% { transform: translate3d(-4px, 0, 0); } 40%, 60% { transform: translate3d(4px, 0, 0); } }
.checked-icon { transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@page { margin: 2cm; }
@media print {
    body { background: white !important; }
    .no-print { display: none !important; }
    .rounded-\[3rem\], .rounded-\[2\.5rem\], .rounded-\[1\.5rem\], .rounded-3xl { border-radius: 0.5rem !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
