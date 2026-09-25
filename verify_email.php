<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
$success = '';

$user_id = $_SESSION['pending_verification_id'] ?? null;
$email = $_SESSION['pending_verification_email'] ?? '';

if (!$user_id) {
    header("Location: register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if (empty($otp)) {
        $error = "يرجى إدخال كود التفعيل.";
    } else {
        $stmt = $pdo->prepare("SELECT id, role, verification_code FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();

        if ($user && $user['verification_code'] === $otp) {
            // زي الفل! الكود طلع صح وبنبدأ نفعل الحساب
            $new_status = ($user['role'] === 'student') ? 'active' : 'suspended';
            
            $update = $pdo->prepare("
                UPDATE users 
                SET email_verified = TRUE, verification_code = NULL, status = :s 
                WHERE id = :id
            ");
            $update->execute([':s' => $new_status, ':id' => $user_id]);

            if ($user['role'] === 'student') {
                $success = "تم تفعيل حسابك بنجاح! يمكنك الآن تسجيل الدخول.";
            } else {
                $success = "تم تفعيل البريد الإلكتروني! حسابك الآن بانتظار موافقة الإدارة (رئيس الجامعة أو الأدمن).";
            }
            
            // بنمسح بيانات التفعيل من السيشين عشان خلاص المهمة تمت بنجاح
            unset($_SESSION['pending_verification_id']);
            unset($_SESSION['pending_verification_email']);
        } else {
            $error = "كود التفعيل غير صحيح. يرجى المحاولة مرة أخرى.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفعيل الحساب - EDU Nexus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        'primary-dark': '#1e40af',
                        secondary: '#0f172a',
                        accent: '#3b82f6',
                        bright: '#60a5fa',
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
        body { font-family: 'Cairo', sans-serif; background-color: #f8fafc; }
        .login-bg {
            background-color: #2563eb;
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

    <div class="flex flex-col md:flex-row w-full max-w-4xl bg-white rounded-3xl shadow-2xl overflow-hidden min-h-[550px]">
    
        <!-- Branding Side -->
        <div class="flex flex-col justify-end items-center w-full md:w-5/12 lg:w-1/2 login-bg relative text-white px-6 py-10 md:py-16 text-center bg-no-repeat h-48 md:h-auto min-h-[180px]">
            <div class="absolute inset-0 login-bg-overlay"></div>
            <div class="relative z-10 flex flex-col items-center w-full">
                <h1 class="text-2xl md:text-4xl font-bold mb-1 md:mb-2 drop-shadow-md">EDU Nexus</h1>
                <h2 class="text-sm md:text-2xl font-medium text-white/90 mb-2 md:mb-5 opacity-90">أمن الحسابات</h2>
                <div class="hidden md:block pt-4 border-t border-white/20 w-full max-w-[200px]">
                    <p class="text-xs font-bold text-white/60 uppercase tracking-widest leading-relaxed">تحقق من الهوية الرقمية</p>
                </div>
            </div>
        </div>

        <!-- Form Side -->
        <div class="w-full md:w-7/12 lg:w-1/2 flex flex-col justify-center p-8 md:p-12 bg-white relative">
            <div class="w-full max-w-sm mx-auto text-center">
                <div class="mb-8">
                    <div class="w-20 h-20 bg-primary/10 text-primary rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-sm ring-1 ring-primary/5">
                        <i class="fas fa-envelope-open-text text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-secondary mb-2">تفعيل حسابك</h3>
                    <p class="text-secondary opacity-60 text-xs leading-relaxed px-4">تم إرسال كود التفعيل المكون من 6 أرقام إلى بريدك الإلكتروني:<br><strong class="text-primary font-bold mt-1 block"><?php echo htmlspecialchars($email); ?></strong></p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl text-xs mb-8 font-black flex items-center justify-center gap-3 border border-rose-100">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-emerald-50 text-emerald-700 p-6 rounded-3xl text-sm mb-8 font-black shadow-sm border border-emerald-100">
                        <i class="fas fa-check-circle text-2xl mb-3 block"></i>
                        <?php echo $success; ?>
                        <div class="mt-6">
                            <a href="index.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 rounded-2xl inline-block shadow-lg shadow-emerald-200 transition-all transform active:scale-95">انتقل لتسجيل الدخول</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-8">
                        <div>
                            <label class="block text-slate-400 text-[10px] font-black uppercase tracking-widest mb-4">كود التفعيل (أدخل 6 أرقام)</label>
                            <input type="text" name="otp" required maxlength="6" 
                                class="w-full text-center text-4xl font-black tracking-[0.4em] py-5 rounded-2xl border-2 border-slate-100 focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none transition bg-slate-50/50 font-mono text-secondary"
                                placeholder="000000">
                        </div>
                        
                        <button type="submit" class="w-full bg-primary hover:bg-bright text-white font-black py-4 rounded-2xl shadow-xl shadow-primary/20 transition-all transform active:scale-[0.98] flex items-center justify-center gap-3">
                            <span>تفعيل الحساب الآن</span>
                            <i class="fas fa-shield-check"></i>
                        </button>
                    </form>

                    <div class="mt-10 pt-6 border-t border-slate-100">
                        <p class="text-xs font-black text-slate-400">
                            لم يصلك الكود؟ 
                            <button type="button" id="resendBtn" onclick="resendCode()" class="text-primary hover:underline font-black ml-1">إعادة إرسال الكود</button>
                        </p>
                        <div id="resendMsg" class="mt-3 text-[11px] font-black hidden"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    let cooldown = 0;
    function resendCode() {
        if (cooldown > 0) return;
        const btn = document.getElementById('resendBtn');
        const msg = document.getElementById('resendMsg');
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
        btn.disabled = true;
        
        fetch('resend_otp.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            msg.classList.remove('hidden');
            if (data.success) {
                msg.className = 'mt-2 text-sm font-bold text-green-600';
                msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                startCooldown();
            } else {
                msg.className = 'mt-2 text-sm font-bold text-red-600';
                msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                btn.innerHTML = 'إعادة إرسال الكود';
                btn.disabled = false;
            }
        })
        .catch(err => {
            msg.classList.remove('hidden');
            msg.className = 'mt-2 text-sm font-bold text-red-600';
            msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> حدث خطأ، يرجى المحاولة لاحقاً.';
            btn.innerHTML = 'إعادة إرسال الكود';
            btn.disabled = false;
        });
    }

    function startCooldown() {
        cooldown = 60;
        const btn = document.getElementById('resendBtn');
        const timer = setInterval(() => {
            cooldown--;
            if (cooldown <= 0) {
                clearInterval(timer);
                btn.innerHTML = 'إعادة إرسال الكود';
                btn.disabled = false;
            } else {
                btn.innerHTML = `يمكنك إعادة الإرسال بعد ${cooldown} ثانية`;
            }
        }, 1000);
    }
    </script>

</body>
</html>
