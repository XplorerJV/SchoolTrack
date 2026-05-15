# Quick Start Guide - School Attendance Management System

## 🚀 Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser

### Step 1: Database Setup

```bash
# Option 1: Using MySQL command line
mysql -u root -p < database.sql

# Option 2: Using phpMyAdmin
# 1. Open http://localhost/phpmyadmin
# 2. Click "Import" tab
# 3. Select database.sql file
# 4. Click "Go"
```

This will:
- Create database `school_attendance`
- Create all tables with schema
- Insert demo data (8 students, 3 users, 30 days of attendance)
- Insert default settings

### Step 2: Configuration

Edit `config.php`:

```php
// Database credentials
define('DB_HOST', 'localhost');   // Your MySQL host
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'school_attendance');

// Application URL
define('APP_URL', 'http://localhost/school'); // Your application URL
```

### Step 3: File Permissions

```bash
chmod 755 school
chmod 755 school/uploads
chmod 755 school/api
```

### Step 4: Verify Installation

Open browser and navigate to:
```
http://localhost/school
```

You should see the login page with demo credentials.

---

## 📝 Demo Account Credentials

```
ADMIN:
  Email: admin@school.com
  Password: password
  Access: Full admin features

PRINCIPAL:
  Email: principal@school.com
  Password: password
  Access: Reports, Attendance, Dashboard

TEACHER:
  Email: teacher@school.com
  Password: password
  Access: Dashboard, My Attendance, Mark Student Attendance
```

---

## ⚙️ Configuration Guide

### 1. Update School Settings

**Path:** Admin Dashboard → Settings

Configure:
- **School Name** - Your school name
- **School Address** - Physical address
- **Academic Year** - Current academic year (e.g., 2025-2026)
- **School Start Time** - When school opens (e.g., 07:30)
- **Late Time Cutoff** - When students are marked late (e.g., 08:15)
- **Absence Cutoff Time** - When students are marked absent if not checked in (e.g., 09:30)

### 2. Setup Email Notifications

**Path:** Admin Dashboard → Settings → Email Notifications

#### Gmail (Recommended)

```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
Username: your-email@gmail.com
Password: your-app-specific-password
From Name: School Attendance System
```

**Getting Gmail App Password:**
1. Enable 2-Step Verification on Gmail
2. Go to Security settings
3. Click "App passwords"
4. Select "Mail" and "Windows Computer"
5. Copy the generated password

#### Office 365

```
SMTP Host: smtp.office365.com
SMTP Port: 587
Encryption: TLS
Username: your-email@company.com
Password: your-password
From Name: School Attendance System
```

#### Custom SMTP

Configure as per your email provider's specifications.

### 3. Add Students

**Path:** Admin Dashboard → Manage Students

Click "Add Student" and fill in:
- Student Name
- Roll Number (must be unique)
- Class
- Section (optional)
- Card UID (RFID card ID)
- Parent Email
- Parent Phone (optional)
- Date of Birth (optional)

### 4. Add Teachers

**Path:** Admin Dashboard → Manage Teachers

Click "Add Teacher" and fill in:
- Teacher Name
- Email
- Phone
- Subject
- Employee ID
- Password (will be set initially as `password`)

---

## 📊 Using the System

### For Admin Users

**Dashboard:**
- View overall statistics
- See today's attendance
- Quick access to all features

**Daily Report:**
- View attendance for specific date
- Filter by class
- See summary statistics
- Export to CSV

**Performance Tracking:**
- View student performance metrics
- See top performers
- Identify frequent absentees
- Track class-wise performance

**Reports:**
- Generate daily/monthly reports
- View absentee lists
- Export data

**Audit Logs:**
- View all system activities
- Filter by action, module, date
- Track changes

### For Teachers

**Dashboard:**
- View their own attendance
- See quick action cards
- Access student list

**Mark Attendance:**
- Select date
- Filter by class
- Mark student attendance
- Add notes/reasons
- All changes are logged

**My Attendance:**
- Mark their own attendance
- View attendance history
- Update records if needed

**Students:**
- View all students
- See student details
- Check attendance percentage

### For Principals

**Dashboard:**
- School overview
- Today's attendance
- Absent student list
- Class-wise performance

**Reports:**
- Generate various reports
- Export for analysis

**Attendance:**
- View attendance records
- Monitor trends

---

## 🔄 Automatic Attendance Processing

### Absent Marking Process

The system automatically marks students absent if they're not checked in by the cutoff time (default: 09:30).

**Setup Cron Job (Optional):**

```bash
# Edit crontab
crontab -e

# Add this line to run every morning at 09:35 AM
35 9 * * * php /var/www/html/school/api/process-absent.php
```

Or run manually:
```bash
php /var/www/html/school/api/process-absent.php
```

---

## 📱 Mobile Compatibility

The system is mobile-friendly and works on:
- Smartphones
- Tablets
- Desktop browsers

Tested on:
- Chrome
- Firefox
- Safari
- Edge

---

## 🔐 Security Features

- Session-based authentication
- Password hashing (bcrypt)
- Role-based access control
- Input validation and sanitization
- CSRF protection through session tokens
- Complete audit logging
- SQL injection prevention (prepared statements)

---

## 🆘 Troubleshooting

### "Database connection failed"
- Check MySQL is running
- Verify credentials in config.php
- Ensure database name is correct

### "Class not found" or "Database error"
- Check all files are uploaded
- Verify file permissions
- Check PHP error logs

### Login not working
- Clear browser cache
- Check default credentials (see above)
- Verify database has demo data

### Email not sending
- Check SMTP credentials
- Verify email is enabled in settings
- Check PHP error logs for SMTP errors
- For Gmail, ensure you're using app password

### Attendance data not showing
- Ensure seed data was imported
- Check database tables are created
- Verify date format is correct (YYYY-MM-DD)

---

## 📈 Best Practices

1. **Regular Backups**
   ```bash
   mysqldump -u root -p school_attendance > backup.sql
   ```

2. **Monitor Audit Logs**
   - Check logs regularly for suspicious activity
   - Review all student/teacher changes

3. **Update Credentials**
   - Change default admin password after first login
   - Set unique passwords for teachers

4. **Email Configuration**
   - Test email settings before enabling notifications
   - Monitor email delivery

5. **Data Maintenance**
   - Archive old attendance records periodically
   - Keep audit logs for compliance

---

## 📞 Support

For issues or questions:
1. Check PROJECT_COMPLETION_REPORT.md for detailed information
2. Review error logs in PHP/MySQL
3. Verify configuration settings
4. Check demo data in database

---

## 🎯 Next Steps

After installation:

1. ✅ Login with demo credentials
2. ✅ Update school settings
3. ✅ Configure email notifications
4. ✅ Add your students and teachers
5. ✅ Test attendance marking
6. ✅ Generate sample reports
7. ✅ Review audit logs
8. ✅ Go live!

---

**Installation Status:** ✅ Complete
**System Status:** ✅ Ready for Use
**Last Updated:** May 15, 2026
