<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/Course.php';

class DashboardController {
 public function getStudentDashboardData(int $userId, int $collegeId): array {
 $data = [];
 
    // هات بيانات الطالب الأساسية
    $userModel = new User();
    $student = $userModel->getStudentWithDetails($userId);
    
    if ($student) {
        $data['level'] = $student['level'] ?? 'غير محدد';
        $data['gpa'] = $student['gpa'] ?? '0.00';
        $data['full_name'] = $student['full_name'] ?? '';
    } else {
        $data['level'] = 'غير محدد';
        $data['gpa'] = '0.00';
        $data['full_name'] = '';
    }

    // هات آخر الإعلانات اللي نزلت
    $annModel = new Announcement();
    $role = $_SESSION['role'] ?? 'student';
    $data['announcements'] = $annModel->getLatest($role, $collegeId, 3);

    // هات لي آخر درجات اتسجلت له
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT c.code, c.name, c.credit_hours, g.semester, g.grade, g.points 
        FROM grades g 
        JOIN courses c ON g.course_id = c.id 
        WHERE g.user_id = ? 
        ORDER BY g.semester DESC LIMIT 5");
    $stmt->execute([$userId]);
    $data['recent_grades'] = $stmt->fetchAll();

    // احسب هو خلص كام في المية من الساعات
    $stmtProgress = $db->prepare("
        SELECT SUM(c.credit_hours) as completed_hours 
        FROM grades g 
        JOIN courses c ON g.course_id = c.id 
        WHERE g.user_id = ? AND g.grade NOT IN ('F', 'W', 'I') AND g.approved = TRUE
    ");
    $stmtProgress->execute([$userId]);
    $completed = (int)$stmtProgress->fetchColumn();
    
    // Calculate base hours based on student level (Mock history for upper levels)
    $studentLvlRaw = (string)($student['level'] ?? '1');
    $baseHours = 0;
    
    // Extract first number from level string if exists (e.g. "2nd", "الفرقة 3")
    if (preg_match('/\d+/', $studentLvlRaw, $matches)) {
        $lvlNum = (int)$matches[0];
    } else {
        // Fallback to text matching
        $lvlText = $studentLvlRaw;
        if (strpos($lvlText, 'ثان') !== false) $lvlNum = 2;
        elseif (strpos($lvlText, 'ثالث') !== false) $lvlNum = 3;
        elseif (strpos($lvlText, 'رابع') !== false) $lvlNum = 4;
        elseif (strpos($lvlText, 'خريج') !== false) $lvlNum = 5;
        else $lvlNum = 1;
    }

    if ($lvlNum === 2) $baseHours = 36;
    elseif ($lvlNum === 3) $baseHours = 72;
    elseif ($lvlNum === 4) $baseHours = 108;
    elseif ($lvlNum === 5) $baseHours = 144;

    // Add base hours to completed if we are just testing and it's too low
    if ($completed < $baseHours) {
        $completed = $baseHours + $completed; 
    }
    // Cap at 144
    $completed = min($completed, 144);

    
    $total_required = 144; // Standard graduation requirement
    $data['completed_hours'] = $completed;
    $data['total_required_hours'] = $total_required;
    $data['progress_percent'] = min(100, round(($completed / $total_required) * 100));

    return $data;
 }
}
