# 🏫 SchoolTrack — NFC School ERP

A complete PHP-based school attendance management system with RFID/NFC card integration, period-wise attendance, real-time dashboards, class-wise performance tracking, and role-based access for Admin, Principal, and Teacher.

**Version:** 2.0.0 | **Status:** Production Ready

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+ with PDO MySQL
- MySQL 5.7+ / MariaDB 10.4+
- XAMPP / LAMP / LEMP

### Installation

```bash
# 1. Clone the repo
git clone https://github.com/joyboy-pega/nfc-school-erp.git
cd nfc-school-erp

# 2. Import database
mysql -u root -p < database.sql

# 3. Configure
# Edit config.php — set DB credentials and APP_URL
```

### config.php
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_attendance');
define('APP_URL',  'http://localhost/school');
```

### Access
- **URL:** `http://localhost/school`
- **Admin:** admin@school.com / `password`
- **Principal:** principal@school.com / `password`
- **Teachers:** teacher1@school.com … teacher10@school.com / `password`

> ⚠️ Change all passwords immediately in production!

---

## 👥 Default Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@school.com | password |
| Principal | principal@school.com | password |
| Teacher 1 (Maths) | teacher1@school.com | password |
| Teacher 2 (Science) | teacher2@school.com | password |
| Teacher 3 (English) | teacher3@school.com | password |
| Teacher 4–10 | teacher4–10@school.com | password |

---

## 📊 Database — What's Included

| Table | Records |
|-------|---------|
| Students | **500** (50 per class, Class 1–10) |
| Teachers | 10 (+ 1 Admin + 1 Principal) |
| Classes | 10 (Class 1 to Class 10) |
| Attendance | Sample data — last 30 days |

### Student Details per Record
- Roll Number, Name, Class, Section (A/B)
- Gender, Date of Birth, Address, Contact
- Parent Email, Parent Phone, RFID Card UID

---

## 📱 Features

### 🔐 Authentication
- Role-based access: Admin / Principal / Teacher
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
- Attendance stats auto-refresh every **30 seconds**
- Live Activity Feed — latest attendance entries
- Class-wise breakdown with progress bars
- "Not Marked Yet" counter

### 🏫 Class Management (Class 1–10)
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
- Class-wise Summary (Class 1–10 ordered)
- Monthly per-student (grouped by class)
- Teacher Attendance
- Frequent Absentees
- CSV export for all reports

### ⚙️ Settings
- School name, address, logo
- Attendance times (start, late cutoff, absent cutoff)
- Academic year

---

## 📂 Directory Structure

```
school/
├── admin/
│   ├── dashboard.php          # Real-time admin dashboard
│   ├── class-folders.php      # Visual class folder cards
│   ├── class-folder.php       # Single class — student list + profiles
│   ├── class-performance.php  # Per-class student performance
│   ├── classes.php            # Class management table
│   ├── students.php           # Student CRUD (with DOB, gender, address, contact)
│   ├── teachers.php           # Teacher management
│   ├── attendance.php         # View/edit attendance
│   ├── reports.php            # All reports + CSV export
│   ├── logs.php               # Audit logs
│   └── settings.php           # School + attendance settings
├── principal/
│   ├── dashboard.php          # Real-time principal dashboard
│   ├── class-folders.php      # Class folder view
│   ├── classes.php            # Class overview
│   ├── attendance.php         # View attendance
│   └── reports.php            # Reports with class-wise summary
├── teacher/
│   ├── dashboard.php          # Real-time teacher dashboard
│   ├── class-folders.php      # Class folder view
│   ├── classes.php            # Class selection with period buttons
│   ├── mark-attendance.php    # Period-wise attendance marking
│   ├── my-attendance.php      # Teacher's own attendance
│   └── students.php           # Student list with full details
├── api/
│   ├── live_stats.php         # Real-time stats JSON API (polled every 30s)
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

## 🔒 Security
- bcrypt password hashing
- PDO prepared statements (SQL injection prevention)
- XSS protection via htmlspecialchars
- Session-based auth with timeout
- Role-based access control
- Complete audit trail

---

## 🛠️ Tech Stack
- **Backend:** PHP 8.x, PDO MySQL
- **Database:** MySQL / MariaDB
- **Frontend:** Vanilla JS, Feather Icons, Chart.js
- **CSS:** Custom (no Bootstrap/Tailwind dependency)
- **Fonts:** Inter, Space Grotesk (Google Fonts)

---

**Created by:** Jayesh V  
**Repo:** https://github.com/joyboy-pega/nfc-school-erp
