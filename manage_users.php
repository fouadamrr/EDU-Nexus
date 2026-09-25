<?php
require_once 'includes/header.php';
?>

<!-- الموديلات اللي بتظهر في الصفحة -->
<div id="globalModals" class="print:hidden">
    <!-- مودال إضافة أو تعديل مستخدم -->
    <div id="userModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[9999] hidden items-center justify-center p-4 transition-all duration-300" style="-webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-all duration-300 flex flex-col max-h-[85vh]" id="userModalContent">
            <div class="bg-gradient-to-r from-primary to-blue-700 p-6 text-white flex justify-between items-center group flex-shrink-0 relative overflow-hidden">
                <!-- Subtle pattern overlay -->
                <div class="absolute inset-0 opacity-10 pointer-events-none">
                    <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0 0 L100 100 M100 0 L0 100" stroke="currentColor" stroke-width="1"></path></svg>
                </div>
                <div class="flex items-center gap-4 relative z-10">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-xl shadow-inner group-hover:rotate-12 transition-transform">
                        <i class="fas fa-user-plus" id="modalHeaderIcon"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black leading-none" id="modalTitle">إضافة مستخدم جديد</h3>
                        <p class="text-[10px] text-white/60 mt-1 uppercase tracking-widest font-bold">بوابة الإدارة الذكية</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white text-white hover:text-primary transition-all flex items-center justify-center relative z-10">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="manage_users.php" class="flex flex-col overflow-hidden flex-grow" id="userForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="userId">
                <input type="hidden" name="source_db" id="sourceDb">

                <div class="p-6 space-y-6 overflow-y-auto custom-scrollbar flex-grow bg-slate-50/30">
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-[11px] font-black text-slate-400 mb-2 uppercase tracking-wider pr-2">نوع الدور (الصلاحية)</label>
                            <div class="relative">
                                <i class="fas fa-shield-alt absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <select name="role" id="role" class="w-full bg-white border border-slate-200 rounded-2xl pr-12 pl-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 appearance-none shadow-sm">
                                    <option value="student">طالب</option>
                                    <?php if (in_array($role, ['super_admin', 'admin', 'dean'])): ?>
                                    <option value="instructor">عضوهيئة تدريس</option>
                                    <option value="affairs">شؤون الطلبة</option>
                                    <?php endif; ?>
                                    <?php if (in_array($role, ['super_admin', 'admin'])): ?>
                                    <option value="admin">مدير النظام</option>
                                    <option value="dean">عميد الكلية</option>
                                    <?php endif; ?>
                                    <?php if ($role === 'super_admin'): ?>
                                    <option value="super_admin">رئيس الجامعة</option>
                                    <?php endif; ?>
                                </select>
                                <i class="fas fa-chevron-down absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 pointer-events-none text-xs"></i>
                            </div>
                        </div>

                        <div id="college_selection" class="hidden animate-fade-in">
                            <label class="block text-[11px] font-black text-slate-400 mb-2 uppercase tracking-wider pr-2">التبعية الأكاديمية (الكلية)</label>
                            <div class="relative">
                                <i class="fas fa-university absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <select name="college_id" id="college_id" class="w-full bg-white border border-slate-200 rounded-2xl pr-12 pl-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 appearance-none shadow-sm">
                                    <option value="">-- اختر الكلية --</option>
                                    <?php foreach ($colleges_list as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 pointer-events-none text-xs"></i>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-black text-slate-400 mb-2 uppercase tracking-wider pr-2">معرف الدخول</label>
                                <div class="relative group">
                                    <i class="fas fa-id-badge absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                                    <input type="text" name="username" id="username" required dir="ltr" class="w-full bg-white border border-slate-200 rounded-2xl pr-12 pl-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 font-mono tracking-wider shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-black text-slate-400 mb-2 uppercase tracking-wider pr-2">كلمة المرور</label>
                                <div class="relative group">
                                    <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                                    <input type="password" name="password" id="password" class="w-full bg-white border border-slate-200 rounded-2xl pr-12 pl-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 shadow-sm" placeholder="••••••••">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-slate-400 mb-2 uppercase tracking-wider pr-2">الاسم الرباعي الكامل</label>
                            <div class="relative group">
                                <i class="fas fa-user absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                                <input type="text" name="full_name" id="full_name" required class="w-full bg-white border border-slate-200 rounded-2xl pr-12 pl-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-slate-400 mb-2 uppercase tracking-wider pr-2">البريد الإلكتروني (Gmail)</label>
                            <div class="relative group">
                                <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                                <input type="email" name="email" id="email_input" dir="ltr" class="w-full bg-white border border-slate-200 rounded-2xl pr-12 pl-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-bold text-slate-700 shadow-sm" placeholder="name@gmail.com">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-slate-50 border-t border-slate-100 grid grid-cols-2 gap-4 flex-shrink-0">
                    <button type="button" onclick="closeModal()" class="bg-white text-slate-500 py-3 rounded-2xl font-black hover:bg-slate-100 transition-all border border-slate-200">إلغاء</button>
                    <button type="submit" class="bg-primary text-white py-3 rounded-2xl font-black shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <span>حفظ البيانات</span>
                        <i class="fas fa-save text-xs opacity-70"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($role === 'super_admin'): ?>
    <!-- مودال توزيع الصلاحيات -->
    <div id="permModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[10000] hidden items-center justify-center p-4 transition-all duration-300" style="-webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);">
        <div id="permModalContent" class="bg-white rounded-[3rem] shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 transition-all duration-300">
            <div class="bg-indigo-900 p-8 text-white flex justify-between items-center">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-2xl shadow-inner border border-white/20">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black" id="permModalTitle">توزيع الصلاحيات</h3>
                        <p class="text-white/60 text-xs mt-1" id="permModalSub">قم بتفعيل موديولات النظام لهذا المستخدم</p>
                    </div>
                </div>
                <button onclick="closePermModal()" class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white text-white hover:text-indigo-900 transition-all flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between gap-4">
                <button onclick="selectAllPerms()" class="text-[10px] font-black text-indigo-600 hover:underline"><i class="fas fa-check-square mr-1"></i> تحديد الكل</button>
                <button onclick="deselectAllPerms()" class="text-[10px] font-black text-rose-600 hover:underline"><i class="fas fa-minus-square mr-1"></i> إلغاء الكل</button>
            </div>

            <div class="p-8 grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[50vh] overflow-y-auto custom-scrollbar" id="permToggleGrid">
                <?php foreach (SYSTEM_PERMISSIONS as $pKey => $pDef): ?>
                <label class="perm-card group relative p-4 rounded-[1.5rem] border-2 border-slate-100 hover:border-indigo-600/30 transition-all cursor-pointer select-none">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100">
                            <i class="fas <?= $pDef['icon'] ?>"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-black text-slate-700 text-sm"><?= $pDef['label'] ?></span>
                            <span class="text-[9px] text-slate-400 leading-tight"><?= $pDef['desc'] ?></span>
                        </div>
                    </div>
                    <input type="checkbox" name="permissions[]" value="<?= $pKey ?>" class="perm-checkbox sr-only" id="p-<?= $pKey ?>">
                    <div class="perm-indicator hidden absolute -left-2 -top-2 bg-indigo-600 text-white w-6 h-6 rounded-full flex items-center justify-center border-4 border-white shadow-lg animate-bounce">
                        <i class="fas fa-check text-[10px]"></i>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="p-8 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <div class="text-xs font-black text-slate-500">محدد: <span id="permCountText" class="text-indigo-600">0</span> صلاحية</div>
                <button onclick="savePermissions()" id="savePermBtn" class="bg-indigo-900 text-white px-10 py-4 rounded-2xl font-black shadow-xl shadow-indigo-900/20 hover:scale-105 active:scale-95 transition-all flex items-center gap-3">
                    <i class="fas fa-save" id="savePermIcon"></i>
                    <span id="savePermText">حفظ الصلاحيات</span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- مودال مراجعة البيانات -->
    <div id="reviewModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[10000] hidden items-center justify-center p-4 transition-all duration-300" style="-webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);">
        <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 transition-all duration-300 flex flex-col max-h-[90vh]" id="reviewModalContent">
            <div class="bg-emerald-600 p-8 text-white flex justify-between items-center flex-shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-xl shadow-inner border border-white/20">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="text-xl font-black">مراجعة الملف التعريفي</h3>
                </div>
                <button onclick="closeReviewModal()" class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white text-white hover:text-emerald-600 transition-all flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-8 overflow-y-auto custom-scrollbar flex-grow space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 mb-1 uppercase">الاسم الكامل</label>
                        <div class="font-black text-slate-800" id="rev_full_name"></div>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 mb-1 uppercase">معرف الدخول</label>
                        <div class="font-mono text-emerald-600 font-bold" id="rev_username"></div>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 mb-1 uppercase">البريد الإلكتروني</label>
                        <div class="text-slate-700 font-bold" id="rev_email"></div>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <label class="block text-[10px] font-black text-slate-400 mb-1 uppercase">الكلية</label>
                        <div class="text-slate-800 font-black" id="rev_college"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-black text-slate-700 mb-4 border-r-4 border-emerald-500 pr-3">المرفقات الرسمية (بطاقة الترشيح)</label>
                    <div class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-[2rem] p-4 text-center group">
                        <img id="rev_nomination_card" src="" alt="Doc" class="w-full h-auto object-contain max-h-[500px] rounded-2xl hidden shadow-lg cursor-zoom-in group-hover:scale-105 transition-transform duration-700" onclick="window.open(this.src, '_blank')">
                        <div id="rev_no_card" class="py-20 text-slate-400">
                            <i class="fas fa-file-excel text-5xl mb-4 opacity-20"></i>
                            <p class="font-black">لا يوجد مستند مرفق</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-8 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 flex-shrink-0">
                <button onclick="closeReviewModal()" class="bg-white border border-slate-200 text-slate-600 px-8 py-3 rounded-2xl font-black hover:bg-slate-100 transition-all">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<?php
// اتأكد إن اللي داخل دا معاه صلاحية يشوف الصفحة دي
if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// بنستخدم الكنترولر بتاع اليوزرز
require_once __DIR__ . '/controllers/UserController.php';
$userController = new UserController();

$message = '';

// بنجيب الكليات عشان نحطها في القائمة
require_once __DIR__ . '/models/College.php';
$collegeModel = new College();
$colleges_list = $collegeModel->findAll();
$colleges_map = [];
foreach ($colleges_list as $c) {
    $colleges_map[$c['id']] = $c['name'];
}

// بنشوف الأكشن اللي مطلوب (إضافة، تعديل، مسح، إلخ)
$college_scope = $_SESSION['college_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete']) || isset($_GET['toggle'])) {
    $action = $_POST['action'] ?? null;
    $postData = $_POST;
    
    if (isset($_GET['delete'])) {
        $action = 'delete';
        $postData['delete'] = $_GET['delete'];
    } elseif (isset($_GET['toggle'])) {
        $action = 'toggle';
        $postData['toggle'] = $_GET['toggle'];
    }
    
    $res = $userController->handleRequest($postData, $action, $role, $college_scope);
    if ($res) {
        $message = $res;
    }
}

require_once __DIR__ . '/includes/permissions.php';

// لستة المستخدمين
$filter_role = $_GET['role'] ?? null;
$all_fetched_users = $userController->getUsersList($role, $college_scope, $filter_role);

$staff_users = [];
$student_users = [];
$suspended_users = [];
$pending_users = [];

foreach ($all_fetched_users as $u) {
    if ($u['status'] === 'suspended') {
        $suspended_users[] = $u;
    } elseif ($u['status'] === 'pending_verification') {
        $pending_users[] = $u;
    } elseif ($u['role'] === 'student') {
        $student_users[] = $u;
    } else {
        $staff_users[] = $u;
    }
}

$active_tab = $_GET['tab'] ?? ($role === 'affairs' ? 'students' : 'staff');
if ($active_tab === 'students') $users = $student_users;
elseif ($active_tab === 'suspended') $users = $suspended_users;
elseif ($active_tab === 'pending') $users = $pending_users;
else { $users = $staff_users; $active_tab = 'staff'; }

// بنجيب كل الصلاحيات (عشان السوبر أدمن يشوفها)
$all_user_perms = [];
if ($role === 'super_admin') {
    try {
        $pStmt = $pdo->query("SELECT user_id, permission FROM user_permissions");
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $all_user_perms[$row['user_id']][] = $row['permission'];
        }
    } catch (Exception $e) { /* table may not exist yet — run apply_permissions_schema.php */ }
}
?>

<div class="space-y-8 animate-fade-in-up max-w-7xl mx-auto">

    <!-- هيدر الصفحة والترحيب -->
    <div class="relative overflow-hidden bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-3xl p-8 md:p-10 text-white shadow-card mb-8 border border-white/10 no-print">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white opacity-10 rounded-full blur-2xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6 group">
            <div class="text-center md:text-right text-white">
                <h1 class="text-3xl font-bold mb-3 text-white flex items-center gap-3">
                    <i class="fas fa-users-cog"></i>
                    <?php echo $role === 'affairs' ? 'إدارة بيانات الطلاب' : 'إدارة الحسابات والصلاحيات'; ?>
                </h1>
                <p class="text-white/80 text-lg font-medium">التحكم الكامل في حسابات المستخدمين، توزيع الصلاحيات، ومراجعة طلبات الانضمام للنظام.</p>
                <?php if ($filter_role): ?>
                <div class="mt-4 flex items-center gap-2 text-sm bg-white/20 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/30 w-fit">
                    <i class="fas fa-filter"></i> تصفية: <span class="font-bold underline"><?php echo htmlspecialchars($filter_role === 'student' ? 'الطلاب' : 'أعضاء التدريس'); ?></span>
                    <a href="manage_users.php" class="mr-2 text-white/60 hover:text-white"><i class="fas fa-times-circle"></i></a>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3 w-full md:w-auto justify-center">
                <?php if ($role === 'super_admin'): ?>
                <a href="permissions_overview.php" class="bg-white/20 backdrop-blur-md border border-white/30 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-white hover:text-primary transition-all flex items-center gap-2 shadow-sm text-sm">
                    <i class="fas fa-th"></i> مصفوفة الصلاحيات
                </a>
                <?php endif; ?>
                <?php if ($role !== 'affairs'): ?>
                <button onclick="openAddModal()" class="bg-white text-primary px-6 py-3 rounded-xl font-bold hover:bg-bg transition-all flex items-center gap-2 shadow-lg">
                    <i class="fas fa-plus"></i> مستخدم جديد
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php echo $message; ?>

    <?php if ($role !== 'affairs'): ?>
    <!-- التابات بتاعت التصفية -->
    <div class="bg-white p-2 rounded-[2rem] shadow-sm border border-slate-100 flex items-center gap-2 overflow-x-auto no-scrollbar">
        <a href="?tab=staff<?php echo $filter_role ? '&role='.$filter_role : ''; ?>" 
           class="flex items-center gap-3 px-6 py-3.5 rounded-2xl font-black transition-all <?php echo $active_tab === 'staff' ? 'bg-primary text-white shadow-lg shadow-primary/20 scale-105' : 'text-slate-500 hover:bg-slate-50'; ?>">
            <i class="fas fa-user-tie"></i>
            <span>الإدارة والأكاديميين</span>
            <span class="inline-flex items-center justify-center <?php echo $active_tab === 'staff' ? 'bg-white/20' : 'bg-slate-100'; ?> px-2.5 py-0.5 rounded-full text-xs font-mono"><?php echo count($staff_users); ?></span>
        </a>
        <a href="?tab=students<?php echo $filter_role ? '&role='.$filter_role : ''; ?>" 
           class="flex items-center gap-3 px-6 py-3.5 rounded-2xl font-black transition-all <?php echo $active_tab === 'students' ? 'bg-primary text-white shadow-lg shadow-primary/20 scale-105' : 'text-slate-500 hover:bg-slate-50'; ?>">
            <i class="fas fa-user-graduate"></i>
            <span>الطلاب</span>
            <span class="inline-flex items-center justify-center <?php echo $active_tab === 'students' ? 'bg-white/20' : 'bg-slate-100'; ?> px-2.5 py-0.5 rounded-full text-xs font-mono"><?php echo count($student_users); ?></span>
        </a>
        <a href="?tab=suspended<?php echo $filter_role ? '&role='.$filter_role : ''; ?>" 
           class="flex items-center gap-3 px-6 py-3.5 rounded-2xl font-black transition-all <?php echo $active_tab === 'suspended' ? 'bg-rose-600 text-white shadow-lg shadow-rose-600/20 scale-105' : 'text-slate-500 hover:bg-slate-50'; ?>">
            <i class="fas fa-user-slash"></i>
            <span>الحسابات الموقوفة</span>
            <span class="inline-flex items-center justify-center <?php echo $active_tab === 'suspended' ? 'bg-white/20' : 'bg-slate-100'; ?> px-2.5 py-0.5 rounded-full text-xs font-mono"><?php echo count($suspended_users); ?></span>
        </a>
        <a href="?tab=pending<?php echo $filter_role ? '&role='.$filter_role : ''; ?>" 
           class="flex items-center gap-3 px-6 py-3.5 rounded-2xl font-black transition-all <?php echo $active_tab === 'pending' ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/20 scale-105' : 'text-slate-500 hover:bg-slate-50'; ?>">
            <i class="fas fa-user-clock"></i>
            <span>قيد المراجعة</span>
            <span class="inline-flex items-center justify-center <?php echo $active_tab === 'pending' ? 'bg-white/20' : 'bg-slate-100'; ?> px-2.5 py-0.5 rounded-full text-xs font-mono"><?php echo count($pending_users); ?></span>
        </a>
    </div>
    <?php endif; ?>

    <!-- جدول عرض المستخدمين -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50/50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-5 text-center w-12">#</th>
                        <th class="px-6 py-5">المستخدم</th>
                        <th class="px-6 py-5">الدور والصلاحية</th>
                        <th class="px-6 py-5 text-center">الحالة</th>
                        <th class="px-6 py-5 text-center">إجراءات التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php $counter = 1; foreach ($users as $u): ?>
                    <tr class="hover:bg-primary/5 transition-all group">
                        <td class="px-6 py-5 text-slate-400 font-mono text-xs text-center"><?php echo $counter++; ?></td>
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 group-hover:bg-primary/10 group-hover:text-primary transition-colors">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-black text-slate-800"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                    <span class="text-[11px] font-mono text-slate-400">@<?php echo htmlspecialchars($u['username']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            <?php
                            $badges = [
                                'super_admin' => 'bg-slate-900 text-white',
                                'admin' => 'bg-primary text-white',
                                'dean' => 'bg-indigo-600 text-white font-black',
                                'instructor' => 'bg-blue-500 text-white',
                                'student' => 'bg-slate-100 text-slate-700',
                                'affairs' => 'bg-emerald-600 text-white'
                            ];
                            $role_icons = [
                                'super_admin' => 'fa-crown',
                                'admin' => 'fa-shield-alt',
                                'dean' => 'fa-user-tie',
                                'instructor' => 'fa-chalkboard-teacher',
                                'student' => 'fa-user-graduate',
                                'affairs' => 'fa-users-cog'
                            ];
                            $role_ar = [
                                'super_admin' => 'رئيس الجامعة',
                                'admin' => 'مدير النظام',
                                'dean' => 'عميد الكلية',
                                'instructor' => 'هيئة التدريس',
                                'student' => 'طالب',
                                'affairs' => 'شؤون الطلبة'
                            ];
                            $badge_class = $badges[$u['role']] ?? 'bg-slate-100 text-slate-600';
                            ?>
                            <div class="flex flex-col items-start gap-1.5">
                                <span class="inline-flex items-center gap-1.5 text-[10px] px-3 py-1 rounded-lg font-black border border-transparent <?php echo $badge_class; ?>">
                                    <i class="fas <?php echo $role_icons[$u['role']] ?? 'fa-user'; ?>"></i>
                                    <?php echo $role_ar[$u['role']] ?? $u['role']; ?>
                                </span>
                                <?php if (!empty($u['college_name'])): ?>
                                <span class="text-[9px] font-black text-primary/70 uppercase">
                                    <i class="fas fa-school ml-1"></i><?php echo htmlspecialchars($u['college_name']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <?php $status = $u['status'] ?? 'active'; ?>
                            <?php if ($status === 'active'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black bg-emerald-50 text-emerald-600 border border-emerald-100">
                                <i class="fas fa-check-circle"></i> نشط
                            </span>
                            <?php elseif ($status === 'pending_verification'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black bg-amber-50 text-amber-600 border border-amber-100">
                                <i class="fas fa-clock"></i> قيد المراجعة
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black bg-rose-50 text-rose-600 border border-rose-100">
                                <i class="fas fa-ban"></i> موقوف
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-5">
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($status === 'pending_verification' || $status === 'suspended'): ?>
                                <button onclick='openReviewModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8"); ?>)'
                                        class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center shadow-sm"
                                        title="مراجعة البيانات">
                                    <i class="fas fa-eye text-sm"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($role === 'super_admin' && !in_array($u['role'], ['super_admin', 'admin', 'student'])): ?>
                                <button onclick="openPermModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', <?= json_encode($all_user_perms[$u['id']] ?? []) ?>)"
                                        class="flex items-center gap-2 px-4 py-1.5 rounded-xl text-[10px] font-black bg-primary text-white hover:bg-indigo-700 transition-all shadow-sm">
                                    <i class="fas fa-sliders-h"></i> الصلاحيات
                                </button>
                                <?php endif; ?>

                                <?php if ($u['role'] === 'student'): ?>
                                <a href="admin_student_academic.php?id=<?php echo $u['id']; ?>"
                                   class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-primary hover:text-white transition-all flex items-center justify-center shadow-sm"
                                   title="السجل الأكاديمي">
                                    <i class="fas fa-file-medical-alt text-sm"></i>
                                </a>
                                <?php endif; ?>

                                <?php 
                                $can_edit = ($role === 'super_admin' || ($role === 'admin' && $u['role'] !== 'super_admin') || ($role === 'dean' && !in_array($u['role'], ['super_admin', 'admin', 'dean'])));
                                $can_manage = ($role === 'super_admin' && $u['role'] !== 'super_admin') || ($role === 'admin' && $u['role'] !== 'super_admin');
                                ?>

                                <?php if ($can_edit): ?>
                                <button onclick='openEditModal(<?php echo json_encode($u); ?>)'
                                        class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white border border-amber-100 transition-all flex items-center justify-center shadow-sm">
                                    <i class="fas fa-edit text-sm"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ($can_manage): ?>
                                <a href="?toggle=<?php echo $u['id']; ?>&db=<?php echo urlencode($u['source_db']); ?>&tab=<?php echo $active_tab; ?>"
                                   class="w-9 h-9 rounded-xl flex items-center justify-center transition-all shadow-sm <?php echo $status === 'active' ? 'bg-rose-50 text-rose-500 border border-rose-200 hover:bg-rose-600 hover:text-white' : 'bg-emerald-50 text-emerald-500 border border-emerald-200 hover:bg-emerald-600 hover:text-white'; ?>"
                                   onclick="return confirm('تأكيد تغيير حالة الحساب؟');">
                                    <i class="fas <?php echo $status === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?> text-sm"></i>
                                </a>
                                <a href="?delete=<?php echo $u['id']; ?>&db=<?php echo urlencode($u['source_db']); ?>&tab=<?php echo $active_tab; ?>"
                                   class="w-9 h-9 rounded-xl bg-slate-50 text-slate-400 hover:bg-rose-600 hover:text-white border border-slate-200 transition-all flex items-center justify-center shadow-sm"
                                   onclick="return confirm('حذف المستخدم نهائياً؟');">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- شوية أكواد جافا سكريبت عشان المودالات والعمليات اللي في الصفحة -->
<script>
    // التحكم في مودال المستخدم (إضافة وتعديل)
    const userModal = document.getElementById('userModal');
    const userContent = document.getElementById('userModalContent');
    const roleSelect = document.getElementById('role');
    const collegeBlock = document.getElementById('college_selection');

    roleSelect.addEventListener('change', function() {
        if (this.value === 'dean' || this.value === 'affairs' || this.value === 'instructor') {
            collegeBlock.classList.remove('hidden');
            collegeBlock.classList.add('animate-fade-in-up');
        } else {
            collegeBlock.classList.add('hidden');
        }
    });

    function openAddModal() {
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').innerText = 'إضافة مستخدم جديد';
        document.getElementById('userForm').reset();
        userModal.classList.remove('hidden');
        userModal.classList.add('flex');
        setTimeout(() => { userContent.classList.remove('scale-95'); }, 10);
    }

    function openEditModal(u) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('modalTitle').innerText = 'تعديل بيانات المستخدم';
        document.getElementById('userId').value = u.id;
        document.getElementById('username').value = u.username;
        document.getElementById('full_name').value = u.full_name;
        document.getElementById('email_input').value = u.email || '';
        document.getElementById('role').value = u.role;
        document.getElementById('sourceDb').value = u.source_db || '';
        roleSelect.dispatchEvent(new Event('change'));
        if(u.college_id) document.getElementById('college_id').value = u.college_id;
        
        userModal.classList.remove('hidden');
        userModal.classList.add('flex');
        setTimeout(() => { userContent.classList.remove('scale-95'); }, 10);
    }

    function closeModal() {
        userContent.classList.add('scale-95');
        setTimeout(() => { userModal.classList.add('hidden'); userModal.classList.remove('flex'); }, 200);
    }

    // شغل الصلاحيات والتبديل بينها
    <?php if ($role === 'super_admin'): ?>
    const permModal = document.getElementById('permModal');
    const permContent = document.getElementById('permModalContent');
    let currentTargetId = null;

    function openPermModal(uid, uname, perms) {
        currentTargetId = uid;
        document.getElementById('permModalTitle').innerText = 'صلاحيات: ' + uname;
        document.querySelectorAll('.perm-checkbox').forEach(cb => {
            cb.checked = perms.includes(cb.value);
            updateCardStyle(cb);
        });
        updateCount();
        permModal.classList.remove('hidden');
        permModal.classList.add('flex');
        setTimeout(() => { permContent.classList.remove('scale-95'); }, 10);
    }

    function updateCardStyle(cb) {
        const card = cb.closest('.perm-card');
        const indicator = card.querySelector('.perm-indicator');
        if (cb.checked) {
            card.classList.add('border-indigo-600', 'bg-indigo-50/50');
            indicator.classList.remove('hidden');
        } else {
            card.classList.remove('border-indigo-600', 'bg-indigo-50/50');
            indicator.classList.add('hidden');
        }
    }

    document.querySelectorAll('.perm-card').forEach(card => {
        card.addEventListener('click', function() {
            const cb = this.querySelector('.perm-checkbox');
            cb.checked = !cb.checked;
            updateCardStyle(cb);
            updateCount();
        });
    });

    function updateCount() {
        document.getElementById('permCountText').innerText = document.querySelectorAll('.perm-checkbox:checked').length;
    }

    function selectAllPerms() { document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = true; updateCardStyle(cb); }); updateCount(); }
    function deselectAllPerms() { document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; updateCardStyle(cb); }); updateCount(); }

    function closePermModal() { permContent.classList.add('scale-95'); setTimeout(() => { permModal.classList.add('hidden'); permModal.classList.remove('flex'); }, 200); }

    function savePermissions() {
        const btn = document.getElementById('savePermBtn');
        const selected = [...document.querySelectorAll('.perm-checkbox:checked')].map(c => c.value);
        btn.disabled = true;
        document.getElementById('savePermIcon').className = 'fas fa-spinner fa-spin';
        
        const fd = new FormData();
        fd.append('user_id', currentTargetId);
        selected.forEach(p => fd.append('permissions[]', p));

        fetch('save_permissions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); } else { alert(data.message); }
        })
        .finally(() => { btn.disabled = false; document.getElementById('savePermIcon').className = 'fas fa-save'; });
    }
    <?php endif; ?>

    // مودال مراجعة البيانات قبل الموافقة
    const revModal = document.getElementById('reviewModal');
    const revContent = document.getElementById('reviewModalContent');
    function openReviewModal(u) {
        document.getElementById('rev_full_name').innerText = u.full_name;
        document.getElementById('rev_username').innerText = u.username;
        document.getElementById('rev_email').innerText = u.email || '—';
        document.getElementById('rev_college').innerText = u.college_name || 'طاقم إداري عام';
        const img = document.getElementById('rev_nomination_card');
        const empty = document.getElementById('rev_no_card');
        if(u.nomination_card) { img.src = u.nomination_card; img.classList.remove('hidden'); empty.classList.add('hidden'); }
        else { img.classList.add('hidden'); empty.classList.remove('hidden'); }
        revModal.classList.remove('hidden'); revModal.classList.add('flex');
        setTimeout(() => { revContent.classList.remove('scale-95'); }, 10);
    }
    function closeReviewModal() { revContent.classList.add('scale-95'); setTimeout(() => { revModal.classList.add('hidden'); revModal.classList.remove('flex'); }, 200); }
</script>

<!-- ستايلات إضافية خاصة بالصفحة دي بس عشان الـ Animations والـ Scrollbar -->

<style>
.animate-fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #1e3a8a; }
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>

<?php require_once 'includes/footer.php'; ?>