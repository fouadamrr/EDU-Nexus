-- سكيما قاعدة بيانات EDU Nexus - الهيكل الأساسي للنظام

SET client_encoding = 'UTF8';

-- 1. الكليات (Colleges)
-- هنا بنخزن أسماء الكليات اللي في الجامعة وبيانات العمداء بتوعهم
CREATE TABLE IF NOT EXISTS colleges (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    name_en     VARCHAR(200),
    dean_username VARCHAR(100),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 2. الأدوار والصلاحيات (Roles & Permissions)
-- السيستم بيمشي بنظام الصلاحيات، كل رول (أدمن، طالب، دكتور...) ليه صلاحيات معينة
CREATE TABLE IF NOT EXISTS roles (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,  -- 'super_admin','admin','dean','affairs','instructor','student'
    name_ar     VARCHAR(100),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS permissions (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

-- 3. المستخدمين (Users)
-- دي الطاولة الأساسية لكل الناس اللي بتدخل السيستم
CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    full_name     VARCHAR(200) NOT NULL,
    email         VARCHAR(200),
    role          VARCHAR(50)  NOT NULL DEFAULT 'student',
    college_id    INT REFERENCES colleges(id) ON DELETE SET NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'active',   -- 'active','suspended'
    profile_pic   VARCHAR(500),
    created_at    TIMESTAMPTZ  DEFAULT NOW()
);

-- 4. الأقسام الدراسية (Departments)
CREATE TABLE IF NOT EXISTS departments (
    id          SERIAL PRIMARY KEY,
    college_id  INT NOT NULL REFERENCES colleges(id) ON DELETE CASCADE,
    name        VARCHAR(200) NOT NULL,
    name_en     VARCHAR(200),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 5. بيانات الطلاب (Students)
-- تفاصيل زيادة عن الطلاب زي المعدل التراكمي والسنة الدراسية
CREATE TABLE IF NOT EXISTS students (
    id                SERIAL PRIMARY KEY,
    user_id           INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    college_id        INT REFERENCES colleges(id) ON DELETE SET NULL,
    national_id       VARCHAR(20),
    major             VARCHAR(200),
    level             SMALLINT DEFAULT 1,
    gpa               NUMERIC(4,2) DEFAULT 0.00,
    enrollment_status VARCHAR(30) DEFAULT 'enrolled',   -- 'enrolled','graduated','suspended','withdrawn'
    doctor_id         INT REFERENCES users(id) ON DELETE SET NULL,
    phone             VARCHAR(30),
    birth_date        DATE,
    enrollment_year   INT,
    registration_blocked BOOLEAN DEFAULT FALSE,
    created_at        TIMESTAMPTZ DEFAULT NOW()
);

-- عناوين الطلاب (عشان متطلبات الملفات الورقية)
CREATE TABLE IF NOT EXISTS student_addresses (
    id          SERIAL PRIMARY KEY,
    student_id  INT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    address     TEXT,
    city        VARCHAR(100),
    governorate VARCHAR(100),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 6. بيانات الدكاترة (Professors)
CREATE TABLE IF NOT EXISTS professors (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    department  VARCHAR(200),
    subject     VARCHAR(200),
    title       VARCHAR(100) DEFAULT 'د.',
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- مكاتب الدكاترة ومواعيد المقابلات
CREATE TABLE IF NOT EXISTS professor_offices (
    id            SERIAL PRIMARY KEY,
    professor_id  INT NOT NULL REFERENCES professors(id) ON DELETE CASCADE,
    building      VARCHAR(100),
    room_number   VARCHAR(20),
    office_hours  VARCHAR(200),
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

-- 7. المواد الدراسية (Courses)
CREATE TABLE IF NOT EXISTS courses (
    id            SERIAL PRIMARY KEY,
    college_id    INT REFERENCES colleges(id) ON DELETE SET NULL,
    code          VARCHAR(30) NOT NULL,
    name          VARCHAR(200) NOT NULL,
    credit_hours  SMALLINT DEFAULT 3,
    level         SMALLINT,                    -- الفرقة الدراسية (1-4)
    instructor_id INT REFERENCES users(id) ON DELETE SET NULL,
    doctor_id     INT REFERENCES users(id) ON DELETE SET NULL,
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (college_id, code)
);

-- المتطلبات السابقة للمواد (عشان الطالب ميسجلش مادة قبل ما يخلص اللي قبلها)
CREATE TABLE IF NOT EXISTS course_prerequisites (
    course_id        INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    prerequisite_id  INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    PRIMARY KEY (course_id, prerequisite_id)
);

-- 8. الفصول الدراسية (Semesters)
CREATE TABLE IF NOT EXISTS semesters (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    name        VARCHAR(100) NOT NULL,           -- زي 'الفصل الدراسي الأول 2024'
    start_date  DATE,
    end_date    DATE,
    is_current  BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 9. القاعات والمدرجات (Classrooms)
CREATE TABLE IF NOT EXISTS classrooms (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    name        VARCHAR(100) NOT NULL,
    building    VARCHAR(100),
    capacity    INT DEFAULT 30,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 10. جدول المحاضرات (Class Schedule)
CREATE TABLE IF NOT EXISTS class_schedule (
    id            SERIAL PRIMARY KEY,
    college_id    INT REFERENCES colleges(id) ON DELETE SET NULL,
    course_id     INT REFERENCES courses(id) ON DELETE CASCADE,
    day_of_week   VARCHAR(20),
    start_time    TIME,
    end_time      TIME,
    location      VARCHAR(200),
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

-- 11. تسجيل المواد (Enrollments)
-- الربط بين الطالب والمواد اللي سجلها في تيرم معين
CREATE TABLE IF NOT EXISTS enrollments (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    course_id   INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    semester    VARCHAR(100),
    status      VARCHAR(30) DEFAULT 'active',   -- 'active','dropped','completed'
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (user_id, course_id, semester)
);

-- استثناءات التسجيل (أذونات خاصة من الأدمن لفتح مادة للطالب)
CREATE TABLE IF NOT EXISTS registration_permissions (
    id          SERIAL PRIMARY KEY,
    student_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    course_id   INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    granted_by  INT REFERENCES users(id) ON DELETE SET NULL,
    note        TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (student_id, course_id)
);
CREATE INDEX IF NOT EXISTS idx_reg_perm_student ON registration_permissions(student_id);

-- 12. الدرجات والنتائج (Grades)
CREATE TABLE IF NOT EXISTS grades (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    course_id   INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    semester    VARCHAR(100),
    grade       VARCHAR(5),              -- 'A', 'B+', 'C'...
    points      NUMERIC(3,1),            -- 4.0, 3.3, 2.0...
    midterm     NUMERIC(5,2),
    final       NUMERIC(5,2),
    practical   NUMERIC(5,2),
    total       NUMERIC(5,2),
    approved    BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 13. الغياب والحضور (Attendance)
CREATE TABLE IF NOT EXISTS attendance (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_id  INT DEFAULT 0,
    doctor_id   INT REFERENCES users(id) ON DELETE SET NULL,
    subject_id  INT DEFAULT 0,
    course_id   INT REFERENCES courses(id) ON DELETE SET NULL,
    date        DATE NOT NULL DEFAULT CURRENT_DATE,
    status      VARCHAR(20) DEFAULT 'present',  -- 'present','absent','late','excused'
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 14. التكليفات والأبحاث (Assignments)
CREATE TABLE IF NOT EXISTS assignments (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    course_id   INT REFERENCES courses(id) ON DELETE CASCADE,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    due_date    TIMESTAMPTZ,
    max_grade   NUMERIC(5,2) DEFAULT 100,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- تسليمات الطلاب للأبحاث
CREATE TABLE IF NOT EXISTS submissions (
    id              SERIAL PRIMARY KEY,
    assignment_id   INT NOT NULL REFERENCES assignments(id) ON DELETE CASCADE,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    file_path       VARCHAR(500),
    content         TEXT,
    grade           NUMERIC(5,2),
    submitted_at    TIMESTAMPTZ DEFAULT NOW()
);

-- 15. المصادر والمكتبة الرقمية (Resources)
CREATE TABLE IF NOT EXISTS resources (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    course_id   INT REFERENCES courses(id) ON DELETE SET NULL,
    doctor_id   INT REFERENCES users(id) ON DELETE SET NULL,
    title       VARCHAR(200),
    book_title  VARCHAR(200),
    category    VARCHAR(100) DEFAULT 'General',
    author      VARCHAR(200),
    file_path   VARCHAR(500),
    file_name   VARCHAR(500),
    image       VARCHAR(255) DEFAULT 'book_default.jpg',
    upload_date DATE DEFAULT CURRENT_DATE,
    type        VARCHAR(30) DEFAULT 'book',   -- 'book','pdf','video','link'
    total_copies   INT DEFAULT 0,
    available_copies INT DEFAULT 0,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- استعارة الكتب (المكتبة الورقية)
CREATE TABLE IF NOT EXISTS borrowed_books (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_id  INT DEFAULT 0,
    book_id     INT NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    due_date    DATE,
    status      VARCHAR(20) DEFAULT 'borrowed',   -- 'borrowed','returned','overdue'
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 16. المصاريف والرسوم المالية (Fees)
CREATE TABLE IF NOT EXISTS fees (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title       VARCHAR(255) DEFAULT '',
    amount      NUMERIC(10,2) NOT NULL,
    description VARCHAR(300),
    due_date    DATE,
    status      VARCHAR(20) DEFAULT 'pending',    -- 'pending','paid','overdue'
    paid_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 17. الامتحانات (Exams)
CREATE TABLE IF NOT EXISTS exams (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    course_id   INT REFERENCES courses(id) ON DELETE CASCADE,
    doctor_id   INT REFERENCES users(id) ON DELETE SET NULL,
    exam_name   VARCHAR(200) NOT NULL,
    total_marks INT DEFAULT 100,
    date        DATE DEFAULT CURRENT_DATE,
    start_time  TIME,
    end_time    TIME,
    room        VARCHAR(100),
    semester    VARCHAR(100),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- رصد درجات امتحان معين
CREATE TABLE IF NOT EXISTS exam_grades (
    id          SERIAL PRIMARY KEY,
    exam_id     INT NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
    student_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    grade_value NUMERIC(5,2),
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (exam_id, student_id)
);

-- لجان الامتحانات وتوزيع الطلاب
CREATE TABLE IF NOT EXISTS exam_committees (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    capacity INT NOT NULL DEFAULT 30,
    current_count INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS exam_distributions (
    id SERIAL PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    committee_id INT NOT NULL REFERENCES exam_committees(id) ON DELETE CASCADE,
    seat_number INT NOT NULL
);

-- توزيع لجان الامتحانات (لأغراض التدقيق)
CREATE TABLE IF NOT EXISTS exam_assignments (
    id              SERIAL PRIMARY KEY,
    exam_id         INT NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    seat_number     INT,
    committee_room  VARCHAR(100),
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (exam_id, user_id)
);

-- 18. الاستبيانات (Surveys)
CREATE TABLE IF NOT EXISTS surveys (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    link        VARCHAR(500),
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS survey_responses (
    id          SERIAL PRIMARY KEY,
    survey_id   INT NOT NULL REFERENCES surveys(id) ON DELETE CASCADE,
    user_id     INT REFERENCES users(id) ON DELETE SET NULL,
    response    TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 19. الإعلانات والتنبيهات (Announcements & Notifications)
CREATE TABLE IF NOT EXISTS announcements (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    title       VARCHAR(300) NOT NULL,
    content     TEXT,
    date        DATE DEFAULT CURRENT_DATE,
    created_by  INT REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notifications (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message     TEXT NOT NULL,
    date        DATE DEFAULT CURRENT_DATE,
    is_read     BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- سجلات الحضور والغياب للرقابة والتحقيق
CREATE TABLE IF NOT EXISTS attendance_logs (
    id              SERIAL PRIMARY KEY,
    doctor_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action          VARCHAR(100),
    date_logged     DATE DEFAULT CURRENT_DATE,
    timestamp       TIMESTAMPTZ DEFAULT NOW(),
    ip_address      VARCHAR(50),
    affected_count  INT DEFAULT 0,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- سجلات التحذيرات الأكاديمية (للطلاب المتعثرين)
CREATE TABLE IF NOT EXISTS academic_warnings (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reason      TEXT,
    issued_at   DATE DEFAULT CURRENT_DATE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 20. الرسائل (Messages)
CREATE TABLE IF NOT EXISTS messages (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    sender_id   INT REFERENCES users(id) ON DELETE SET NULL,
    receiver_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    subject     VARCHAR(300),
    body        TEXT,
    is_read     BOOLEAN DEFAULT FALSE,
    sent_at     TIMESTAMPTZ DEFAULT NOW()
);

-- 21. إعدادات النظام (Settings)
CREATE TABLE IF NOT EXISTS settings (
    id          SERIAL PRIMARY KEY,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    key         VARCHAR(100) NOT NULL,
    value       TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (college_id, key)
);

-- 22. المديرين (Admins)
CREATE TABLE IF NOT EXISTS admins (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    permissions TEXT[],
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 23. سجل العمليات (Logs)
-- هنا بنسجل كل حركة بتحصل في السيستم عشان الرقابة والأمان
CREATE TABLE IF NOT EXISTS logs (
    id          SERIAL PRIMARY KEY,
    user_id     INT REFERENCES users(id) ON DELETE SET NULL,
    college_id  INT REFERENCES colleges(id) ON DELETE SET NULL,
    action      VARCHAR(200),
    details     TEXT,
    ip_address  VARCHAR(50),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ========================================================
-- فهارس (Indexes) لتحسين سرعة البحث والأداء
-- ========================================================
CREATE INDEX IF NOT EXISTS idx_users_username    ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_college     ON users(college_id);
CREATE INDEX IF NOT EXISTS idx_students_user     ON students(user_id);
CREATE INDEX IF NOT EXISTS idx_students_college  ON students(college_id);
CREATE INDEX IF NOT EXISTS idx_grades_user       ON grades(user_id);
CREATE INDEX IF NOT EXISTS idx_grades_course     ON grades(course_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_user  ON enrollments(user_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_college ON enrollments(college_id);
CREATE INDEX IF NOT EXISTS idx_courses_college   ON courses(college_id);
CREATE INDEX IF NOT EXISTS idx_attendance_user   ON attendance(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_fees_user         ON fees(user_id);
CREATE INDEX IF NOT EXISTS idx_settings_key      ON settings(key);
CREATE INDEX IF NOT EXISTS idx_logs_created      ON logs(created_at DESC);

-- ========================================================
-- إدخال الأدوار الأساسية في السيستم (Seed Data)
-- ========================================================
INSERT INTO roles (name, name_ar) VALUES
    ('super_admin', 'رئيس الجامعة'),
    ('admin',       'مدير النظام'),
    ('dean',        'عميد الكلية'),
    ('affairs',     'شؤون الطلبة'),
    ('instructor',  'عضو هيئة تدريس'),
    ('student',     'طالب')
ON CONFLICT (name) DO NOTHING;

-- ========================================================
-- 24. نظام الامتحانات الأونلاين (Online Examination System)
-- ========================================================

--  1. الامتحانات الأونلاين
CREATE TABLE IF NOT EXISTS online_exams (
    id           SERIAL PRIMARY KEY,
    college_id   INT REFERENCES colleges(id) ON DELETE SET NULL,
    course_id    INT REFERENCES courses(id) ON DELETE CASCADE,
    created_by   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title        VARCHAR(300) NOT NULL,
    description  TEXT,
    type         VARCHAR(30) NOT NULL DEFAULT 'quiz',
    duration_min INT NOT NULL DEFAULT 60,
    start_time   TIMESTAMPTZ,
    end_time     TIMESTAMPTZ,
    status       VARCHAR(20) NOT NULL DEFAULT 'inactive', -- 'inactive','active','closed'
    randomize_q  BOOLEAN DEFAULT TRUE,
    show_results BOOLEAN DEFAULT TRUE,
    pass_score   NUMERIC(5,2) DEFAULT 50,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

--  2. الأسئلة
CREATE TABLE IF NOT EXISTS online_questions (
    id             SERIAL PRIMARY KEY,
    exam_id        INT NOT NULL REFERENCES online_exams(id) ON DELETE CASCADE,
    question_text  TEXT NOT NULL,
    type           VARCHAR(20) NOT NULL DEFAULT 'mcq',  -- 'mcq','true_false','essay'
    points         NUMERIC(5,2) DEFAULT 1,
    order_index    INT DEFAULT 0,
    created_at     TIMESTAMPTZ DEFAULT NOW()
);

--  3. الاختيارات (للأسئلة الاختياري والصح والغلط)
CREATE TABLE IF NOT EXISTS online_choices (
    id           SERIAL PRIMARY KEY,
    question_id  INT NOT NULL REFERENCES online_questions(id) ON DELETE CASCADE,
    choice_text  TEXT NOT NULL,
    is_correct   BOOLEAN DEFAULT FALSE,
    order_index  INT DEFAULT 0
);

--  4. تسليمات الطلاب (الإجابات النهائية والدرجات)
CREATE TABLE IF NOT EXISTS online_submissions (
    id            SERIAL PRIMARY KEY,
    exam_id       INT NOT NULL REFERENCES online_exams(id) ON DELETE CASCADE,
    student_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    started_at    TIMESTAMPTZ DEFAULT NOW(),
    submitted_at  TIMESTAMPTZ,
    score         NUMERIC(6,2),
    max_score     NUMERIC(6,2),
    percentage    NUMERIC(5,2),
    status        VARCHAR(20) DEFAULT 'in_progress',
    ip_address    VARCHAR(50),
    UNIQUE (exam_id, student_id)
);

--  5. تفاصيل الإجابات (إجابة كل سؤال لوحده)
CREATE TABLE IF NOT EXISTS online_answers (
    id                  SERIAL PRIMARY KEY,
    submission_id       INT NOT NULL REFERENCES online_submissions(id) ON DELETE CASCADE,
    question_id         INT NOT NULL REFERENCES online_questions(id) ON DELETE CASCADE,
    selected_choice_id  INT REFERENCES online_choices(id) ON DELETE SET NULL,
    essay_answer        TEXT,
    is_correct          BOOLEAN,
    points_earned       NUMERIC(5,2),
    instructor_note     TEXT,
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (submission_id, question_id)
);

--  6. سجل مراقبة الامتحانات (منع الغش ومراقبة تحركات الطالب)
CREATE TABLE IF NOT EXISTS exam_logs (
    id           SERIAL PRIMARY KEY,
    exam_id      INT REFERENCES online_exams(id) ON DELETE CASCADE,
    student_id   INT REFERENCES users(id) ON DELETE CASCADE,
    action       VARCHAR(100) NOT NULL,   -- 'tab_switch', 'copy_attempt', 'refresh'...
    details      TEXT,
    ip_address   VARCHAR(50),
    user_agent   VARCHAR(500),
    timestamp    TIMESTAMPTZ DEFAULT NOW()
);

-- ==============================================================================================
-- قسم التحديثات والإصلاحات (Patches) - بنصلح فيها أي مشاكل ظهرت في الهياكل القديمة
-- ==============================================================================================

-- إصلاح 1: ربط توزيع اللجان بالمستخدمين بشكل صحيح
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_name = 'fk_exam_distributions_student'
          AND table_name = 'exam_distributions'
    ) THEN
        ALTER TABLE exam_distributions
            ADD CONSTRAINT fk_exam_distributions_student
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE;
    END IF;
END $$;

-- إصلاح 2: تحويل قيم الأصفار الافتراضية في الحقول القديمة لـ NULL عشان الدقة
DO $$
BEGIN
    -- تعديل جدول الحضور
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'attendance' AND column_name = 'student_id') THEN
        ALTER TABLE attendance ALTER COLUMN student_id SET DEFAULT NULL;
        UPDATE attendance SET student_id = NULL WHERE student_id = 0;
    END IF;

    -- تعديل جدول الكتب المستعارة
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'borrowed_books' AND column_name = 'student_id') THEN
        ALTER TABLE borrowed_books ALTER COLUMN student_id SET DEFAULT NULL;
        UPDATE borrowed_books SET student_id = NULL WHERE student_id = 0;
    END IF;
END $$;

-- إصلاح 3: منع تكرار الدرجات لنفس الطالب والمادة في نفس التيرم
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'uq_grades_user_course_semester' AND table_name = 'grades') THEN
        ALTER TABLE grades ADD CONSTRAINT uq_grades_user_course_semester UNIQUE (user_id, course_id, semester);
    END IF;
END $$;

-- إصلاح 4: منع الطالب من تسليم نفس البحث أكتر من مرة
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'uq_submissions_assignment_user' AND table_name = 'submissions') THEN
        ALTER TABLE submissions ADD CONSTRAINT uq_submissions_assignment_user UNIQUE (assignment_id, user_id);
    END IF;
END $$;

-- إصلاح 5: تحويل 'timestamp' لـ 'logged_at' في سجلات الحضور
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'attendance_logs' AND column_name = 'timestamp') 
    AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'attendance_logs' AND column_name = 'logged_at') THEN
        ALTER TABLE attendance_logs RENAME COLUMN "timestamp" TO logged_at;
    END IF;
END $$;

-- إصلاح 6: تحسين حقول حالات المستخدمين والطلاب (التأكد من القيم المسموحة)
DO $$
BEGIN
    -- حالة المستخدم
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'chk_users_status' AND table_name = 'users') THEN
        ALTER TABLE users ADD CONSTRAINT chk_users_status CHECK (status IN ('active', 'suspended'));
    END IF;

    -- حالة الطالب الدراسية
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'chk_students_enrollment_status' AND table_name = 'students') THEN
        ALTER TABLE students ADD CONSTRAINT chk_students_enrollment_status CHECK (enrollment_status IN ('enrolled', 'graduated', 'suspended', 'withdrawn'));
    END IF;

    -- نطاق المعدل التراكمي (0 لـ 4)
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'chk_students_gpa' AND table_name = 'students') THEN
        ALTER TABLE students ADD CONSTRAINT chk_students_gpa CHECK (gpa >= 0.00 AND gpa <= 4.00);
    END IF;
END $$;

-- إصلاح 7: فهارس إضافية للأداء
CREATE INDEX IF NOT EXISTS idx_grades_semester        ON grades(semester);
CREATE INDEX IF NOT EXISTS idx_attendance_date        ON attendance(date);
CREATE INDEX IF NOT EXISTS idx_exam_grades_student    ON exam_grades(student_id);
CREATE INDEX IF NOT EXISTS idx_messages_receiver      ON messages(receiver_id);
CREATE INDEX IF NOT EXISTS idx_fees_status            ON fees(status);

-- نهاية ملف السكيما