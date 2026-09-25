<?php
require_once 'includes/header.php';

// ── Super Admin only ──────────────────────────────────────────
if ($role !== 'super_admin') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// ── Ensure user_permissions table exists ─────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            permission VARCHAR(100) NOT NULL,
            granted_by INT REFERENCES users(id) ON DELETE SET NULL,
            granted_at TIMESTAMPTZ DEFAULT NOW(),
            UNIQUE (user_id, permission)
        )
    ");
} catch (Exception $e) {}

// ── Load all non-student, non-super_admin users ───────────────
$staffStmt = $pdo->query("
    SELECT u.id, u.full_name, u.username, u.role, u.status,
    c.name AS college_name
    FROM users u
    LEFT JOIN colleges c ON u.college_id = c.id
    WHERE u.role NOT IN ('student', 'super_admin')
    ORDER BY 
        CASE u.role 
            WHEN 'admin' THEN 1 
            WHEN 'dean' THEN 2 
            WHEN 'affairs' THEN 3 
            WHEN 'instructor' THEN 4
            ELSE 5 
        END,
        u.full_name ASC
");
$staff_users = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Load ALL permissions for ALL of those users at once ───────
$all_perms_map = []; // [user_id => [permission, ...]]
if (!empty($staff_users)) {
    $uids = array_column($staff_users, 'id');
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $permStmt = $pdo->prepare("
        SELECT user_id, permission FROM user_permissions 
        WHERE user_id IN ({$placeholders})
    ");
    $permStmt->execute($uids);
    foreach ($permStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $all_perms_map[$row['user_id']][] = $row['permission'];
    }
}

// ── Summary stats ─────────────────────────────────────────────
$perm_keys = array_keys(SYSTEM_PERMISSIONS);
$total_users = count($staff_users);
$total_grants = 0;
foreach ($all_perms_map as $u_perms) {
    $total_grants += count($u_perms);
}
$total_possible = $total_users * count($perm_keys);

// Role badge helper
$role_labels = [
    'admin'      => ['label' => 'مدير النظام', 'color' => 'bg-slate-900 text-white'],
    'dean'       => ['label' => 'عميد كلية', 'color' => 'bg-indigo-600 text-white'],
    'affairs'    => ['label' => 'شؤون طلاب', 'color' => 'bg-emerald-600 text-white'],
    'instructor' => ['label' => 'هيئة التدريس', 'color' => 'bg-blue-500 text-white'],
];

$perm_col_colors = [
    'registration' => '#3b82f6',
    'students'     => '#6366f1',
    'programs'     => '#8b5cf6',
    'schedules'    => '#06b6d4',
    'exams'        => '#f97316',
    'results'      => '#10b981',
    'financial'    => '#22c55e',
    'absence'      => '#f59e0b',
    'library'      => '#f43f5e',
    'mail'         => '#0ea5e9',
    'supervision'  => '#64748b',
];
?>

<style>
/* Modern styling for the matrix */
.matrix-header-bg {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
}
.user-col { position: sticky; right: 0; z-index: 20; }
.perm-header-cell {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
    white-space: nowrap;
    height: 140px;
    display: flex; align-items: center; justify-content: flex-start;
    padding: 15px 10px;
    font-size: 0.7rem; font-weight: 900;
    gap: 8px;
}
.toggle-pill {
    display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 24px; border-radius: 50px;
    cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative; border: none; outline: none;
}
.toggle-pill .knob {
    position: absolute; width: 18px; height: 18px; border-radius: 50%;
    top: 3px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: white; shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.toggle-pill.is-on { background: #1e3a8a; }
.toggle-pill.is-on .knob { left: calc(100% - 21px); }
.toggle-pill.is-off { background: #e2e8f0; }
.toggle-pill.is-off .knob { left: 3px; background: #94a3b8; }

.matrix-row:hover .user-col { background-color: #f8fafc !important; }
.col-highlight { background-color: rgba(30, 58, 138, 0.03) !important; }

@keyframes pulse-save { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
.saving-indicator { animation: pulse-save 1s infinite; }

.toast {
    position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 1000;
}
.toast.show { transform: translateX(-50%) translateY(0); }
</style>

<div class="space-y-8 animate-fade-in-up max-w-full">

    <!-- Premium Banner -->
    <div class="matrix-header-bg rounded-[2.5rem] p-8 md:p-10 text-white shadow-2xl relative overflow-hidden group">
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl group-hover:scale-110 transition-transform"></div>
        <div class="relative z-10 flex flex-col xl:flex-row items-center justify-between gap-8">
            <div class="text-center md:text-right">
                <h1 class="text-3xl md:text-4xl font-black mb-4 flex items-center justify-center md:justify-start gap-4">
                    <i class="fas fa-shield-halved opacity-80"></i>
                    مصفوفة صلاحيات المنصة
                </h1>
                <p class="text-white/80 font-medium text-lg">التحكم المركزي الشامل في موديولات النظام لجميع الكوادر الإدارية والأكاديمية.</p>
                <div class="flex flex-wrap gap-3 mt-6 justify-center md:justify-start">
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl px-5 py-2.5 flex items-center gap-3">
                        <i class="fas fa-users text-indigo-200"></i>
                        <span class="text-lg font-black"><?php echo $total_users; ?></span> <span class="text-xs font-bold text-white/60">مستخدم</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl px-5 py-2.5 flex items-center gap-3">
                        <i class="fas fa-key text-indigo-200"></i>
                        <span class="text-lg font-black"><?php echo count($perm_keys); ?></span> <span class="text-xs font-bold text-white/60">موديول</span>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl px-5 py-2.5 flex items-center gap-3">
                        <i class="fas fa-check-double text-indigo-200"></i>
                        <span class="text-lg font-black"><?php echo $total_grants; ?></span> <span class="text-xs font-bold text-white/60">منح فعّال</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="grantAll()" class="bg-white text-primary px-6 py-3.5 rounded-2xl font-black shadow-lg hover:scale-105 transition-all text-sm flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> منح الكل
                </button>
                <button onclick="revokeAll()" class="bg-rose-500 text-white px-6 py-3.5 rounded-2xl font-black shadow-lg hover:scale-105 transition-all text-sm flex items-center gap-2">
                    <i class="fas fa-minus-circle"></i> سحب الكل
                </button>
                <a href="manage_users.php" class="bg-indigo-900/40 text-white border border-white/20 px-6 py-3.5 rounded-2xl font-black backdrop-blur-md hover:bg-white hover:text-primary transition-all text-sm">
                    إدارة الحسابات
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-[2rem] p-4 shadow-sm border border-slate-100 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-2 md:pb-0">
            <span class="text-xs font-black text-slate-400 ml-4 whitespace-nowrap">تصفية حسب الدور:</span>
            <button onclick="filterMatrix('all', this)" class="role-filter active bg-primary text-white px-5 py-2 rounded-xl text-xs font-black shadow-lg shadow-primary/20 transition-all">الكل</button>
            <button onclick="filterMatrix('admin', this)" class="role-filter bg-slate-50 text-slate-500 px-5 py-2 rounded-xl text-xs font-black hover:bg-slate-100 transition-all">المديرين</button>
            <button onclick="filterMatrix('dean', this)" class="role-filter bg-slate-50 text-slate-500 px-5 py-2 rounded-xl text-xs font-black hover:bg-slate-100 transition-all">العمداء</button>
            <button onclick="filterMatrix('affairs', this)" class="role-filter bg-slate-50 text-slate-500 px-5 py-2 rounded-xl text-xs font-black hover:bg-slate-100 transition-all">شؤون الطلاب</button>
            <button onclick="filterMatrix('instructor', this)" class="role-filter bg-slate-50 text-slate-500 px-5 py-2 rounded-xl text-xs font-black hover:bg-slate-100 transition-all">هيئة التدريس</button>
        </div>
        <div class="flex items-center gap-4 text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50 px-4 py-2 rounded-xl border border-slate-100">
            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-primary"></span> نشط</div>
            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-200"></span> ملغي</div>
            <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-primary/30 border border-primary"></span> مدير</div>
        </div>
    </div>

    <!-- Matrix Matrix Scrollable Container -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
        <div class="overflow-x-auto custom-scrollbar" id="matrixScroll">
            <table class="w-full text-right border-collapse min-w-[1200px]" id="matrixTable">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="user-col sticky right-0 bg-slate-50 z-30 px-8 py-10 border-b border-l border-slate-100 transition-all">
                            <div class="flex items-center gap-2 text-slate-500">
                                <i class="fas fa-user-shield"></i> المستخدم المختص
                            </div>
                        </th>
                        <?php foreach (SYSTEM_PERMISSIONS as $pKey => $pDef): ?>
                        <th class="perm-col-head px-2 border-b border-slate-100 align-bottom pb-4" data-col-id="<?php echo $pKey; ?>">
                            <div class="perm-header-cell" style="color: <?php echo $perm_col_colors[$pKey] ?? '#94a3b8'; ?>;">
                                <i class="fas <?php echo $pDef['icon']; ?> text-sm"></i>
                                <?php echo $pDef['label']; ?>
                            </div>
                        </th>
                        <?php endforeach; ?>
                        <th class="px-6 py-6 border-b border-slate-100 text-center">
                            <div class="perm-header-cell text-slate-400">
                                <i class="fas fa-chart-line text-sm"></i> المؤشر
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($staff_users as $user): 
                        $uid = $user['id'];
                        $is_admin = $user['role'] === 'admin';
                        $u_perms = $all_perms_map[$uid] ?? [];
                        $p_count = $is_admin ? count($perm_keys) : count($u_perms);
                        $initials = mb_substr($user['full_name'], 0, 1, 'UTF-8');
                        $r_badge = $role_labels[$user['role']] ?? ['label' => $user['role'], 'color' => 'bg-slate-100'];
                    ?>
                    <tr class="matrix-row group hover:bg-slate-50 transition-all <?php echo $is_admin ? 'is-admin-row' : ''; ?>" data-role="<?php echo $user['role']; ?>" data-uid="<?php echo $uid; ?>">
                        <td class="user-col sticky right-0 bg-white group-hover:bg-slate-50 z-20 px-8 py-5 border-l border-slate-100 shadow-[10px_0_15px_-5px_rgba(0,0,0,0.02)] transition-all">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-indigo-600 flex items-center justify-center text-white font-black text-sm shadow-md">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-black text-slate-800 text-sm truncate"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[9px] font-black px-2 py-0.5 rounded-md <?php echo $r_badge['color']; ?> uppercase tracking-tighter"><?php echo $r_badge['label']; ?></span>
                                        <span class="text-[9px] text-slate-400 font-mono">@<?php echo $user['username']; ?></span>
                                    </div>
                                    <?php if (!empty($user['college_name'])): ?>
                                    <span class="text-[8px] text-primary/60 font-black mt-1 uppercase"><i class="fas fa-building ml-1"></i><?php echo $user['college_name']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <?php foreach ($perm_keys as $perm_key): 
                            $has = $is_admin || in_array($perm_key, $u_perms);
                        ?>
                        <td class="text-center p-2 group/btn <?php echo $is_admin ? 'bg-primary/5' : ''; ?>" onmouseenter="highlightCol('<?php echo $perm_key; ?>', true)" onmouseleave="highlightCol('<?php echo $perm_key; ?>', false)" data-col-id="<?php echo $perm_key; ?>">
                            <?php if ($is_admin): ?>
                            <div class="text-primary/40"><i class="fas fa-infinity text-xs"></i></div>
                            <?php else: ?>
                            <button class="toggle-pill <?php echo $has ? 'is-on' : 'is-off'; ?>" data-id="<?php echo $uid; ?>" data-perm="<?php echo $perm_key; ?>" onclick="toggleRowPerm(this)">
                                <span class="knob"></span>
                            </button>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>

                        <td class="px-6 py-4 text-center">
                            <?php if ($is_admin): ?>
                            <span class="text-[9px] font-black text-primary bg-primary/10 px-3 py-1 rounded-full"><i class="fas fa-star text-[8px] ml-1"></i>كامل</span>
                            <?php else: ?>
                            <div class="relative w-10 h-10 mx-auto">
                                <svg class="w-10 h-10 transform -rotate-90">
                                    <circle cx="20" cy="20" r="16" class="stroke-slate-100" stroke-width="4" fill="none"></circle>
                                    <circle cx="20" cy="20" r="16" class="stroke-primary transition-all duration-700 arc-indicator" data-uid="<?php echo $uid; ?>" stroke-width="4" fill="none" stroke-dasharray="<?php echo ($p_count/count($perm_keys))*100; ?> 100" stroke-linecap="round"></circle>
                                </svg>
                                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-black text-primary p-count-label" data-uid="<?php echo $uid; ?>"><?php echo $p_count; ?></span>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Save Rows / Batch Logic Notification -->
<div id="saveToast" class="toast hidden bg-slate-900 text-white px-8 py-4 rounded-[1.5rem] shadow-2xl flex items-center gap-4 border border-white/10">
    <div class="w-6 h-6 rounded-full border-2 border-primary border-t-transparent animate-spin" id="toastLoader"></div>
    <div class="flex flex-col">
        <span class="text-sm font-black" id="toastMsg">جارٍ مزامنة الصلاحيات...</span>
        <span class="text-[10px] text-white/50" id="toastSub">لا تغلق الصفحة حتى الانتهاء</span>
    </div>
</div>

<script>
    const TOTAL_MODS = <?php echo count($perm_keys); ?>;
    
    function highlightCol(col, active) {
        document.querySelectorAll(`[data-col-id="${col}"]`).forEach(el => {
            if (active) el.classList.add('bg-primary/5'); else el.classList.remove('bg-primary/5');
        });
    }

    function toggleRowPerm(btn) {
        const isOn = btn.classList.contains('is-on');
        const uid = btn.dataset.id;
        const perm = btn.dataset.perm;
        
        // Optimistic UI
        if (isOn) {
            btn.classList.remove('is-on'); btn.classList.add('is-off');
        } else {
            btn.classList.remove('is-off'); btn.classList.add('is-on');
        }
        
        updateIndicators(uid);
        triggerSave(uid);
    }

    function updateIndicators(uid) {
        const row = document.querySelector(`.matrix-row[data-uid="${uid}"]`);
        if (!row) return;
        const count = row.querySelectorAll('.toggle-pill.is-on').length;
        const arc = row.querySelector('.arc-indicator');
        const label = row.querySelector('.p-count-label');
        
        if (arc) arc.style.strokeDasharray = `${(count / TOTAL_MODS) * 100} 100`;
        if (label) label.textContent = count;
    }

    let saveTimeout = null;
    function triggerSave(uid) {
        clearTimeout(saveTimeout);
        showToast('جاري التعديل...', true);
        saveTimeout = setTimeout(() => saveUserPermissions(uid), 1000);
    }

    function saveUserPermissions(uid) {
        const row = document.querySelector(`.matrix-row[data-uid="${uid}"]`);
        const perms = [...row.querySelectorAll('.toggle-pill.is-on')].map(b => b.dataset.perm);
        
        const fd = new FormData();
        fd.append('user_id', uid);
        perms.forEach(p => fd.append('permissions[]', p));

        fetch('save_permissions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✓ تم الحفظ بنجاح', false);
                setTimeout(hideToast, 2000);
            } else {
                showToast('✗ فشل الحفظ: ' + data.message, false);
            }
        }).catch(() => showToast('✗ خطأ في الاتصال', false));
    }

    function grantAll() {
        if (!confirm('منح كافة الصلاحيات المتاحة لجميع المستخدمين المعروضين؟')) return;
        document.querySelectorAll('.matrix-row:not(.is-admin-row) .toggle-pill.is-off').forEach(b => {
            b.classList.remove('is-off'); b.classList.add('is-on');
        });
        syncFullMatrix();
    }

    function revokeAll() {
        if (!confirm('سحب كافة الصلاحيات من جميع المستخدمين المحددين؟')) return;
        document.querySelectorAll('.matrix-row:not(.is-admin-row) .toggle-pill.is-on').forEach(b => {
            b.classList.remove('is-on'); b.classList.add('is-off');
        });
        syncFullMatrix();
    }

    function syncFullMatrix() {
        const rows = document.querySelectorAll('.matrix-row:not(.is-admin-row)');
        showToast('جاري مزامنة الكتلة الكاملة...', true);
        let completed = 0;
        rows.forEach(row => {
            const uid = row.dataset.uid;
            updateIndicators(uid);
            const perms = [...row.querySelectorAll('.toggle-pill.is-on')].map(b => b.dataset.perm);
            const fd = new FormData();
            fd.append('user_id', uid);
            perms.forEach(p => fd.append('permissions[]', p));
            fetch('save_permissions.php', { method: 'POST', body: fd }).finally(() => {
                completed++;
                if (completed === rows.length) {
                    showToast('✓ تمت المزامنة الكلية بنجاح', false);
                    setTimeout(hideToast, 2000);
                }
            });
        });
    }

    function showToast(msg, loading) {
        const t = document.getElementById('saveToast');
        document.getElementById('toastMsg').textContent = msg;
        document.getElementById('toastLoader').style.display = loading ? 'block' : 'none';
        t.classList.remove('hidden');
        t.classList.add('show');
    }
    function hideToast() { document.getElementById('saveToast').classList.remove('show'); }

    function filterMatrix(role, btn) {
        document.querySelectorAll('.role-filter').forEach(b => {
            b.classList.remove('active', 'bg-primary', 'text-white', 'shadow-lg');
            b.classList.add('bg-slate-50', 'text-slate-500');
        });
        btn.classList.add('active', 'bg-primary', 'text-white', 'shadow-lg');
        btn.classList.remove('bg-slate-50', 'text-slate-500');

        document.querySelectorAll('.matrix-row').forEach(row => {
            if (role === 'all' || row.dataset.role === role) row.classList.remove('hidden');
            else row.classList.add('hidden');
        });
    }

    // Custom CSS for scrollbar
    const style = document.createElement('style');
    style.textContent = `
        .custom-scrollbar::-webkit-scrollbar { height: 10px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 3px solid #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #1e3a8a; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    `;
    document.head.appendChild(style);
</script>

<?php require_once 'includes/footer.php'; ?>
