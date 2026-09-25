CREATE TABLE IF NOT EXISTS online_exams (
    id SERIAL PRIMARY KEY,
    course_id INT NOT NULL,
    college_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(50) DEFAULT 'quiz',
    start_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    duration_min INT DEFAULT 60,
    status VARCHAR(50) DEFAULT 'draft',
    pass_score DECIMAL(5,2) DEFAULT 50.00,
    show_results BOOLEAN DEFAULT false,
    randomize_q BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS online_questions (
    id SERIAL PRIMARY KEY,
    exam_id INT NOT NULL REFERENCES online_exams(id) ON DELETE CASCADE,
    order_index INT DEFAULT 0,
    type VARCHAR(50) DEFAULT 'mcq',
    question_text TEXT NOT NULL,
    points DECIMAL(5,2) DEFAULT 1.00
);

CREATE TABLE IF NOT EXISTS online_choices (
    id SERIAL PRIMARY KEY,
    question_id INT NOT NULL REFERENCES online_questions(id) ON DELETE CASCADE,
    order_index INT DEFAULT 0,
    choice_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT false
);

CREATE TABLE IF NOT EXISTS online_submissions (
    id SERIAL PRIMARY KEY,
    exam_id INT NOT NULL REFERENCES online_exams(id) ON DELETE CASCADE,
    student_id INT NOT NULL,
    status VARCHAR(50) DEFAULT 'in_progress',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    ip_address VARCHAR(45) NULL
);

CREATE TABLE IF NOT EXISTS online_answers (
    id SERIAL PRIMARY KEY,
    submission_id INT NOT NULL REFERENCES online_submissions(id) ON DELETE CASCADE,
    question_id INT NOT NULL REFERENCES online_questions(id) ON DELETE CASCADE,
    choice_id INT NULL REFERENCES online_choices(id) ON DELETE CASCADE,
    text_answer TEXT NULL,
    score DECIMAL(5,2) DEFAULT 0.00
);

CREATE TABLE IF NOT EXISTS exam_logs (
    id SERIAL PRIMARY KEY,
    exam_id INT NOT NULL REFERENCES online_exams(id) ON DELETE CASCADE,
    student_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
