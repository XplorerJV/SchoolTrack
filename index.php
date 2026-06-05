<?php
require_once 'auth.php';

if (isLoggedIn()) {
    header('Location: ' . getRoleDashboard($_SESSION['role']));
    exit;
}

$schoolName    = getSetting('school_name') ?: 'Springfield Public School';
$schoolAddress = getSetting('school_address') ?: '';
$schoolLogo    = mediaUrl(getSetting('school_logo'));

// Fetch teachers for quick-select
$db = getDB();
$teachers = $db->query("SELECT name, email FROM users WHERE role='teacher' AND is_active=1 ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        $user = loginUser($email, $password);
        if ($user) {
            header('Location: ' . getRoleDashboard($user['role']));
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($schoolName) ?> — Attendance Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/feather-icons@4.29.0/dist/feather.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;background:#0f172a;overflow:hidden;}
.bg-grid{
    position:fixed;inset:0;
    background-image:linear-gradient(rgba(56,189,248,.15) 1px,transparent 1px),linear-gradient(90deg,rgba(56,189,248,.15) 1px,transparent 1px);
    background-size:60px 60px;
    opacity:1;
}
.bg-grid::after{
    content:'';
    position:fixed;inset:0;
    background:radial-gradient(ellipse 80% 80% at 50% 50%, rgba(56,189,248,0.07) 0%, transparent 70%);
    pointer-events:none;
}
@keyframes flowX {
    0%   { background-position: 0 0, 0 0; }
    100% { background-position: 60px 0, 60px 0; }
}
@keyframes flowY {
    0%   { background-position: 0 0; }
    100% { background-position: 0 60px; }
}
.bg-grid-h{
    position:fixed;inset:0;pointer-events:none;
    background-image:linear-gradient(rgba(56,189,248,0) 0px, rgba(56,189,248,0.18) 1px, rgba(56,189,248,0) 2px);
    background-size:60px 60px;
    animation:flowY 3s linear infinite;
    opacity:.6;
}
.bg-grid-v{
    position:fixed;inset:0;pointer-events:none;
    background-image:linear-gradient(90deg, rgba(56,189,248,0) 0px, rgba(56,189,248,0.18) 1px, rgba(56,189,248,0) 2px);
    background-size:60px 60px;
    animation:flowX 3s linear infinite;
    opacity:.6;
}
.bg-glow{position:fixed;top:-200px;right:-200px;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(6,182,212,.15),transparent 70%);}
.bg-glow2{position:fixed;bottom:-200px;left:-200px;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(30,64,175,.2),transparent 70%);}
.login-wrapper{position:relative;z-index:10;display:flex;width:100%;min-height:100vh;}
.left-panel{flex:1;display:flex;flex-direction:column;justify-content:center;padding:60px;color:#fff;}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:60px;}
.brand-icon{width:48px;height:48px;background:linear-gradient(135deg,#1e40af,#06b6d4);border-radius:12px;display:flex;align-items:center;justify-content:center;}
.brand-name{font-family:'Space Grotesk',sans-serif;font-size:22px;font-weight:700;color:#fff;line-height:1.2;}
.hero-title{font-family:'Space Grotesk',sans-serif;font-size:48px;font-weight:700;line-height:1.15;margin-bottom:20px;}
.hero-title span{color:#06b6d4;}
.hero-sub{font-size:16px;color:rgba(255,255,255,.6);line-height:1.7;max-width:400px;}
.features{display:flex;flex-direction:column;gap:16px;margin-top:50px;}
.feature{display:flex;align-items:center;gap:14px;color:rgba(255,255,255,.7);font-size:14px;}
.feature-icon{width:36px;height:36px;background:rgba(255,255,255,.08);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.map-card{flex:0 0 420px;min-width:420px;background:rgba(15,23,42,.85);border:1px solid rgba(59,130,246,.18);border-radius:32px;box-shadow:0 24px 60px rgba(15,23,42,.25);display:flex;flex-direction:column;gap:18px;padding:24px;}
.map-card-header{display:flex;flex-direction:column;gap:6px;}
.map-card-title{font-size:18px;font-weight:700;color:#fff;}
.map-card-sub{font-size:13px;color:rgba(255,255,255,.65);line-height:1.5;}
.map-frame{width:100%;height:280px;border-radius:22px;overflow:hidden;border:1px solid rgba(255,255,255,.08);background:#0f172a;}
.right-panel{flex:0 0 480px;display:flex;align-items:center;justify-content:center;padding:40px;background:rgba(255,255,255,.03);border-left:1px solid rgba(255,255,255,.06);}
.login-box{width:100%;max-width:400px;}
@media(max-width:1200px){.map-card{min-width:auto;flex:1;max-width:520px;margin:0 auto;}}
@media(max-width:900px){.left-panel{display:none;}.map-card{display:none;}.right-panel{width:100%;}}
.login-title{font-family:'Space Grotesk',sans-serif;font-size:28px;font-weight:700;color:#fff;margin-bottom:6px;}
.login-sub{font-size:14px;color:rgba(255,255,255,.5);margin-bottom:36px;}
.form-group{margin-bottom:20px;}
.form-label{display:block;font-size:13px;font-weight:600;color:rgba(255,255,255,.7);margin-bottom:8px;}
.form-input{width:100%;padding:12px 16px;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-size:14px;font-family:inherit;outline:none;transition:all .2s;}
.form-input::placeholder{color:rgba(255,255,255,.3);}
.form-input:focus{border-color:#3b82f6;background:rgba(59,130,246,.08);}
.input-wrap{position:relative;}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);}
.input-icon + .form-input{padding-left:42px;}
.btn-login{width:100%;padding:13px;background:linear-gradient(135deg,#1e40af,#3b82f6);border:none;border-radius:10px;color:#fff;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s;margin-top:4px;}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 8px 25px rgba(59,130,246,.4);}
.demo-creds{margin-top:28px;padding:16px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;}
.demo-title{font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.4);margin-bottom:12px;font-weight:600;}
.demo-item{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;cursor:pointer;}
.demo-item:last-child{margin-bottom:0;}
.demo-role{font-size:12px;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:6px;}
.demo-email{font-size:12px;color:rgba(255,255,255,.4);font-family:monospace;}
.role-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase;}
.rb-admin{background:rgba(239,68,68,.2);color:#fca5a5;}
.rb-principal{background:rgba(16,185,129,.2);color:#6ee7b7;}
.rb-teacher{background:rgba(59,130,246,.2);color:#93c5fd;}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
@media(max-width:900px){.left-panel{display:none;}.right-panel{width:100%;}}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="bg-grid-h"></div>
<div class="bg-grid-v"></div>
<div class="bg-glow"></div>
<div class="bg-glow2"></div>

<div class="login-wrapper">
    <div class="left-panel">
        <div class="brand">
            <?php if ($schoolLogo): ?>
                <img src="<?= htmlspecialchars($schoolLogo) ?>" alt="School Logo" style="width:48px;height:48px;border-radius:12px;object-fit:cover;border:1px solid rgba(255,255,255,.15);flex-shrink:0;">
            <?php else: ?>
                <div class="brand-icon"><svg width="24" height="24" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg></div>
            <?php endif; ?>
            <div>
                <div class="brand-name"><?= htmlspecialchars($schoolName) ?></div>
                <?php if ($schoolAddress): ?>
                <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px;line-height:1.4;"><?= nl2br(htmlspecialchars($schoolAddress)) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <h1 class="hero-title">Smart <span>Attendance</span><br>Management</h1>
        <p class="hero-sub">RFID card-based attendance tracking for students, manual attendance for teachers, and comprehensive reporting for administrators.</p>
        <div class="features">
            <div class="feature"><div class="feature-icon"><svg width="18" height="18" fill="none" stroke="#06b6d4" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>RFID Card-based Student Attendance</div>
            <div class="feature"><div class="feature-icon"><svg width="18" height="18" fill="none" stroke="#06b6d4" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg></div>Role-based Access Control</div>
            <div class="feature"><div class="feature-icon"><svg width="18" height="18" fill="none" stroke="#06b6d4" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.4 10.18a19.79 19.79 0 01-3.07-8.67A2 2 0 012.31 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 9.4a16 16 0 006.72 6.72l1.76-1.76a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg></div>Automated Absent Notifications</div>
            <div class="feature"><div class="feature-icon"><svg width="18" height="18" fill="none" stroke="#06b6d4" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>Reports & Analytics Dashboard</div>
        </div>
    </div>



    <div class="right-panel">
        <div class="login-box">
            <div class="login-title">Welcome back</div>
            <div class="login-sub">Sign in to your account</div>

            <?php if($error): ?>
            <div class="error-msg">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-wrap">
                        <span class="input-icon"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                        <input type="email" name="email" class="form-input" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required id="emailField">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></span>
                        <input type="password" name="password" class="form-input" placeholder="••••••••" required id="passField">
                    </div>
                </div>
                <button type="submit" class="btn-login">Sign In →</button>
            </form>

            <div class="demo-creds">
                <div class="demo-title">Quick Login</div>
                <div class="demo-item" onclick="fillCreds('admin@school.com','password')">
                    <span class="role-badge rb-admin">Admin</span>
                    <span class="demo-email">admin@school.com</span>
                </div>
                <div class="demo-item" onclick="fillCreds('principal@school.com','password')">
                    <span class="role-badge rb-principal">Principal</span>
                    <span class="demo-email">principal@school.com</span>
                </div>
                <div class="demo-item" onclick="toggleTeacherList()" style="flex-direction:column;align-items:flex-start;gap:8px">
                    <div style="display:flex;justify-content:space-between;width:100%">
                        <span class="role-badge rb-teacher">Teacher</span>
                        <span style="font-size:11px;color:rgba(255,255,255,.4)" id="teacherToggleHint">▼ Select teacher</span>
                    </div>
                    <div id="teacherList" style="display:none;width:100%;max-height:180px;overflow-y:auto;border-top:1px solid rgba(255,255,255,.08);padding-top:8px;">
                        <?php foreach($teachers as $t): ?>
                        <div onclick="event.stopPropagation();fillCreds('<?= htmlspecialchars($t['email']) ?>','password')" style="padding:7px 8px;border-radius:6px;cursor:pointer;font-size:12px;color:rgba(255,255,255,.7);transition:background .15s" onmouseover="this.style.background='rgba(59,130,246,.15)'" onmouseout="this.style.background='transparent'">
                            <span style="color:#93c5fd;font-weight:600"><?= htmlspecialchars($t['name']) ?></span>
                            <span style="color:rgba(255,255,255,.35);margin-left:6px;font-family:monospace"><?= htmlspecialchars($t['email']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
feather.replace();
function fillCreds(e,p){
    document.getElementById('emailField').value=e;
    document.getElementById('passField').value=p;
    document.getElementById('teacherList').style.display='none';
    document.getElementById('teacherToggleHint').textContent='▼ Select teacher';
}
function toggleTeacherList(){
    var list=document.getElementById('teacherList');
    var hint=document.getElementById('teacherToggleHint');
    if(list.style.display==='none'){list.style.display='block';hint.textContent='▲ Close';}
    else{list.style.display='none';hint.textContent='▼ Select teacher';}
}
</script>
</body>
</html>