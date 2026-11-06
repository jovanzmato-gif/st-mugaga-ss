CREATE DATABASE IF NOT EXISTS st_mugaga_ss CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE st_mugaga_ss;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  category ENUM('news','event','announcement') NOT NULL DEFAULT 'news',
  image_url VARCHAR(255) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(20) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  level ENUM('O Level','A Level') NOT NULL,
  description VARCHAR(255) NULL,
  UNIQUE KEY uq_subject (name, level)
);

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admission_no VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  gender ENUM('Male','Female') NOT NULL,
  date_of_birth DATE NULL,
  parent_contact VARCHAR(20) NULL,
  class_id INT NOT NULL,
  passport_photo VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_full_name VARCHAR(150) NOT NULL,
  gender ENUM('Male','Female') NOT NULL,
  date_of_birth DATE NOT NULL,
  parent_contact VARCHAR(20) NOT NULL,
  previous_school VARCHAR(150) NOT NULL,
  ple_aggregates TINYINT UNSIGNED NULL,
  desired_class_id INT NOT NULL,
  passport_photo VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (desired_class_id) REFERENCES classes(id)
);

CREATE TABLE IF NOT EXISTS results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  term ENUM('Term 1','Term 2','Term 3') NOT NULL,
  year SMALLINT NOT NULL,
  subject_id INT NOT NULL,
  marks DECIMAL(5,2) NOT NULL,
  grade CHAR(1) NOT NULL,
  remark VARCHAR(100) NULL,
  UNIQUE KEY uq_result (student_id, term, year, subject_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

INSERT IGNORE INTO classes (name) VALUES ('S1'),('S2'),('S3'),('S4'),('S5'),('S6');

INSERT IGNORE INTO subjects (name, level) VALUES
('English','O Level'),('Mathematics','O Level'),('Biology','O Level'),
('Physics','O Level'),('Chemistry','O Level'),('History','O Level'),
('Geography','O Level'),('Entrepreneurship','O Level'),('Computer Studies','O Level'),
('Religious Education','O Level'),
('Mathematics','A Level'),('Physics','A Level'),('Chemistry','A Level'),
('Biology','A Level'),('Economics','A Level'),('Geography','A Level'),
('History','A Level'),('Entrepreneurship','A Level'),('Literature in English','A Level'),
('Agriculture','A Level');

INSERT INTO posts (title, body, category) VALUES
('Welcome to St. Mugaga Secondary School',
 'Welcome to our school website. Learn about our subjects, admissions, and school life.',
 'announcement');