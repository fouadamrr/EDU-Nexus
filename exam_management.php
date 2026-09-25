<?php
require_once 'includes/header.php';
/** @var PDO $pdo */

if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
require_permission('exams');

// Auto-create exam tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_committees (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location VARCHAR(255),
        capacity INT NOT NULL DEFAULT 30,
        current_count INT DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_distributions (
        id SERIAL PRIMARY KEY,
        student_id INT NOT NULL UNIQUE,
        committee_id INT NOT NULL,
        seat_number VARCHAR(50) NOT NULL UNIQUE,
        exam_number VARCHAR(20),
        FOREIGN KEY (committee_id) REFERENCES exam_committees(id) ON DELETE CASCADE
    )");
    // Ensure existing table is updated
    try { $pdo->exec("ALTER TABLE exam_distributions ALTER COLUMN seat_number TYPE VARCHAR(50)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE exam_distributions ADD CONSTRAINT unique_seat_number UNIQUE (seat_number)"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE exam_distributions ADD COLUMN IF NOT EXISTS exam_number VARCHAR(20)"); } catch(Exception $e){}
} catch (Exception $e) {}

// ── College & role scope ─────────────────────────────────────────────
$college_scope = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? null) : null;
$colleges_list = $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$levels_all = $pdo->query("SELECT DISTINCT level::TEXT FROM students WHERE level IS NOT NULL ORDER BY level")->fetchAll(PDO::FETCH_COLUMN);

$action = $_GET['action'] ?? 'view';
$success_msg = '';
$error_msg = '';

// POST: Add Committee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_committee'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $capacity = (int)$_POST['capacity'];

    if ($capacity > 0 && !empty($name)) {
        try {
            $pdo->prepare("INSERT INTO exam_committees (name, location, capacity) VALUES (?, ?, ?)")
                ->execute([$name, $location, $capacity]);
            $success_msg = "تمت إضافة اللجنة بنجاح.";
        } catch (Exception $e) {
            $error_msg = "حدث خطأ: " . $e->getMessage();
        }
    } else {
        $error_msg = "يرجى تعبئة جميع الحقول المطلوبة.";
    }
}

// POST: Update Committee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_committee'])) {
    $id = (int)$_POST['committee_id'];
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $capacity = (int)$_POST['capacity'];

    if ($id > 0 && $capacity > 0 && !empty($name)) {
        try {
            // Check current count to avoid setting capacity too low
            $c_stmt = $pdo->prepare("SELECT current_count FROM exam_committees WHERE id = ?");
            $c_stmt->execute([$id]);
            $current_count = (int)$c_stmt->fetchColumn();

            if ($capacity < $current_count) {
                $error_msg = "لا يمكن تقليل السعة لأقل من عدد الطلاب الحالي الموزَّعين ({$current_count}).";
            } else {
                $pdo->prepare("UPDATE exam_committees SET name = ?, location = ?, capacity = ? WHERE id = ?")
                    ->execute([$name, $location, $capacity, $id]);
                $success_msg = "تم تحديث بيانات اللجنة بنجاح.";
            }
        } catch (Exception $e) {
            $error_msg = "حدث خطأ أثناء التحديث: " . $e->getMessage();
        }
    } else {
        $error_msg = "يرجى التأكد من صحة جميع البيانات.";
    }
}

// POST: Auto Distribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_distribute'])) {
    try {
        $dist_college = $college_scope ?? (isset($_POST['dist_college']) && $_POST['dist_college'] !== '' ? (int)$_POST['dist_college'] : null);
        $dist_level = isset($_POST['dist_level']) && $_POST['dist_level'] !== '' ? trim($_POST['dist_level']) : null;

        $q = "SELECT u.id, u.username FROM users u LEFT JOIN students s ON u.id = s.user_id WHERE u.role = 'student' AND u.id NOT IN (SELECT student_id FROM exam_distributions)";
        $q_params = [];
        if ($dist_college) { $q .= " AND s.college_id = ?"; $q_params[] = $dist_college; }
        if ($dist_level) { $q .= " AND s.level = ?"; $q_params[] = $dist_level; }
        $q .= " ORDER BY u.id";

        $stmt_unassigned = $pdo->prepare($q);
        $stmt_unassigned->execute($q_params);
        $unassigned_students = $stmt_unassigned->fetchAll(PDO::FETCH_ASSOC);

        $stmt_c = $pdo->query("SELECT id, capacity, current_count FROM exam_committees WHERE current_count < capacity ORDER BY id");
        $committees = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

        if (empty($committees)) {
            $error_msg = "لا توجد لجان متاحة أو بها سعة كافية. يرجى إضافة لجان أولاً.";
        } else {
            $distributed = 0;
            foreach ($unassigned_students as $st_data) {
                $sid = $st_data['id'];
                $snum = $st_data['username'];
                $assigned = false;
                foreach ($committees as &$c) {
                    if ($c['current_count'] < $c['capacity']) {
                        $c['current_count']++;
                        $seat = $snum; // Registration number is the seat number
                        $exam_num = str_pad($c['current_count'], 4, '0', STR_PAD_LEFT);
                        if ($dist_college) { $exam_num = $dist_college . str_pad($c['current_count'], 4, '0', STR_PAD_LEFT); }
                        $pdo->prepare("INSERT INTO exam_distributions (student_id, committee_id, seat_number, exam_number) VALUES (?, ?, ?, ?)")->execute([$sid, $c['id'], $seat, $exam_num]);
                        $pdo->prepare("UPDATE exam_committees SET current_count = ? WHERE id = ?")->execute([$c['current_count'], $c['id']]);
                        $distributed++;
                        $assigned = true;
                        break;
                    }
                }
                unset($c);
                if (!$assigned) break;
            }
            $success_msg = "تم توزيع {$distributed} طالب بنجاح.";
        }
    } catch (Exception $e) { $error_msg = "حدث خطأ أثناء التوزيع: " . $e->getMessage(); }
}

// POST: Clear Distribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_distribution'])) {
    try {
        $clear_college = $college_scope ?? (isset($_POST['clear_college']) && $_POST['clear_college'] !== '' ? (int)$_POST['clear_college'] : null);
        $clear_level = isset($_POST['clear_level']) && $_POST['clear_level'] !== '' ? trim($_POST['clear_level']) : null;
        if (!$clear_college && !$clear_level) {
            $pdo->exec("DELETE FROM exam_distributions");
            $pdo->exec("UPDATE exam_committees SET current_count = 0");
            $success_msg = "تم مسح جميع التوزيعات وتفريغ اللجان بنجاح.";
        } else {
            $del_q = "DELETE FROM exam_distributions WHERE student_id IN (SELECT u.id FROM users u LEFT JOIN students s ON u.id = s.user_id WHERE u.role = 'student'";
            $del_params = [];
            if ($clear_college) { $del_q .= " AND s.college_id = ?"; $del_params[] = $clear_college; }
            if ($clear_level) { $del_q .= " AND s.level = ?"; $del_params[] = $clear_level; }
            $del_q .= ")";
            $del_stmt = $pdo->prepare($del_q); $del_stmt->execute($del_params);
            $pdo->exec("UPDATE exam_committees ec SET current_count = (SELECT COUNT(*) FROM exam_distributions d WHERE d.committee_id = ec.id)");
            $success_msg = "تم مسح التوزيعات المحددة بنجاح.";
        }
    } catch (Exception $e) { $error_msg = "حدث خطأ: " . $e->getMessage(); }
}

// Delete Committee
if (isset($_GET['delete_committee'])) {
    try {
        $id = (int)$_GET['delete_committee'];
        $pdo->prepare("DELETE FROM exam_committees WHERE id = ?")->execute([$id]);
        $success_msg = "تم حذف اللجنة بنجاح.";
        $action = 'define';
    } catch (Exception $e) { $error_msg = "حدث خطأ: لا يمكن حذف لجنة تحتوي على طلاب موزعين."; }
}

// Stats
$total_committees = $pdo->query("SELECT COUNT(*) FROM exam_committees")->fetchColumn() ?: 0;
$total_distributed = $pdo->query("SELECT COUNT(*) FROM exam_distributions")->fetchColumn() ?: 0;
$total_students_q = "SELECT COUNT(*) FROM users WHERE role = 'student'";
$ts_params = [];
if ($college_scope) { 
    $total_students_q .= " AND id IN (SELECT user_id FROM students WHERE college_id = ?)"; 
    $ts_params[] = $college_scope;
}
$stmt = $pdo->prepare($total_students_q);
$stmt->execute($ts_params);
$total_students = $stmt->fetchColumn() ?: 0;
$unassigned_count = max(0, $total_students - $total_distributed);

// Handle Edit Mode Data Fetching
$edit_committee = null;
if ($action === 'define' && isset($_GET['edit_id'])) {
    $e_stmt = $pdo->prepare("SELECT * FROM exam_committees WHERE id = ?");
    $e_stmt->execute([(int)$_GET['edit_id']]);
    $edit_committee = $e_stmt->fetch(PDO::FETCH_ASSOC);
}

$action_titles = ['define' => 'تعريف لجان الامتحانات والقاعات', 'distribute' => 'توزيع الطلاب على لجان الامتحانات', 'view' => 'إدارة شئون الامتحانات'];
$title = $action_titles[$action] ?? $action_titles['view'];
if ($edit_committee) $title = "تعديل بيانات اللجنة: " . htmlspecialchars($edit_committee['name']);
?>

<div class="space-y-6 animate-fade-in-up max-w-7xl mx-auto">
    <!-- Welcome Banner (Modernized) -->
    <div class="relative overflow-hidden bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-3xl p-8 md:p-10 text-white shadow-card mb-8 border border-white/10 no-print">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white opacity-10 rounded-full blur-2xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6 group">
            <div class="text-center md:text-right text-white">
                <h1 class="text-3xl font-bold mb-3 text-white flex items-center gap-3">
                    <i class="fas fa-tasks"></i>
                    <?php echo $title; ?>
                </h1>
                <p class="text-white/80 text-lg font-medium">واجهة التحكم الذكية في لجان وتوزيعات الامتحانات وإدارة شئون الطلاب بفعالية.</p>
                <?php if ($college_scope && isset($_SESSION['college_name'])): ?>
                <div class="mt-4 flex items-center gap-2 text-sm bg-white/20 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/30 w-fit mx-auto md:mx-0">
                    <i class="fas fa-school"></i> نطاق البيانات: <span class="font-bold"><?php echo htmlspecialchars($_SESSION['college_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3 w-full md:w-auto justify-center">
                <a href="exam_management.php" class="bg-white/20 backdrop-blur-md border border-white/30 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-white hover:text-primary transition-all flex items-center gap-2 shadow-sm text-sm">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <a href="exam_management.php?action=define" class="bg-white/20 backdrop-blur-md border border-white/30 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-white hover:text-primary transition-all flex items-center gap-2 shadow-sm text-sm">
                    <i class="fas fa-plus"></i> اللجان
                </a>
                <a href="exam_management.php?action=distribute" class="bg-white text-primary px-6 py-3 rounded-xl font-bold hover:bg-bg transition-all flex items-center gap-2 shadow-lg">
                    <i class="fas fa-users-cog"></i> التوزيع الآلي
                </a>
            </div>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl border border-emerald-100 font-bold flex items-center gap-3 shadow-sm mb-6 animate-fade-in-up">
        <i class="fas fa-check-circle text-xl"></i> <?php echo $success_msg; ?>
    </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="bg-rose-50 text-rose-700 p-4 rounded-xl border border-rose-100 font-bold flex items-center gap-3 shadow-sm mb-6 animate-fade-in-up">
        <i class="fas fa-exclamation-triangle text-xl"></i> <?php echo $error_msg; ?>
    </div>
    <?php endif; ?>

    <!-- KPI Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-primary transition-all">
            <div class="w-14 h-14 bg-primary/10 rounded-2xl flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all shadow-inner">
                <i class="fas fa-university text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">إجمالي اللجان</p>
                <div class="text-3xl font-black text-slate-800"><?php echo $total_committees; ?></div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-emerald-500 transition-all">
            <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 group-hover:bg-emerald-500 group-hover:text-white transition-all shadow-inner">
                <i class="fas fa-user-check text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">طلاب موزَّعون</p>
                <div class="text-3xl font-black text-slate-800"><?php echo $total_distributed; ?></div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-amber-500 transition-all">
            <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 group-hover:bg-amber-500 group-hover:text-white transition-all shadow-inner">
                <i class="fas fa-user-clock text-2xl"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">طلاب غير موزَّعين</p>
                <div class="text-3xl font-black text-slate-800"><?php echo $unassigned_count; ?></div>
            </div>
        </div>
    </div>

    <?php if ($action === 'define'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add/Edit Form -->
        <div class="lg:col-span-1 bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm transition-all <?php echo $edit_committee ? 'ring-4 ring-primary/5' : ''; ?>">
            <h3 class="font-bold text-xl mb-6 text-slate-800 flex items-center gap-3">
                <i class="fas <?php echo $edit_committee ? 'fa-edit' : 'fa-plus-circle'; ?> text-primary bg-primary/10 p-2.5 rounded-xl text-sm"></i> 
                <?php echo $edit_committee ? 'تعديل بيانات اللجنة' : 'إضافة لجنة جديدة'; ?>
            </h3>
            <form method="POST" class="space-y-6">
                <?php if ($edit_committee): ?>
                    <input type="hidden" name="committee_id" value="<?php echo $edit_committee['id']; ?>">
                <?php endif; ?>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-signature text-primary/60 text-xs"></i> اسم اللجنة / المدرج
                    </label>
                    <input type="text" name="name" required value="<?php echo $edit_committee ? htmlspecialchars($edit_committee['name']) : ''; ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 placeholder:font-normal" placeholder="مثال: لجنة أ — مدرج ١">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-primary/60 text-xs"></i> مقر اللجنة (المبنى)
                    </label>
                    <input type="text" name="location" value="<?php echo $edit_committee ? htmlspecialchars($edit_committee['location']) : ''; ?>" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 placeholder:font-normal" placeholder="مثال: مبنى الهندسة، الدور الأول">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                        <i class="fas fa-users text-primary/60 text-xs"></i> السعة (عدد المقاعد)
                    </label>
                    <input type="number" name="capacity" value="<?php echo $edit_committee ? $edit_committee['capacity'] : '30'; ?>" min="5" required class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700">
                </div>
                
                <div class="flex flex-col gap-3 pt-4">
                    <?php if ($edit_committee): ?>
                        <button type="submit" name="update_committee" class="w-full bg-indigo-600 text-white py-5 rounded-2xl font-bold hover:bg-indigo-700 shadow-xl shadow-indigo-200 transition-all flex items-center justify-center gap-3 transform hover:scale-[1.02] active:scale-95">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                        <a href="exam_management.php?action=define" class="w-full bg-slate-100 text-slate-600 py-4 rounded-2xl font-bold text-center hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-times"></i> إلغاء التعديل
                        </a>
                    <?php else: ?>
                        <button type="submit" name="add_committee" class="w-full bg-primary text-white py-5 rounded-2xl font-bold hover:bg-indigo-700 shadow-xl shadow-primary/20 transition-all flex items-center justify-center gap-3 transform hover:scale-[1.02] active:scale-95">
                            <i class="fas fa-save"></i> حفظ وإضافة اللجنة
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Committees List -->
        <div class="lg:col-span-2">
            <?php
            $committees = $pdo->query("SELECT *, ROUND((current_count::NUMERIC / NULLIF(capacity,0)) * 100, 0) AS fill_pct FROM exam_committees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($committees)): ?>
            <div class="p-16 text-center text-slate-500 border-2 border-dashed border-slate-200 rounded-[2.5rem] bg-slate-50/50">
                <i class="fas fa-school text-slate-300 text-6xl mb-4"></i>
                <p class="font-bold text-xl text-slate-600">لا توجد لجان مسجلة حالياً.</p>
                <p class="text-sm mt-2 text-slate-400">قم بإضافة اللجان من النموذج للبدء في عملية التوزيع.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto rounded-[2.5rem] border border-slate-100 shadow-sm bg-white overflow-hidden">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50/80 text-slate-500 font-bold border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-5 text-center">اسم اللجنة</th>
                            <th class="px-6 py-5 text-center">المقر والمبنى</th>
                            <th class="px-6 py-5 text-center">السعة القصوى</th>
                            <th class="px-6 py-5 text-center">الإشغال</th>
                            <th class="px-6 py-5 text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($committees as $c): $pct = (int)$c['fill_pct']; ?>
                        <tr class="hover:bg-primary/5 transition-all group">
                            <td class="px-6 py-5 font-black text-slate-800 text-center"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td class="px-6 py-5 font-medium text-slate-500 text-xs text-center"><?php echo htmlspecialchars($c['location'] ?: '—'); ?></td>
                            <td class="px-6 py-5 text-center font-mono font-black text-slate-600 text-base"><?php echo $c['capacity']; ?></td>
                            <td class="px-6 py-5">
                                <div class="flex flex-col gap-2 min-w-[140px]">
                                    <div class="flex justify-between items-center text-[11px] font-bold">
                                        <span class="text-primary"><?php echo $c['current_count']; ?> / <?php echo $c['capacity']; ?></span>
                                        <span class="<?php echo $pct >= 90 ? 'text-rose-500' : 'text-slate-400'; ?>"><?php echo $pct; ?>%</span>
                                    </div>
                                    <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden shadow-inner">
                                        <div class="h-full <?php echo $pct >= 90 ? 'bg-rose-500' : 'bg-primary'; ?> rounded-full transition-all duration-1000" style="width:<?php echo min(100,$pct); ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center flex items-center justify-center gap-2">
                                <a href="exam_management.php?action=define&edit_id=<?php echo $c['id']; ?>" class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all shadow-sm transform hover:scale-105 active:scale-95">
                                    <i class="fas fa-edit"></i> تعديل
                                </a>
                                <a href="exam_management.php?action=define&delete_committee=<?php echo $c['id']; ?>" onclick="return confirm('تأكيد مسح اللجنة نهائياً؟')" class="inline-flex items-center gap-2 bg-white border border-slate-200 text-rose-600 hover:bg-rose-600 hover:text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all shadow-sm transform hover:scale-105 active:scale-95">
                                    <i class="fas fa-trash-alt"></i> حذف
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($action === 'distribute'): ?>
    <div class="bg-white p-8 md:p-12 rounded-[2.5rem] shadow-sm border border-slate-100">
        <div class="flex items-center gap-5 mb-8">
            <div class="w-14 h-14 bg-primary/10 text-primary rounded-2xl flex items-center justify-center shadow-inner">
                <i class="fas fa-magic text-2xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-2xl text-slate-800">التوزيع الآلي والذكي</h3>
                <p class="text-slate-500 text-sm">محرك التوزيع التلقائي للطلاب غير الموزَّعين بناءً على السعة المتاحة لضمان دقة التنظيم.</p>
            </div>
        </div>

        <div class="bg-indigo-50/50 border border-indigo-100 rounded-3xl p-6 mb-10 flex items-start gap-4">
            <div class="bg-indigo-100 p-2.5 rounded-xl mt-0.5">
                <i class="fas fa-info-circle text-primary"></i>
            </div>
            <div class="text-sm leading-relaxed text-indigo-900">
                <strong class="block mb-1 text-primary text-base">قواعد التوزيع:</strong>
                سيتم توزيع الطلاب الذين لم تُعيَّن لهم لجانٌ بعد فقط. يمكنك تصفية الطلاب حسب الكلية والفرقة لتنفيذ التوزيع على مراحل.
                <?php if ($college_scope): ?>
                <div class="mt-2 font-bold text-primary italic bg-white/50 px-3 py-1 rounded-lg border border-primary/20 w-fit">
                    <i class="fas fa-shield-alt"></i> ملاحظة: هذا الإجراء مقيَّد بطلاب <?php echo htmlspecialchars($_SESSION['college_name']); ?> فقط.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php if (!$college_scope): ?>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
                        <i class="fas fa-university text-primary/60 text-xs"></i> الكلية المستهدفة
                    </label>
                    <select name="dist_college" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700">
                        <option value="">جميع الكليات</option>
                        <?php foreach ($colleges_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
                        <i class="fas fa-layer-group text-primary/60 text-xs"></i> الفرقة الدراسية
                    </label>
                    <select name="dist_level" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700">
                        <option value="">جميع الفرق</option>
                        <?php foreach ($levels_all as $lv): ?>
                        <option value="<?php echo htmlspecialchars($lv); ?>"><?php echo htmlspecialchars($lv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-5 pt-6 border-t border-slate-100">
                <button type="submit" name="auto_distribute" class="bg-primary text-white px-12 py-5 rounded-[2rem] shadow-xl shadow-primary/20 font-black hover:bg-indigo-700 transition-all flex items-center justify-center gap-3 transform hover:scale-105 active:scale-95">
                    <i class="fas fa-wand-sparkles text-xl"></i> تنفيذ التوزيع الآن
                </button>
                <button type="submit" name="clear_distribution" onclick="return confirm('تنبيه هام للغاية: سيتم مسح كافة التوزيعات المختارة وتصفير عدادات اللجان. هل تريد الاستمرار؟')" class="bg-rose-50 text-rose-600 border border-rose-100 px-10 py-5 rounded-[2rem] font-bold hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center gap-3 active:scale-95">
                    <i class="fas fa-trash-can"></i> مسح التوزيعات الحالية
                </button>
            </div>
        </form>
    </div>

    <?php
    $prev_sql = "SELECT d.seat_number, d.exam_number, u.full_name, u.username, ec.name AS committee_name, s.level FROM exam_distributions d JOIN exam_committees ec ON d.committee_id = ec.id JOIN users u ON d.student_id = u.id LEFT JOIN students s ON u.id = s.user_id WHERE 1=1";
    $prev_params = [];
    if ($college_scope) { $prev_sql .= " AND s.college_id = ?"; $prev_params[] = $college_scope; }
    $prev_sql .= " ORDER BY ec.name, d.seat_number LIMIT 100";
    $prev_stmt = $pdo->prepare($prev_sql);
    $prev_stmt->execute($prev_params);
    $dist_students = $prev_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($dist_students)): ?>
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden mt-10">
        <div class="p-6 md:p-8 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between bg-slate-50/50 gap-4">
            <h4 class="font-bold text-slate-800 flex items-center gap-3 text-lg">
                <i class="fas fa-list-ol text-primary bg-primary/10 p-2.5 rounded-xl text-sm"></i>
                معاينة نتائج التوزيع <span class="bg-primary text-white text-[11px] px-3 py-1 rounded-full font-black">أول <?php echo count($dist_students); ?> طالب</span>
            </h4>
            <a href="exam_reports.php?type=seats" class="text-sm font-bold text-primary hover:bg-primary hover:text-white transition-all flex items-center gap-2 bg-white px-5 py-2.5 rounded-2xl border border-slate-200 shadow-sm active:scale-95">
                <i class="fas fa-file-invoice"></i> استخراج الكشوف الرسمية
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50/50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-5 text-center">اسم الطالب</th>
                        <th class="px-6 py-5 text-center">رقم القيد</th>
                        <th class="px-6 py-5 text-center">الفرقة</th>
                        <th class="px-6 py-5 text-center">اللجنة المخصصة</th>
                        <th class="px-6 py-5 text-center">رقم الجلوس</th>
                        <th class="px-6 py-5 text-center">الرقم الامتحاني</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($dist_students as $st): ?>
                    <tr class="hover:bg-primary/5 transition-all">
                        <td class="px-6 py-5 font-black text-slate-800 text-center"><?php echo htmlspecialchars($st['full_name']); ?></td>
                        <td class="px-6 py-5 text-center font-mono text-xs text-slate-400"><?php echo htmlspecialchars($st['username']); ?></td>
                        <td class="px-6 py-5 text-center">
                            <?php if ($st['level']): ?>
                            <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-[11px] font-black"><?php echo htmlspecialchars($st['level']); ?></span>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <span class="bg-primary/10 text-primary px-3 py-1 rounded-lg text-xs font-bold border border-primary/20"><?php echo htmlspecialchars($st['committee_name']); ?></span>
                        </td>
                        <td class="px-6 py-5 text-center font-mono text-primary font-black text-xl"><?php echo $st['seat_number']; ?></td>
                        <td class="px-6 py-5 text-center">
                            <span class="bg-slate-800 text-white px-4 py-1.5 rounded-xl text-sm font-black font-mono shadow-md"><?php echo htmlspecialchars($st['exam_number'] ?? '—'); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Landing Dashboard -->
    <div class="relative overflow-hidden bg-white border-2 border-dashed border-slate-200 rounded-[3rem] p-12 md:p-24 text-center hover:bg-slate-50/50 transition-all group">
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-primary/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-1000"></div>
        <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-indigo-500/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-1000"></div>
        <div class="relative z-10">
            <div class="w-28 h-28 bg-primary/10 text-primary rounded-[2.5rem] flex items-center justify-center mx-auto mb-10 shadow-inner transform group-hover:rotate-6 transition-transform">
                <i class="fas fa-tasks text-5xl"></i>
            </div>
            <h3 class="text-4xl font-black text-slate-800 mb-6 tracking-tight">إدارة منظومة الامتحانات</h3>
            <p class="text-slate-500 max-w-xl mx-auto mb-14 text-xl leading-relaxed font-medium">بوابتك المركزية لإعداد اللجان، تنفيذ التوزيعات الذكية، واستخراج التقارير الرسمية بدقة متناهية.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <a href="exam_management.php?action=define" class="group/btn bg-white border border-slate-200 text-slate-700 font-black py-8 px-10 rounded-[2.5rem] hover:border-primary hover:text-primary transition-all flex flex-col items-center gap-4 shadow-sm hover:shadow-xl hover:shadow-primary/5 active:scale-95">
                    <div class="w-16 h-16 bg-slate-50 text-slate-400 group-hover/btn:bg-primary/10 group-hover/btn:text-primary rounded-2xl flex items-center justify-center transition-all">
                        <i class="fas fa-plus-square text-3xl"></i>
                    </div>
                    <span class="text-lg">تعريف اللجان</span>
                </a>
                <a href="exam_management.php?action=distribute" class="group/btn bg-primary text-white font-black py-8 px-10 rounded-[2.5rem] hover:bg-indigo-700 transition-all flex flex-col items-center gap-4 shadow-xl shadow-primary/20 hover:-translate-y-2 active:scale-95">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center transition-all">
                        <i class="fas fa-magic text-3xl"></i>
                    </div>
                    <span class="text-lg">التوزيع الآلي</span>
                </a>
                <a href="exam_reports.php" class="group/btn bg-slate-800 text-white font-black py-8 px-10 rounded-[2.5rem] hover:bg-slate-900 transition-all flex flex-col items-center gap-4 shadow-xl shadow-slate-200 active:scale-95">
                    <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center transition-all">
                        <i class="fas fa-file-shield text-3xl"></i>
                    </div>
                    <span class="text-lg">التقارير والكشوف</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.animate-fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once 'includes/footer.php'; ?>
