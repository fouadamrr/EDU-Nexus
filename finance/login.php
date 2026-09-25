<?php
session_start();
require_once 'db.php';

/** @var PDO $pdo */
global $pdo;

$colleges = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM colleges ORDER BY name");
    $colleges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$error      = '';
$active_tab = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_type']) && $_POST['login_type'] === 'staff') {
        $active_tab = 'staff';
        $username   = $_POST['username'] ?? '';
        $password   = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (in_array($user['role'], ['super_admin', 'admin', 'dean', 'affairs'])) {
                $_SESSION['user_id']          = $user['id'];
                $_SESSION['role']             = $user['role'];
                $_SESSION['full_name']        = $user['full_name'];
                $_SESSION['college_id']       = $user['college_id'];
                $_SESSION['finance_logged_in'] = true;
                header('Location: index.php');
                exit;
            } else {
                $error = 'عذراً، لا تملك صلاحية الدخول للبوابة المالية.';
            }
        } else {
            $error = 'خطأ في اسم المستخدم أو كلمة المرور.';
        }
    } else {
        $active_tab   = 'student';
        $student_code = $_POST['student_code'] ?? '';

        if (!empty($student_code)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'student'");
            $stmt->execute([$student_code]);
            $student = $stmt->fetch();

            if ($student) {
                $selected_college = $_POST['service'] ?? '';
                if ($selected_college != '999' && $student['college_id'] != $selected_college) {
                    $error = 'عذراً، هذا الطالب غير مقيد بالكلية المختارة. يرجى اختيار الكلية الصحيحة.';
                } else {
                    $_SESSION['user_id']          = $student['id'];
                    $_SESSION['role']             = 'student';
                    $_SESSION['full_name']        = $student['full_name'];
                    $_SESSION['college_id']       = $student['college_id'];
                    $_SESSION['finance_logged_in'] = true;
                    header('Location: fees.php');
                    exit;
                }
            } else {
                $error = 'عذراً، كود الطالب هذا غير مسجل في النظام.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | EDU Nexus</title>
    <link rel="shortcut icon" href="assets/images/logo.png?v=1.1" type="image/x-icon">
    <link rel="icon" type="image/png" href="assets/images/logo.png?v=1.1">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e3a8a',
                        'primary-dark': '#1e3a8a',
                        gold: '#ffffffff',
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f8fafc; }
        .login-bg {
            background-image: url('assets/images/1.jpeg');
            background-size: cover;
            background-position: center;
        }
        .login-bg-overlay {
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.7));
        }
        .tab-active {
            border: 2px solid #000;
            background: white;
            color: #000;
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-1">

    <div class="flex flex-col-reverse md:flex-row w-full max-w-4xl bg-white rounded-[2rem] shadow-2xl overflow-hidden md:min-h-[420px] animate-fade-in">
        
        <!-- الجانب بتاع فورم الدخول (Left on Desktop) -->
        <div class="w-full md:w-1/2 flex flex-col p-4 md:p-8 bg-white relative">
            
            <div class="flex flex-col items-center mb-4">
                <div class="w-full flex justify-between items-start mb-4">
                    <img src="assets/images/logo.png" alt="Logo" class="h-12 w-auto">
                    <div class="bg-blue-50 px-3 py-1 rounded-full border border-blue-100">
                        <p class="text-[10px] font-black text-primary uppercase tracking-wider">البوابة المالية</p>
                    </div>
                </div>
                
                <div class="w-full text-center md:text-right">
                    <h3 class="text-2xl font-black text-slate-800 mb-1 flex items-center justify-center md:justify-start gap-2">
                        مرحباً بك مجدداً <span class="animate-bounce">👋</span>
                    </h3>
                    <p class="text-slate-400 font-bold text-[11px]">سجل الدخول للوصول إلى حسابك في EDU Nexus</p>
                </div>
            </div>

            <!-- Tab Switcher (Roles) -->
            <div class="flex gap-3 mb-6">
                <button onclick="switchTab('staff')" id="staffTab" class="flex-1 py-3 px-4 rounded-xl text-sm font-black transition-all border-2 border-transparent bg-slate-50 text-slate-400 <?php echo $active_tab === 'staff' ? 'tab-active' : ''; ?>">
                    إدارة / موظف
                </button>
                <button onclick="switchTab('student')" id="studentTab" class="flex-1 py-3 px-4 rounded-xl text-sm font-black transition-all border-2 border-transparent bg-slate-50 text-slate-400 <?php echo $active_tab === 'student' ? 'tab-active' : ''; ?>">
                    طالب
                </button>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border-r-4 border-red-500 text-red-700 text-sm font-bold rounded-lg flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Forms Container -->
            <div class="flex-1 flex flex-col justify-center">
                <!-- Student Form -->
                <form method="POST" id="studentForm" class="<?php echo $active_tab === 'student' ? '' : 'hidden'; ?> space-y-4">
                    <input type="hidden" name="login_type" value="student">
                    
                    <div>
                        <label class="block text-[11px] font-black text-slate-700 mb-1.5 mr-1">الكلية / الخدمة</label>
                        <select name="service" id="serviceSelect" class="w-full bg-blue-50/50 border-2 border-blue-100/50 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:border-primary/30 transition-all appearance-none" required onchange="onCollegeSelect(this)">
                            <option value="">-- اختر الكلية --</option>
                            <?php foreach ($colleges as $col): ?>
                            <option value="<?php echo $col['id']; ?>">
                                <?php echo htmlspecialchars($col['name']); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="999">خدمات عامة</option>
                        </select>
                    </div>

                    <div id="studentInputArea" class="<?php echo isset($_POST['service']) ? '' : 'hidden'; ?> animate-fade-in space-y-4">
                        <div>
                            <label class="block text-[11px] font-black text-slate-700 mb-1.5 mr-1">اسم المستخدم / رقم القيد</label>
                            <div class="relative">
                                <input type="text" name="student_code" class="w-full bg-blue-50/50 border-2 border-blue-100/50 rounded-xl px-12 py-3 text-sm font-bold text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-primary/30 transition-all" placeholder="username" required>
                                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400">
                                    <i class="fas fa-user text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-black py-3.5 rounded-xl shadow-xl shadow-blue-900/20 transition-all flex items-center justify-center gap-3 text-base">
                            دخول النظام <i class="fas fa-arrow-left text-xs"></i>
                        </button>
                    </div>
                </form>

                <!-- Staff Form -->
                <form method="POST" id="staffForm" class="<?php echo $active_tab === 'staff' ? '' : 'hidden'; ?> space-y-4">
                    <input type="hidden" name="login_type" value="staff">
                    
                    <div>
                        <label class="block text-[11px] font-black text-slate-700 mb-1.5 mr-1">اسم المستخدم / رقم القيد</label>
                        <div class="relative">
                            <input type="text" name="username" class="w-full bg-blue-50/50 border-2 border-blue-100/50 rounded-xl px-12 py-3 text-sm font-bold text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-primary/30 transition-all" placeholder="username" required>
                            <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-address-card text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-slate-700 mb-1.5 mr-1">كلمة المرور</label>
                        <div class="relative">
                            <input type="password" name="password" class="w-full bg-blue-50/50 border-2 border-blue-100/50 rounded-xl px-12 py-3 text-sm font-bold text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-primary/30 transition-all" placeholder="••••••••" required>
                            <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-eye-slash text-xs"></i>
                            </div>
                            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[10px] font-bold px-2">
                        <label class="flex items-center gap-2 text-slate-500 cursor-pointer">
                            <input type="checkbox" class="rounded text-primary focus:ring-primary scale-75"> تذكرني
                        </label>
                        <a href="#" class="text-blue-600 hover:underline">نسيت كلمة المرور؟</a>
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-black py-3.5 rounded-xl shadow-xl shadow-blue-900/20 transition-all flex items-center justify-center gap-3 text-base">
                        دخول النظام <i class="fas fa-arrow-left text-xs"></i>
                    </button>
                </form>
            </div>

            <div class="mt-6 text-center">
                <p class="text-[11px] font-bold text-slate-500">
                    ليس لديك حساب؟ <a href="#" class="text-blue-600 hover:underline">إنشاء حساب جديد</a>
                </p>
            </div>

            <div class="mt-auto pt-6 text-center text-[10px] text-slate-300 font-bold">
                © 2026 جميع الحقوق محفوظة لـ EDU Nexus
            </div>

        </div>

        <!-- الجانب الذي فيه الصورة (Right on Desktop) -->
        <div class="flex flex-col justify-end items-center w-full md:w-2/4 login-bg relative text-white p-6 md:p-10 text-center min-h-[280px]">
            <div class="absolute inset-0 login-bg-overlay"></div>
            <div class="relative z-12 flex flex-col items-center w-full">
                <h2 class="text-xl font-black mb-0 tracking-tight drop-shadow-lg">EDU Nexus</h2>
                <p class="text-sm  text-white mb-0 drop-shadow-md">البوابة المالية</p>
            </div>
        </div>

    </div>

    <script>
        function switchTab(tab) {
            const studentTab = document.getElementById('studentTab');
            const staffTab = document.getElementById('staffTab');
            const studentForm = document.getElementById('studentForm');
            const staffForm = document.getElementById('staffForm');

            if (tab === 'student') {
                studentTab.classList.add('tab-active');
                studentTab.classList.remove('text-slate-400');
                staffTab.classList.remove('tab-active');
                staffTab.classList.add('text-slate-400');
                studentForm.classList.remove('hidden');
                staffForm.classList.add('hidden');
            } else {
                staffTab.classList.add('tab-active');
                staffTab.classList.remove('text-slate-400');
                studentTab.classList.remove('tab-active');
                studentTab.classList.add('text-slate-400');
                staffForm.classList.remove('hidden');
                studentForm.classList.add('hidden');
            }
        }

        function onCollegeSelect(sel) {
            const inputArea = document.getElementById('studentInputArea');
            if (sel.value !== '') {
                inputArea.classList.remove('hidden');
            } else {
                inputArea.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
