<?php
// models/Department.php
require_once __DIR__ . '/Model.php';

class Department extends Model {
 protected string $table = 'departments';

 public function findByCollege(int $college_id): array {
 return $this->findAll(['college_id' => $college_id], 'name ASC');
 }

 public function getCollegeName(int $college_id): string {
 $stmt = $this->db->prepare("SELECT name FROM colleges WHERE id = ?");
 $stmt->execute([$college_id]);
 return $stmt->fetchColumn() ?: 'غير معروف';
 }
}
