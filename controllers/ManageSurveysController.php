<?php
require_once __DIR__ . '/../models/Survey.php';

class ManageSurveysController {
 public function handleAddRequest(array $postData): string {
 $title = $postData['title'] ?? '';
 $link = $postData['link'] ?? '';
 $description = $postData['description'] ?? '';

 $surveyModel = new Survey();
 $surveyModel->insert([
 'title' => $title,
 'link' => $link,
 'description' => $description,
 'created_at' => date('Y-m-d')
 ]);
 return '<div class="bg-primary text-white p-3 rounded mb-4">تم إضافة الاستبيان بنجاح</div>';
 }

 public function handleDeleteRequest(int $id): string {
 $surveyModel = new Survey();
 if ($surveyModel->delete($id)) {
 return '<div class="bg-secondary text-white p-3 rounded mb-4">تم حذف الاستبيان</div>';
 }
 return '';
 }

 public function getAllSurveys(): array {
 $surveyModel = new Survey();
 $surveys = $surveyModel->findAll();
 usort($surveys, function ($a, $b) {
 return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
 });
 return $surveys;
 }
}
