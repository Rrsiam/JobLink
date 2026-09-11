CREATE DATABASE IF NOT EXISTS joblink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE joblink;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS saved_jobs;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS applicant_profiles;
DROP TABLE IF EXISTS employer_profiles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 phone VARCHAR(30) DEFAULT NULL,
 password VARCHAR(255) NOT NULL,
 role ENUM('applicant','employer','admin') NOT NULL,
 status ENUM('active','pending','suspended','inactive') DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE applicant_profiles (
 user_id INT PRIMARY KEY,
 professional_title VARCHAR(150) DEFAULT NULL,
 address VARCHAR(255) DEFAULT NULL,
 dob DATE DEFAULT NULL,
 gender VARCHAR(30) DEFAULT NULL,
 career_objective TEXT,
 education TEXT,
 experience TEXT,
 skills TEXT,
 certifications TEXT,
 languages VARCHAR(255) DEFAULT NULL,
 resume VARCHAR(255) DEFAULT NULL,
 photo VARCHAR(255) DEFAULT NULL,
 CONSTRAINT fk_applicant_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE employer_profiles (
 user_id INT PRIMARY KEY,
 company_name VARCHAR(160) NOT NULL,
 industry VARCHAR(120) DEFAULT NULL,
 address VARCHAR(255) DEFAULT NULL,
 website VARCHAR(180) DEFAULT NULL,
 company_size VARCHAR(80) DEFAULT NULL,
 description TEXT,
 benefits TEXT,
 logo VARCHAR(255) DEFAULT NULL,
 cover VARCHAR(255) DEFAULT NULL,
 verified TINYINT(1) DEFAULT 0,
 CONSTRAINT fk_employer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 status ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE jobs (
 id INT AUTO_INCREMENT PRIMARY KEY,
 employer_id INT NOT NULL,
 category_id INT DEFAULT NULL,
 title VARCHAR(180) NOT NULL,
 type ENUM('Full-time','Part-time','Internship','Remote') DEFAULT 'Full-time',
 workplace ENUM('Office','Remote','Hybrid') DEFAULT 'Office',
 location VARCHAR(160) NOT NULL,
 vacancy INT DEFAULT 1,
 salary_min DECIMAL(12,2) DEFAULT NULL,
 salary_max DECIMAL(12,2) DEFAULT NULL,
 education TEXT,
 experience TEXT,
 skills TEXT,
 description TEXT NOT NULL,
 responsibilities TEXT,
 benefits TEXT,
 deadline DATE NOT NULL,
 status ENUM('draft','active','closed','expired') DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_job_employer FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_job_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE applications (
 id INT AUTO_INCREMENT PRIMARY KEY,
 job_id INT NOT NULL,
 applicant_id INT NOT NULL,
 cover_letter TEXT,
 resume_path VARCHAR(255) DEFAULT NULL,
 status ENUM('pending','under review','shortlisted','interview','accepted','rejected','hired') DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_application(job_id,applicant_id),
 CONSTRAINT fk_application_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 CONSTRAINT fk_application_applicant FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE saved_jobs (
 id INT AUTO_INCREMENT PRIMARY KEY,
 applicant_id INT NOT NULL,
 job_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_saved_job(applicant_id,job_id),
 CONSTRAINT fk_saved_applicant FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_saved_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_settings (
 user_id INT PRIMARY KEY,
 job_recommendations TINYINT(1) DEFAULT 1,
 status_updates TINYINT(1) DEFAULT 1,
 employer_messages TINYINT(1) DEFAULT 1,
 weekly_summary TINYINT(1) DEFAULT 1,
 CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(150) NOT NULL,
 phone VARCHAR(30) DEFAULT NULL,
 subject VARCHAR(180) NOT NULL,
 user_type VARCHAR(50) DEFAULT NULL,
 message TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories(name) VALUES
('IT & Software'),('Marketing'),('Business'),('Design'),('Education'),('Customer Service'),('Accounting'),('Healthcare');

-- All demo accounts use password: password123
INSERT INTO users(id,name,email,phone,password,role,status) VALUES
(1,'System Admin','admin@joblink.test','01700000001','$2y$12$0Ri4.Ch1BCk5UhRWdMGZ4eiQucMZltCSpkqGFlFTB5ye3yaMyw6QK','admin','active'),
(2,'Rahim Ahmed','applicant@joblink.test','01700000002','$2y$12$0Ri4.Ch1BCk5UhRWdMGZ4eiQucMZltCSpkqGFlFTB5ye3yaMyw6QK','applicant','active'),
(3,'Nusrat Jahan','nusrat@joblink.test','01700000003','$2y$12$0Ri4.Ch1BCk5UhRWdMGZ4eiQucMZltCSpkqGFlFTB5ye3yaMyw6QK','applicant','active'),
(4,'Tanvir Hasan','employer@joblink.test','01700000004','$2y$12$0Ri4.Ch1BCk5UhRWdMGZ4eiQucMZltCSpkqGFlFTB5ye3yaMyw6QK','employer','active'),
(5,'Mim Akter','hr@digitalmind.test','01700000005','$2y$12$0Ri4.Ch1BCk5UhRWdMGZ4eiQucMZltCSpkqGFlFTB5ye3yaMyw6QK','employer','active');

INSERT INTO applicant_profiles(user_id,professional_title,address,career_objective,education,experience,skills,certifications,languages) VALUES
(2,'Junior Web Developer','Dhaka','Motivated web development student seeking an entry-level opportunity.','BSc in Computer Science, ongoing','Academic projects and freelance practice','HTML, CSS, JavaScript, PHP, MySQL','Responsive Web Design','Bangla, English'),
(3,'UI/UX Designer','Chattogram','Creative designer interested in user-centered digital products.','BSc in CSE','One year of design practice','Figma, UI Design, Prototyping','Google UX Certificate','Bangla, English');

INSERT INTO employer_profiles(user_id,company_name,industry,address,website,company_size,description,benefits,verified) VALUES
(4,'TechSoft Ltd.','Software and IT Services','Banani, Dhaka','https://example.com','51–100 employees','TechSoft builds web and business software for local and international clients.','Learning support, performance bonus, flexible leave',1),
(5,'Digital Mind','Digital Marketing','Agrabad, Chattogram','https://example.org','11–50 employees','Digital Mind provides creative marketing and content services.','Training, remote days, team events',1);

INSERT INTO jobs(id,employer_id,category_id,title,type,workplace,location,vacancy,salary_min,salary_max,education,experience,skills,description,responsibilities,benefits,deadline,status) VALUES
(1,4,1,'Junior Web Developer','Full-time','Office','Dhaka',3,25000,35000,'Bachelor degree or current final-year student','0–2 years','HTML, CSS, JavaScript, PHP, MySQL','Join our development team and work on responsive business websites.','Build pages, connect PHP forms, work with MySQL, test and debug features.','Performance bonus, learning support, friendly environment',DATE_ADD(CURDATE(), INTERVAL 30 DAY),'active'),
(2,4,4,'UI/UX Designer','Full-time','Hybrid','Dhaka',2,30000,45000,'Bachelor degree preferred','1–2 years','Figma, Wireframing, Prototyping','Design simple and effective interfaces for web applications.','Create wireframes, design systems, prototypes, and handoff files.','Hybrid work and training budget',DATE_ADD(CURDATE(), INTERVAL 25 DAY),'active'),
(3,5,2,'Digital Marketing Intern','Internship','Remote','Remote',4,12000,18000,'University student','No experience required','Social Media, Content Writing, Canva','Support social media campaigns and content planning.','Prepare posts, research trends, and support campaign reports.','Remote work and internship certificate',DATE_ADD(CURDATE(), INTERVAL 20 DAY),'active'),
(4,5,6,'Customer Support Executive','Full-time','Office','Chattogram',5,20000,28000,'Bachelor degree','0–1 year','Communication, Customer Service','Provide helpful support to customers through phone and email.','Answer questions, maintain records, and escalate issues.','Festival bonus and paid leave',DATE_ADD(CURDATE(), INTERVAL 18 DAY),'active');

INSERT INTO applications(job_id,applicant_id,cover_letter,status) VALUES
(1,2,'I have completed multiple PHP and MySQL projects and would like to join your team.','shortlisted'),
(2,3,'My Figma and prototyping experience matches this role.','under review'),
(4,2,'I have strong communication skills and can support customers professionally.','pending');

INSERT INTO saved_jobs(applicant_id,job_id) VALUES (2,2),(2,3);
INSERT INTO user_settings(user_id) VALUES (2),(3),(4),(5);
