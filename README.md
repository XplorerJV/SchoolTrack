# 🏫 SchoolTrack — NFC School ERP

A complete PHP-based school attendance management system with RFID/NFC card integration, period-wise attendance, real-time dashboards, class-wise performance tracking, and role-based access for Admin, Principal, and Teacher.

**Version:** 2.0.0 | **Status:** Production Ready | **PHP:** 8.0+ | **DB:** MySQL 5.7+

---

## 📦 Download & Setup (ZIP)

### Step 1 — Download
- Click the green **Code** button → **Download ZIP**
- Extract the ZIP — you will get a folder named `SchoolTrack-master` (or similar)
- Rename it to **`school`**

### Step 2 — Move to Web Server
| Server | Folder Path |
|--------|-------------|
| XAMPP (Windows) | `C:\xampp\htdocs\school` |
| XAMPP (Mac) | `/Applications/XAMPP/htdocs/school` |
| LAMP (Linux) | `/var/www/html/school` |

### Step 3 — Import Database

**Option A — phpMyAdmin (Recommended for beginners)**
1. Start Apache + MySQL in XAMPP Control Panel
2. Open `http://localhost/phpmyadmin`
3. Click **New** → Database name: `school_attendance` → Create
4. Select `school_attendance` database → Click **Import** tab
5. Choose `database.sql` from the project folder → Click **Go**

**Option B — Command Line**
```bash
mysql -u root -p < database.sql
```

### Step 4 — Configure `config.php`

Open `school/config.php` and verify these lines (default values work for XAMPP):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');               // Add password if your MySQL has one
define('DB_NAME', 'school_attendance');
define('APP_URL', 'http://localhost/school');
```

### Step 5 — Open in Browser

```
http://localhost/school
```

You should see the login page. ✅

---

## 🔐 Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@school.com | password |
| Principal | principal@school.com | password |
| Teacher 1 (Maths) | teacher1@school.com | password |
| Teacher 2 (Science) | teacher2@school.com | password |
| Teacher 3 (English) | teacher3@school.com | password |
| Teacher 4–10 | teacher4@school.com … teacher10@school.com | password |

> ⚠️ Change all passwords immediately after first login in production!

---

## 👥 What's Included in the Database

| Table | Records |
|-------|---------|
| Students | **500** (50 per class, Class 1–10) |
| Teachers | 10 + 1 Admin + 1 Principal |
| Classes | 10 (Class 1 to Class 10) |
| Attendance | Sample data — last 30 days |

---

## 📱 Features

### 🔐 Authentication
- Role-based access: Admin / Principal / Teacher
- Teacher quick-select on login page
- Session timeout (1 hour)
- Audit logging for all actions

### 📋 Period-wise Attendance
- 6 periods per day (08:00–14:30)
- Mark Present / Absent / Late / Excused per period
- RFID card scan support
- Bulk mark (All Present / All Absent)
- Live row color coding

### 📡 Real-time Dashboard
- Live clock (updates every second)
- Attendance stats auto-refresh every 30 seconds
- Live Activity Feed — latest attendance entries
- Class-wise breakdown with progress bars

### 🏫 Class Management
- Class Folders — visual card view with daily stats
- Color-coded: 🟢 ≥75% | 🟡 ≥50% | 🔴 <50% | ⚫ Not marked
- Per-class performance with individual student grades
- Direct period buttons (P1–P6) from class card

### 📈 Performance Tracking
- Per-student attendance % with progress bar
- Grade: Excellent (≥90%) / Good (≥75%) / Average (≥60%) / Poor (<60%)
- Date range filter
- CSV export

### 📊 Reports
- Daily Summary
- Class-wise Summary (Class 1–10)
- Monthly per-student (grouped by class)
- Teacher Attendance
- Frequent Absentees
- CSV export for all reports

### 📱 Mobile Responsive
- Works on all screen sizes — mobile, tablet, desktop
- Hamburger menu on mobile
- Touch-friendly UI

---

## 📂 Directory Structure

```
school/
├── admin/
│   ├── dashboard.php          # Real-time admin dashboard
│   ├── class-folders.php      # Visual class folder cards
│   ├── class-folder.php       # Single class — student list
│   ├── class-performance.php  # Per-class student performance + CSV export
│   ├── classes.php            # Class management
│   ├── students.php           # Student CRUD
│   ├── teachers.php           # Teacher management
│   ├── attendance.php         # View/edit attendance
│   ├── reports.php            # All reports + CSV export
│   ├── logs.php               # Audit logs
│   └── settings.php           # School + attendance settings
├── principal/
│   ├── dashboard.php
│   ├── class-folders.php
│   ├── classes.php
│   ├── attendance.php
│   └── reports.php
├── teacher/
│   ├── dashboard.php
│   ├── class-folders.php
│   ├── classes.php
│   ├── mark-attendance.php    # Period-wise attendance marking
│   ├── my-attendance.php      # Teacher's own attendance
│   └── students.php
├── api/
│   ├── live_stats.php         # Real-time stats JSON (polled every 30s)
│   ├── attendance.php         # RFID hardware endpoint
│   ├── mark_attendance.php    # Mark attendance via card scan
│   ├── student_lookup.php     # Card UID → student lookup
│   └── process-absent.php     # Absence processor
├── assets/css/base.css        # Complete custom CSS (no Bootstrap)
├── auth.php                   # Auth functions
├── config.php                 # DB config + helper functions
├── header.php                 # Sidebar + nav
├── footer.php                 # Scripts + feather icons
├── index.php                  # Login page
├── logout.php
└── database.sql               # Complete DB schema + 500 students + sample data
```

---

## 🔌 RFID Hardware API

```
POST /api/attendance.php
Body: card_uid=CARD0001

Response:
{
  "success": true,
  "student_name": "Aarav Sharma",
  "class": "5",
  "status": "present",
  "timestamp": "2026-05-20 08:45:30"
}
```

---

## 🛠️ Troubleshooting

| Problem | Fix |
|---------|-----|
| White screen / DB error | Check `config.php` credentials, ensure MySQL is running |
| Login not working | Verify `database.sql` was imported correctly |
| CSS not loading | Make sure folder is named exactly `school` and `APP_URL` matches |
| CSV export broken | Ensure no whitespace/output before PHP tags in files |
| Images/logo not showing | Check `uploads/` folder exists and `APP_URL` is correct |

---

## 🔒 Security

- bcrypt password hashing
- PDO prepared statements (SQL injection prevention)
- XSS protection via `htmlspecialchars`
- Session-based auth with 1-hour timeout
- Role-based access control
- Complete audit trail

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.x, PDO MySQL
- **Database:** MySQL / MariaDB
- **Frontend:** Vanilla JS, Feather Icons, Chart.js
- **CSS:** Custom (no Bootstrap/Tailwind)
- **Fonts:** Inter, Space Grotesk (Google Fonts)

---

## 🗄️ Database Backup

```bash
mysqldump -u root -p school_attendance > backup.sql
```

---

**Created by:** Jayesh V  
**Repo:** https://github.com/joyboy-pega/nfc-school-erp  
**Mirror:** https://github.com/XplorerJV/SchoolTrack
