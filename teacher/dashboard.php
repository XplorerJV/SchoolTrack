<?php
$pageTitle = 'Teacher Dashboard';
require_once __DIR__ . '/../auth.php';
requireRole('teacher', '../index.php');
require_once __DIR__ . '/../header.php';

$db   = getDB();
$user = getCurrentUser();
$today = date('Y-m-d');

// Teacher's own attendance today
$stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
$stmt->execute([$user['id'], $today]);
$myAttendance = $stmt->fetch();

// This month stats
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(status='present') as present, SUM(status='absent') as absent, SUM(status='late') as late FROM teacher_attendance WHERE teacher_id=? AND MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())");
$stmt->execute([$user['id']]);
$monthStats = $stmt->fetch();
$myPct = $monthStats['total'] > 0 ? round($monthStats['present'] / $monthStats['total'] * 100, 1) : 0;
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="grid"></i> Welcome, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h1>
        <p><?= date('l, d M Y') ?> &nbsp;|&nbsp; <span id="liveClock" style="color:#10b981;font-weight:600"></span></p>
    </div>
    <span style="font-size:12px;color:#94a3b8;display:flex;align-items:center;gap:5px">
        <span style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;animation:pulse 2s infinite"></span>
        Live updates every 30s
    </span>
</div>

<style>
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
.rt-val{font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:700;color:#0f172a;transition:all .4s}
.rt-val.updated{color:#10b981;transform:scale(1.08)}
</style>

<div class="page-content">

    <!-- My attendance status today -->
    <div class="card mb-6">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
            <div style="display:flex;align-items:center;gap:16px">
                <div style="width:52px;height:52px;border-radius:50%;background:<?= $myAttendance ? '#d1fae5' : '#fef3c7' ?>;display:flex;align-items:center;justify-content:center">
                    <i data-feather="<?= $myAttendance ? 'check-circle' : 'alert-circle' ?>" style="color:<?= $myAttendance ? '#10b981' : '#f59e0b' ?>;width:26px;height:26px"></i>
                </div>
                <div>
                    <div style="font-size:15px;font-weight:700;color:#0f172a">My Attendance Today</div>
                    <?php if ($myAttendance): ?>
                    <div style="font-size:13px;color:#64748b">
                        <span class="badge badge-<?= $myAttendance['status'] ?>"><?= ucfirst($myAttendance['status']) ?></span>
                        &nbsp; Time In: <?= $myAttendance['time_in'] ? date('h:i A', strtotime($myAttendance['time_in'])) : '-' ?>
                    </div>
                    <?php else: ?>
                    <div style="font-size:13px;color:#f59e0b">Not marked yet today</div>
                    <?php endif; ?>
                </div>
            </div>
            <a href="my-attendance.php" class="btn <?= $myAttendance ? 'btn-secondary' : 'btn-warning' ?>">
                <i data-feather="<?= $myAttendance ? 'edit' : 'plus' ?>"></i>
                <?= $myAttendance ? 'Update' : 'Mark My Attendance' ?>
            </a>
        </div>
    </div>

    <!-- My monthly stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1)"><i data-feather="calendar" style="color:#3b82f6"></i></div>
            <div class="stat-content"><p class="stat-label">Days This Month</p><p class="stat-value"><?= $monthStats['total'] ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="check-circle" style="color:#10b981"></i></div>
            <div class="stat-content"><p class="stat-label">Present</p><p class="stat-value"><?= $monthStats['present'] ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div>
            <div class="stat-content"><p class="stat-label">Absent</p><p class="stat-value"><?= $monthStats['absent'] ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="percent" style="color:#f59e0b"></i></div>
            <div class="stat-content"><p class="stat-label">My Attendance %</p><p class="stat-value" style="color:<?= $myPct>=75?'#10b981':'#ef4444' ?>"><?= $myPct ?>%</p></div>
        </div>
    </div>

    <!-- School live stats today -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card" style="border-top:3px solid #10b981">
            <div class="stat-icon" style="background:rgba(16,185,129,.1)"><i data-feather="check-circle" style="color:#10b981"></i></div>
            <div class="stat-content"><p class="stat-label">School Present</p><p class="rt-val" id="val-present">—</p></div>
        </div>
        <div class="stat-card" style="border-top:3px solid #ef4444">
            <div class="stat-icon" style="background:rgba(239,68,68,.1)"><i data-feather="x-circle" style="color:#ef4444"></i></div>
            <div class="stat-content"><p class="stat-label">School Absent</p><p class="rt-val" id="val-absent">—</p></div>
        </div>
        <div class="stat-card" style="border-top:3px solid #f59e0b">
            <div class="stat-icon" style="background:rgba(245,158,11,.1)"><i data-feather="clock" style="color:#f59e0b"></i></div>
            <div class="stat-content"><p class="stat-label">Late Today</p><p class="rt-val" id="val-late">—</p></div>
        </div>
        <div class="stat-card" style="border-top:3px solid #94a3b8">
            <div class="stat-icon" style="background:rgba(148,163,184,.1)"><i data-feather="minus-circle" style="color:#94a3b8"></i></div>
            <div class="stat-content"><p class="stat-label">Not Marked</p><p class="rt-val" id="val-notmarked">—</p></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Live class table -->
        <div class="card">
            <div class="card-header">
                <h3><i data-feather="layers"></i> All Classes — Today</h3>
                <span style="font-size:11px;color:#94a3b8" id="lastUpdated">Updating...</span>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-wrapper">
                    <table class="table">
                        <thead><tr><th>Class</th><th style="text-align:center">Present</th><th style="text-align:center">Absent</th><th style="text-align:center">%</th><th style="text-align:center">Mark</th></tr></thead>
                        <tbody id="classTableBody"><tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="card">
            <div class="card-header"><h3><i data-feather="zap"></i> Quick Actions</h3></div>
            <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php for($c=1;$c<=10;$c++): ?>
                    <a href="mark-attendance.php?class=<?= $c ?>&period=1" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f8fafc;border-radius:8px;text-decoration:none;color:#1e293b;border:1px solid #e2e8f0;transition:all .2s" onmouseover="this.style.background='#eff6ff';this.style.borderColor='#3b82f6'" onmouseout="this.style.background='#f8fafc';this.style.borderColor='#e2e8f0'">
                        <span style="font-weight:600">Class <?= $c ?></span>
                        <span style="font-size:12px;color:#3b82f6;font-weight:600">Mark Attendance →</span>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const statusColors={present:['#d1fae5','#065f46'],absent:['#fee2e2','#7f1d1d'],late:['#fef3c7','#92400e'],excused:['#dbeafe','#1e40af']};
function esc(s){return String(s).replace(/[&<>"']/g,c=>'&#'+c.charCodeAt(0)+';');}
function animateVal(id,v){const el=document.getElementById(id);if(!el)return;if(el.textContent!==String(v)){el.textContent=v;el.classList.add('updated');setTimeout(()=>el.classList.remove('updated'),600);}}

function fetchStats(){
    fetch('../api/live_stats.php').then(r=>r.json()).then(d=>{
        if(d.error)return;
        animateVal('val-present',d.present);
        animateVal('val-absent',d.absent);
        animateVal('val-late',d.late);
        animateVal('val-notmarked',d.not_marked);

        const tbody=document.getElementById('classTableBody');
        if(d.classes&&d.classes.length){
            tbody.innerHTML=d.classes.map(c=>{
                const pct=c.total>0?Math.round(c.present/c.total*100):0;
                const color=pct>=75?'#059669':(pct>=50?'#d97706':'#dc2626');
                const bg=pct>=75?'#d1fae5':(pct>=50?'#fef3c7':'#fee2e2');
                return `<tr>
                    <td><strong>Class ${c.class}</strong></td>
                    <td style="text-align:center"><span class="badge badge-present">${c.present}</span></td>
                    <td style="text-align:center"><span class="badge badge-absent">${c.absent}</span></td>
                    <td style="text-align:center"><span class="badge" style="background:${bg};color:${color}">${pct}%</span></td>
                    <td style="text-align:center"><a href="mark-attendance.php?class=${c.class}&period=1" class="btn btn-sm btn-primary" style="font-size:11px;padding:4px 8px">Mark</a></td>
                </tr>`;
            }).join('');
        } else {
            tbody.innerHTML='<tr><td colspan="5" style="text-align:center;padding:20px;color:#94a3b8">No attendance today yet</td></tr>';
        }
        document.getElementById('lastUpdated').textContent='Updated: '+d.time;
        feather.replace();
    }).catch(()=>{});
}

function updateClock(){document.getElementById('liveClock').textContent=new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});}
fetchStats();
setInterval(fetchStats,30000);
setInterval(updateClock,1000);
updateClock();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
