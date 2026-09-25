<?php
require_once 'db.php';

// التأكد من الصلاحيات (شؤون، عميد، أدمن)
if (!in_array($_SESSION['role'] ?? '', ['admin', 'dean', 'affairs'])) {
    die('غير مصرح لك بدخول هذه الصفحة.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die('معرف الطلب غير صحيح.');
}

// جلب بيانات الطلب مع بيانات الطالب
$sql = "SELECT r.*, u.full_name, u.username, c.name as college_name, u.email
        FROM graduation_certificate_requests r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN colleges c ON c.id = r.college_id
        WHERE r.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    die('الطلب غير موجود.');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مراجعة مستندات - <?php echo htmlspecialchars($req['full_name']); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Outfit:wght@400;700&display=swap');
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
        }

        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-radius: 16px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .college-info h1 {
            margin: 0;
            color: #1e293b;
            font-size: 24px;
        }

        .college-info p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .request-badge {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: bold;
            color: #475569;
        }

        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
            background: #fdfdfd;
            border: 1px solid #f1f5f9;
            padding: 20px;
            border-radius: 12px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 600;
            color: #334155;
        }

        .document-section {
            margin-top: 40px;
        }

        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .doc-title::before {
            content: '';
            width: 4px;
            height: 16px;
            background: #6366f1;
            border-radius: 2px;
        }

        .doc-image-wrapper {
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            margin-bottom: 30px;
        }

        .doc-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .print-container {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                max-width: none;
            }
            .no-print {
                display: none;
            }
            .page-break {
                page-break-before: always;
            }
        }

        .floating-actions {
            position: fixed;
            bottom: 30px;
            left: 30px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn-print {
            background: #6366f1;
            color: white;
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <div class="college-info">
                <h1><?php echo htmlspecialchars($req['college_name']); ?></h1>
                <p>مراجعة مستندات طلب شهادة التخرج</p>
            </div>
            <div class="request-badge">
                طلب رقم #<?php echo $req['id']; ?>
            </div>
        </div>

        <!-- Student Data -->
        <div class="student-details">
            <div class="detail-item">
                <span class="detail-label">اسم الطالب</span>
                <span class="detail-value"><?php echo htmlspecialchars($req['full_name']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">الرقم القومي / كود الطالب</span>
                <span class="detail-value"><?php echo htmlspecialchars($req['username']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">نوع الطلب</span>
                <span class="detail-value"><?php echo $req['is_reissue'] ? 'إعادة إصدار' : 'إصدار أول مرة'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">تاريخ التقديم</span>
                <span class="detail-value"><?php echo date('d/m/Y', strtotime($req['created_at'])); ?></span>
            </div>
        </div>

        <!-- Documents -->
        <div class="document-section">
            <div class="doc-title">الصورة الشخصية للطلاب</div>
            <div class="doc-image-wrapper">
                <?php if ($req['photo_path']): ?>
                    <img src="<?php echo htmlspecialchars($req['photo_path']); ?>" class="doc-image" alt="الصورة الشخصية">
                <?php else: ?>
                    <p style="color: #94a3b8;">لم يتم رفع ملف</p>
                <?php endif; ?>
            </div>

            <div class="page-break"></div>

            <div class="doc-title">صورة البطاقة الشخصية (الرقم القومي)</div>
            <div class="doc-image-wrapper">
                <?php if ($req['national_id_path']): ?>
                    <img src="<?php echo htmlspecialchars($req['national_id_path']); ?>" class="doc-image" alt="البطاقة الشخصية">
                <?php else: ?>
                    <p style="color: #94a3b8;">لم يتم رفع ملف</p>
                <?php endif; ?>
            </div>

            <div class="page-break"></div>

            <div class="doc-title">صورة شهادة الميلاد</div>
            <div class="doc-image-wrapper">
                <?php if ($req['birth_cert_path']): ?>
                    <img src="<?php echo htmlspecialchars($req['birth_cert_path']); ?>" class="doc-image" alt="شهادة الميلاد">
                <?php else: ?>
                    <p style="color: #94a3b8;">لم يتم رفع ملف</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="floating-actions no-print">
        <button onclick="window.close()" class="btn btn-back">إغلاق المعاينة</button>
        <button onclick="window.print()" class="btn btn-print">طباعة / حفظ كـ PDF</button>
    </div>

    <script>
        // فتح حوار الطباعة تلقائياً بعد التحميل
        window.onload = function() {
            // setTimeout(function() { window.print(); }, 500);
        };
    </script>

</body>
</html>

