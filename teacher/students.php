<?php
$pageTitle = 'Students';
require_once __DIR__ . '/../auth.php';
requireRole('teacher', '../index.php');
require_once __DIR__ . '/../header.php';

$db = getDB();
$filterClass = $_GET['class'] ?? '';
$search      = trim($_GET['search'] ?? '');

$classes = $db->query("SELECT DISTINCT class FROM students WHERE is_active=1 ORDER BY CAST(class AS UNSIGNED)")->fetchAll();

$query  = "SELECT * FROM students WHERE is_active=1";
$params = [];
if ($filterClass) { $query .= " AND class=?"; $params[] = $filterClass; }
if ($search)      { $query .= " AND (name LIKE ? OR roll_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$query .= " ORDER BY CAST(class AS UNSIGNED), roll_number";

$stmt = $db->prepare($query); $stmt->execute($params);
$students = $stmt->fetchAll();

// View single student
$viewStudent = null;
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT * FROM students WHERE id=?");
    $stmt->execute([(int)$_GET['view']]);
    $viewStudent = $stmt->fetch();
    if ($viewStudent) {
        // Attendance stats last 30 days
        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT date) as total_days,
                SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status='absent'  THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status='late'    THEN 1 ELSE 0 END) as late
            FROM student_attendance
            WHERE student_id=? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND period=1
        ");
        $stmt->execute([$viewStudent['id']]);
        $viewStudent['att'] = $stmt->fetch();
        $t = $viewStudent['att']['total_days'];
        $viewStudent['att']['pct'] = $t>0 ? round(($viewStudent['att']['present']/$t)*100,1) : 0;

        // Recent attendance records
        $stmt = $db->prepare("SELECT * FROM student_attendance WHERE student_id=? AND period=1 ORDER BY date DESC LIMIT 10");
        $stmt->execute([$viewStudent['id']]);
        $viewStudent['recent'] = $stmt->fetchAll();
    }
}
?>

<div class="page-header">
    <div class="header-content">
        <h1><i data-feather="users"></i> Students</h1>
        <p><?= count($students) ?> students found</p>
    </div>
</div>

<div class="page-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <div class="form-group" style="min-width:150px">
                    <label>Class</label>
                    <select name="class" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['class'] ?>" <?= $filterClass==$c['class']?'selected':'' ?>>Class <?= $c['class'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="min-width:220px">
                    <label>Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name or Roll No">
                </div>
                <?php if($filterClass): ?><input type="hidden" name="class" value="<?= htmlspecialchars($filterClass) ?>"><?php endif; ?>
                <button type="submit" class="btn btn-primary"><i data-feather="search"></i> Search</button>
                <a href="students.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header"><h3><i data-feather="list"></i> Students (<?= count($students) ?>)</h3></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Gender</th>
                            <th>DOB</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Parent Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                        <tr><td colspan="10" style="text-align:center;padding:30px;color:#6b7280">No students found</td></tr>
                        <?php else: ?>
                        <?php foreach($students as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['roll_number']) ?></strong></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><span class="badge" style="background:#dbeafe;color:#1e40af">Class <?= htmlspecialchars($s['class']) ?></span></td>
                            <td><?= htmlspecialchars($s['section']??'-') ?></td>
                            <td><?= ucfirst($s['gender']??'-') ?></td>
                            <td style="font-size:13px"><?= $s['date_of_birth'] ? date('d M Y',strtotime($s['date_of_birth'])) : '-' ?></td>
                            <td style="font-size:13px"><?= htmlspecialchars($s['contact']??'-') ?></td>
                            <td style="font-size:12px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($s['address']??'') ?>"><?= htmlspecialchars($s['address']??'-') ?></td>
                            <td style="font-size:13px"><?= htmlspecialchars($s['parent_phone']??'-') ?></td>
                            <td>
                                <a href="?view=<?= $s['id'] ?><?= $filterClass?"&class=$filterClass":'' ?>" class="btn btn-sm btn-secondary"><i data-feather="eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Student Detail Modal -->
<?php if ($viewStudent): ?>
<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto">
        <div style="padding:24px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:18px;font-weight:700"><?= htmlspecialchars($viewStudent['name']) ?></h3>
            <a href="students.php<?= $filterClass?"?class=$filterClass":'' ?>" style="color:#64748b;text-decoration:none;font-size:20px">&times;</a>
        </div>
        <div style="padding:24px">
            <!-- Details Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
                <?php
                $details = [
                    'Roll Number'  => $viewStudent['roll_number'],
                    'Class'        => 'Class ' . $viewStudent['class'],
                    'Section'      => $viewStudent['section'] ?? '-',
                    'Gender'       => ucfirst($viewStudent['gender'] ?? '-'),
                    'Date of Birth'=> $viewStudent['date_of_birth'] ? date('d M Y',strtotime($viewStudent['date_of_birth'])) : '-',
                    'Contact'      => $viewStudent['contact'] ?? '-',
                    'Parent Phone' => $viewStudent['parent_phone'] ?? '-',
                    'Parent Email' => $viewStudent['parent_email'] ?? '-',
                    'Card UID'     => $viewStudent['card_uid'] ?? '-',
                ];
                foreach($details as $label=>$val): ?>
                <div style="background:#f8fafc;padding:12px;border-radius:8px">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px"><?= $label ?></div>
                    <div style="font-size:14px;font-weight:600;color:#1e293b"><?= htmlspecialchars($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($viewStudent['address']): ?>
            <div style="background:#f8fafc;padding:12px;border-radius:8px;margin-bottom:20px">
                <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Address</div>
                <div style="font-size:14px;color:#1e293b"><?= htmlspecialchars($viewStudent['address']) ?></div>
            </div>
            <?php endif; ?>

            <!-- Attendance Stats -->
            <?php $att = $viewStudent['att']; ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;margin-bottom:20px">
                <div style="font-size:13px;font-weight:600;color:#065f46;margin-bottom:12px">Attendance (Last 30 Days — Period 1)</div>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <?php
                    $pct = $att['pct'];
                    $statItems = [
                        ['Days','total_days','#3b82f6'],
                        ['Present','present','#10b981'],
                        ['Absent','absent','#ef4444'],
                        ['Late','late','#f59e0b'],
                    ];
                    foreach($statItems as $si): ?>
                    <div style="text-align:center;flex:1;min-width:60px">
                        <div style="font-size:22px;font-weight:700;color:<?= $si[2] ?>"><?= $att[$si[1]] ?></div>
                        <div style="font-size:11px;color:#64748b"><?= $si[0] ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div style="text-align:center;flex:1;min-width:60px">
                        <div style="font-size:22px;font-weight:700;color:<?= $pct>=75?'#059669':'#dc2626' ?>"><?= $pct ?>%</div>
                        <div style="font-size:11px;color:#64748b">Rate</div>
                    </div>
                </div>
            </div>

            <!-- Recent Records -->
            <?php if (!empty($viewStudent['recent'])): ?>
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Recent Attendance</div>
            <div style="display:flex;flex-direction:column;gap:6px">
                <?php foreach($viewStudent['recent'] as $r):
                    $colors = ['present'=>['#d1fae5','#065f46'],'absent'=>['#fee2e2','#7f1d1d'],'late'=>['#fef3c7','#92400e'],'excused'=>['#dbeafe','#1e40af']];
                    $c = $colors[$r['status']] ?? ['#f1f5f9','#475569'];
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f8fafc;border-radius:6px">
                    <span style="font-size:13px;color:#374151"><?= date('d M Y',strtotime($r['date'])) ?></span>
                    <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:12px;background:<?= $c[0] ?>;color:<?= $c[1] ?>"><?= ucfirst($r['status']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="padding:16px 24px;border-top:1px solid #f1f5f9">
            <a href="students.php<?= $filterClass?"?class=$filterClass":'' ?>" class="btn btn-secondary" style="width:100%;justify-content:center">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../footer.php'; ?>
