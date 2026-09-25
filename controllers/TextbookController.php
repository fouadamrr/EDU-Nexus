<?php
// إدارة موضوع الكتب والفلوس بتاعتها
require_once __DIR__ . '/../db.php';

class TextbookController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_pdo();
    }

    // هات الكتب اللي الطالب يقدر يشوفها في كليته وفرقته
    public function getStudentTextbooks(int $student_id, int $college_id, int $level): array
    {
        /*
         * يظهر الكتاب للطالب إذا كان بنفس الكلية، وإما:
         *   1. مخصص لمادة معينة (course_id) مسجّل فيها الطالب
         *   2. أو مخصص لجميع الفرق (level = 0)
         *   3. أو مستواه (level) يطابق فرقة الطالب الحالية أو مادة شايلها
         */
        $sql = "
            SELECT DISTINCT ON (r.id)
                   r.*,
                   tpr.id           AS request_id,
                   tpr.status       AS payment_status,
                   tpr.requested_at,
                   tpr.paid_at,
                   u.full_name      AS instructor_name
            FROM resources r
            LEFT JOIN textbook_payment_requests tpr
                   ON tpr.book_id = r.id AND tpr.student_id = :sid1
            LEFT JOIN users u ON u.id = r.doctor_id
            WHERE r.is_textbook = TRUE
              AND r.college_id  = :cid
              AND (
                  r.course_id IN (
                      SELECT course_id FROM enrollments WHERE user_id = :sid2 AND status = 'active'
                  )
               OR r.level = 0
               OR r.level = :lvl
               OR r.level IN (
                    SELECT DISTINCT c.level
                    FROM   enrollments e
                    JOIN   courses     c ON c.id = e.course_id
                    WHERE  e.user_id   = :sid3
                      AND  e.status    = 'active'
                      AND  c.level    IS NOT NULL
               )
              )
            ORDER BY r.id, r.created_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sid1' => $student_id,
            ':sid2' => $student_id,
            ':sid3' => $student_id,
            ':cid'  => $college_id,
            ':lvl'  => $level,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // الطالب بيبعت طلب دفع لكتاب معين
    public function submitPaymentRequest(int $book_id, int $student_id, int $college_id): array
    {
        // Guard: book must exist + is_textbook
        $chk = $this->pdo->prepare("SELECT id FROM resources WHERE id = :id AND is_textbook = TRUE");
        $chk->execute([':id' => $book_id]);
        if (!$chk->fetch()) {
            return ['success' => false, 'message' => 'الكتاب غير موجود أو غير متاح.'];
        }

        // Guard: no duplicate
        $dup = $this->pdo->prepare(
            "SELECT id FROM textbook_payment_requests WHERE book_id = :bid AND student_id = :sid"
        );
        $dup->execute([':bid' => $book_id, ':sid' => $student_id]);
        if ($dup->fetch()) {
            return ['success' => false, 'message' => 'لقد أرسلت طلب دفع لهذا الكتاب مسبقاً.'];
        }

        $ins = $this->pdo->prepare("
            INSERT INTO textbook_payment_requests (book_id, student_id, college_id, status)
            VALUES (:bid, :sid, :cid, 'pending')
        ");
        $ins->execute([':bid' => $book_id, ':sid' => $student_id, ':cid' => $college_id]);

        return ['success' => true, 'message' => 'تم إرسال طلب الدفع بنجاح. انتظر موافقة شؤون الطلاب.'];
    }

    // بتوع الشؤون بيشوفوا الطلبات اللي جاتلهم عشان يفلتروها
    public function getPaymentRequests(int $college_id = 0, int $level = 0, string $status = ''): array
    {
        $where  = [];
        $params = [];

        if ($college_id > 0) {
            $where[] = 'tpr.college_id = :cid';
            $params[':cid'] = $college_id;
        }
        if ($level > 0) {
            $where[] = 'r.level = :lvl';
            $params[':lvl'] = $level;
        }
        if ($status !== '') {
            $where[] = 'tpr.status = :st';
            $params[':st'] = $status;
        }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT tpr.*,
                   r.title         AS book_name,
                   r.level         AS book_level,
                   r.price         AS book_price,
                   r.file_path     AS file_path,
                   u_student.full_name  AS student_name,
                   u_student.username   AS student_username,
                   s.level              AS student_level,
                   col.name             AS college_name,
                   u_conf.full_name     AS confirmed_by_name
            FROM textbook_payment_requests tpr
            JOIN resources r            ON r.id  = tpr.book_id
            JOIN users u_student        ON u_student.id = tpr.student_id
            LEFT JOIN students s        ON s.user_id    = tpr.student_id
            LEFT JOIN colleges col      ON col.id       = tpr.college_id
            LEFT JOIN users u_conf      ON u_conf.id    = tpr.confirmed_by
            {$whereStr}
            ORDER BY tpr.requested_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // أكد إن الطالب دا دفع خلاص
    public function confirmPayment(int $request_id, int $confirmed_by): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE textbook_payment_requests
            SET status = 'paid', paid_at = NOW(), confirmed_by = :cb
            WHERE id = :id
        ");
        $stmt->execute([':id' => $request_id, ':cb' => $confirmed_by]);
        return $stmt->rowCount() > 0;
    }

    // ارفض الطلب واكتب السبب لو فيه ملاحظات
    public function rejectPayment(int $request_id, int $confirmed_by, string $notes = ''): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE textbook_payment_requests
            SET status = 'rejected', confirmed_by = :cb, notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([':id' => $request_id, ':cb' => $confirmed_by, ':notes' => $notes]);
        return $stmt->rowCount() > 0;
    }

    // رجع الطلب تاني "بانتظار التأكيد" لو حصل غلط
    public function resetRequest(int $request_id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE textbook_payment_requests
            SET status = 'pending', paid_at = NULL, confirmed_by = NULL, notes = NULL
            WHERE id = :id
        ");
        $stmt->execute([':id' => $request_id]);
        return $stmt->rowCount() > 0;
    }

    // نزل شيت إكسيل بالداتا اللي عندنا
    public function exportToCSV(array $requests): void
    {
        $levelNames = [0 => 'جميع الفرق', 1 => 'الفرقة الأولى', 2 => 'الفرقة الثانية',
                       3 => 'الفرقة الثالثة', 4 => 'الفرقة الرابعة'];
        $statusMap  = ['pending' => 'بانتظار التأكيد', 'paid' => 'تم الدفع', 'rejected' => 'مرفوض'];

        // Clear any previous output (like HTML from header.php)
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="textbook_payments_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['#', 'اسم الطالب', 'رقم الطالب', 'الكلية', 'الفرقة',
                       'عنوان الكتاب', 'السعر', 'الحالة', 'تاريخ الطلب',
                       'تاريخ الدفع', 'تم التأكيد بواسطة', 'ملاحظات']);

        $i = 1;
        foreach ($requests as $r) {
            $bookTitle  = $r['book_title'] ?? $r['book_name'] ?? '—';
            $lvl        = $levelNames[(int)($r['book_level'] ?? 0)] ?? '—';
            $statusAr   = $statusMap[$r['status'] ?? ''] ?? $r['status'];
            fputcsv($out, [
                $i++,
                $r['student_name']     ?? '—',
                $r['student_username'] ?? '—',
                $r['college_name']     ?? '—',
                $lvl,
                $bookTitle,
                $r['book_price']       ?? 0,
                $statusAr,
                $r['requested_at']     ? date('Y-m-d', strtotime($r['requested_at'])) : '—',
                $r['paid_at']          ? date('Y-m-d', strtotime($r['paid_at']))      : '—',
                $r['confirmed_by_name'] ?? '—',
                $r['notes']            ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // الدكتور يشوف كتبه والطلبة اللي دفعوا فيها
    public function getInstructorBooks(int $instructor_id): array
    {
        $sql = "
            SELECT r.*,
                   col.name AS college_name,
                   (SELECT COUNT(*) FROM textbook_payment_requests tpr
                    WHERE tpr.book_id = r.id AND tpr.status = 'paid')    AS paid_count,
                   (SELECT COUNT(*) FROM textbook_payment_requests tpr
                    WHERE tpr.book_id = r.id AND tpr.status = 'pending') AS pending_count
            FROM resources r
            LEFT JOIN colleges col ON col.id = r.college_id
            WHERE r.doctor_id    = :did
              AND r.is_textbook  = TRUE
            ORDER BY r.college_id, r.level, r.created_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':did' => $instructor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // هات المواد اللي الدكتور دا بيديها
    public function getInstructorCourses(int $instructor_id): array
    {
        // Doctor can be instructor_id or doctor_id
        $sql = "SELECT id, name, code, level FROM courses 
                WHERE instructor_id = :did OR doctor_id = :did 
                ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':did' => $instructor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ارفع كتاب جديد على السيستم
    public function uploadTextbook(array $postData, array $files, int $instructor_id): array
    {
        $title      = trim($postData['book_title']  ?? '');
        $college_id = (int)($postData['college_id'] ?? 0);
        $level      = (int)($postData['level']      ?? 0);
        $course_id  = (int)($postData['course_id']  ?? 0); // NEW
        $price      = (float)($postData['price']    ?? 0);

        if (!$title) return ['success' => false, 'message' => 'أدخل عنوان الكتاب.'];
        if (!$college_id) return ['success' => false, 'message' => 'اختر الكلية.'];
        if (!isset($files['book_file']) || $files['book_file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'يرجى رفع ملف صحيح.'];
        }

        // === Security: Validate file extension whitelist ===
        $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
        $origNameCheck = strtolower(basename($files['book_file']['name']));
        $ext = pathinfo($origNameCheck, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'نوع الملف غير مسموح به. الأنواع المسموح بها: PDF, Word, PowerPoint, صور.'];
        }

        $uploadDir = str_replace('\\', '/', realpath(__DIR__ . '/../')) . '/uploads/books/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $origName  = basename($files['book_file']['name']);
        $safeName  = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $origName);
        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($files['book_file']['tmp_name'], $targetPath)) {
            $err = error_get_last();
            return ['success' => false, 'message' => 'فشل الرفع. خطأ: ' . ($err['message'] ?? 'غير معروف') . ' - مسار: ' . $targetPath];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO resources
                (doctor_id, college_id, course_id, title, file_path,
                 level, is_textbook, price, type)
            VALUES
                (:did, :cid, :course, :bt, :fp,
                 :lvl, TRUE, :price, 'book')
        ");
        $stmt->execute([
            ':did'    => $instructor_id,
            ':cid'    => $college_id,
            ':course' => $course_id > 0 ? $course_id : null,
            ':bt'     => $title,
            ':fp'     => 'uploads/books/' . $safeName,
            ':lvl'    => $level,
            ':price'  => $price,
        ]);

        return ['success' => true, 'message' => 'تم رفع الكتاب بنجاح وأصبح متاحاً للطلاب بعد الدفع.'];
    }

    // هات أسماء الكليات اللي عندنا
    public function getAllColleges(): array
    {
        return $this->pdo->query("SELECT id, name FROM colleges ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    // شوية أرقام سريعة كدا للشؤون
    public function getAffairsStats(int $college_id = 0): array
    {
        $cond   = $college_id ? 'WHERE tpr.college_id = ' . (int)$college_id : '';
        $sql    = "SELECT
                     COUNT(*)                                           AS total,
                     COUNT(*) FILTER (WHERE status='pending')          AS pending,
                     COUNT(*) FILTER (WHERE status='paid')             AS paid,
                     COUNT(*) FILTER (WHERE status='rejected')         AS rejected
                   FROM textbook_payment_requests tpr {$cond}";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['total' => 0, 'pending' => 0, 'paid' => 0, 'rejected' => 0];
    }
}
