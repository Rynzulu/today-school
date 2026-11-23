-- ========================================================
-- SCHOOL MANAGEMENT SYSTEM (SMS) - ENTERPRISE EDITION
-- Complete Master MySQL Database DDL & Seed Script
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ========================================================

-- Select your created database in phpMyAdmin before importing this file.
-- (CREATE DATABASE / USE omitted so shared hosting imports work cleanly.)

SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables
DROP TABLE IF EXISTS `school_state_snapshots`;
DROP TABLE IF EXISTS `homework_submissions`;
DROP TABLE IF EXISTS `learning_materials`;
DROP TABLE IF EXISTS `graduation_records`;
DROP TABLE IF EXISTS `exeat_permits`;
DROP TABLE IF EXISTS `hostel_allocations`;
DROP TABLE IF EXISTS `hostel_rooms`;
DROP TABLE IF EXISTS `hostel_blocks`;
DROP TABLE IF EXISTS `weekly_meal_marking`;
DROP TABLE IF EXISTS `meal_logs`;
DROP TABLE IF EXISTS `meal_cards`;
DROP TABLE IF EXISTS `transport_assignments`;
DROP TABLE IF EXISTS `transport_routes`;
DROP TABLE IF EXISTS `transport_vehicles`;
DROP TABLE IF EXISTS `notices`;
DROP TABLE IF EXISTS `calendar_events`;
DROP TABLE IF EXISTS `subject_scores`;
DROP TABLE IF EXISTS `student_electives`;
DROP TABLE IF EXISTS `subject_combinations`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `assessment_columns`;
DROP TABLE IF EXISTS `result_locks`;
DROP TABLE IF EXISTS `exam_marks`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `fee_payments`;
DROP TABLE IF EXISTS `fees`;
DROP TABLE IF EXISTS `marks`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `timetable`;
DROP TABLE IF EXISTS `subject_assignments`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `school_sections`;
DROP TABLE IF EXISTS `academic_batches`;
DROP TABLE IF EXISTS `academic_years`;
DROP TABLE IF EXISTS `campuses`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- 1. USERS TABLE (Authentication & Granular RBAC)
-- ========================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin', 'admin', 'headteacher', 'deputy_headteacher', 'senior_teacher', 'teacher', 'student', 'parent') NOT NULL DEFAULT 'student',
  `email` VARCHAR(120) NOT NULL,
  `campus_id` VARCHAR(50) DEFAULT 'all',
  `privileges` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_username` (`username`),
  UNIQUE KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 2. CAMPUSES TABLE (Multi-Campus & Multi-School Support)
-- ========================================================
CREATE TABLE `campuses` (
  `campus_id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `campus_type` ENUM('Secondary', 'Primary', 'Combined', 'ECE', 'Tertiary') NOT NULL DEFAULT 'Secondary',
  `curriculum_system` VARCHAR(60) NOT NULL DEFAULT 'National_9_Point',
  `motto` VARCHAR(255) DEFAULT 'Strive for Excellence and Moral Integrity',
  `center_no` VARCHAR(80) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `website` VARCHAR(150) DEFAULT NULL,
  `principal_name` VARCHAR(100) DEFAULT 'Dr. A. Vance (Ph.D)',
  `principal_title` VARCHAR(80) DEFAULT 'Head Teacher / Principal',
  `logo_url` TEXT DEFAULT NULL,
  `total_capacity` INT UNSIGNED DEFAULT 500,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`campus_id`),
  UNIQUE KEY `idx_campus_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 3. ACADEMIC YEARS TABLE
-- ========================================================
CREATE TABLE `academic_years` (
  `year_id` VARCHAR(50) NOT NULL,
  `year_name` VARCHAR(60) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `current_term` VARCHAR(50) NOT NULL DEFAULT 'TERM 2 2026',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('upcoming', 'active', 'completed') NOT NULL DEFAULT 'active',
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATE NOT NULL,
  PRIMARY KEY (`year_id`),
  UNIQUE KEY `idx_year_name` (`year_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 4. SCHOOL SECTIONS (ECE, Primary, Junior & Senior Secondary)
-- ========================================================
CREATE TABLE `school_sections` (
  `section_id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `head_title` VARCHAR(100) DEFAULT 'Section Coordinator',
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATE NOT NULL,
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `idx_section_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 5. CLASSES TABLE
-- ========================================================
CREATE TABLE `classes` (
  `class_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campus_id` VARCHAR(50) DEFAULT NULL,
  `class_name` VARCHAR(80) NOT NULL,
  `section` VARCHAR(30) NOT NULL,
  `room_number` VARCHAR(30) DEFAULT NULL,
  `capacity` INT UNSIGNED NOT NULL DEFAULT 40,
  `class_teacher_id` INT UNSIGNED DEFAULT NULL,
  `section_division` VARCHAR(30) DEFAULT 'SECONDARY',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`),
  UNIQUE KEY `idx_class_section` (`class_name`, `section`),
  KEY `idx_class_campus` (`campus_id`),
  CONSTRAINT `fk_class_campus` FOREIGN KEY (`campus_id`)
    REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 6. TEACHERS TABLE
-- ========================================================
CREATE TABLE `teachers` (
  `teacher_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `campus_id` VARCHAR(50) DEFAULT NULL,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `qualification` VARCHAR(120) DEFAULT 'Bachelor of Education',
  `hire_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`teacher_id`),
  UNIQUE KEY `idx_teachers_user_id` (`user_id`),
  KEY `idx_teachers_subject` (`subject`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teachers_campus` FOREIGN KEY (`campus_id`)
    REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 7. STUDENTS TABLE (Includes Parent Link & Boarding Status)
-- ========================================================
CREATE TABLE `students` (
  `student_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `campus_id` VARCHAR(50) DEFAULT NULL,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `dob` DATE NOT NULL,
  `class_id` INT UNSIGNED DEFAULT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `admission_no` VARCHAR(40) NOT NULL,
  `blood_group` VARCHAR(10) DEFAULT 'O+',
  `address` VARCHAR(255) DEFAULT 'Great East Road, Katete',
  `status` ENUM('active', 'graduated', 'transferred', 'promoted') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `idx_students_user_id` (`user_id`),
  UNIQUE KEY `idx_students_admission_no` (`admission_no`),
  KEY `idx_students_class_id` (`class_id`),
  KEY `idx_students_parent_id` (`parent_id`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`class_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_students_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_students_campus` FOREIGN KEY (`campus_id`)
    REFERENCES `campuses` (`campus_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 8. SUBJECTS TABLE (Core & Electives)
-- ========================================================
CREATE TABLE `subjects` (
  `subject_id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `is_elective` TINYINT(1) NOT NULL DEFAULT 0,
  `department` VARCHAR(60) NOT NULL DEFAULT 'General',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `idx_subjects_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 9. TEACHER SUBJECT ASSIGNMENTS
-- ========================================================
CREATE TABLE `subject_assignments` (
  `assignment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL DEFAULT '2025-2026',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `idx_teacher_class_subject` (`teacher_id`, `class_id`, `subject_name`),
  KEY `idx_assign_class` (`class_id`),
  CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_assign_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 10. ATTENDANCE TABLE
-- ========================================================
CREATE TABLE `attendance` (
  `attendance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
  `remarks` VARCHAR(255) DEFAULT NULL,
  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `idx_student_date` (`student_id`, `date`),
  KEY `idx_attendance_date` (`date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 11. MARKS / GRADES TABLE
-- ========================================================
CREATE TABLE `marks` (
  `mark_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `score` DECIMAL(5,2) NOT NULL,
  `term` VARCHAR(50) NOT NULL,
  `max_score` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `grade` VARCHAR(5) DEFAULT NULL,
  `teacher_comment` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mark_id`),
  UNIQUE KEY `idx_student_subject_term` (`student_id`, `subject`, `term`),
  KEY `idx_marks_term` (`term`),
  KEY `idx_marks_subject` (`subject`),
  CONSTRAINT `fk_marks_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 12. FEES TABLE
-- ========================================================
CREATE TABLE `fees` (
  `fee_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(120) NOT NULL DEFAULT 'Term Tuition & Facility Fee',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `due_date` DATE NOT NULL,
  `status` ENUM('paid', 'partial', 'unpaid') NOT NULL DEFAULT 'unpaid',
  `receipt_no` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`fee_id`),
  KEY `idx_fees_student_id` (`student_id`),
  KEY `idx_fees_status` (`status`),
  KEY `idx_fees_receipt_no` (`receipt_no`),
  CONSTRAINT `fk_fees_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 13. TIMETABLE SCHEDULE
-- ========================================================
CREATE TABLE `timetable` (
  `timetable_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` INT UNSIGNED NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
  `period_number` TINYINT UNSIGNED NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `room` VARCHAR(30) DEFAULT NULL,
  `teacher_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`timetable_id`),
  KEY `idx_timetable_class_day` (`class_id`, `day_of_week`),
  CONSTRAINT `fk_timetable_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 14. EXAMS TABLE
-- ========================================================
CREATE TABLE `exams` (
  `exam_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(120) NOT NULL,
  `exam_type` ENUM('Midterm', 'Final', 'Quiz', 'Unit Assessment', 'Mock', 'Continuous Assessment') NOT NULL DEFAULT 'Midterm',
  `class_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `total_marks` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  `passing_marks` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  `exam_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `term` VARCHAR(50) NOT NULL DEFAULT 'TERM 2 2026',
  `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`exam_id`),
  KEY `idx_exams_class` (`class_id`),
  KEY `idx_exams_date` (`exam_date`),
  CONSTRAINT `fk_exams_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 15. EXAM MARKS TABLE
-- ========================================================
CREATE TABLE `exam_marks` (
  `exam_mark_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `marks_obtained` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `grade` VARCHAR(5) DEFAULT '9',
  `points` TINYINT UNSIGNED DEFAULT 9,
  `standard` VARCHAR(50) DEFAULT 'UNSATISFACTORY',
  `remarks` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`exam_mark_id`),
  UNIQUE KEY `idx_exam_student` (`exam_id`, `student_id`),
  KEY `idx_exam_marks_grade` (`grade`),
  CONSTRAINT `fk_exam_marks_exam` FOREIGN KEY (`exam_id`)
    REFERENCES `exams` (`exam_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_marks_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 16. ASSESSMENT COLUMNS (Continuous Assessment / CA)
-- ========================================================
CREATE TABLE `assessment_columns` (
  `column_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `column_key` VARCHAR(50) NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `max_marks` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`column_id`),
  UNIQUE KEY `idx_column_key` (`column_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 17. RESULT LOCKS
-- ========================================================
CREATE TABLE `result_locks` (
  `lock_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `locked_for_teachers` TINYINT(1) NOT NULL DEFAULT 0,
  `locked_for_learners` TINYINT(1) NOT NULL DEFAULT 0,
  `locked_reason` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 18. SUBJECT SCORES TABLE (Scholastic Continuous Assessment)
-- ========================================================
CREATE TABLE `subject_scores` (
  `score_id` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `term` VARCHAR(50) NOT NULL DEFAULT 'TERM 2 2026',
  `academic_year` VARCHAR(20) NOT NULL DEFAULT '2026',
  `did_not_sit` TINYINT(1) NOT NULL DEFAULT 0,
  `assessment_scores` JSON DEFAULT NULL,
  `final_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `class_average` DECIMAL(5,2) DEFAULT 45.00,
  `grade` VARCHAR(5) DEFAULT '9',
  `points` TINYINT UNSIGNED DEFAULT 9,
  `standard` VARCHAR(50) DEFAULT 'UNSATISFACTORY',
  `teacher_comment` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`score_id`),
  UNIQUE KEY `idx_student_subject_term_score` (`student_id`, `subject_name`, `term`),
  CONSTRAINT `fk_subject_scores_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 19. HOSTEL BLOCKS & DORMITORIES
-- ========================================================
CREATE TABLE `hostel_blocks` (
  `block_id` VARCHAR(50) NOT NULL,
  `campus_id` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `gender` ENUM('Boys', 'Girls', 'Junior Boys', 'Junior Girls', 'Mixed') NOT NULL,
  `warden_name` VARCHAR(100) NOT NULL,
  `warden_phone` VARCHAR(30) DEFAULT NULL,
  `total_rooms` INT UNSIGNED NOT NULL DEFAULT 10,
  `total_capacity` INT UNSIGNED NOT NULL DEFAULT 60,
  `status` ENUM('operational', 'maintenance') NOT NULL DEFAULT 'operational',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`block_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 20. HOSTEL ALLOCATIONS (Bed Assignments)
-- ========================================================
CREATE TABLE `hostel_allocations` (
  `allocation_id` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `student_name` VARCHAR(120) NOT NULL,
  `admission_no` VARCHAR(40) NOT NULL,
  `class_name` VARCHAR(80) NOT NULL,
  `block_id` VARCHAR(50) NOT NULL,
  `block_name` VARCHAR(120) NOT NULL,
  `room_number` VARCHAR(30) NOT NULL,
  `bed_number` VARCHAR(30) NOT NULL,
  `campus_id` VARCHAR(50) DEFAULT NULL,
  `allocated_date` DATE NOT NULL,
  `status` ENUM('active', 'checked_out', 'exeat_leave') NOT NULL DEFAULT 'active',
  `emergency_contact` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`allocation_id`),
  KEY `idx_alloc_student` (`student_id`),
  KEY `idx_alloc_block` (`block_id`),
  CONSTRAINT `fk_alloc_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_alloc_block` FOREIGN KEY (`block_id`)
    REFERENCES `hostel_blocks` (`block_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 21. EXEAT PERMITS (Hostel Gate Passes)
-- ========================================================
CREATE TABLE `exeat_permits` (
  `exeat_id` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `student_name` VARCHAR(120) NOT NULL,
  `admission_no` VARCHAR(40) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `departure_date` DATE NOT NULL,
  `expected_return` DATE NOT NULL,
  `actual_return` DATE DEFAULT NULL,
  `approved_by` VARCHAR(100) NOT NULL,
  `status` ENUM('active', 'returned', 'overdue') NOT NULL DEFAULT 'active',
  `created_at` DATE NOT NULL,
  PRIMARY KEY (`exeat_id`),
  KEY `idx_exeat_student` (`student_id`),
  CONSTRAINT `fk_exeat_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 22. MEAL CARDS & CAFETERIA PASSES
-- ========================================================
CREATE TABLE `meal_cards` (
  `card_id` VARCHAR(50) NOT NULL,
  `card_number` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `student_name` VARCHAR(120) NOT NULL,
  `admission_no` VARCHAR(40) NOT NULL,
  `class_name` VARCHAR(80) NOT NULL,
  `campus_id` VARCHAR(50) DEFAULT NULL,
  `meal_plan` VARCHAR(80) NOT NULL DEFAULT 'Full Boarding (3 Meals)',
  `dietary_requirements` VARCHAR(80) NOT NULL DEFAULT 'Standard',
  `status` ENUM('active', 'suspended', 'lost', 'expired') NOT NULL DEFAULT 'active',
  `payment_status` ENUM('paid', 'unpaid', 'partial') NOT NULL DEFAULT 'paid',
  `issued_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `idx_card_number` (`card_number`),
  KEY `idx_meal_student` (`student_id`),
  CONSTRAINT `fk_meal_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 23. MEAL LOGS (Dining Hall Attendance)
-- ========================================================
CREATE TABLE `meal_logs` (
  `log_id` VARCHAR(50) NOT NULL,
  `card_id` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `student_name` VARCHAR(120) NOT NULL,
  `admission_no` VARCHAR(40) NOT NULL,
  `meal_type` ENUM('Breakfast', 'Lunch', 'Dinner', 'Tea / Snack') NOT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `campus_id` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('served', 'flagged', 'dietary_alert') NOT NULL DEFAULT 'served',
  `server_name` VARCHAR(100) NOT NULL DEFAULT 'Duty Warden',
  PRIMARY KEY (`log_id`),
  KEY `idx_log_card` (`card_id`),
  KEY `idx_log_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 24. TRANSPORT VEHICLES
-- ========================================================
CREATE TABLE `transport_vehicles` (
  `vehicle_id` VARCHAR(50) NOT NULL,
  `plate_number` VARCHAR(30) NOT NULL,
  `model` VARCHAR(80) NOT NULL,
  `driver_name` VARCHAR(100) NOT NULL,
  `driver_phone` VARCHAR(30) NOT NULL,
  `capacity` INT UNSIGNED NOT NULL DEFAULT 35,
  `assigned_route_id` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('active', 'maintenance', 'idle') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`vehicle_id`),
  UNIQUE KEY `idx_plate_number` (`plate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 25. TRANSPORT ROUTES
-- ========================================================
CREATE TABLE `transport_routes` (
  `route_id` VARCHAR(50) NOT NULL,
  `route_name` VARCHAR(120) NOT NULL,
  `start_point` VARCHAR(120) NOT NULL,
  `end_point` VARCHAR(120) NOT NULL,
  `term_fee` DECIMAL(10,2) NOT NULL DEFAULT 450.00,
  `assigned_vehicle_id` VARCHAR(50) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`route_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 26. NOTICES & ANNOUNCEMENTS
-- ========================================================
CREATE TABLE `notices` (
  `notice_id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `category` ENUM('general', 'academic', 'exam', 'fees', 'urgent') NOT NULL DEFAULT 'general',
  `author_name` VARCHAR(100) NOT NULL,
  `author_role` VARCHAR(60) NOT NULL,
  `target_type` ENUM('everyone', 'class') NOT NULL DEFAULT 'everyone',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`notice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 27. CALENDAR EVENTS
-- ========================================================
CREATE TABLE `calendar_events` (
  `event_id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `event_type` ENUM('term_date', 'holiday', 'exam', 'sports', 'parent_meeting', 'staff_meeting', 'deadline', 'cultural') NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_national` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 28. LEARNING MATERIALS & HOMEWORK
-- ========================================================
CREATE TABLE `learning_materials` (
  `material_id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `type` ENUM('homework', 'notes', 'document', 'past_paper') NOT NULL DEFAULT 'homework',
  `subject` VARCHAR(100) NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `class_name` VARCHAR(100) DEFAULT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `teacher_name` VARCHAR(120) NOT NULL,
  `due_date` DATE DEFAULT NULL,
  `max_points` DECIMAL(5,2) DEFAULT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_size` VARCHAR(50) DEFAULT NULL,
  `attachment_data` LONGTEXT DEFAULT NULL,
  `attachment_type` VARCHAR(100) DEFAULT 'application/pdf',
  `academic_year` VARCHAR(50) DEFAULT '2025-2026',
  `term` VARCHAR(50) DEFAULT 'TERM 2 2026',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`material_id`),
  KEY `idx_mat_class` (`class_id`),
  KEY `idx_mat_subject` (`subject`),
  KEY `idx_mat_teacher` (`teacher_id`),
  KEY `idx_mat_type` (`type`),
  CONSTRAINT `fk_mat_class` FOREIGN KEY (`class_id`)
    REFERENCES `classes` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mat_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 29. HOMEWORK SUBMISSIONS
-- ========================================================
CREATE TABLE `homework_submissions` (
  `submission_id` VARCHAR(50) NOT NULL,
  `material_id` VARCHAR(50) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `student_name` VARCHAR(120) NOT NULL,
  `admission_no` VARCHAR(50) NOT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submission_text` TEXT DEFAULT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_size` VARCHAR(50) DEFAULT NULL,
  `attachment_data` LONGTEXT DEFAULT NULL,
  `status` ENUM('submitted', 'graded') NOT NULL DEFAULT 'submitted',
  `grade` VARCHAR(10) DEFAULT NULL,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `teacher_feedback` TEXT DEFAULT NULL,
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `idx_sub_mat_student` (`material_id`, `student_id`),
  KEY `idx_sub_student` (`student_id`),
  CONSTRAINT `fk_sub_material` FOREIGN KEY (`material_id`)
    REFERENCES `learning_materials` (`material_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sub_student` FOREIGN KEY (`student_id`)
    REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 30. SCHOOL STATE SNAPSHOTS (Cross-Device Sync Envelope)
-- ========================================================
CREATE TABLE `school_state_snapshots` (
  `id` TINYINT UNSIGNED NOT NULL,
  `state_json` LONGTEXT NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED INITIAL CORE DATA
-- ========================================================
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Default Superadmin & Institutional Staff Users (Default password: password123)
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `email`, `campus_id`) VALUES
(100, 'superadmin', '$2y$10$IrZyr1Yyw/orR8hBWEY2IusuJebm4xLfAfMYRcDtj3lTDgxyq7aSK', 'superadmin', 'superadmin@katetems.edu', 'all'),
(1, 'admin', '$2y$10$IrZyr1Yyw/orR8hBWEY2IusuJebm4xLfAfMYRcDtj3lTDgxyq7aSK', 'superadmin', 'admin@katetems.edu', 'all'),
(101, 'headteacher', '$2y$10$IrZyr1Yyw/orR8hBWEY2IusuJebm4xLfAfMYRcDtj3lTDgxyq7aSK', 'headteacher', 'principal@katetems.edu', 'all'),
(102, 'deputy', '$2y$10$IrZyr1Yyw/orR8hBWEY2IusuJebm4xLfAfMYRcDtj3lTDgxyq7aSK', 'deputy_headteacher', 'deputy.academic@katetems.edu', 'all'),
(103, 'senior_teacher', '$2y$10$IrZyr1Yyw/orR8hBWEY2IusuJebm4xLfAfMYRcDtj3lTDgxyq7aSK', 'senior_teacher', 'hod.science@katetems.edu', 'all'),
(2, 'elena', '$2y$10$IrZyr1Yyw/orR8hBWEY2IusuJebm4xLfAfMYRcDtj3lTDgxyq7aSK', 'teacher', 'morrison@katetems.edu', 'all'),
(3, 'teacher', '$2y$10$IrZyr1Yyw/orR8hBWEY2IusuJebm4xLfAfMYRcDtj3lTDgxyq7aSK', 'teacher', 'faculty@katetems.edu', 'all')
ON DUPLICATE KEY UPDATE id=id;

-- 2. Primary Campus Record
INSERT INTO `campuses` (`campus_id`, `name`, `code`, `campus_type`, `curriculum_system`, `motto`, `center_no`, `address`, `phone`, `email`, `principal_name`, `principal_title`) VALUES
('campus_sec', 'KATETE BOARDING SECONDARY SCHOOL', 'KBSS', 'Secondary', 'National_9_Point', 'Strive for Excellence and Moral Integrity', 'ECZ EXAMINATION CENTER NO: 2049', 'Great East Road, Katete, Eastern Province, Zambia', '+260 216 252 011', 'admin@katetesms.edu', 'Dr. A. Vance (Ph.D)', 'Head Teacher / Principal')
ON DUPLICATE KEY UPDATE campus_id=campus_id;

-- 3. Core Academic Year
INSERT INTO `academic_years` (`year_id`, `year_name`, `start_date`, `end_date`, `current_term`, `is_active`, `status`, `created_at`) VALUES
('ay_2026', '2025-2026', '2026-01-12', '2026-12-04', 'TERM 2 2026', 1, 'active', '2026-01-01')
ON DUPLICATE KEY UPDATE year_id=year_id;

-- 4. Result Lock Initial Flag
INSERT INTO `result_locks` (`lock_id`, `locked_for_teachers`, `locked_for_learners`, `locked_reason`) VALUES
(1, 0, 0, 'Results active and approved for current session.')
ON DUPLICATE KEY UPDATE lock_id=lock_id;

SET FOREIGN_KEY_CHECKS = 1;
