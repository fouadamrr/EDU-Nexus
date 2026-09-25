<?php
ob_start();
require_once 'includes/header.php';
$header_html = ob_get_clean();

require_once __DIR__ . '/controllers/MilitaryController.php';
$controller = new MilitaryController();

// Check permissions
$isAdmin = in_array($role, ['super_admin', 'admin', 'dean', 'affairs']);
$target_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;

if (!$isAdmin && $target_id !== $user_id) {
    die("Unauthorized access.");
}

function getGenderFromID($national_id) {
    if (!$national_id || strlen($national_id) < 14) return 'غير محدد';
    $digit = (int)$national_id[12]; // 13th digit (index 12)
    return ($digit % 2 === 0) ? 'أنثى' : 'ذكر';
}

$results = [];
if (isset($_GET['bulk']) && $isAdmin) {
    $all = $controller->getRegistrations();
    foreach ($all as $reg) {
        if (in_array($reg['status'], ['approved', 'passed'])) {
            $reg['gender'] = getGenderFromID($reg['national_id']);
            $results[] = $reg;
        }
    }
    // Group Boys first
    $boys = array_filter($results, function($r) { return $r['gender'] === 'ذكر'; });
    $girls = array_filter($results, function($r) { return $r['gender'] === 'أنثى'; });
    $others = array_filter($results, function($r) { return !in_array($r['gender'], ['ذكر', 'أنثى']); });
    $results = array_merge($boys, $girls, $others);
} else {
    $reg = $controller->getStudentRegistration($target_id);
    if ($reg) {
        $reg['gender'] = getGenderFromID($reg['national_id']);
        $results[] = array_merge($reg, [
            'full_name' => ($target_id === $user_id) ? $full_name : ($reg['full_name'] ?? 'طالب'),
            'username' => ($target_id === $user_id) ? $username : ($reg['username'] ?? ''),
            'session_title' => $reg['title'] ?? 'دورة غير محددة'
        ]);
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>ملف طلب التربية العسكرية - EDU Nexus</title>
    <style>
        @page {
            size: A4;
            margin: 10mm 15mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; background: #fff; }
            .print-page {
                page-break-after: always;
                height: 250mm; /* Reduced to ensure it fits within A4 printable area */
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box;
            }
            .gender-separator { display: none; }
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 20px 0;
        }
        .container {
            max-width: 850px;
            margin: 0 auto;
        }
        .print-page {
            background: #fff;
            padding: 30px;
            margin-bottom: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            position: relative;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #1e3a8a;
            margin: 0;
            font-size: 22px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #edf2f7;
        }
        .info-item {
            font-size: 14px;
            color: #475569;
        }
        .info-item b {
            color: #1a202c;
            margin-left: 5px;
        }
        .document-image-box {
            width: 90%;
            margin-inline: auto;
            height: 450px; /* Adjusted height for better balance with reduced width */
            border: 2px solid #2563eb;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #fff;
            padding: 12px;
            position: relative;
            overflow: hidden;
        }
        .document-image-box img {
            max-width: 100%;
            max-height: calc(100% - 30px);
            object-fit: contain;
            margin-top: 5px;
        }
        .image-label {
            font-size: 13px;
            font-weight: 800;
            color: #1e3a8a;
            background: #eff6ff;
            padding: 4px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border: 1px solid #dbeafe;
        }
        .footer-sigs {
            margin-top: 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #1e293b;
        }
        .signature-line {
            margin-top: 50px;
            border-bottom: 2px dashed #94a3b8;
            width: 70%;
            margin-inline: auto;
        }
        .page-num {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            color: #94a3b8;
        }
        .gender-separator {
            background: #1e3a8a;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="no-print bg-white p-6 rounded-xl shadow-lg mb-8 border border-slate-200 text-center">
        <h2 class="text-xl font-bold mb-4 text-primary">معاينة ملفات التقديم للطباعة (نسخة الورقتين)</h2>
        <div class="flex items-center justify-center gap-4">
            <button onclick="window.print()" class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-opacity-90 transition shadow-lg flex items-center gap-2">
                <i class="fas fa-print"></i> بدء الطباعة (صفحتان لكل طالب)
            </button>
            <a href="military.php" class="bg-gray-100 text-slate-600 px-6 py-3 rounded-xl font-bold hover:bg-gray-200 transition">العودة</a>
        </div>
        <p class="text-slate-400 text-[11px] mt-3">تم تقسيم الملف لورقتين لضمان وضوح الصور وعدم تداخل البيانات.</p>
    </div>

    <?php 
    $current_gender = null;
    foreach ($results as $res): 
        if (isset($_GET['bulk']) && $current_gender !== $res['gender']):
            $current_gender = $res['gender'];
            $gender_label = $current_gender === 'ذكر' ? 'مـلـفـات الـطـلاب (الـبـنـيـن)' : 'مـلـفـات الـطـالـبـات (الـبـنـات)';
    ?>
        <div class="gender-separator no-print">
            <i class="fas <?php echo $current_gender === 'ذكر' ? 'fa-male' : 'fa-female'; ?> ml-2"></i>
            <?php echo $gender_label; ?>
        </div>
    <?php endif; ?>

    <!-- PAGE 1: Data + ID Card -->
    <div class="print-page">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <img src="assets/images/logo.png" alt="Logo" style="height: 35px; width: 35px; object-fit: contain;">
                    <span style="font-size: 13px; font-weight: 800; color: #1e3a8a;">EDU Nexus</span>
                </div>
                <span style="font-size: 11px; color: #64748b;">إدارة التربية العسكرية</span>
            </div>
            <h1>استمارة بيانات الطالب - دورة التربية العسكرية</h1>
            <p style="font-size: 13px; color: #475569; margin-top: 5px; font-weight: bold;">
                <?php echo htmlspecialchars($res['session_title']); ?>
            </p>
        </div>

        <div class="info-grid">
            <div class="info-item"><b>اسم الطالب:</b> <?php echo htmlspecialchars($res['full_name']); ?></div>
            <div class="info-item"><b>النوع:</b> <?php echo $res['gender']; ?></div>
            <div class="info-item"><b>الكلية:</b> <?php echo htmlspecialchars($res['college_name'] ?? 'غير محددة'); ?></div>
            <div class="info-item"><b>رقم القيد:</b> <span style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($res['username']); ?></span></div>
            <div class="info-item"><b>تاريخ التقديم:</b> <?php echo date('Y-m-d', strtotime($res['created_at'])); ?></div>
            <div class="info-item"><b>الرقم القومي:</b> <span style="font-family: monospace;"><?php echo htmlspecialchars($res['national_id'] ?? 'غير مسجل'); ?></span></div>
        </div>

        <div class="document-image-box">
            <?php if ($res['id_card_path']): ?>
                <img src="<?php echo $res['id_card_path']; ?>" alt="ID Card">
            <?php else: ?>
                <div style="margin: auto; color: #cbd5e1; font-size: 13px;">[ لم يتم رفع صورة البطاقة ]</div>
            <?php endif; ?>
        </div>
        
        <div class="page-num">الصفحة ١ من ٢</div>
    </div>

    <!-- PAGE 2: Uni Card + Signatures -->
    <div class="print-page">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <img src="assets/images/logo.png" alt="Logo" style="height: 35px; width: 35px; object-fit: contain;">
                    <span style="font-size: 13px; font-weight: 800; color: #1e3a8a;">EDU Nexus</span>
                </div>
                <span style="font-size: 11px; color: #64748b;">إدارة التربية العسكرية</span>
            </div>
            <h1>تكملة الملف - استمارة الطالب</h1>
            <p style="font-size: 13px; color: #475569; margin-top: 5px; font-weight: 0;"><?php echo htmlspecialchars($res['full_name']); ?> - <?php echo htmlspecialchars($res['username']); ?></p>
        </div>

        <div class="document-image-box">
            <?php if ($res['uni_card_path']): ?>
                <img src="<?php echo $res['uni_card_path']; ?>" alt="University Card">
            <?php else: ?>
                <div style="margin: auto; color: #cbd5e1; font-size: 13px;">[ لم يتم رفع صورة الكارنيه ]</div>
            <?php endif; ?>
        </div>

        <div class="footer-sigs">
            <div>
                <p>توقيع الطالب المقر بصحة البيانات</p>
                <div class="signature-line"></div>
            </div>
            <div>
                <p>ختم وتوقيع إدارة التربية العسكرية</p>
                <div class="signature-line"></div>
            </div>
        </div>

        <div class="page-num">الصفحة ٢ من ٢</div>
    </div>

    <?php endforeach; ?>
</div>

</body>
</html>
<?php exit; ?>
