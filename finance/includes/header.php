<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/permissions.php';

/** @var PDO $pdo */
/** @var UniversityDB $db */

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'مستخدم';
$role      = $_SESSION['role'] ?? 'student';
$is_admin  = in_array($role, ['super_admin', 'admin', 'dean', 'affairs']);

// Navigation Logic for Finance Portal
$nav_items = [];
if ($role === 'student') {
    $nav_items = [
        ['group' => 'البوابة المالية', 'items' => [
            ['link' => 'index.php', 'icon' => 'fa-home', 'title' => 'نظرة عامة', 'match' => 'index'],
            ['link' => 'fees.php', 'icon' => 'fa-file-invoice-dollar', 'title' => 'الرسوم الدراسية', 'match' => 'fees'],
            ['link' => 'textbook_payments.php', 'icon' => 'fa-book-open', 'title' => 'مدفوعات الكتب', 'match' => 'textbook_payments'],
            ['link' => 'official_documents.php', 'icon' => 'fa-file-signature', 'title' => 'إصدار الوثائق', 'match' => 'official_documents'],
        ]]
    ];
} else if ($is_admin) {
    $nav_items = [
        ['group' => 'الإدارة المالية', 'items' => [
            ['link' => 'index.php', 'icon' => 'fa-chart-pie', 'title' => 'نظرة عامة', 'match' => 'index'],
            ['link' => 'manage_fees.php', 'icon' => 'fa-money-check-alt', 'title' => 'إدارة الرسوم', 'match' => 'manage_fees'],
            ['link' => 'fees.php', 'icon' => 'fa-users', 'title' => 'سدادات الطلاب', 'match' => 'fees'],
            ['link' => 'textbook_payments.php', 'icon' => 'fa-book', 'title' => 'مبيعات الكتب', 'match' => 'textbook_payments'],
            ['link' => 'official_documents.php', 'icon' => 'fa-file-contract', 'title' => 'توثيق المستندات', 'match' => 'official_documents'],
            ['link' => 'graduation_cert_review.php', 'icon' => 'fa-user-graduate', 'title' => 'طلبات التخرج', 'match' => 'graduation_cert_review'],
        ]]
    ];
}

$current_page = basename($_SERVER['PHP_SELF'], ".php");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="2HzF_ElwQcZSUBrFW16SrSS5xgBXJibSgXO-YQ4YLbI" />

    <title>EDU Nexus | البوابة المالية</title>
    <!-- SEO Meta Tags -->
    <meta name="description" content="EDU Nexus - البوابة المالية: نظام إدارة المصروفات الدراسية، دفع الكتب، وإصدار الوثائق الرسمية للطلاب بأمان وسهولة.">
    <meta name="keywords" content="EDU Nexus, البوابة المالية, مصروفات دراسية, دفع كتب, وثائق تخرج, نظام جامعي مالي">
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    
    <!-- Open Graph for Social Media -->
    <meta property="og:title" content="EDU Nexus | البوابة المالية">
    <meta property="og:description" content="أدر مدفوعاتك الدراسية واستخرج وثائقك الرسمية بسهولة من خلال بوابتنا المالية المتطورة.">
    <meta property="og:image" content="../assets/images/logo.png">
    <meta property="og:type" content="website">

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#0f4c81', dark: '#0a3356' },
                        gold: '#d4af37',
                        bg: '#f8fafc',
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --primary: #0f4c81;
            --primary-dark: #0a3356;
            --gold: #d4af37;
            --bg: #f8fafc;
        }
        body { font-family: 'Cairo', sans-serif; background: var(--bg); color: #1e293b; }
        .sidebar { width: 260px; background: var(--primary-dark); height: 100vh; position: fixed; right: 0; top: 0; z-index: 1000; box-shadow: -10px 0 30px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        nav { flex: 1; overflow-y: auto; overflow-x: hidden; }
        nav::-webkit-scrollbar { width: 4px; }
        nav::-webkit-scrollbar-track { background: transparent; }
        nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 15px; margin: 2px 10px; border-radius: 10px; color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600; transition: 0.3s; text-decoration: none; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: white; }
        .nav-item.active { background: var(--gold); color: var(--primary-dark); }
        .nav-group-title { padding: 15px 25px 5px; font-size: 9px; text-transform: uppercase; letter-spacing: 1.2px; color: rgba(255,255,255,0.3); font-weight: 800; }
        .main-content { margin-right: 260px; padding: 25px; min-height: 100vh; }
        header { background: white; padding: 16px 24px; border-radius: 20px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="p-6 mb-2 flex items-center gap-3">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg overflow-hidden p-1">
                <img src="../assets/images/logo.png" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <h2 class="text-white font-black text-base leading-tight">EDU Nexus</h2>
                <p class="text-[9px] text-white/40 uppercase tracking-widest">Finance Portal</p>
            </div>
        </div>

        <div class="bg-white/5 mx-3 p-3 rounded-xl border border-white/10 flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-lg bg-gold flex items-center justify-center text-primary-dark font-black text-xs">
                <?php echo mb_substr($full_name, 0, 1, 'UTF-8'); ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-white font-bold text-xs truncate"><?php echo htmlspecialchars($full_name); ?></p>
                <p class="text-[9px] text-white/40 uppercase"><?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>

        <nav>
            <?php foreach ($nav_items as $group): ?>
                <div class="nav-group-title"><?php echo $group['group']; ?></div>
                <?php foreach ($group['items'] as $item): ?>
                    <a href="<?php echo $item['link']; ?>" class="nav-item <?php echo ($current_page == $item['match']) ? 'active' : ''; ?>">
                        <i class="fas <?php echo $item['icon']; ?> w-5"></i>
                        <span><?php echo $item['title']; ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
            
            <div class="mt-4 pt-4 border-t border-white/5">
                <?php
                // محاولة حساب رابط البوابة الأكاديمية الرئيسية
                $main_url = "../dashboard.php";
                if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'finance.') !== false) {
                    $main_host = str_replace('finance.', '', $_SERVER['HTTP_HOST']);
                    $main_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $main_host . "/dashboard.php";
                }
                ?>
                <a href="<?php echo $main_url; ?>" class="nav-item text-white/60 hover:text-white py-2">
                    <i class="fas fa-arrow-right w-5"></i>
                    <span>العودة للأكاديمية</span>
                </a>
                <a href="logout.php" class="nav-item text-red-400 py-2">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <header>
            <div class="flex items-center gap-4">
                <h1 class="font-black text-xl text-primary-dark">البوابة المالية</h1>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-left hidden md:block">
                    <p class="text-xs font-black text-slate-400"><?php echo date('l, d F Y'); ?></p>
                    <p class="text-sm font-bold text-primary-dark">أهلاً بك، <?php echo htmlspecialchars($full_name); ?></p>
                </div>
            </div>
        </header>