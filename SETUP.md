# 🚀 SchoolTrack Setup Guide

## Step-by-Step Installation Instructions

### Prerequisites
- PHP 7.4+ (with PDO MySQL extension)
- MySQL 5.7+
- XAMPP or equivalent local server
- Browser (Chrome, Firefox, Safari, Edge)

---

## Step 1: Prepare the Environment

### For XAMPP Users:
1. Extract files to `C:\xampp\htdocs\school\`
2. Ensure MySQL is running (`mysql_start.bat`)
3. Open phpMyAdmin: http://localhost/phpmyadmin

### For Linux/Mac Users:
```bash
cd /var/www/html
sudo mkdir school
sudo cp -r /path/to/school/* ./school/
sudo chown -R www-data:www-data ./school/
```

---

## Step 2: Create Database

### Method 1: phpMyAdmin (Recommended)
1. Open http://localhost/phpmyadmin
2. Create new database: `school_attendance`
3. Go to Import tab
4. Upload `database.sql` file
5. Click Import

### Method 2: Command Line
```bash
mysql -u root -p < database.sql
```

### Method 3: Manual
1. Open `database.sql` file
2. Copy all content
3. Paste in phpMyAdmin SQL tab
4. Execute

---

## Step 3: Configure Database Connection

1. Open `config.php`
2. Update database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', 'your_password'); // Your MySQL password
define('DB_NAME', 'school_attendance');
define('APP_URL', 'http://localhost/school');
```

---

## Step 4: Verify Installation

### Test Database Connection
1. Create test file `test.php` in root folder:
```php
<?php
require_once 'config.php';
try {
    $db = getDB();
    echo "✓ Database connected successfully!";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>
```

2. Visit http://localhost/school/test.php
3. Should see: "✓ Database connected successfully!"
4. Delete test.php after verification

---

## Step 5: Access the System

### Login with Default Credentials

**Admin Dashboard:**
- URL: http://localhost/school/
- Email: admin@school.com
- Password: password

**Principal Dashboard:**
- Email: principal@school.com
- Password: password

**Teacher Dashboard:**
- Email: teacher@school.com
- Password: password

---

## Step 6: Initial Configuration

### Change Default Passwords (REQUIRED)
1. Log in as Admin
2. Go to Admin → Teachers/Settings
3. Change admin password first
4. Create new admin account
5. Delete default admin account

### Configure School Settings
1. Go to Admin → Settings
2. Enter School Name
3. Set attendance times:
   - School Start: 7:30 AM
   - Late Cutoff: 8:15 AM
   - Absence Cutoff: 9:30 AM
4. Save settings

---

## Step 7: Add Sample Data

### Add Students
1. Go to Admin → Students
2. Click "Add Student"
3. Enter details:
   - Name: Alice Johnson
   - Roll No: S001
   - Class: 10
   - Section: A
   - Card UID: CARD001 (for RFID)
   - Parent Email: alice@example.com
4. Save

### Add Teachers
1. Go to Admin → Teachers
2. Click "Add Teacher"
3. Enter:
   - Name: Teacher Name
   - Email: teacher@example.com
   - Password: Set new password
   - Employee ID: TCH001
   - Subject: English
4. Save

---

## Step 8: Email Configuration (Optional)

### Enable Absence Notifications
1. Go to Admin → Settings
2. Enable "Email Notifications": Yes
3. Enter SMTP Details:

**For Gmail:**
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
Username: your-email@gmail.com
Password: app_specific_password (NOT regular password)
From Name: School Attendance
```

**Get Gmail App Password:**
1. Go to https://myaccount.google.com
2. Enable 2-Factor Authentication
3. Go to Security → App passwords
4. Generate password for "Mail"
5. Use generated password

4. Click "Save Settings"

### Test Email
1. Create a student
2. Wait until after cutoff time (9:30 AM)
3. Check logs to verify notification sent

---

## Step 9: RFID Hardware Integration

### Hardware Configuration
Your RFID reader should send POST request to:
```
URL: http://your-server/school/api/attendance.php
Parameter: card_uid=CARD001
```

### Example Hardware Code (Arduino):
```cpp
#include <WiFi.h>
#include <HTTPClient.h>

void sendAttendance(String cardUID) {
    HTTPClient http;
    String serverUrl = "http://192.168.1.100/school/api/attendance.php";
    
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    String postData = "card_uid=" + cardUID;
    int httpCode = http.POST(postData);
    
    Serial.println("Response: " + http.getString());
    http.end();
}
```

---

## Step 10: Set Up Cron Job (Optional)

### For Automated Absence Processing
The system can automatically mark students absent and send notifications.

**Linux/Mac:**
```bash
# Edit crontab
crontab -e

# Add this line (runs every 30 minutes from 9:30 AM - 5 PM):
*/30 9-17 * * * wget -q http://localhost/school/api/process-absent.php

# Or using PHP CLI:
*/30 9-17 * * * php /var/www/html/school/api/process-absent.php
```

**Windows (Task Scheduler):**
1. Open Task Scheduler
2. Create Basic Task
3. Trigger: Daily, Repeat: Every 30 minutes
4. Action: Start Program
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `D:\xampp\htdocs\school\api\process-absent.php`

---

## 🔍 Verification Checklist

- [x] Database created successfully
- [x] config.php configured with correct credentials
- [x] Can login with admin@school.com
- [x] Can login with principal@school.com
- [x] Can login with teacher@school.com
- [x] Can access Admin Dashboard
- [x] Can add students
- [x] Can add teachers
- [x] Settings page accessible
- [x] Email configuration saved (if enabled)
- [x] Attendance API responding to requests

---

## 🐛 Common Issues & Solutions

### Issue: "Database connection failed"
**Solution:**
- Check MySQL is running
- Verify credentials in config.php
- Ensure database user has all permissions:
```sql
GRANT ALL PRIVILEGES ON school_attendance.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

### Issue: "Login not working"
**Solution:**
- Clear browser cookies
- Try incognito/private browsing
- Check session.save_path is writable:
```php
echo session_save_path();
```

### Issue: "Emails not sending"
**Solution:**
- Verify SMTP settings in Admin → Settings
- Check email logs: Admin → Logs
- Test with Admin → Settings → [Save] to trigger test
- Check firewall allows port 587/465
- Verify parent_email field has valid email

### Issue: "RFID Card not recording attendance"
**Solution:**
- Check card_uid matches database exactly (case-sensitive)
- Verify API endpoint is accessible:
  ```bash
  curl -X POST http://localhost/school/api/attendance.php -d "card_uid=CARD001"
  ```
- Check student record exists and is active
- Verify it's not already marked for the day
- Check date/time settings are correct

### Issue: "500 Error on pages"
**Solution:**
- Check PHP error log
- Enable error reporting in config.php
- Verify all required functions exist
- Check file permissions (755 for directories, 644 for files)

---

## 🔐 Security Best Practices

### Immediately After Installation:
1. **Change all default passwords**
2. **Update database credentials** in config.php
3. **Move config.php** outside web root (optional but recommended)
4. **Enable HTTPS** in production
5. **Set strong database passwords**
6. **Disable phpMyAdmin** access in production
7. **Set proper file permissions:**
   ```bash
   chmod 755 /var/www/html/school
   chmod 644 /var/www/html/school/*.php
   chmod 700 /var/www/html/school/config.php
   ```

### Regular Maintenance:
1. Review audit logs weekly
2. Monitor email notifications
3. Update student/teacher records
4. Backup database monthly
5. Check attendance data accuracy
6. Monitor failed login attempts

---

## 📊 Testing the System

### Test Attendance Marking
1. Go to Student Management
2. Create student with card UID "TEST001"
3. Go to Teacher Dashboard
4. Mark your attendance
5. Go to Admin → Attendance
6. Verify records appear

### Test Absence Notification
1. Create student with parent email
2. Wait until after cutoff time (9:30 AM)
3. Go to Admin → Logs
4. Search for "AUTO_ABSENT_MARK"
5. Verify notification sent

### Test Reports
1. Go to Admin → Reports
2. Select date range with attendance data
3. Generate Daily, Monthly, and Absent reports
4. Export to CSV
5. Verify data accuracy in Excel

---

## 🎯 Success Indicators

✅ System is properly configured when:
- All dashboards load without errors
- Can add/edit/delete students and teachers
- Attendance is marked automatically via card
- Reports generate correctly
- Absence notifications send (if configured)
- All audit logs record actions
- CSV exports work in Excel

---

## 📞 Troubleshooting Support

### Check These Files:
- Database: `database.sql`
- Config: `config.php`
- Errors: PHP error log
- Audit: Admin → Logs
- Email: Email notifications table

### Debug Steps:
1. Check browser console (F12) for JS errors
2. Check PHP error log for server errors
3. Review audit logs for failed actions
4. Test API endpoints directly with curl/Postman
5. Verify database data directly in phpMyAdmin

---

## 📚 Next Steps

After successful setup:
1. Customize school name and settings
2. Import actual student data
3. Configure RFID hardware
4. Set up email notifications
5. Train admin staff
6. Perform full system testing
7. Go live!

---

**Installation Date:** ________________  
**Completed By:** ________________  
**Verified On:** ________________

---

Need help? Check README.md for detailed feature documentation.
