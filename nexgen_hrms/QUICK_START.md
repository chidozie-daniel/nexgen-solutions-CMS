# Quick Start Guide - NexGen HRMS

## ⚡ 5-Minute Setup

### Step 1: Ensure Services Are Running
- XAMPP Control Panel: Start **Apache** and **MySQL**
- Default MySQL credentials: `root` (no password)

### Step 2: Database Initialization
1. Open web browser
2. Go to: `http://localhost/nexgen_hrms/test_setup.php`
3. Click "Run Setup" (if button appears) or page auto-runs
4. Wait for all green checkmarks ✓
5. Note any errors in red ✗

### Step 3: First Login
1. Go to: `http://localhost/nexgen_hrms/login.php`
2. Use one of these credentials:

| Role | Username | Password |
|------|----------|----------|
| Administrator | admin | Admin@123 |
| HR Manager | hrmanager | hr123 |
| Project Leader | projlead | pl123 |
| Employee | employee | Employee@123 |

3. Click "Sign In"

### Step 4: Explore Dashboard
- View your personalized dashboard
- Check available actions based on your role
- Navigate using the left sidebar

---

## 🎯 Key Features by Role

### Employee
- Apply for leave
- View my tasks
- Check salary information
- Submit task updates

### HR Manager
- Approve/Reject leave requests
- Process payroll
- Manage employee records
- View system reports

### Project Leader
- Create new projects
- Assign team members
- Assign tasks
- Track project progress

### Administrator
- User management (all features)
- System settings
- View all reports
- Full system access

---

## 📋 Common Tasks

### Apply for Leave
1. Dashboard → Quick Actions → "Apply for Leave"
2. Select leave type and dates
3. Enter reason
4. Submit application
5. HR will review and approve/reject

### Assign a Task
1. Dashboard → Quick Actions → "Assign Task"
2. Select employee
3. Enter task details
4. Set priority and due date
5. Submit
6. Employee receives task assignment

### Create a Project
1. Dashboard → Projects → "Create New Project"
2. Fill in project details
3. Select team members
4. Set budget and timeline
5. Create project
6. Start assigning tasks

### Process Payroll
*HR/Admin only*
1. Navigate to Payroll → Process Payroll
2. Select month
3. Review salary details
4. Approve salaries
5. Mark as paid

---

## 🔧 Troubleshooting

### "Connection Failed" Error
- [ ] Is MySQL running? (Check XAMPP)
- [ ] Is the database created? (Run test_setup.php)
- [ ] Check `config/database.php` for correct settings

### Can't Login
- [ ] Did you run test_setup.php? (Creates default users)
- [ ] Are you using correct password? (Check table above)
- [ ] Clear browser cache and try again

### Missing Data in Tables
- [ ] Ensure test_setup.php completed successfully
- [ ] Check for red error messages
- [ ] Verify all tables exist in phpMyAdmin

### Page Shows Blank/Errors
- [ ] Check browser console (F12) for JavaScript errors
- [ ] Verify all files are in correct directories
- [ ] Ensure PHP is processing the file

---

## 📁 Important Files & Directories

| Path | Purpose |
|------|---------|
| `config/database.php` | Database credentials |
| `includes/auth.php` | Authentication logic |
| `modules/` | Feature modules |
| `public/uploads/` | File storage (needs write permission) |
| `test_setup.php` | Database setup validation |
| `setup_database.php` | Database initialization |

---

## 🔐 Security Tips

1. **Change Default Passwords**: After first login, change passwords
2. **Use HTTPS**: Enable SSL in production
3. **Backup Data**: Regular database backups
4. **File Permissions**: Set proper permissions on `public/uploads/`
5. **Keep Updated**: Check for security updates

---

## 📞 Getting Help

### Check These First:
1. Read: `BUG_FIX_SUMMARY.md` - What was fixed
2. Read: `SETUP_GUIDE.md` - Detailed setup instructions
3. Check: Browser console (F12) for JavaScript errors
4. Verify: All files exist in correct locations

### Common Issues & Solutions:
- **Database won't create**: Run test_setup.php, check MySQL is running
- **Can't login**: Verify test_setup.php created users (check in phpMyAdmin)
- **Pages show errors**: Check if all includes paths are correct
- **Files not uploading**: Set write permission on `public/uploads/`

---

## 🚀 Next Steps

1. ✓ Explore all modules as different users
2. ✓ Test leave application workflow
3. ✓ Create a test project
4. ✓ Assign some tasks
5. ✓ Test payroll processing
6. ✓ Change user passwords
7. ✓ Customize system as needed

---

## 📊 Dashboard Overview

Your dashboard shows:
- **Welcome message** with personalized greeting
- **Quick stats** based on your role
- **Recent activities** (tasks, leaves, etc.)
- **Quick action buttons** for common tasks
- **Pending approvals** (HR/Admin only)

---

## ⌚ Time Estimates

| Task | Time |
|------|------|
| Database setup | 1-2 minutes |
| First login | 1 minute |
| Explore dashboard | 5 minutes |
| Apply for leave | 2-3 minutes |
| Create project | 5-10 minutes |
| Assign task | 2-3 minutes |
| Process payroll | 10-15 minutes |

---

## 📝 Notes

- Demo data is pre-populated in the database
- You can add more employees using the admin panel
- All test credentials are documented in login.php
- The system uses UTC timezone by default
- Fiscal year is calendar year (Jan-Dec)

---

**Happy using NexGen HRMS! 🎉**

For detailed documentation, see:
- `SETUP_GUIDE.md` - Complete setup guide
- `BUG_FIX_SUMMARY.md` - Technical fixes and improvements
- `README.md` - Project overview (if exists)
