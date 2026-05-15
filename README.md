# SchoolTrack - Attendance Management System MVP

## 📋 Overview
A complete PHP-based school attendance management system with RFID card integration, automated email notifications, comprehensive reporting, and role-based dashboards for Admin, Principal, and Teacher.

**Version:** 1.0.0  
**Status:** MVP Build (16-hour deployment)

---

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+ with PDO MySQL extension
- MySQL 5.7+
- XAMPP/LAMP/LEMP server
- Modern web browser

### Installation Steps

#### 1. Database Setup
```bash
# Open phpMyAdmin (http://localhost/phpmyadmin)
# Import the database.sql file
# OR run MySQL command:
mysql -u root -p < /path/to/database.sql
```

#### 2. Configuration
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password
define('DB_NAME', 'school_attendance');
define('APP_URL', 'http://localhost/school');  // Your URL
```

#### 3. Email Configuration (Optional)
To enable absent notifications, configure SMTP in Admin → Settings:
- **Gmail:** Use App Passwords (not regular password)
- **Office 365:** smtp.office365.com:587
- **Custom SMTP:** Add your provider details

#### 4. Set File Permissions
```bash
chmod 755 /var/www/html/school
chmod 755 /var/www/html/school/api
```

#### 5. Access the System
- **Login:** http://localhost/school/index.php
- **Default Credentials:** (See "Default Users" section below)

---

## 👥 Default Users

### Admin Account
- **Email:** admin@school.com
- **Password:** password (default)
- **Role:** System Administrator

### Principal Account  
- **Email:** principal@school.com
- **Password:** password (default)
- **Role:** School Principal

### Teacher Account
- **Email:** teacher@school.com
- **Password:** password (default)
- **Role:** Classroom Teacher

**⚠️ IMPORTANT:** Change default passwords immediately in production!

---

## 📱 Core Features

### 1. **Authentication & Authorization**
- Role-based access control (Admin, Principal, Teacher)
- Session management with 1-hour timeout
- Secure password hashing
- Audit logging for all actions

### 2. **Student Attendance (RFID Integration)**
- Hardware sends POST request to `/api/attendance.php`
- Automatic attendance marking via card UID
- Late detection based on school start time
- Prevents duplicate attendance marking
- Real-time status response to hardware

**Hardware Integration:**
```bash
# Hardware sends POST request:
POST /api/attendance.php
card_uid=CARD001

# Response:
{
  "success": true,
  "student_name": "Alice Johnson",
  "class": "10",
  "status": "present",
  "timestamp": "2026-05-15 08:45:30"
}
```

### 3. **Teacher Attendance**
- Self-service attendance marking
- Manual correction capability  
- Time in/out tracking
- Multiple status types (present, absent, late, half-day, on-leave)
- Audit trail of all changes

### 4. **Automated Absence Notifications**
- Configured cutoff time (default: 9:30 AM)
- Automatic absence detection via cron job
- Email notifications to parent/guardian
- Professional HTML email templates
- Notification tracking and logging

**Setup Cron Job:**
```bash
# Add to crontab (runs every 30 minutes after 9:30 AM)
*/30 9-17 * * * wget http://localhost/school/api/process-absent.php
```

### 5. **Admin Dashboard**
- Real-time attendance statistics
- Class-wise attendance summary
- Student and teacher management
- Attendance editing capabilities
- System settings configuration

### 6. **Principal Dashboard**
- School-wide attendance overview
- Class-wise performance metrics
- Absent student tracking
- Trend analysis (30-day)
- Access to detailed reports

### 7. **Reports & Exports**
- **Daily Report:** Same-day attendance snapshot
- **Monthly Report:** Period attendance analytics
- **Absent Students:** Frequent absentee tracking
- **CSV Export:** Download any report
- **Attendance Percentage:** Student-wise metrics

### 8. **Audit Logs**
- Complete activity tracking
- Login/logout logging
- Attendance edit history
- Administrative actions
- IP address recording

### 9. **Settings Management**
- School configuration
- Attendance times (start, late, cutoff)
- SMTP configuration for emails
- Academic year setting

---

## 📂 Directory Structure

```
school/
├── admin/                    # Admin dashboard pages
│   ├── dashboard.php        # Overview & statistics
│   ├── students.php         # Student management
│   ├── teachers.php         # Teacher management
│   ├── attendance.php       # Attendance viewing/editing
│   ├── reports.php          # Reports & CSV export
│   ├── logs.php             # Audit logs viewer
│   └── settings.php         # System configuration
├── principal/               # Principal pages
│   ├── dashboard.php        # School overview
│   ├── attendance.php       # Attendance view
│   └── reports.php          # Detailed reports
├── teacher/                 # Teacher pages
│   ├── dashboard.php        # Teacher dashboard
│   ├── my-attendance.php    # Self-service attendance
│   └── students.php         # Student list
├── api/                     # API endpoints
│   ├── attendance.php       # RFID attendance API
│   └── process-absent.php   # Absence processor
├── auth.php                 # Authentication functions
├── config.php               # Database & app config
├── email.php                # Email service
├── header.php               # Page header template
├── footer.php               # Page footer template
├── index.php                # Login page
├── logout.php               # Logout handler
└── database.sql             # Database schema
```

---

## 🔌 API Reference

### Student Attendance Endpoint
**POST** `/api/attendance.php`

**Parameters:**
- `card_uid` (string, required) - RFID Card UID

**Response (Success):**
```json
{
  "success": true,
  "message": "Attendance marked",
  "student_name": "Alice Johnson",
  "class": "10",
  "status": "present",
  "timestamp": "2026-05-15 08:45:30"
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Already marked today"
}
```

### Absent Processing Endpoint
**GET/POST** `/api/process-absent.php`

**Response:**
```json
{
  "success": true,
  "message": "Processed 5 absent student(s)",
  "processed": 5
}
```

---

## 🗄️ Database Schema

### Core Tables
- **users** - Admin, Principal, Teacher accounts
- **students** - Student records with card UIDs
- **student_attendance** - Daily attendance records
- **teacher_attendance** - Teacher attendance tracking
- **audit_logs** - Complete activity history
- **email_notifications** - Email notification tracking
- **settings** - System configuration

### Key Relationships
- students.id ← student_attendance.student_id
- users.id ← teacher_attendance.teacher_id
- users.id ← audit_logs.user_id

---

## ⚙️ Configuration

### School Start Times (settings)
- **school_start_time:** 7:30 AM
- **late_time:** 8:15 AM (marked late after this)
- **cutoff_time:** 9:30 AM (marked absent after this)

### Email Configuration
Configure in Admin → Settings:
- SMTP Host (e.g., smtp.gmail.com)
- SMTP Port (587 for TLS, 465 for SSL)
- Username & Password
- Encryption Type (TLS or SSL)

**Gmail Setup:**
1. Enable 2-Factor Authentication
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use app password in SMTP Password field

---

## 🔒 Security Features

- **Password Hashing:** bcrypt (PHP password_hash)
- **SQL Injection Prevention:** Prepared statements
- **XSS Protection:** htmlspecialchars on output
- **Session Security:** Token-based with timeout
- **Audit Logging:** Complete action tracking
- **Role-Based Access:** Strict permission checking
- **IP Logging:** Track login locations

---

## 📊 Reporting Features

### Reports Available
1. **Daily Attendance:** Real-time snapshot
2. **Monthly Attendance:** Period analytics with percentages
3. **Frequent Absentees:** Top offenders list
4. **Class-wise Summary:** Class-level metrics
5. **Student Performance:** Individual attendance percentage

### Export Formats
- CSV (Excel compatible)
- Automatic date-based filenames
- All column formats included

---

## 🔧 Maintenance

### Regular Tasks
- Monitor audit logs for suspicious activity
- Review absence notification delivery
- Update student card assignments
- Archive old attendance records (6+ months)
- Verify email configuration monthly

### Backup Strategy
```bash
# MySQL backup
mysqldump -u root -p school_attendance > backup_$(date +%Y%m%d).sql

# File backup
tar -czf school_backup_$(date +%Y%m%d).tar.gz /var/www/html/school/
```

---

## 🐛 Troubleshooting

### Database Connection Failed
- Verify MySQL is running
- Check credentials in config.php
- Ensure database is created
- Check database user permissions

### Emails Not Sending
- Verify SMTP settings in Admin → Settings
- Check firewall allows port 587/465
- Test with simple email first
- Check audit logs for failed attempts
- Verify parent_email is set for students

### RFID Hardware Integration Not Working
- Verify API endpoint is accessible
- Check card UID matches database
- Monitor /api/attendance.php response
- Verify time settings are correct

### Session Timeout Issues
- Clear browser cookies
- Check PHP session configuration
- Verify SESSION_TIMEOUT constant (default: 3600)

---

## 📝 Usage Examples

### Adding a Student
1. Go to Admin → Students
2. Click "Add Student"
3. Enter: Name, Roll Number, Class
4. Assign RFID Card UID
5. Enter parent contact info
6. Save

### Configuring Email
1. Go to Admin → Settings
2. Enable "Email Notifications"
3. Enter SMTP details
4. Test with student absence
5. Check Admin → Logs for delivery

### Generating Reports
1. Go to Admin/Principal → Reports
2. Select date range
3. Choose report type
4. Click Export CSV
5. Open in Excel for further analysis

---

## 📞 Support & Documentation

### File References
- **Core Logic:** config.php, auth.php
- **API:** api/attendance.php, api/process-absent.php
- **Styling:** header.php (embedded CSS)
- **Email:** email.php

### Common Modifications
- **Change default times:** config.php or Admin → Settings
- **Customize email template:** email.php → sendAbsentNotification()
- **Adjust timeout:** config.php → SESSION_TIMEOUT
- **Modify report formats:** admin/reports.php

---

## 📄 Version History

### v1.0.0 (MVP - 2026-05-15)
- ✅ Authentication & role management
- ✅ Student RFID attendance
- ✅ Teacher self-service attendance
- ✅ Admin student/teacher management
- ✅ Principal overview dashboard
- ✅ Comprehensive reporting
- ✅ Email notifications
- ✅ Audit logging
- ✅ CSV export functionality

---

## 📋 Features Checklist

- [x] Authentication System
- [x] Role-based Access Control
- [x] Student Management
- [x] Teacher Management
- [x] RFID Card Integration
- [x] Student Attendance Marking
- [x] Teacher Attendance Marking
- [x] Automated Absence Detection
- [x] Email Notifications
- [x] Admin Dashboard
- [x] Principal Dashboard
- [x] Teacher Dashboard
- [x] Reports (Daily/Monthly)
- [x] CSV Export
- [x] Audit Logs
- [x] Settings Management
- [x] Session Management
- [x] Permission Checking

---

## 🎯 Next Steps (Phase 2 - Future)

- [ ] Mobile App (Student/Parent Portal)
- [ ] SMS Notifications
- [ ] Biometric Integration
- [ ] Face Recognition
- [ ] Parent Portal App
- [ ] Analytics Dashboard
- [ ] Attendance Calendar
- [ ] Notification Preferences
- [ ] Class Schedule Management
- [ ] Leave Management

---

**Created by:** Jayesh V  
**For:** School Attendance Management  
**Deployment Date:** 14 May 2026
