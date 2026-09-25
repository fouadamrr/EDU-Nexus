<?php
require_once 'includes/header.php';

if (!in_array($role, ['super_admin', 'admin', 'dean', 'affairs'])) {
 echo "<script>window.location.href='index.php';</script>";
 exit;
}
require_permission('exams');

// Auto-create exam tables/columns
try {
 $pdo->exec("CREATE TABLE IF NOT EXISTS exam_committees (
 id SERIAL PRIMARY KEY,
 name VARCHAR(255) NOT NULL,
 location VARCHAR(255),
 capacity INT NOT NULL DEFAULT 30,
 current_count INT DEFAULT 0
 )");
 $pdo->exec("CREATE TABLE IF NOT EXISTS exam_distributions (
 id SERIAL PRIMARY KEY,
 student_id INT NOT NULL UNIQUE,
 committee_id INT NOT NULL,
 seat_number INT NOT NULL,
 exam_number VARCHAR(20),
 FOREIGN KEY (committee_id) REFERENCES exam_committees(id) ON DELETE CASCADE
 )");
 try { $pdo->exec("ALTER TABLE exam_distributions ADD COLUMN IF NOT EXISTS exam_number VARCHAR(20)"); } catch(Exception $e){}
} catch (Exception $e) {}

$college_scope = in_array($role, ['dean', 'affairs']) ? ($_SESSION['college_id'] ?? null) : null;
$filter_committee = isset($_GET['committee']) && $_GET['committee'] !== '' ? (int)$_GET['committee'] : null;
$filter_level = isset($_GET['level']) && $_GET['level'] !== '' ? trim($_GET['level']) : null;
$filter_college = isset($_GET['college']) && $_GET['college'] !== '' ? (int)$_GET['college'] : $college_scope;

$committees_list = $pdo->query("SELECT id, name FROM exam_committees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$colleges_list = !$college_scope ? $pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) : [];
$levels_all = $pdo->query("SELECT DISTINCT level::TEXT FROM students WHERE level IS NOT NULL ORDER BY level")->fetchAll(PDO::FETCH_COLUMN);

$sql = "
 SELECT 
 d.seat_number,
 COALESCE(d.exam_number, LPAD(d.seat_number::TEXT, 4, '0')) AS exam_number,
 u.full_name,
 u.username AS reg_number,
 ec.name AS committee_name,
 ec.location AS committee_location,
 s.level,
 s.major,
 col.name AS college_name,
 s.college_id
 FROM exam_distributions d
 JOIN exam_committees ec ON d.committee_id = ec.id
 JOIN users u ON d.student_id = u.id
 LEFT JOIN students s ON u.id = s.user_id
 LEFT JOIN colleges col ON s.college_id = col.id
 WHERE 1=1
";
$params = [];
if ($filter_committee) { $sql .= " AND d.committee_id = ?"; $params[] = $filter_committee; }
if ($filter_level !== null && $filter_level !== '') { $sql .= " AND s.level::TEXT = ?"; $params[] = $filter_level; }
if ($filter_college) { $sql .= " AND s.college_id = ?"; $params[] = $filter_college; }
$sql .= " ORDER BY ec.name, d.seat_number";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logo_path = 'assets/images/logo.png';
$logo_b64 = '';
if (file_exists($logo_path)) {
 $logo_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
@page {
 size: A4 portrait;
 margin: 8mm; /* Standard safe margin for printers */
}

@media print {
 body { 
 background: #fff !important; 
 margin: 0; padding: 0; 
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
 }
 header, #sidebar, #sidebarBackdrop, .no-print, aside { display: none !important; }
 div[class*="pr-72"] { padding-right: 0 !important; }
 main { padding: 0 !important; margin: 0 !important; width: 100% !important; }
 #cards-grid {
 display: grid !important;
 grid-template-columns: 95mm 95mm !important; /* Reduced width for safer margins */
 gap: 4mm 4mm !important; 
 padding: 0 !important;
 margin: 0 auto !important;
 width: 194mm !important;
 justify-content: center;
 }
 .id-card {
 break-inside: avoid;
 page-break-inside: avoid;
 border: 1.5px solid #2563eb !important;
 box-shadow: none !important;
 height: 50mm !important; 
 width: 95mm !important; /* Fixed width (9.5cm) */
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
 position: relative;
 overflow: hidden;
 }
}

#cards-grid {
 display: grid;
 grid-template-columns: repeat(2, 1fr);
 gap: 15px;
 padding: 10px;
}

.id-card {
 border: 1.5px solid #2563eb;
 border-radius: 8px;
 overflow: hidden;
 background: #fff;
 position: relative;
 font-family: 'Cairo', sans-serif;
 direction: rtl;
 height: 50mm;
 width: 100%;
 max-width: 95mm;
 margin: 0 auto;
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
}

.card-header {
 background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
 padding: 4px 10px;
 display: flex;
 align-items: center;
 gap: 6px;
 height: 30px;
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
}

.card-logo {
 width: 22px;
 height: 22px;
 border-radius: 4px;
 background: #FFFFFF !important;
 display: flex;
 align-items: center;
 justify-content: center;
 padding: 2px;
 flex-shrink: 0;
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
}

.card-uni-name {
 color: #FFFFFF !important;
 font-size: 8px;
 font-weight: 800;
 line-height: 1;
}

.card-title-badge {
 display: block;
 color: #fff;
 font-size: 6px;
 font-weight: 700;
 opacity: 0.85;
 margin-top: 1px;
}

.exam-number-section {
 background: #f8faff !important;
 border-bottom: 1px solid #e2e8f0;
 padding: 4px 10px;
 display: flex;
 align-items: center;
 justify-content: space-between;
 height: 34px;
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
}

.exam-number-label {
 font-size: 6.5px;
 color: #64748b;
 font-weight: 800;
}

.exam-number-value {
 font-size: 15px;
 font-weight: 900;
 color: #2563eb !important;
 font-family: 'Courier New', monospace;
 line-height: 1;
}

.seat-badge {
 background: #2563eb !important;
 color: white !important;
 padding: 2px 5px;
 border-radius: 4px;
 text-align: center;
 min-width: 45px;
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
}

.seat-badge-num {
 font-size: 10px;
 font-weight: 900;
 display: block;
 line-height: 1;
}

.card-body {
 padding: 4px 10px;
 display: flex;
 flex-direction: column;
 gap: 2px;
}

.card-field {
 display: flex;
 align-items: center;
 gap: 4px;
}

.card-field-icon {
 width: 12px;
 height: 12px;
 background: #f1f5f9 !important;
 border-radius: 3px;
 display: flex;
 align-items: center;
 justify-content: center;
 color: #2563eb !important;
 font-size: 6px;
 flex-shrink: 0;
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
}

.card-field-label {
 font-size: 6px;
 color: #94a3b8;
 font-weight: 700;
 line-height: 1;
}

.card-field-value {
 font-size: 8px;
 color: #1e293b !important;
 font-weight: 800;
 line-height: 1;
 white-space: nowrap;
 overflow: hidden;
 text-overflow: ellipsis;
}

.committee-highlight {
 background: #eff6ff !important; 
 border-radius: 5px; 
 padding: 2px 6px;
 margin-top: 2px;
 -webkit-print-color-adjust: exact !important; 
 print-color-adjust: exact !important;
}

.card-corner {
 position: absolute;
 bottom: 0;
 left: 0;
 width: 30px;
 height: 30px;
 background: linear-gradient(135deg, transparent 50%, rgba(37,99,235,0.04) 50%);
}
</style>
</head>
<body>

<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Control Panel -->
    <div class="no-print bg-white p-6 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-secondary flex items-center gap-3">
                    <span class="w-10 h-10 bg-indigo-50 text-primary rounded-xl flex items-center justify-center">
                        <i class="fas fa-id-card"></i>
                    </span>
                    بطاقات الجلوس المعيارية (9.5 × 5 سم)
                </h1>
                <p class="text-slate-500 text-sm mt-1">تنسيق آمن للهوامش: 9.5 سم عرض × 5 سم ارتفاع | الورقة A4 Portrait</p>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="bg-primary text-white px-8 py-3 rounded-xl font-bold hover:bg-primary transition shadow-xl flex items-center gap-2">
                    <i class="fas fa-print"></i> طباعة (<?php echo count($cards); ?>)
                </button>
            </div>
        </div>
    </div>

    <!-- Cards Grid -->
    <div id="cards-grid">
        <?php foreach ($cards as $card): ?>
        <div class="id-card">
            <!-- Compact Header -->
            <div class="card-header">
                <div class="card-logo">
                    <?php if ($logo_b64): ?>
                        <img src="<?php echo $logo_b64; ?>" alt="Logo" style="width:100%; height:100%; object-fit:contain;">
                    <?php else: ?>
                        <i class="fas fa-university" style="color:#2563eb; font-size:10px;"></i>
                    <?php endif; ?>
                </div>
                <div style="flex:1;">
                    <div class="card-uni-name"><?php echo htmlspecialchars($card['college_name'] ?? 'الجامعة'); ?></div>
                    <span class="card-title-badge">بطاقة الجلوس الامتحانية</span>
                </div>
                <div style="text-align:left;">
                    <div style="color:rgba(255,255,255,0.7); font-size:6px; font-weight:700;">الفرقة</div>
                    <div style="color:#fff; font-size:10px; font-weight:900; line-height:1;"><?php echo htmlspecialchars($card['level'] ?: '—'); ?></div>
                </div>
            </div>

            <!-- Compact Exam/Seat Section -->
            <div class="exam-number-section">
                <div>
                    <div class="exam-number-label">الرقم الامتحاني</div>
                    <div class="exam-number-value" style="<?php echo strlen($card['exam_number']) > 6 ? 'font-size:12px;' : ''; ?>"><?php echo htmlspecialchars($card['exam_number']); ?></div>
                </div>
                <div class="seat-badge">
                    <span style="font-size:6px; font-weight:700; opacity:0.85; display:block;">رقم الجلوس</span>
                    <span class="seat-badge-num"><?php echo (string)$card['seat_number']; ?></span>
                </div>
            </div>

            <!-- Highly Compact Body -->
            <div class="card-body">
                <div class="card-field">
                    <div class="card-field-icon"><i class="fas fa-user"></i></div>
                    <div style="flex:1; overflow:hidden;">
                        <div class="card-field-label">اسم الطالب</div>
                        <div class="card-field-value"><?php echo htmlspecialchars($card['full_name']); ?></div>
                    </div>
                </div>

                <div style="display:flex; gap:6px;">
                    <div class="card-field" style="flex:1">
                        <div class="card-field-icon" style="background:#fff7ed !important; color:#c2410c !important;"><i class="fas fa-university"></i></div>
                        <div style="flex:1; overflow:hidden;">
                            <div class="card-field-label">الكلية</div>
                            <div class="card-field-value"><?php echo htmlspecialchars($card['college_name'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="card-field" style="flex:1">
                        <div class="card-field-icon" style="background:#fef3c7 !important; color:#92400e !important;"><i class="fas fa-id-card"></i></div>
                        <div style="flex:1; overflow:hidden;">
                            <div class="card-field-label">رقم القيد</div>
                            <div class="card-field-value" style="font-family:monospace;"><?php echo htmlspecialchars($card['reg_number']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Line 3: Committee -->
                <div class="card-field committee-highlight">
                    <div class="card-field-icon" style="background:#dbeafe !important; color:#1d4ed8 !important;"><i class="fas fa-school"></i></div>
                    <div style="flex:1; overflow:hidden;">
                        <div class="card-field-value" style="color:#1d4ed8 !important; font-size:7.5px;">
                            <?php echo htmlspecialchars($card['committee_name']); ?> — <?php echo htmlspecialchars($card['committee_location'] ?: '—'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>

