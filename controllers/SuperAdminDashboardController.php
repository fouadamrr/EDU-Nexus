<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/College.php';

class SuperAdminDashboardController {
 public function getDashboardStats(): array {
 $userModel = new User();
 $collegeModel = new College();
 $db = Database::getConnection();

 // Colleges
 $colleges = $collegeModel->findAll();
 $total_colleges = count($colleges);

 // Map counts
 $roleCounts = [];
 foreach ($db->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role") as $r) {
 $roleCounts[$r['role']] = (int)$r['cnt'];
 }

 $statusCounts = [];
 foreach ($db->query("SELECT status, COUNT(*) AS cnt FROM users GROUP BY status") as $s) {
 $statusCounts[$s['status'] ?? 'active'] = (int)$s['cnt'];
 }

 $colleges_data = $collegeModel->getStudentCounts();

 $stats = [
 'students' => $roleCounts['student'] ?? 0,
 'instructors' => $roleCounts['instructor'] ?? 0,
 'deans' => $roleCounts['dean'] ?? 0,
 'affairs' => $roleCounts['affairs'] ?? 0,
 'active' => $statusCounts['active'] ?? 0,
 'suspended' => $statusCounts['suspended'] ?? 0,
 'colleges_data' => $colleges_data,
 ];

 return [
 'total_colleges' => $total_colleges,
 'stats' => $stats
 ];
 }
}
