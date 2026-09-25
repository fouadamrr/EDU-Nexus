<?php
require_once __DIR__ . '/../config/database.php';

class ScheduleController {

 public function getStudentSchedule(int $user_id): array {
 $db = Database::getConnection();

 // Auto-create class_schedule table if it doesn't exist
 $db->exec("
 CREATE TABLE IF NOT EXISTS class_schedule (
 id SERIAL PRIMARY KEY,
 course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
 day_of_week VARCHAR(20) NOT NULL,
 start_time TIME NOT NULL,
 end_time TIME NOT NULL,
 location VARCHAR(100),
 created_at TIMESTAMPTZ DEFAULT NOW()
 );
 CREATE INDEX IF NOT EXISTS idx_cs_course ON class_schedule(course_id);
 ");

 $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
 $timetable = [];
 foreach ($days as $day) {
 $timetable[$day] = [];
 }

 // Fetch schedule slots for courses this student is either explicitly enrolled in 
 // OR the course belongs to their college_id and level (automatic cohort scheduling).
 $stmt = $db->prepare("
 SELECT cs.id, cs.course_id, cs.day_of_week,
 cs.start_time, cs.end_time, cs.location,
 c.code, c.name
 FROM class_schedule cs
 JOIN courses c ON cs.course_id = c.id
 JOIN students s ON s.user_id = :uid
 LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = :uid
 WHERE (e.id IS NOT NULL) 
 OR (c.college_id = s.college_id AND c.level = s.level)
 ORDER BY cs.day_of_week, cs.start_time
 ");
 $stmt->execute([':uid' => $user_id]);
 $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

 foreach ($items as $item) {
 $day = $item['day_of_week'];
 if (in_array($day, $days)) {
 $timetable[$day][] = $item;
 }
 }

 foreach ($timetable as $day => &$slots) {
 usort($slots, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
 }

 return [
 'days' => $days,
 'timetable' => $timetable,
 ];
 }
}
