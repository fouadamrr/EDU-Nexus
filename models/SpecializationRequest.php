<?php
// models/SpecializationRequest.php
require_once __DIR__ . '/Model.php';

class SpecializationRequest extends Model {
 protected string $table = 'specialization_requests';

 public function findByStudentId(int $student_id): array {
 return $this->findAll(['student_id' => $student_id], 'created_at DESC');
 }

 public function findPendingByCollege(int $college_id): array {
 return $this->findAll(['college_id' => $college_id, 'status' => 'pending'], 'created_at ASC');
 }
}
