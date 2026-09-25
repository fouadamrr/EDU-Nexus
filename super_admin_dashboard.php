<?php
require_once 'includes/header.php';

// اتأكد إن اللي داخل دا رئيس الجامعة أو السوبر أدمن
if ($role !== 'super_admin') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// بنجمع كل أرقام السيستم من الكنترولر المخصص للموضوع دا
require_once __DIR__ . '/controllers/SuperAdminDashboardController.php';
$dashController = new SuperAdminDashboardController();
$dashData = $dashController->getDashboardStats();

$total_colleges = $dashData['total_colleges'];
$stats = $dashData['stats'];

// بنجهز البيانات عشان نبعتها للـ JS ونرسم الرسومات البيانية
$college_labels = json_encode(array_column($stats['colleges_data'], 'name'), JSON_UNESCAPED_UNICODE);
$college_counts = json_encode(array_column($stats['colleges_data'], 'student_count'));
$role_labels = json_encode(['طلاب', 'أعضاء هيئة تدريس', 'عمداء', 'شئون طلاب'], JSON_UNESCAPED_UNICODE);
$role_counts = json_encode([$stats['students'], $stats['instructors'], $stats['deans'], $stats['affairs']]);
$status_labels = json_encode(['حسابات نشطة', 'حسابات موقوفة'], JSON_UNESCAPED_UNICODE);
$status_counts = json_encode([$stats['active'], $stats['suspended']]);
?>

<div class="space-y-8 animate-fade-in-up">

    <!-- بانر الترحيب برئاسة الجامعة -->
    <div class="relative overflow-hidden mesh-gradient rounded-[2.5rem] p-6 md:p-10 text-white shadow-2xl mb-10 border border-white/10 stagger-1">
        <div class="absolute -right-20 -top-20 w-96 h-96 bg-indigo-500/15 rounded-full blur-[80px] animate-pulse"></div>
        <div class="absolute -left-10 -bottom-10 w-72 h-72 bg-blue-400/15 rounded-full blur-[60px] animate-pulse" style="animation-delay: 2s;"></div>
        
        <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-8">
            <div class="text-center lg:text-right max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-indigo-100 text-xs font-bold mb-4">
                    <span class="relative flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                    </span>
                    النظام يعمل بكفاءة عالية
                </div>
                <h1 class="text-3xl md:text-5xl font-black mb-4 tracking-tight leading-tight">
                    مرحباً بالسيد <span class="text-transparent bg-clip-text bg-gradient-to-r from-white via-indigo-200 to-indigo-100">رئيس الجامعة</span> 👋
                </h1>
                <p class="text-xl font-bold mb-6 text-white/80"><?php echo htmlspecialchars($full_name); ?></p>
                <div class="flex flex-wrap gap-3 justify-center lg:justify-start">
                    <div class="bg-white/10 backdrop-blur-xl border border-white/20 text-white px-5 py-2.5 rounded-2xl font-bold shadow-lg text-sm flex items-center gap-2.5 transition-all hover:bg-white/20">
                        <i class="fas fa-university text-indigo-300"></i>
                        المركز الرئيسي لإدارة الجامعة
                    </div>
                    <div class="bg-indigo-900/40 backdrop-blur-xl border border-white/10 text-indigo-50 px-5 py-2.5 rounded-2xl font-medium shadow-lg text-sm flex items-center gap-2.5 transition-all hover:bg-indigo-900/50">
                        <i class="fas fa-calendar-alt text-indigo-300"></i>
                        <?php echo date('Y/m/d'); ?>
                    </div>
                </div>
            </div>
            <div class="relative group stagger-2">
                <div class="absolute inset-0 bg-white/20 blur-xl rounded-full scale-110 group-hover:scale-125 transition-transform duration-700 opacity-40"></div>
                <div class="w-24 h-24 md:w-32 md:h-32 bg-white/10 backdrop-blur-2xl border border-white/30 shadow-2xl rounded-[2rem] flex items-center justify-center p-4 overflow-hidden flex-shrink-0 transform hover:rotate-6 transition-all duration-700 animate-float-slow">
                    <img src="assets/images/logo.png?v=1.1" alt="EDU Nexus Logo" class="w-full h-full object-contain filter drop-shadow-xl">
                </div>
            </div>
        </div>
    </div>

    <!-- شبكة أرقام السيستم -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <div class="glass-card p-8 rounded-[2.5rem] flex items-center gap-6 stagger-1">
            <div class="w-18 h-18 bg-primary/10 rounded-[1.5rem] flex items-center justify-center text-primary shadow-inner">
                <i class="fas fa-building text-3xl"></i>
            </div>
            <div>
                <p class="text-slate-500 text-xs font-black uppercase tracking-[0.2em] mb-2">إجمالي الكليات</p>
                <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo $total_colleges; ?></p>
            </div>
        </div>

        <div class="glass-card p-8 rounded-[2.5rem] flex items-center gap-6 stagger-2">
            <div class="w-18 h-18 bg-indigo-50 rounded-[1.5rem] flex items-center justify-center text-indigo-600 shadow-inner">
                <i class="fas fa-user-graduate text-3xl"></i>
            </div>
            <div>
                <p class="text-slate-500 text-xs font-black uppercase tracking-[0.2em] mb-2">إجمالي الطلاب</p>
                <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo $stats['students']; ?></p>
            </div>
        </div>

        <div class="glass-card p-8 rounded-[2.5rem] flex items-center gap-6 stagger-3">
            <div class="w-18 h-18 bg-blue-50 rounded-[1.5rem] flex items-center justify-center text-blue-600 shadow-inner">
                <i class="fas fa-chalkboard-teacher text-3xl"></i>
            </div>
            <div>
                <p class="text-slate-500 text-xs font-black uppercase tracking-[0.2em] mb-2">هيئة التدريس</p>
                <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo $stats['instructors']; ?></p>
            </div>
        </div>

        <div class="glass-card p-8 rounded-[2.5rem] flex items-center gap-6 stagger-4">
            <div class="w-18 h-18 bg-emerald-50 rounded-[1.5rem] flex items-center justify-center text-emerald-600 shadow-inner">
                <i class="fas fa-check-circle text-3xl"></i>
            </div>
            <div>
                <p class="text-slate-500 text-xs font-black uppercase tracking-[0.2em] mb-2">حسابات نشطة</p>
                <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo $stats['active']; ?></p>
            </div>
        </div>
    </div>

    <!-- جزء الرسومات البيانية -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-10">
        <!-- Role Distribution Doughnut -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 flex flex-col">
            <h3 class="font-black text-slate-800 mb-8 flex items-center gap-3 text-lg">
                <i class="fas fa-chart-pie text-primary bg-primary/10 p-2.5 rounded-xl text-sm"></i>
                توزيع المستخدمين حسب الأدوار
            </h3>
            <div class="flex-grow flex items-center justify-center" style="height:300px;">
                <canvas id="roleChart"></canvas>
            </div>
        </div>

        <!-- Account Status Doughnut -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 flex flex-col">
            <h3 class="font-black text-slate-800 mb-8 flex items-center gap-3 text-lg">
                <i class="fas fa-toggle-on text-emerald-600 bg-emerald-50 p-2.5 rounded-xl text-sm"></i>
                تحليل حالة الحسابات (نشط / موقوف)
            </h3>
            <div class="flex-grow flex items-center justify-center" style="height:300px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- إشغال الكليات -->
    <?php if (!empty($stats['colleges_data'])): ?>
    <div class="bg-white rounded-[3rem] shadow-sm border border-slate-100 p-8 md:p-12 mt-10">
        <h3 class="font-black text-slate-800 mb-10 flex items-center gap-4 text-xl">
            <i class="fas fa-chart-bar text-primary bg-primary/5 p-3 rounded-2xl"></i>
            إشغال الكليات (عدد الطلاب المسجلين)
        </h3>
        <div style="height:350px;">
            <canvas id="collegeChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- اختصارات الإدارة -->
    <div class="mt-16">
        <div class="flex items-center justify-between mb-10 stagger-3">
            <h3 class="text-3xl font-black text-secondary flex items-center gap-4">
                <span class="w-2 h-10 bg-amber-500 rounded-full"></span>
                مركز الإدارة والتحكم السريع
            </h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <a href="manage_colleges.php" class="nav-card group bg-white p-10 rounded-[3rem] sticky shadow-soft border border-slate-100 flex flex-col items-center text-center gap-6 stagger-1">
                <div class="icon-container w-24 h-24 bg-primary/5 text-primary rounded-[2rem] flex items-center justify-center transition-all duration-500 shadow-inner">
                    <i class="fas fa-university text-4xl"></i>
                </div>
                <div>
                    <h4 class="font-black text-slate-900 text-xl mb-2">إدارة منظومة الكليات</h4>
                    <p class="text-slate-500 text-sm font-medium leading-relaxed">التحكم الكامل في الهيكل الأكاديمي والعمادات وقواعد البيانات المركزية</p>
                </div>
                <div class="mt-2 px-6 py-2 rounded-full bg-slate-50 text-primary text-xs font-black uppercase tracking-widest group-hover:bg-primary group-hover:text-white transition-colors">
                    دخول المنصة <i class="fas fa-arrow-left mr-2"></i>
                </div>
            </a>

            <a href="manage_users.php" class="nav-card group bg-white p-10 rounded-[3rem] shadow-soft border border-slate-100 flex flex-col items-center text-center gap-6 stagger-2">
                <div class="icon-container w-24 h-24 bg-indigo-50 text-indigo-600 rounded-[2rem] flex items-center justify-center transition-all duration-500 shadow-inner">
                    <i class="fas fa-user-shield text-4xl"></i>
                </div>
                <div>
                    <h4 class="font-black text-slate-900 text-xl mb-2">إدارة حسابات النظام</h4>
                    <p class="text-slate-500 text-sm font-medium leading-relaxed">رقابة الصلاحيات، تأمين الحسابات، ومتابعة النشاط الإداري لجميع المستخدمين</p>
                </div>
                <div class="mt-2 px-6 py-2 rounded-full bg-slate-50 text-indigo-600 text-xs font-black uppercase tracking-widest group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                    إدارة الأمان <i class="fas fa-arrow-left mr-2"></i>
                </div>
            </a>

            <a href="settings.php" class="nav-card group p-10 rounded-[3rem] bg-slate-900 shadow-2xl flex flex-col items-center text-center gap-6 stagger-3">
                <div class="icon-container w-24 h-24 bg-white/10 text-white rounded-[2rem] flex items-center justify-center transition-all duration-500 shadow-inner">
                    <i class="fas fa-microchip text-4xl"></i>
                </div>
                <div>
                    <h4 class="font-black text-white text-xl mb-2">الإعدادات المتقدمة</h4>
                    <p class="text-slate-400 text-sm font-medium leading-relaxed">ضبط ثوابت الأنظمة، المحرك البرمجي، وتوزيع الموارد التقنية للمنصة</p>
                </div>
                <div class="mt-2 px-6 py-2 rounded-full bg-white/10 text-white text-xs font-black uppercase tracking-widest group-hover:bg-white group-hover:text-slate-900 transition-colors">
                    تخصيص النظام <i class="fas fa-arrow-left mr-2"></i>
                </div>
            </a> 
        </div>
    </div>

</div>

<!-- التشارتس -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'Cairo, sans-serif';
Chart.defaults.color = '#64748b';

// دالة مساعدة عشان نطلع ألوان مدرجة
function createGradient(ctx, colorStart, colorEnd) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, colorStart);
    gradient.addColorStop(1, colorEnd);
    return gradient;
}

const commonDoughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%', 
    spacing: 0, // Segments touching
    borderRadius: 0, // No rounded corners for a continuous ring
    plugins: {
        legend: { 
            position: 'bottom', 
            labels: { 
                font: { family: 'Cairo', size: 13, weight: '700' }, 
                padding: 25,
                usePointStyle: true,
                pointStyle: 'circle'
            } 
        },
        tooltip: {
            backgroundColor: 'rgba(30, 41, 59, 1)',
            padding: 12,
            titleFont: { family: 'Cairo', size: 14, weight: 'bold' },
            bodyFont: { family: 'Cairo', size: 13 },
            cornerRadius: 12,
            displayColors: true
        }
    }
};

// باليتة الألوان
const paletteA = ['#1e3a8a', '#4338ca', '#3b82f6', '#10b981'];
const paletteB = ['#059669', '#e11d48'];

// تشارت توزيع الأدوار
const roleCtx = document.getElementById('roleChart').getContext('2d');
new Chart(roleCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo $role_labels; ?>,
        datasets: [{
            data: <?php echo $role_counts; ?>,
            backgroundColor: paletteA,
            hoverOffset: 10,
            borderWidth: 0,
        }]
    },
    options: commonDoughnutOptions
});

// تشارت حالة الحسابات
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo $status_labels; ?>,
        datasets: [{
            data: <?php echo $status_counts; ?>,
            backgroundColor: paletteB,
            hoverOffset: 10,
            borderWidth: 0,
        }]
    },
    options: commonDoughnutOptions
});

<?php if (!empty($stats['colleges_data'])): ?>
// تشارت الطلبة في كل كلية
const collegeCtx = document.getElementById('collegeChart').getContext('2d');
new Chart(collegeCtx, {
    type: 'bar',
    data: {
        labels: <?php echo $college_labels; ?>,
        datasets: [{
            label: 'عدد الطلاب',
            data: <?php echo $college_counts; ?>,
            backgroundColor: 'rgba(30, 58, 138, 0.85)', // Solid-ish indigo for comfort
            borderRadius: 10,
            barThickness: 32, // More breathable bars
            hoverBackgroundColor: '#1e3a8a',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                padding: 12,
                titleFont: { family: 'Cairo', size: 14, weight: 'bold' },
                bodyFont: { family: 'Cairo', size: 12 },
                cornerRadius: 10
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                ticks: { font: { family: 'Cairo', weight: '700', size: 11 } }
            },
            x: {
                grid: { display: false },
                ticks: { font: { family: 'Cairo', size: 11, weight: '700' } }
            }
        }
    }
});
<?php endif; ?>
</script>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.4);
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        --accent-glow: 0 0 20px rgba(59, 130, 246, 0.3);
    }

    .mesh-gradient {
        background-color: #1e3a8a;
        background-image: 
            radial-gradient(at 0% 0%, hsla(225, 39%, 30%, 1) 0, transparent 50%), 
            radial-gradient(at 50% 0%, hsla(225, 39%, 20%, 1) 0, transparent 50%), 
            radial-gradient(at 100% 0%, hsla(225, 39%, 10%, 1) 0, transparent 50%), 
            radial-gradient(at 0% 100%, hsla(225, 39%, 20%, 1) 0, transparent 50%), 
            radial-gradient(at 50% 100%, hsla(225, 39%, 15%, 1) 0, transparent 50%), 
            radial-gradient(at 100% 100%, hsla(225, 39%, 25%, 1) 0, transparent 50%);
        position: relative;
        overflow: hidden;
    }

    .mesh-gradient::before {
        content: '';
        position: absolute;
        width: 150%;
        height: 150%;
        top: -25%;
        left: -25%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 20s infinite linear;
        pointer-events: none;
    }

    @keyframes float {
        0% { transform: rotate(0deg) translate(0, 0); }
        50% { transform: rotate(180deg) translate(2%, 5%); }
        100% { transform: rotate(360deg) translate(0, 0); }
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .glass-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 15px 45px rgba(0, 0, 0, 0.1);
        border-color: rgba(255, 255, 255, 0.6);
        background: rgba(255, 255, 255, 0.85);
    }

    .nav-card {
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .nav-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    }

    .nav-card:hover .icon-container {
        transform: rotate(12deg) scale(1.1);
        background: var(--primary-gradient);
        color: white;
        box-shadow: var(--accent-glow);
    }

    /* أنيميشن الدخول */
    .stagger-1 { animation: fadeInUp 0.8s 0.1s both; }
    .stagger-2 { animation: fadeInUp 0.8s 0.2s both; }
    .stagger-3 { animation: fadeInUp 0.8s 0.3s both; }
    .stagger-4 { animation: fadeInUp 0.8s 0.4s both; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-float-slow {
        animation: floating 6s ease-in-out infinite;
    }

    @keyframes floating {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-15px); }
    }
</style>

<?php require_once 'includes/footer.php'; ?>
