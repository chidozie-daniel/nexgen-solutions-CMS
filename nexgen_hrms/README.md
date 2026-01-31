# NexGen HRMS - Human Resource Management System

A comprehensive HR management system built with PHP, MySQL, and Bootstrap 5.

## Features

### Core Features
- **User Authentication & Authorization**
  - Role-based access control (Admin, HR, Project Leader, Employee)
  - Secure password hashing
  - Session management

- **Dashboard**
  - Personalized dashboard based on user role
  - Real-time statistics and metrics
  - Quick access to common functions

- **Leave Management**
  - Apply for different types of leave (Annual, Sick, Casual)
  - Leave balance tracking
  - Approval workflow for HR/Admin
  - Leave history and reports

- **Task Management**
  - Task assignment and tracking
  - Progress monitoring
  - Status updates and comments
  - Deadline management

- **Project Management**
  - Project creation and management
  - Team member assignment
  - Project status tracking
  - Budget and timeline management

- **Payroll Management**
  - Salary details and history
  - Payment processing
  - Deductions and bonuses
  - Payroll reports

- **User Management** (Admin/HR only)
  - Employee registration
  - Profile management
  - Role assignment
  - Status management

### Security Features
- SQL injection prevention with prepared statements
- XSS protection
- Session security
- Input validation
- CSRF protection

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/MAMP (for local development)

### Setup Instructions

1. **Clone/Download the project**
   ```bash
   git clone <repository-url>
   cd nexgen_hrms
   ```

2. **Database Setup**
   - Create a MySQL database named `nexgen_hrms`
   - Run the provided SQL file:
     ```sql
   -- Import nexgen_hrms.sql file
   ```
   - Or use the setup script:
   ```
   http://localhost/nexgen_hrms/setup_database.php
   ```

3. **Configuration**
   - Update database credentials in `config/database.php` if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'nexgen_hrms');
   ```

4. **Default Login**
   - **Admin**: `admin` / `admin123`
   - **HR**: `hrmanager` / `hr123`
   - **Project Leader**: `projlead` / `pl123`
   - **Employee**: `employee` / `emp123`

### Directory Structure

```
nexgen_hrms/
├── config/
│   └── database.php          # Database configuration
├── includes/
│   ├── auth.php             # Authentication class
│   ├── functions.php        # Helper functions
│   ├── header.php          # HTML header and navigation
│   └── footer.php          # HTML footer and scripts
├── modules/
│   ├── admin/              # Admin-specific modules
│   ├── leave/              # Leave management
│   ├── payroll/            # Payroll management
│   ├── projects/           # Project management
│   └── tasks/              # Task management
├── assets/                # Static assets (CSS, JS, images)
├── index.php              # Public landing page
├── login.php              # Login page
├── dashboard.php          # Main dashboard
├── register.php           # User registration
├── logout.php             # Logout handler
├── setup_database.php     # Database setup script
└── nexgen_hrms.sql       # Database schema
```

## User Roles and Permissions

### Admin
- Full system access
- User management
- System settings
- All module permissions

### HR (Human Resources)
- Leave management and approval
- Payroll processing
- Employee management
- Reports generation

### Project Leader
- Task assignment
- Project management
- Team coordination
- Progress monitoring

### Employee
- Personal dashboard
- Leave applications
- Task management
- Payroll viewing

## Security Considerations

1. **SQL Injection**: All database queries use prepared statements
2. **XSS Protection**: Output is properly escaped
3. **Authentication**: Secure session management
4. **Input Validation**: Form inputs are validated
5. **Password Security**: Uses PHP's password_hash() function

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Support

For issues and support:
1. Check the error logs
2. Verify database connection
3. Ensure proper file permissions
4. Test with default credentials

## License

This project is for educational and demonstration purposes.

---

**Note**: This is a demonstration project. For production use, additional security measures and testing are recommended.
