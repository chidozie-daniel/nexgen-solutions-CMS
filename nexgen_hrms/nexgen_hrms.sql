-- Database: nexgen_hrms
-- ========================================

-- Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) UNIQUE NOT NULL,
  `username` varchar(50) UNIQUE NOT NULL,
  `email` varchar(100) UNIQUE NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('employee','project_leader','hr','admin') DEFAULT 'employee',
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default.jpg',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Table structure for table `leaves`
CREATE TABLE IF NOT EXISTS `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `leave_type` enum('sick','casual','annual','maternity','paternity','unpaid') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `duration_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `hr_remarks` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Table structure for table `tasks`
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('pending','in_progress','review','completed','cancelled') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Table structure for table `task_comments`
CREATE TABLE IF NOT EXISTS `task_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Table structure for table `projects`
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_code` varchar(50) UNIQUE NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `project_leader` int(11) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `status` enum('planning','active','on_hold','completed','cancelled') DEFAULT 'planning',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`project_leader`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Table structure for table `project_members`
CREATE TABLE IF NOT EXISTS `project_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','team_lead','contributor') DEFAULT 'member',
  `joined_date` date DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_member` (`project_id`, `user_id`)
);

-- Table structure for table `inquiries`
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `service` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','contacted','converted','closed') DEFAULT 'new',
  `assigned_to` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Table structure for table `salaries`
CREATE TABLE IF NOT EXISTS `salaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `month` varchar(7) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `overtime_hours` decimal(5,2) DEFAULT 0,
  `overtime_rate` decimal(10,2) DEFAULT 0,
  `bonus` decimal(10,2) DEFAULT 0,
  `deductions` decimal(10,2) DEFAULT 0,
  `tax` decimal(10,2) DEFAULT 0,
  `net_salary` decimal(10,2) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` enum('bank_transfer','cash','check','online') DEFAULT 'bank_transfer',
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_salary` (`user_id`, `month`)
);

-- Table structure for table `attendance`
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT 0,
  `status` enum('present','absent','half_day','leave','holiday') DEFAULT 'absent',
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_attendance` (`user_id`, `date`)
);

-- Insert default admin user (password: Admin@123)
INSERT IGNORE INTO users (employee_id, username, email, password, full_name, role, department, position, salary, status) 
VALUES ('ADMIN001', 'admin', 'admin@nexgensolutions.com', '$2y$10$BOSoR5WJytjHVqI8F/sxCehhlsCAp4ftUURYQP4Ejm6LZwrnAqooe', 'System Administrator', 'admin', 'IT', 'Administrator', 100000.00, 'active');

-- Insert default HR user (password: hr123)
INSERT IGNORE INTO users (employee_id, username, email, password, full_name, role, department, position, salary, status) 
VALUES ('HR001', 'hrmanager', 'hr@nexgensolutions.com', '$2y$10$P8Igw37k.ExaMDqRC0YVXeZwimCTrHkigrYazIH5OG5RI8pquks2m', 'HR manager', 'hr', 'Human Resources', 'HR Manager', 45000.00, 'active');

-- Insert default Project Leader user (password: pl123)
INSERT IGNORE INTO users (employee_id, username, email, password, full_name, role, department, position, salary, status) 
VALUES ('PL001', 'projlead', 'pl@nexgensolutions.com', '$2y$10$xDb451.8LK60i/PxV0KY2ebQSmxEZyfRIPNx6bPi.TXWobFy.Wzkq', 'Project leader', 'project_leader', 'Development', 'Project Leader', 55000.00, 'active');

-- Insert default Employee user (password: Employee@123)
INSERT IGNORE INTO users (employee_id, username, email, password, full_name, role, department, position, salary, status) 
VALUES ('EMP001', 'employee', 'employee@nexgensolutions.com', '$2y$10$JqDUIPknrL0qu/g8wjcKqOqsAEPpHFaNazS/IIijNhQ3g5VcojOiO', 'Employee', 'employee', 'Development', 'Software Engineer', 40000.00, 'active');

-- Create indexes for better performance
-- Note: MySQL does not support "CREATE INDEX IF NOT EXISTS" across common versions.
-- These may error on re-run; the setup script suppresses duplicate-index errors.
CREATE INDEX idx_user_role ON users(role);
CREATE INDEX idx_task_status ON tasks(status);
CREATE INDEX idx_leave_status ON leaves(status);
CREATE INDEX idx_salary_month ON salaries(month);