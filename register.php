<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/controllers/MailController.php';

$error = '';
$success = '';

// بنعالج بيانات التسجيل لما اليوزر يدوس على الزرار
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'student';
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $college_id = (int)($_POST['college_id'] ?? 0);

    // 1. بنأكد إن البيانات كاملة وصح
    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($phone) || empty($national_id) || $college_id === 0) {
        $error = "يرجى ملء جميع الحقول المطلوبة.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "البريد الإلكتروني غير صالح.";
    } elseif (strlen($national_id) !== 14) {
        $error = "الرقم القومي يجب أن يتكون من 14 رقماً.";
    } else {
        // 2. بنشوف هل البيانات دي (يوزر نيم، إيميل، أو بطاقة) متسجلة قبل كدا؟
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e OR national_id = :nid LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $email, ':nid' => $national_id]);
        if ($stmt->fetch()) {
            $error = "اسم المستخدم أو البريد الإلكتروني أو الرقم القومي مسجل بالفعل.";
        } else {
            // 3. بنرفع صورة بطاقة الترشيح لو هو طالب
            $nomination_card_path = null;
            if ($role === 'student') {
                if (isset($_FILES['nomination_card']) && $_FILES['nomination_card']['error'] === 0) {
                    $allowed = ['jpg', 'jpeg', 'png'];
                    $ext = strtolower(pathinfo($_FILES['nomination_card']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $filename = "nomination_" . time() . "_" . $username . "." . $ext;
                        $upload_dir = __DIR__ . "/uploads/documents/";
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                        move_uploaded_file($_FILES['nomination_card']['tmp_name'], $upload_dir . $filename);
                        $nomination_card_path = "uploads/documents/" . $filename;
                    } else {
                        $error = "يجب أن تكون بطاقة الترشيح صورة (JPG, PNG).";
                    }
                } else {
                    $error = "يرجى رفع صورة بطاقة الترشيح.";
                }
            }

            if (!$error) {
                // 4. بنعمل كود التفعيل (أو الـ OTP) وبنشفر كلمة السر
                $otp = rand(100000, 999999);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $status = ($role === 'student') ? 'pending_verification' : 'suspended';

                try {
                    $pdo->beginTransaction();

                    // بنضيفه في جدول اليوزرات الأساسي
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password, full_name, email, role, college_id, phone, national_id, verification_code, status)
                        VALUES (:u, :p, :fn, :e, :r, :c, :ph, :nid, :otp, :s) RETURNING id
                    ");
                    $stmt->execute([
                        ':u' => $username,
                        ':p' => $hashed_password,
                        ':fn' => $full_name,
                        ':e' => $email,
                        ':r' => $role,
                        ':c' => $college_id,
                        ':ph' => $phone,
                        ':nid' => $national_id,
                        ':otp' => $otp,
                        ':s' => $status
                    ]);
                    $new_user_id = $stmt->fetchColumn();

                    // لو طالب، بنضيف بياناته في جدول الطلاب
                    if ($role === 'student' && $new_user_id) {
                        $stmt = $pdo->prepare("
                            INSERT INTO students (user_id, college_id, national_id, phone, nomination_card)
                            VALUES (:uid, :cid, :nid, :ph, :nc)
                        ");
                        $stmt->execute([
                            ':uid' => $new_user_id,
                            ':cid' => $college_id,
                            ':nid' => $national_id,
                            ':ph' => $phone,
                            ':nc' => $nomination_card_path
                        ]);
                    }

                    // 5. نبعت إيميل التفعيل
                    $mail = new MailController();
                    if ($mail->sendVerificationEmail($email, $otp)) {
                        $pdo->commit();
                        $_SESSION['pending_verification_email'] = $email;
                        $_SESSION['pending_verification_id'] = $new_user_id;
                        header("Location: verify_email.php");
                        exit;
                    } else {
                        $pdo->rollBack();
                        $error = "حدث خطأ أثناء إرسال إيميل التفعيل. يرجى مراجعة بيانات Gmail الخاصة بك.";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "حدث خطأ في النظام: " . $e->getMessage();
                }
            }
        }
    }
}

// بنجيب لستة الكليات عشان تظهر في الاختيارات
$colleges = $pdo->query("SELECT id, name FROM colleges ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب جديد - EDU Nexus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
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
        body { font-family: 'Cairo', sans-serif; background-color: #f8fafc; }
        .form-card { background: white; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .login-bg {
            background-color: #1e3a8a;
            background-image: url('assets/images/1.jpeg');
            background-size: cover;
            background-position: 30% 0%;
            background-repeat: no-repeat;
        }
        .login-bg-overlay {
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.3) 50%, rgba(0, 0, 0, 0) 100%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-bg p-4 md:p-8">

    <div class="flex flex-col md:flex-row w-full max-w-5xl bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100 min-h-[700px]">
        
        <!-- الجنب اللي فيه فورمة التسجيل (على اليسار) -->
        <div class="w-full md:w-1/2 lg:w-1/2 p-6 md:p-10 lg:p-12 bg-white order-2 md:order-1">
            <div class="w-full">
                <div class="flex justify-between items-center mb-8 gap-4">
                    <div class="text-right">
                        <h3 class="text-2xl font-black text-secondary mb-1">فتح حساب جديد</h3>
                        <p class="text-secondary opacity-60 text-xs">انضم لآلاف الطلاب في رحلتهم التعليمية</p>
                    </div>
                        <img src="assets/images/logo.png?v=1.2" alt="Logo" class="w-16 h-16 object-contain">
                </div>

                <?php if ($error): ?>
                    <div class="bg-rose-50 border-r-4 border-rose-500 text-rose-700 p-4 mb-6 rounded-xl text-sm font-bold flex items-center shadow-sm">
                        <i class="fas fa-exclamation-circle ml-3 text-lg"></i>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <form id="registrationForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    
                    <div class="bg-slate-50 p-4 rounded-[2rem] border border-slate-100 mb-8">
                        <label class="block text-slate-700 text-xs font-black mb-4 pr-2 border-r-4 border-primary">نوع الحساب (الدور)</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <label class="cursor-pointer group">
                                <input type="radio" name="role" value="student" class="hidden peer" checked onchange="toggleStudentFields(true)">
                                <div class="text-center p-3 border-2 border-slate-100 rounded-2xl peer-checked:border-primary peer-checked:bg-primary/5 group-hover:border-primary/30 transition-all">
                                    <i class="fas fa-user-graduate block text-xl mb-1 text-slate-400 peer-checked:text-primary"></i>
                                    <span class="text-[10px] font-black text-slate-500 peer-checked:text-primary">طالب</span>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="role" value="instructor" class="hidden peer" onchange="toggleStudentFields(false)">
                                <div class="text-center p-3 border-2 border-slate-100 rounded-2xl peer-checked:border-primary peer-checked:bg-primary/5 group-hover:border-primary/30 transition-all">
                                    <i class="fas fa-chalkboard-teacher block text-xl mb-1 text-slate-400 peer-checked:text-primary"></i>
                                    <span class="text-[10px] font-black text-slate-500 peer-checked:text-primary">دكتور</span>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="role" value="dean" class="hidden peer" onchange="toggleStudentFields(false)">
                                <div class="text-center p-3 border-2 border-slate-100 rounded-2xl peer-checked:border-primary peer-checked:bg-primary/5 group-hover:border-primary/30 transition-all">
                                    <i class="fas fa-user-tie block text-xl mb-1 text-slate-400 peer-checked:text-primary"></i>
                                    <span class="text-[10px] font-black text-slate-500 peer-checked:text-primary">عميد</span>
                                </div>
                            </label>
                            <label class="cursor-pointer group">
                                <input type="radio" name="role" value="affairs" class="hidden peer" onchange="toggleStudentFields(false)">
                                <div class="text-center p-3 border-2 border-slate-100 rounded-2xl peer-checked:border-primary peer-checked:bg-primary/5 group-hover:border-primary/30 transition-all">
                                    <i class="fas fa-user-shield block text-xl mb-1 text-slate-400 peer-checked:text-primary"></i>
                                    <span class="text-[10px] font-black text-slate-500 peer-checked:text-primary">شؤون</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-[11px] font-black mb-2 mr-2">الاسم الرباعي الكامل</label>
                            <div class="relative">
                                <i class="fas fa-user absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="full_name" required class="w-full pr-12 pl-4 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition bg-slate-50/50" placeholder="مثال: أحمد محمد علي حسن">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-[11px] font-black mb-2 mr-2">اسم المستخدم / رقم القيد</label>
                            <div class="relative">
                                <i class="fas fa-id-badge absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="username" required autocomplete="off" class="w-full pr-12 pl-4 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition bg-slate-50/50 font-mono tracking-wider" placeholder="رقم القيد الجامعي">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-[11px] font-black mb-2 mr-2">البريد الإلكتروني (Gmail)</label>
                            <div class="relative">
                                <i class="fas fa-envelope absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="email" name="email" required class="w-full pr-12 pl-4 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition bg-slate-50/50" placeholder="name@gmail.com">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-[11px] font-black mb-2 mr-2">كلمة المرور</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="password" name="password" required autocomplete="new-password" class="w-full pr-12 pl-4 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition bg-slate-50/50" placeholder="••••••••">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-[11px] font-black mb-2 mr-2">رقم الهاتف</label>
                            <div class="relative">
                                <i class="fas fa-phone absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="phone" required class="w-full pr-12 pl-4 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition bg-slate-50/50 font-mono" placeholder="01xxxxxxxxx">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-[11px] font-black mb-2 mr-2">الرقم القومي (14 رقم)</label>
                            <div class="relative">
                                <i class="fas fa-id-card absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="national_id" required maxlength="14" minlength="14" class="w-full pr-12 pl-4 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition bg-slate-50/50 font-mono" placeholder="29xxxxxxxxx...">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-[11px] font-black mb-2 mr-2">التبعية الأكاديمية (الكلية)</label>
                            <div class="relative">
                                <i class="fas fa-university absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <select name="college_id" required class="w-full pr-12 pl-4 py-4 rounded-2xl border border-slate-200 focus:ring-4 focus:ring-primary/10 focus:border-primary outline-none transition bg-slate-50/50 cursor-pointer appearance-none">
                                    <option value="0">اختر الكلية...</option>
                                    <?php foreach($colleges as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- رفع بطاقة الترشيح (بتبان للطلبة بس) -->
                        <div id="nomination-section" class="md:col-span-2">
                            <label class="block text-slate-700 text-[11px] font-black mb-4 mr-2 border-r-4 border-primary px-3">صورة بطاقة الترشيح أو المستند الرسمي</label>
                            <div class="relative border-4 border-dashed border-slate-100 rounded-[2.5rem] p-6 md:p-10 text-center hover:border-primary/30 hover:bg-primary/5 transition-all cursor-pointer group shadow-inner bg-slate-50/30">
                                <input type="file" name="nomination_card" id="nomination_card" class="absolute inset-0 opacity-0 cursor-pointer z-20" accept="image/*" onchange="previewImage(this)">
                                <div id="preview-container" class="transition-all duration-300 relative z-10">
                                    <div class="w-16 h-16 bg-white rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-xl text-primary group-hover:scale-110 group-hover:rotate-6 transition-all duration-500 ring-4 ring-slate-50">
                                        <i class="fas fa-cloud-upload-alt text-2xl"></i>
                                    </div>
                                    <p class="text-sm font-black text-slate-700">اضغط هنا أو اسحب الصورة لرفعها</p>
                                    <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest">JPG, PNG - Max 5MB</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- الجنب اللي فيه اللوجو والزرار (على اليمين) -->
        <div class="w-full md:w-1/2 lg:w-1/2 flex flex-col login-bg relative text-white order-1 md:order-2 min-h-[450px]">
            <div class="absolute inset-0 login-bg-overlay"></div>
            
            <div class="relative z-10 flex flex-col items-center w-full h-full justify-between p-8 md:p-10">
                <!-- الجزء العلوي (لوجو أو مساحة فاضية) -->
                <div class="w-full"></div>

                <!-- الجزء السفلي (النصوص والزرار) -->
                <div class="w-full flex flex-col items-center">
                    <div class="mb-8 text-center">
                        <h1 class="text-3xl md:text-4xl font-black mb-1 drop-shadow-2xl tracking-tight">EDU Nexus</h1>
                        <h2 class="text-sm md:text-xl font-medium text-white/90 mb-2 drop-shadow-xl">نظام التعليم الآمن</h2>
                        <div class="hidden md:block mt-3 pt-3 border-t border-white/20 w-full max-w-[100px] mx-auto">
                            <p class="text-[10px] font-bold text-white/60 tracking-widest uppercase opacity-80">بوابتك للتميز</p>
                        </div>
                    </div>

                    <!-- زرار الإرسال مرفوع لفوق شوية -->
                    <div class="w-full mb-4">
                        <button type="submit" form="registrationForm" class="w-full bg-primary hover:bg-bright text-white font-black py-5 rounded-2xl shadow-2xl shadow-black/30 transition-all transform active:scale-[0.98] flex items-center justify-center gap-4 text-sm lg:text-base border border-white/10 backdrop-blur-sm bg-opacity-90">
                            <span>إنشاء الحساب وتلقي كود التفعيل</span>
                            <i class="fas fa-arrow-left"></i>
                        </button>
                    </div>

                    <div class="text-center">
                        <p class="text-[11px] font-black text-white/80 drop-shadow-lg">
                            لديك حساب بالفعل؟ 
                            <a href="index.php" class="text-white hover:underline ml-1 border-b border-white/30">سجل دخولك من هنا</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleStudentFields(isStudent) {
            const section = document.getElementById('nomination-section');
            const fileInput = document.getElementById('nomination_card');
            if (isStudent) {
                section.classList.remove('hidden');
                fileInput.required = true;
            } else {
                section.classList.add('hidden');
                fileInput.required = false;
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-container').innerHTML = `
                        <div class="relative inline-block group">
                            <img src="${e.target.result}" class="h-40 mx-auto rounded-3xl shadow-xl mb-4 border-4 border-white">
                            <div class="absolute inset-0 bg-primary/20 rounded-3xl opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <i class="fas fa-check-circle text-white text-4xl"></i>
                            </div>
                        </div>
                        <p class="text-xs text-primary font-black">تم اختيار الصورة بنجاح</p>
                    `;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
