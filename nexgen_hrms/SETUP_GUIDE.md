# NexGen HRMS - Setup and Implementation Guide

## Project Overview
NexGen HRMS is a comprehensive Human Resources Management System built with PHP, MySQL, and Bootstrap 5. It provides complete functionality for employee management, leave processing, task assignment, project management, and payroll processing.

## Bug Fixes and Improvements Applied

### Security Fixes

#### 1. SQL Injection Vulnerabilities (CRITICAL)
- **Fixed in `modules/payroll/process.php`**: Converted direct SQL queries to prepared statements
  - Line 19-32: Single payroll action queries now use parameterized statements
  - Line 45-62: Bulk action queries now safely handle array parameters
  
- **Fixed in `dashboard.php`**: All user-specific statistics queries now use prepared statements
  - Lines 43, 56, 68, 78, 301, 350: Changed from direct SQL interpolation to parameterized queries

- **Fixed in `modules/payroll/submit_inputs.php`**: Team member queries now use prepared statements
  - Lines 14-29: Project leader team member retrieval fixed

- **Fixed in `modules/admin/users.php`**: User management actions now use prepared statements
  - Lines 18-61: All user action queries (activate, suspend, delete) now use parameterized statements

#### 2. Cross-Site Scripting (XSS) Vulnerabilities
- **Fixed in `modules/tasks/view.php`**: Added HTML escaping to all user-controlled output
  - Task titles, project names, assigned user names, and comments now properly escaped

- **Fixed in `register.php`**: Employee ID in success message now HTML escaped

- **Fixed in `modules/payroll/process.php`**: All displayed employee data now properly escaped

#### 3. Database and Configuration Issues
- **Updated `nexgen_hrms.sql`**: 
  - Added `task_comments` table (was referenced but missing)
  - Added proper `IF NOT EXISTS` clauses to all CREATE TABLE statements
  - Added unique constraints and indexes for better performance
  - Fixed default user inserts with proper password hashes
  - Updated INSERT statements to use `INSERT IGNORE` to prevent duplicate key errors

- **Fixed `setup_database.php`**:
  - Updated password generation to use correct hashing
  - Fixed prepared statement usage for user inserts
  - Corrected demo credentials to match login.php

### Functionality Improvements

#### 1. Database Setup
- Created `test_setup.php` for comprehensive setup validation
- Database initialization now properly handles all required tables
- Proper error handling for database operations

#### 2. Authentication
- Login system properly hashes passwords using `password_hash()` with PASSWORD_DEFAULT
- Demo credentials updated:
  - **Admin**: admin / Admin@123
  - **HR Manager**: hrmanager / hr123
  - **Project Leader**: projlead / pl123
  - **Employee**: employee / Employee@123

#### 3. Module Structure
- All modules properly include header and footer files
- Consistent page title handling across all modules
- Proper role-based access control on all pages

## Installation Instructions

### Step 1: Database Setup
1. Ensure XAMPP/MySQL is running
2. Open your browser and navigate to: `http://localhost/nexgen_hrms/test_setup.php`
3. The script will automatically:
   - Create the database
   - Create all necessary tables
   - Insert default test users
   - Validate the setup

### Step 2: Verify Installation
Check the test setup page for all green checkmarks. If any errors appear, ensure:
- MySQL server is running
- Database user has proper permissions
- No existing database conflicts

### Step 3: Login
1. Navigate to `http://localhost/nexgen_hrms/login.php`
2. Use any of the demo credentials above
3. Dashboard will load based on user role

## Project Structure

```
nexgen_hrms/
├── config/
│   └── database.php           # Database configuration
├── includes/
│   ├── auth.php              # Authentication class
│   ├── functions.php         # Helper functions
│   ├── header.php            # Page header template
│   └── footer.php            # Page footer template
├── modules/
│   ├── admin/
│   │   ├── users.php         # User management
│   │   ├── settings.php      # System settings
│   │   └── reports.php       # Reports
│   ├── leave/
│   │   ├── apply.php         # Apply for leave
│   │   ├── manage.php        # Manage leave requests
│   │   └── my_leaves.php     # Employee's leave history
│   ├── tasks/
│   │   ├── assign.php        # Assign new task
│   │   ├── my_tasks.php      # View assigned tasks
│   │   ├── view.php          # View task details
│   │   └── add_comment.php   # Add task comments
│   ├── projects/
│   │   ├── index.php         # List projects
│   │   ├── create.php        # Create new project
│   │   ├── details.php       # Project details
│   │   ├── update.php        # Update project
│   │   └── add_member.php    # Add project members
│   ├── payroll/
│   │   ├── my_salary.php     # View salary
│   │   ├── submit_inputs.php # Submit payroll inputs
│   │   └── process.php       # Process payroll
│   └── inquiries/
│       ├── list.php          # List inquiries
│       ├── view.php          # View inquiry details
│       └── send_reply.php    # Send reply
├── public/
│   └── uploads/              # User uploads
├── assets/
│   ├── css/                  # Stylesheets
│   └── js/                   # JavaScript files
├── index.php                 # Landing page
├── login.php                 # Login page
├── register.php              # User registration (admin only)
├── dashboard.php             # Main dashboard
├── logout.php                # Logout
├── test_setup.php            # Setup validation script
├── nexgen_hrms.sql           # Database schema
└── setup_database.php        # Database setup script

```

## Database Schema

### Main Tables:
- **users**: Employee information and authentication
- **leaves**: Leave requests and approval tracking
- **tasks**: Task assignments and status tracking
- **task_comments**: Comments on tasks
- **projects**: Project information and status
- **project_members**: Project team membership
- **salaries**: Payroll information
- **attendance**: Attendance tracking
- **inquiries**: Client inquiries

## Features

### Employee Module
- View personal dashboard
- Apply for leave with balance tracking
- View assigned tasks
- Update task progress
- Add task comments
- View salary information

### HR Module (In Addition to Employee Features)
- Manage employee leave requests (approve/reject)
- View all employee information
- Process payroll
- Generate reports
- Manage user accounts

### Admin Module (All Features)
- Full user and system management
- Complete payroll processing
- System settings configuration
- Access to all reports
- Ability to modify any record

### Project Leader Module (In Addition to Employee Features)
- Create and manage projects
- Assign team members
- Assign tasks to team members
- View team progress

## Testing

### Quick Test:
1. Go to `http://localhost/nexgen_hrms/test_setup.php` to validate setup
2. Log in with admin credentials
3. Navigate through dashboard
4. Test leave application
5. Test task assignment

### Demo Scenarios:
1. **Admin**: Full system access, user management
2. **HR Manager**: Leave approval, payroll processing, employee management
3. **Project Leader**: Create projects, assign tasks, manage team
4. **Employee**: Apply leave, view tasks, see salary

## Security Notes

- All SQL queries now use prepared statements to prevent SQL injection
- All user output is HTML escaped to prevent XSS attacks
- Passwords are hashed using PHP's `password_hash()` function
- Role-based access control enforced on all protected pages
- Session management implemented with proper login/logout flow

## Troubleshooting

### Database Connection Failed
- Ensure MySQL is running
- Check `config/database.php` for correct credentials
- Verify database exists using `phpMyAdmin`

### Login Issues
- Clear browser cookies and cache
- Verify test user exists in database
- Check `setup_database.php` was run successfully

### Module Errors
- Ensure all files are in correct directories
- Check file permissions
- Verify includes paths are correct

## Next Steps

1. **Customize**: Update company name, logo, and branding
2. **Add More Employees**: Use admin panel to register employees
3. **Configure Settings**: Set up leave allocations and payroll parameters
4. **Generate Reports**: Use the reports module for analytics
5. **Backup Data**: Regular database backups recommended

## Support

For issues or questions, check:
1. Application logs in browser console
2. MySQL error logs
3. File permissions on `/public/uploads/` directory
4. PHP error logs in XAMPP

## Version Information
- **Current Version**: 1.0
- **Last Updated**: January 2026
- **Built With**: PHP, MySQL, Bootstrap 5, jQuery

---

**Installation Complete!** Your NexGen HRMS is ready to use.
