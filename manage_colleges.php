<?php
require_once 'includes/header.php';

// اتأكد إن اللي داخل دا أدمن أو عميد أو سوبر أدمن
if ($role !== 'super_admin' && $role !== 'admin' && $role !== 'dean') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
require_permission('programs');

// بنستخدم الكنترولر بتاع الكليات
require_once __DIR__ . '/controllers/CollegeController.php';
$collegeController = new CollegeController();

$message = '';

// التعامل مع الطلبات (إضافة، تعديل، مسح)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete'])) {
    $action = $_POST['action'] ?? (isset($_GET['delete']) ? 'delete' : null);
    $postData = $_POST;
    if (isset($_GET['delete'])) {
        $postData['id'] = $_GET['delete'];
    }
    
    $res = $collegeController->handleRequest($postData, $action);
    if ($res) {
        $message = $res;
    }
}

// جيب لستة الكليات
$college_id_session = $_SESSION['college_id'] ?? 0;
$colleges = $collegeController->getCollegesList($role, (int)$college_id_session);
usort($colleges, fn($a, $b) => (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
?>

<div class="space-y-8 animate-fade-in-up max-w-7xl mx-auto">

    <!-- هيدر الصفحة والترحيب -->
    <div class="relative overflow-hidden bg-gradient-to-r from-primary via-indigo-600 to-blue-600 rounded-3xl p-8 md:p-10 text-white shadow-card mb-8 border border-white/10 no-print">
        <div class="absolute -right-20 -top-20 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white opacity-10 rounded-full blur-2xl"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6 group">
            <div class="text-center md:text-right text-white">
                <h1 class="text-3xl font-bold mb-3 text-white flex items-center gap-3">
                    <i class="fas fa-building"></i>
                    إدارة الهيكل الأكاديمي والكليات
                </h1>
                <p class="text-white/80 text-lg font-medium">التحكم في شؤون الكليات، تعيين العمداء، وإدارة الإحداثيات الجغرافية لمواقع الحرم الجامعي.</p>
                <div class="mt-4 flex items-center gap-4">
                    <div class="flex items-center gap-2 text-sm bg-white/20 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/30">
                        <i class="fas fa-university"></i> إجمالي الكليات: <span class="font-black"><?php echo count($colleges); ?></span>
                    </div>
                </div>
            </div>
            <?php if ($role === 'admin' || $role === 'super_admin'): ?>
            <button onclick="openAddModal()" class="bg-white text-primary px-8 py-4 rounded-2xl font-black hover:bg-bg transition-all flex items-center gap-2 shadow-lg scale-105 active:scale-95">
                <i class="fas fa-plus"></i> إضافة كلية جديدة
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- جدول الكليات -->
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-primary/10 text-primary rounded-2xl flex items-center justify-center text-xl shadow-inner">
                    <i class="fas fa-list-ul"></i>
                </div>
                <div>
                    <h3 class="font-black text-slate-800 text-lg">قائمة الكليات الموثقة</h3>
                    <p class="text-slate-400 text-xs font-medium">عرض بيانات العمداء وإحصائيات الطلاب والمواقع الجغرافية</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-8 py-6 w-16 text-center opacity-50">المعرف</th>
                        <th class="px-8 py-6">اسم الكلية / المنشأة</th>
                        <th class="px-8 py-6">عمادة الكلية</th>
                        <th class="px-8 py-6">الحساب الإداري</th>
                        <th class="px-8 py-6 text-center">إجمالي الطلاب</th>
                        <th class="px-8 py-6 text-center">الموقع الجغرافي</th>
                        <?php if ($role === 'admin' || $role === 'super_admin'): ?>
                        <th class="px-8 py-6 text-center">إجراءات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($colleges)): ?>
                    <tr>
                        <td colspan="7" class="py-24 text-center group">
                            <div class="w-24 h-24 bg-slate-50 rounded-[2rem] flex items-center justify-center mx-auto mb-6 text-slate-200 group-hover:scale-110 transition-transform">
                                <i class="fas fa-building text-5xl"></i>
                            </div>
                            <h4 class="font-black text-slate-400">لا توجد كليات مسجلة حالياً</h4>
                            <p class="text-slate-300 text-xs mt-1">ابدأ بإضافة أول كلية لتنظيم الهيكل الأكاديمي</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($colleges as $c): 
                        $cid = (int)$c['id'];
                        $is_archived = isset($c['status']) && $c['status'] === 'archived';
                    ?>
                    <tr class="hover:bg-primary/5 transition-all group <?php echo $is_archived ? 'opacity-50 grayscale' : ''; ?>">
                        <td class="px-8 py-6 text-slate-400 font-mono text-xs text-center border-l border-slate-50/50"><?php echo $cid; ?></td>
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all shadow-sm">
                                    <i class="fas fa-school text-sm"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-black text-slate-800 text-base"><?php echo htmlspecialchars($c['name']); ?></span>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">جامعة EDU Nexus</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6 font-bold text-slate-700">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-user-tie text-indigo-600/30 text-xs"></i>
                                <?php echo htmlspecialchars($c['dean_fullname'] ?? 'غير محدد'); ?>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-slate-100 rounded-lg text-slate-500 font-mono text-xs border border-slate-200">
                                <i class="fas fa-user-circle"></i>
                                <?php echo htmlspecialchars($c['dean_username'] ?? '—'); ?>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-center">
                            <span class="inline-flex items-center gap-2 px-4 py-1.5 bg-primary text-white rounded-xl text-xs font-black shadow-sm ring-4 ring-primary/5">
                                <i class="fas fa-user-graduate text-[10px]"></i>
                                <?php echo number_format($c['student_count'] ?? 0); ?>
                            </span>
                        </td>
                        <td class="px-8 py-6 text-center">
                            <?php if (!empty($c['latitude']) && !empty($c['longitude'])): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $c['latitude']; ?>,<?php echo $c['longitude']; ?>" target="_blank"
                               class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-600 px-4 py-1.5 rounded-xl text-xs font-black hover:bg-emerald-600 hover:text-white transition-all border border-emerald-100">
                                <i class="fas fa-location-dot"></i> عرض الخريطة
                            </a>
                            <?php else: ?>
                            <span class="text-rose-400 font-bold text-[10px] italic">
                                <i class="fas fa-map-pin ml-1"></i> مفقود
                            </span>
                            <?php endif; ?>
                        </td>
                        <?php if ($role === 'admin' || $role === 'super_admin'): ?>
                        <td class="px-8 py-6 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick='openEditModal(<?php echo json_encode([
                                    "id" => $c["id"],
                                    "name" => $c["name"] ?? "",
                                    "dean_name" => $c["dean_fullname"] ?? "",
                                    "dean_username" => $c["dean_username"] ?? "",
                                    "latitude" => $c["latitude"] ?? "",
                                    "longitude" => $c["longitude"] ?? ""
                                ], JSON_UNESCAPED_UNICODE); ?>)'
                                        class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-center border border-indigo-100 shadow-sm"
                                        title="تعديل البيانات">
                                    <i class="fas fa-pen-nib text-sm"></i>
                                </button>
                                <a href="?delete=<?php echo (int)$c['id']; ?>"
                                   class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm"
                                   onclick="return confirm('تنبيه: سيتم أرشفة قاعدة بيانات الكلية وحساب العميد. استمرار؟');"
                                   title="أرشفة الكلية">
                                    <i class="fas fa-archive text-sm"></i>
                                </a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- مودال الكلية (إضافة وتعديل) -->
<div id="collegeModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[200] hidden items-center justify-center p-4 transition-all duration-300">
    <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-all duration-300" id="collegeModalContent">
        <div class="bg-primary p-8 text-white flex justify-between items-center group">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center text-2xl shadow-inner group-hover:rotate-12 transition-transform border border-white/20">
                    <i class="fas fa-building-circle-check" id="modalHeaderIcon"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black" id="modalTitle">إضافة كلية جديدة</h3>
                    <p class="text-white/60 text-xs mt-1">تحديد الهوية الأكاديمية وصلاحيات العمادة</p>
                </div>
            </div>
            <button onclick="closeModal()" class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white text-white hover:text-primary transition-all flex items-center justify-center">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" class="p-8 space-y-6" id="collegeForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="collegeId">

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-black text-slate-500 mb-2 uppercase tracking-widest">اسم الكلية الرباعي المعتمد</label>
                    <input type="text" name="name" id="collegeName" required placeholder="مثال: كلية الذكاء الاصطناعي"
                           class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition-all font-black text-slate-800">
                </div>

                <div class="p-6 bg-slate-50 rounded-[2rem] border border-slate-100 space-y-4">
                    <div class="flex items-center gap-2 mb-2 text-primary font-black text-sm">
                        <i class="fas fa-user-shield"></i> بيانات عميد الكلية
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 mb-1">الاسم الكامل للعميد</label>
                        <input type="text" name="dean_name" id="deanName" required placeholder="أدخل الاسم رباعياً"
                               class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 focus:border-primary outline-none text-sm font-bold">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 mb-1">معرف الدخول (اسم المستخدم)</label>
                            <input type="text" name="dean_username" id="deanUsername" required dir="ltr"
                                   class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 focus:border-primary outline-none text-sm font-mono tracking-wider">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 mb-1">كلمة المرور <span id="pwReq" class="text-rose-500">*</span></label>
                            <input type="password" name="dean_password" id="deanPassword"
                                   class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 focus:border-primary outline-none text-sm">
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-emerald-50/50 rounded-[2rem] border border-emerald-100/50 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-emerald-700 font-black text-sm">
                            <i class="fas fa-location-dot"></i> الإحداثيات الجغرافية
                        </div>
                        <button type="button" onclick="detectLocation()" class="text-[10px] bg-emerald-600 text-white px-3 py-1.5 rounded-lg font-black hover:bg-emerald-700 transition-all flex items-center gap-1 shadow-sm shadow-emerald-200">
                            <i class="fas fa-crosshairs"></i> تحديد التلقائي
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" step="0.000001" name="latitude" id="collegeLatitude" placeholder="خط العرض" class="w-full bg-white border border-emerald-100 rounded-xl px-4 py-2 focus:border-emerald-500 outline-none text-xs font-mono" dir="ltr">
                        <input type="number" step="0.000001" name="longitude" id="collegeLongitude" placeholder="خط الطول" class="w-full bg-white border border-emerald-100 rounded-xl px-4 py-2 focus:border-emerald-500 outline-none text-xs font-mono" dir="ltr">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-8">
                <button type="button" onclick="closeModal()" class="bg-slate-100 text-slate-500 py-4 rounded-2xl font-black hover:bg-slate-200 transition-all">إلغاء</button>
                <button type="submit" class="bg-primary text-white py-4 rounded-2xl font-black shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-3">
                    <i class="fas fa-save"></i> حفظ جميع البيانات
                </button>
            </div>
        </form>
    </div>
</div>

<!-- شوية أكواد جافا سكريبت عشان المودالات وتحديد الموقع -->
<script>
    const modal = document.getElementById('collegeModal');
    const content = document.getElementById('collegeModalContent');
    const form = document.getElementById('collegeForm');

    function openAddModal() {
        form.reset();
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').innerText = 'إضافة كلية جديدة';
        document.getElementById('deanPassword').required = true;
        document.getElementById('pwReq').style.display = 'inline';
        document.getElementById('deanUsername').readOnly = false;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => content.classList.remove('scale-95'), 10);
    }

    function openEditModal(c) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('modalTitle').innerText = 'تعديل بيانات الكلية';
        document.getElementById('collegeId').value = c.id;
        document.getElementById('collegeName').value = c.name;
        document.getElementById('deanName').value = c.dean_name;
        document.getElementById('deanUsername').value = c.dean_username;
        document.getElementById('deanUsername').readOnly = true;
        document.getElementById('deanPassword').required = false;
        document.getElementById('pwReq').style.display = 'none';
        document.getElementById('collegeLatitude').value = c.latitude || '';
        document.getElementById('collegeLongitude').value = c.longitude || '';
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => content.classList.remove('scale-95'), 10);
    }

    function closeModal() {
        content.classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); modal.classList.remove('flex'); }, 200);
    }

    function detectLocation() {
        if (!navigator.geolocation) return alert('الجهاز لا يدعم المواقع الحية.');
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('collegeLatitude').value = pos.coords.latitude.toFixed(6);
            document.getElementById('collegeLongitude').value = pos.coords.longitude.toFixed(6);
        }, err => alert('خطأ في التحديد: ' + err.message));
    }
</script>

<!-- ستايلات خاصة بالـ Animations بتاعة الصفحة -->
<style>
.animate-fade-in-up { animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once 'includes/footer.php'; ?>
