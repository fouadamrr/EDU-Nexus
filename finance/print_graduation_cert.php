<?php
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die('معرف غير صحيح');

// جلب بيانات الطلب مع بيانات الطالب والكلية
$sql = "SELECT r.*, u.full_name, u.username, c.name as college_name, s.major, s.level, s.gpa, s.national_id, s.birth_date
        FROM graduation_certificate_requests r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN colleges c ON c.id = r.college_id
        LEFT JOIN students s ON s.user_id = u.id
        WHERE r.id = :id AND r.status = 'approved'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die('الطلب غير موجود أو لم يتم اعتماده نهائياً بعد.');

// حساب التقدير
$gpa = $data['gpa'];
if ($gpa >= 3.4) $grade = 'امتياز مع مرتبة الشرف';
elseif ($gpa >= 3.0) $grade = 'جيد جداً';
elseif ($gpa >= 2.4) $grade = 'جيد';
else $grade = 'مقبول';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>شهادة تخرج رسمية - <?php echo htmlspecialchars($data['full_name']); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Reem+Kufi:wght@400;700&display=swap');
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f1f1;
            display: flex;
            justify-content: center;
            font-family: 'Amiri', serif;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            body { 
                background: none; 
                margin: 0;
                padding: 0;
            }
            .a4-page { 
                box-shadow: none; 
                margin: 0; 
                border: none;
                width: 210mm;
                height: 296mm; /* تقليل 1 ملم للضمان */
            }
            .no-print { display: none; }
        }

        .a4-page {
            width: 210mm;
            height: 297mm;
            padding: 10mm;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
            position: relative;
            background-image: url('https://www.transparenttextures.com/patterns/paper-fibers.png');
        }

        /* البرواز الأزرق الرسمي */
        .outer-border {
            width: 100%;
            height: 100%;
            border: 8px double #4f46e5;
            padding: 5px;
            box-sizing: border-box;
        }

        .inner-border {
            width: 100%;
            height: 100%;
            border: 2px solid #4338ca;
            padding: 20px;
            box-sizing: border-box;
            position: relative;
        }

        /* الجزء العلوي (اللوجو والصورة) */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .student-photo-area {
            position: relative;
            width: 110px;
            height: 140px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .student-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-stamp {
            position: absolute;
            bottom: -15px;
            right: -15px;
            width: 70px;
            height: 70px;
            opacity: 0.6;
            mix-blend-mode: multiply;
            transform: rotate(-15deg);
        }

        .university-header {
            text-align: center;
            flex: 1;
        }

        .univ-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 5px;
        }

        .dept-info {
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            line-height: 1.5;
            color: #1e293b;
        }

        .cert-title {
            text-align: center;
            font-family: 'Reem Kufi', sans-serif;
            font-size: 38px;
            color: #1e1b4b;
            margin: 15px 0;
            border-bottom: 2px solid #4f46e5;
            display: inline-block;
            padding: 0 40px;
            margin-left: auto;
            margin-right: auto;
            display: block;
            width: fit-content;
        }

        /* محتوى الشهادة */
        .cert-body {
            margin-top: 30px;
            font-size: 20px;
            line-height: 2.3;
            text-align: justify;
            color: #334155;
        }

        .field-value {
            font-weight: bold;
            color: #1e1b4b;
            border-bottom: 1px dotted #94a3b8;
            padding: 0 10px;
            display: inline-block;
            min-width: 130px;
            text-align: center;
        }

        /* الأختام والتواقيع في الأسفل */
        .footer-sigs {
            margin-top: 80px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            text-align: center;
        }

        .sig-box {
            font-weight: bold;
            font-size: 16px;
            color: #1e293b;
        }

        .official-stamp-circle {
            position: absolute;
            bottom: 40px;
            right: 15px;
            width: 150px;
            height: 150px;
            border: 4px double #4f46e5; /* لون الختم */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            opacity: 0.7;
            mix-blend-mode: multiply;
            transform: rotate(-10deg);
            z-index: 10;
        }

        .stamp-inner-text {
            border: 2px solid #1e40af;
            border-radius: 50%;
            width: 110px;
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .btn-print {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #c5a059;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            z-index: 100;
        }
    </style>
</head>
<body>

    <button class="btn-print no-print" onclick="window.print()">طباعة الشهادة الرسمية</button>

    <div class="a4-page">
        <div class="outer-border">
            <div class="inner-border">
                
                <!-- Watermark Logo -->
                <div class="watermark">
                    <img src="assets/images/logo.png" style="width: 400px; opacity: 0.05; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none;">
                </div>

                <!-- الجزء العلوي -->
                <div class="header">
                    <div class="dept-info">
                        جامعة EDU Nexus الذكية<br>
                        كلية <?php echo htmlspecialchars($data['college_name']); ?><br>
                        إدارة شئون الخريجين
                    </div>
                    
                    <div class="university-header">
                        <img src="assets/images/logo.png" class="univ-logo">
                        <div style="font-weight: bold; font-size: 14px; margin-top: 5px; color: #4f46e5;">HH00<?php echo $data['id']; ?></div>
                    </div>

                    <div class="student-photo-area">
                        <?php if ($data['photo_path']): ?>
                            <img src="<?php echo htmlspecialchars($data['photo_path']); ?>" class="student-photo">
                        <?php endif; ?>
                        <!-- ختم فوق الصورة -->
                        <img src="https://upload.wikimedia.org/wikipedia/commons/3/3a/Jon_Arbuckle.svg" class="photo-stamp" style="display:none">
                        <!-- نستخدم SVG للختم ليكون أكثر واقعية -->
                        <div class="photo-stamp">
                            <svg viewBox="0 0 100 100" style="fill: #1e40af; opacity: 0.5;">
                                <circle cx="50" cy="50" r="45" fill="none" stroke="#1e40af" stroke-width="2" />
                                <text x="50%" y="50%" text-anchor="middle" font-size="10" dy=".3em">ختم الجامعة</text>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="cert-title">شهادة مـؤقـتـة</div>

                <div class="cert-body">
                    تشهد الكلية بأن السيد / <span class="field-value"><?php echo htmlspecialchars($data['full_name']); ?></span> <br>
                    بجهة: <span class="field-value"><?php echo htmlspecialchars($data['address'] ?? 'القاهرة'); ?></span> 
                    بطاقة (شخصية / عائلية) رقم: <span class="field-value"><?php echo htmlspecialchars($data['national_id'] ?? $data['username']); ?></span> <br>
                    صادرة من: <span class="field-value">سجل مدني الذكية</span> بتاريخ: <span class="field-value"><?php echo date('d / m / Y', strtotime($data['birth_date'] ?? 'now')); ?></span> وجنسيته: <span class="field-value">مصري</span> <br>
                    حصل على درجة <span class="field-value">بكالوريوس في <?php echo htmlspecialchars($data['college_name']); ?></span> في: <span class="field-value"><?php echo htmlspecialchars($data['major'] ?? 'التخصص العام'); ?></span> <br>
                    بتقدير: <span class="field-value"><?php echo $grade; ?></span> دور: <span class="field-value">مايو <?php echo date('Y'); ?>م</span> مركز: <span class="field-value">الجامعة الذكية</span> <br>
                    تاريخ موافقة أ.د عميد الكلية بناءً على التفويض الصادر من مجلس الكلية رقم: <span class="field-value">145</span> <br>
                    بتاريخ: <span class="field-value"><?php echo date('d / m / Y', strtotime($data['dean_reviewed_at'] ?? 'now')); ?></span> و مجلس الجامعة بتاريخ: <span class="field-value"><?php echo date('d / m / Y', strtotime($data['dean_reviewed_at'] ?? 'now')); ?></span> <br>
                    وتحررت هذه الشهادة بناءً على طلبه لتقديمها إلى: <span class="field-value">من يهمه الأمر</span> <br>
                    وعلى الجهة المقدم إليها هذه الشهادة التحقق من أن مقدمها هو صاحب الشهادة. <br>
                    <div style="margin-top: 15px; font-size: 18px; border-top: 1px solid #ddd; padding-top: 10px;">
                        وسددت رسوم البراءة بالقسيمة رقم: <span class="field-value"><?php echo htmlspecialchars($data['clearance_receipt'] ?? 'ــــــــــــــــ'); ?></span> بتاريخ: <span class="field-value"><?php echo date('d / m / Y'); ?></span> <br>
                        وسددت رسوم الشهادة بالقسيمة رقم: <span class="field-value"><?php echo htmlspecialchars($data['certificate_receipt'] ?? 'ــــــــــــــــ'); ?></span> بتاريخ: <span class="field-value"><?php echo date('d / m / Y'); ?></span>
                    </div>
                </div>

                <!-- الأختام والتواقيع -->
                <div class="footer-sigs">
                    <div class="sig-box">
                        الموظف المختص<br>
                        <div style="margin-top: 15px; border-top: 1px solid #333; width: 120px; margin-left: auto; margin-right: auto;"></div>
                    </div>
                    <div class="sig-box">
                        مدير الإدارة<br>
                        <div style="margin-top: 15px; border-top: 1px solid #333; width: 120px; margin-left: auto; margin-right: auto;"></div>
                    </div>
                    <div class="sig-box">
                        مدير عام الكلية<br>
                        <div style="margin-top: 15px; border-top: 1px solid #333; width: 120px; margin-left: auto; margin-right: auto;"></div>
                    </div>
                    <div class="sig-box">
                        عميد الكلية<br>
                        أ.د / <?php echo htmlspecialchars($data['dean_username'] ?? 'ــــــــــــــــــــــــ'); ?>
                        <div style="margin-top: 15px; border-top: 1px solid #333; width: 120px; margin-left: auto; margin-right: auto;"></div>
                    </div>
                </div>

                <!-- الختم الدائري الأزرق -->
                <div class="official-stamp-circle">
                    <div class="stamp-inner-text">
                        <img src="assets/images/logo.png" style="width: 50px; height: 50px; object-fit: contain; margin-bottom: 5px;">
                        <span style="font-size: 10px;">إدارة الخريجين</span>
                        <span style="font-size: 8px;">جامعة EDU Nexus</span>
                    </div>
                </div>

                <div style="position: absolute; bottom: 20px; left: 40px; font-size: 12px; color: #888;">
                    تحريراً في: <?php echo date('d / m / Y'); ?>م
                </div>

            </div>
        </div>
    </div>

</body>
</html>

