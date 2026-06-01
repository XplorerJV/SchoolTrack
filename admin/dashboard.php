<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../auth.php';
requireRole('admin', '../index.php');
require_once __DIR__ . '/../header.php';

$db    = getDB();
$today = date('Y-m-d');

$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE is_active=1")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn();
$totalClasses  = $db->query("SELECT COUNT(DISTINCT class) FROM students WHERE is_active=1")->fetchColumn();
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="grid"></i> Admin Dashboard</h1>
        <p id="liveDate"><?= date('l, d M Y') ?> &nbsp;|&nbsp; <span id="liveClock" style="color:#10b981;font-weight:600"></span></p>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
        <span id="refreshBadge" style="font-size:12px;color:#94a3b8;display:flex;align-items:center;gap:5px">
            <span id="refreshDot" style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;animation:pulse 2s infinite"></span>
            Live — updates every 30s
        </span>
    </div>
</div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
.rt-val { font-family:'Space Grotesk',sans-serif; font-size:32px; font-weight:700; color:#0f172a; transition:all .4s; }
.rt-val.updated { color:#10b981; transform:scale(1.08); }
.live-row td { transition:background .3s; }
.live-row.flash { background:#f0fdf4 !important; }
</style>

<div class="page-content">

    <!-- Static stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="users" style="color:#3b82f6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Students</p><p class="stat-value"><?= $totalStudents ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.1)"><i data-feather="briefcase" style="color:#8b5cf6"></i></div>
            <div class="stat-content"><p class="stat-label">Total Teachers</p><p class="stat-value"><?= $totalTeachers ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(6,182,212,.1)"><i data-feather="layers" style="color:#06b6d4"></i></div>
            <div class="stat-content"><p class="stat-label">Total Classes</p><p class="stat-value"><?= $totalClasses ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="calendar" style="color:#f59e0b"></i></div>
            <div class="stat-content"><p class="stat-label">Academic Year</p><p class="stat-value" style="font-size:18px"><?= getSetting('academic_year') ?: '2025-26' ?></p></div>
        </div>
    </div>

    <!-- LIVE attendance stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card" style="border-top:3px solid #10b981">
            <div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="check-circle" style="color:#10b981"></i></div>
            <div class="stat-content"><p class="stat-label">Present Today</p><p class="rt-val" id="val-present">—</p></div>
        </div>
        <div class="stat-card" style="border-top:3px solid #ef4444">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div>
            <div class="stat-content"><p class="stat-label">Absent Today</p><p class="rt-val" id="val-absent">—</p></div>
        </div>
        <div class="stat-card" style="border-top:3px solid #f59e0b">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="clock" style="color:#f59e0b"></i></div>
            <div class="stat-content"><p class="stat-label">Late Today</p><p class="rt-val" id="val-late">—</p></div>
        </div>
        <div class="stat-card" style="border-top:3px solid #94a3b8">
            <div class="stat-icon" style="background:rgba(148,163,184,.1)"><i data-feather="minus-circle" style="color:#94a3b8"></i></div>
            <div class="stat-content"><p class="stat-label">Not Marked Yet</p><p class="rt-val" id="val-notmarked">—</p></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Live class-wise table -->
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="bar-chart-2"></i> Class-wise Attendance Today</h3>
                <span style="font-size:11px;color:#94a3b8" id="lastUpdated">Updating...</span>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Class</th><th style="text-align:center">Total</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">Late</th><th style="text-align:center">%</th></tr></thead>
                        <tbody id="classTableBody">
                            <tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Live recent activity feed -->
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="activity"></i> Live Activity Feed</h3>
                <span style="font-size:11px;background:#d1fae5;color:#065f46;padding:3px 8px;border-radius:10px">● LIVE</span>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Student</th><th>Class</th><th style="text-align:center">Status</th><th>Time</th></tr></thead>
                        <tbody id="recentTableBody">
                            <tr><td colspan="4" style="text-align:center;padding:20px;color:#94a3b8">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header"><h3><i data-feather="zap"></i> Quick Actions</h3></div>
        <div class="card-body">
            <div class="flex flex-wrap gap-3">
                <a href="class-folders.php" class="btn btn-success"><i data-feather="folder"></i> Class Folders</a>
                <a href="classes.php" class="btn btn-primary"><i data-feather="layers"></i> Classes</a>
                <a href="students.php" class="btn btn-primary"><i data-feather="users"></i> Students</a>
                <a href="teachers.php" class="btn btn-primary"><i data-feather="briefcase"></i> Teachers</a>
                <a href="attendance.php" class="btn btn-primary"><i data-feather="check-square"></i> Attendance</a>
                <a href="reports.php" class="btn btn-secondary"><i data-feather="bar-chart-2"></i> Reports</a>
                <a href="logs.php" class="btn btn-secondary"><i data-feather="file-text"></i> Logs</a>
                <a href="settings.php" class="btn btn-secondary"><i data-feather="settings"></i> Settings</a>
            </div>
        </div>
    </div>
</div>

<script>
const statusColors = {
    present: ['#d1fae5','#065f46'],
    absent:  ['#fee2e2','#7f1d1d'],
    late:    ['#fef3c7','#92400e'],
    excused: ['#dbeafe','#1e40af']
};

function animateVal(id, newVal) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.textContent !== String(newVal)) {
        el.textContent = newVal;
        el.classList.add('updated');
        setTimeout(() => el.classList.remove('updated'), 600);
    }
}

function fetchStats() {
    fetch('../api/live_stats.php')
        .then(r => r.json())
        .then(d => {
            if (d.error) return;

            // Update stat cards
            animateVal('val-present',    d.present);
            animateVal('val-absent',     d.absent);
            animateVal('val-late',       d.late);
            animateVal('val-notmarked',  d.not_marked);

            // Update class table
            const tbody = document.getElementById('classTableBody');
            if (d.classes && d.classes.length) {
                tbody.innerHTML = d.classes.map(c => {
                    const pct   = c.total > 0 ? Math.round(c.present / c.total * 100) : 0;
                    const color = pct >= 75 ? '#059669' : (pct >= 50 ? '#d97706' : '#dc2626');
                    const bg    = pct >= 75 ? '#d1fae5' : (pct >= 50 ? '#fef3c7' : '#fee2e2');
                    return `<tr class="live-row">
                        <td><strong>Class ${c.class}</strong></td>
                        <td style="text-align:center">${c.total}</td>
                        <td style="text-align:center"><span class="badge badge-present">${c.present}</span></td>
                        <td style="text-align:center"><span class="badge badge-absent">${c.absent}</span></td>
                        <td style="text-align:center"><span class="badge badge-late">${c.late}</span></td>
                        <td style="text-align:center"><span class="badge" style="background:${bg};color:${color}">${pct}%</span></td>
                    </tr>`;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">No attendance marked today</td></tr>';
            }

            // Update recent feed
            const rtbody = document.getElementById('recentTableBody');
            if (d.recent && d.recent.length) {
                rtbody.innerHTML = d.recent.map(r => {
                    const c = statusColors[r.status] || ['#f1f5f9','#475569'];
                    const t = r.time_in ? r.time_in.substring(0,5) : '--:--';
                    return `<tr class="live-row">
                        <td><strong>${esc(r.name)}</strong><div style="font-size:11px;color:#94a3b8">${esc(r.roll_number)}</div></td>
                        <td>Class ${esc(r.class)}</td>
                        <td style="text-align:center"><span class="badge" style="background:${c[0]};color:${c[1]}">${r.status.charAt(0).toUpperCase()+r.status.slice(1)}</span></td>
                        <td style="font-size:13px">${t}</td>
                    </tr>`;
                }).join('');
            } else {
                rtbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#94a3b8">No activity today yet</td></tr>';
            }

            document.getElementById('lastUpdated').textContent = 'Updated: ' + d.time;
            feather.replace();
        })
        .catch(() => {});
}

function esc(s) { return String(s).replace(/[&<>"']/g, c => '&#'+c.charCodeAt(0)+';'); }

// Live clock
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent =
        now.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true});
}

// Init
fetchStats();
setInterval(fetchStats, 30000); // refresh every 30 seconds
setInterval(updateClock, 1000); // clock every second
updateClock();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
