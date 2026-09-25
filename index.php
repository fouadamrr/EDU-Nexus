<?php
session_start();

// بنقفل الإيرورز عشان متبانش لليوزر وتبوظ المنظر، وبنخليها تتسجل في اللوج بس
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/includes/helpers.php';

if (isset($_SESSION['user_id'])) {
    // لو هو أصلاً مسجل دخول، وديه على الداشبورد بتاعته على طول بدل ما يقف هنا
    $role = $_SESSION['role'] ?? '';
    if ($role === 'super_admin') {
        header('Location: super_admin_dashboard.php');
    } elseif (in_array($role, ['admin', 'dean', 'affairs'])) {
        header('Location: admin_dashboard.php');
    } elseif ($role === 'instructor') {
        header('Location: instructor_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // بيبدأ يشوف اليوزر كتب إيه لما داس على زرار الاستلام
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_type = $_POST['login_type'] ?? 'student'; 

    if (empty($username) || empty($password)) {
        $error = "بيانات الدخول غير مكتملة";
    } else {
        try {
            $authController = new AuthController();
            // بنكلم الـ controller ونشوف الدنيا فيها إيه
            $result = $authController->login($username, $password, $login_type); 
            
            if ($result['status'] === true) {
                header('Location: ' . $result['redirect']);
                exit;
            } else {
                $error = $result['message'];
            }
        } catch (PDOException $e) {
            $error = "خطأ ";
            error_log("Login DB Error: " . $e->getMessage());
        } catch (Exception $e) {
            $error = "حدث خطأ غير متوقع.";
            error_log("Login Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="2HzF_ElwQcZSUBrFW16SrSS5xgBXJibSgXO-YQ4YLbI" />

    <title>EDU Nexus</title>
    <!-- SEO & Icons -->
    <meta name="description" content="EDU Nexus هو منصة تعليمية متكاملة تهدف لتطوير العملية الأكاديمية، توفر للطلاب وأعضاء هيئة التدريس أدوات ذكية لإدارة النتائج، الجداول، والامتحانات الإلكترونية بأعلى معايير الأمان.">
    <meta name="keywords" content="EDU Nexus, نظام تعليمي, إدارة جامعات, نتائج الطلاب, جداول دراسية, امتحانات إلكترونية">
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    
    <!-- Open Graph for Social Media -->
    <meta property="og:title" content="EDU Nexus">
    <meta property="og:description" content="بوابتك المتكاملة للخدمات التعليمية والأكاديمية الحديثة.">
    <meta property="og:image" content="assets/images/logo.png">
    <meta property="og:type" content="website">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e3a8a',
                        'primary-dark': '#1e40af',
                        secondary: '#0f172a',
                        accent: '#3b82f6',
                        bright: '#2563eb',
                        bg: '#f8fafc'
                    },
                    fontFamily: {
                        cairo: ['Cairo', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8fafc;
        }
        .login-bg {
            background-image: url('assets/images/1.jpeg');
            background-size: cover;
            background-position: center;
        }
        .login-bg-overlay {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.1) 60%, rgba(0, 0, 0, 0) 100%);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center bg-bg p-4 md:p-0">

    <div class="flex flex-col md:flex-row w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden md:min-h-[350px]">
    
        <!-- الجنب اللي فيه اللوجو والشكل السينمائي دا -->
        <div class="flex flex-col justify-end items-center w-full md:w-5/12 lg:w-1/2 login-bg relative text-white px-6 pb-6 pt-10 text-center bg-no-repeat h-72 md:h-auto min-h-[220px]">
            <div class="absolute inset-0 login-bg-overlay"></div>
            <div class="relative z-10 flex flex-col items-center w-full">
                <h1 class="text-2xl md:text-3xl font-black mb-1 drop-shadow-2xl tracking-tight">EDU Nexus</h1>
                <h2 class="text-xs md:text-lg font-medium text-white/90 drop-shadow-xl">البوابة الأكاديمية</h2>
                <div class="hidden md:block mt-2 pt-2 border-t border-white/20 w-full max-w-[80px]">
                    <p class="text-[9px] font-bold text-white/60 tracking-widest uppercase opacity-80">بوابتك للتميز</p>
                </div>
            </div>
        </div>

        <!-- الجنب بتاع فورم الدخول -->
        <div class="w-full md:w-7/12 lg:w-1/2 flex flex-col justify-center p-6 md:py-2 md:px-10 bg-white relative overflow-y-auto custom-scrollbar">
            <div class="w-full max-w-md mx-auto">
                
                <!-- الهيدر في حالة الكمبيوتر -->
                <div class="hidden md:flex justify-between items-center mb-6 gap-6">
                    <div class="text-right">
                        <h3 class="text-2xl md:text-3xl font-black text-secondary mb-1">مرحباً بك مجدداً 👋</h3>
                        <p class="text-secondary opacity-60 text-xs font-bold">سجل الدخول للوصول إلى لوحة التحكم</p>
                    </div>
                    <div class="w-20 h-20 flex-shrink-0">
                        <img src="assets/images/logo.png?v=1.2" alt="Logo" class="w-full h-full object-contain">
                    </div>
                </div>

                <!-- الهيدر في حالة الموبايل -->
                <div class="md:hidden text-center mb-10">
                    <h2 class="text-xl font-black text-secondary mb-6 opacity-80 uppercase tracking-widest">EDU Nexus</h2>
                    <div class="flex items-center justify-center gap-4 text-right">
                        <div class="flex flex-col">
                            <h3 class="text-xl font-black text-secondary leading-tight">مرحباً بك مجدداً 👋</h3>
                            <p class="text-secondary opacity-60 text-[10px] mt-1 font-bold">سجل الدخول للوصول إلى لوحة التحكم</p>
                        </div>
                        <div class="w-16 h-16 flex-shrink-0">
                            <img src="assets/images/logo.png?v=1.2" alt="Logo" class="w-full h-full object-contain">
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="bg-rose-50 border-r-4 border-rose-500 text-rose-700 p-4 mb-6 rounded-xl text-xs font-black flex items-center shadow-sm">
                        <i class="fas fa-exclamation-circle ml-3 text-lg"></i>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4 text-right flex flex-col">
                    <!-- يختار هو طالب ولا تبع الـ IT والدكاترة -->
                    <div class="bg-bg p-1 rounded-xl flex mb-1 w-full">
                        <button type="button" id="btn-student" onclick="setRole('student')"
                            class="flex-1 py-2 text-sm font-black rounded-lg transition-all bg-white text-primary shadow-sm">طالب</button>
                        <button type="button" id="btn-staff" onclick="setRole('staff')"
                            class="flex-1 py-2 text-sm font-black rounded-lg transition-all text-secondary opacity-60 hover:opacity-100">IT / دكتور</button>
                    </div>

                    <input type="hidden" name="login_type" id="login_type" value="student">

                    <!-- Input Fields -->
                    <div class="space-y-3">
                        <div>
                            <label for="username" class="block text-secondary text-[11px] font-black mb-1.5 mr-2">اسم المستخدم / رقم القيد</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                                    <i class="fas fa-id-badge text-sm"></i>
                                </span>
                                <input type="text" name="username" id="username" required
                                    class="block w-full pr-11 pl-4 py-3.5 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition bg-slate-50/50 focus:bg-white text-slate-800 outline-none font-mono"
                                    placeholder="user name">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-secondary text-[11px] font-black mb-1.5 mr-2">كلمة المرور</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                                    <i class="fas fa-lock text-sm"></i>
                                </span>
                                <input type="password" name="password" id="password" required
                                    class="block w-full pr-11 pl-12 py-3.5 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition bg-slate-50/50 focus:bg-white text-slate-800 outline-none"
                                    placeholder="password">
                                <button type="button" onclick="togglePassword()"
                                    class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 hover:text-primary transition">
                                    <i class="fas fa-eye text-sm" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] px-2">
                        <label class="flex items-center gap-1.5 cursor-pointer group">
                            <input type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary bg-white w-3 h-3">
                            <span class="text-slate-600 group-hover:text-secondary transition-colors font-bold">تذكرني</span>
                        </label>
                        <a href="forgot_password.php" class="text-primary hover:text-bright font-black transition-colors">نسيت كلمة المرور؟</a>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-2 flex flex-col items-center md:items-stretch gap-3">
                        <button type="submit"
                            class="w-full md:w-full bg-primary hover:bg-bright text-white font-black py-3.5 rounded-2xl shadow-xl shadow-primary/20 transition-all transform active:scale-[0.98] flex items-center justify-center gap-3">
                            <span>دخول النظام</span>
                            <i class="fas fa-sign-in-alt text-sm"></i>
                        </button>
                        
                        <div class="text-center pt-1">
                            <p class="text-xs font-black text-slate-400">
                                ليس لديك حساب؟ 
                                <a href="register.php" class="text-primary hover:underline">إنشاء حساب جديد</a>
                            </p>
                        </div>
                    </div>
                </form>

                <div class="mt-8 text-center pt-3 border-t border-slate-100">
                    <p class="text-[10px] text-secondary opacity-70">&copy; <?php echo date('Y'); ?> جميع الحقوق محفوظة لـ EDU Nexus</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // فانكشن بتظبط شكل الفورم على حسب اليوزر هيسجل دخول كإيه
        function setRole(role) {
            const btnStudent = document.getElementById('btn-student');
            const btnStaff = document.getElementById('btn-staff');
            const userInput = document.getElementById('username');
            const hiddenType = document.getElementById('login_type');
            const hint = document.getElementById('username-hint');

            if (role === 'student') {
                btnStudent.className = "flex-1 py-2.5 rounded-lg text-sm font-bold bg-white text-primary shadow-sm transition-all duration-200";
                btnStaff.className = "flex-1 py-2.5 rounded-lg text-sm font-bold text-secondary opacity-80 hover:text-slate-700 transition-all duration-200";

                userInput.placeholder = "أدخل رقم القيد (مثال: 20261050)";
                hiddenType.value = 'student';
                hint.textContent = "";
            } else {
                btnStaff.className = "flex-1 py-2.5 rounded-lg text-sm font-bold bg-white text-primary shadow-sm transition-all duration-200";
                btnStudent.className = "flex-1 py-2.5 rounded-lg text-sm font-bold text-secondary opacity-80 hover:text-slate-700 transition-all duration-200";

                userInput.placeholder = "أدخل اسم المستخدم";
                hiddenType.value = 'staff';
                hint.textContent = "";
            }
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>