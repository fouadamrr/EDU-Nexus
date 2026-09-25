<?php
require_once __DIR__ . '/Model.php';

class StudentDetail extends Model {
 protected string $table = 'students';
 
 public function findByUserId($user_id) {
 $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ? LIMIT 1");
 $stmt->execute([$user_id]);
 return $stmt->fetch();
 }
}
