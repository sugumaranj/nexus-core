<?php
declare(strict_types=1);
$r         = $report ?? [];
$quiz      = $r['quiz'] ?? [];
$att       = $r['prelims_attendance'] ?? [];
$attempts  = $r['attempts'] ?? [];
$selected  = $r['selected_finalists'] ?? [];
$analytics = $r['analytics'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#1e293b}
h1{font-size:16px;margin:0 0 4px 0;color:#0f172a}
h2{font-size:12px;margin:14px 0 6px 0;border-bottom:1px solid #cbd5e1;padding-bottom:3px}
.meta{font-size:9px;color:#475569;margin-bottom:10px}
.kpi{width:100%;border-collapse:collapse;margin-bottom:10px}
.kpi td{border:1px solid #cbd5e1;padding:6px;background:#f8fafc}
table.data{width:100%;border-collapse:collapse;margin-bottom:10px}
table.data th{background:#1e3a5f;color:#fff;padding:5px;text-align:left;font-size:9px}
table.data td{border:1px solid #cbd5e1;padding:4px 5px;font-size:9px}
.sel{background:#ecfdf5;font-weight:bold}
</style>
</head>
<body>
<h1>NexusCore — Official Prelims Examination Report</h1>
<div class="meta">
    Report ID: <strong><?= htmlspecialchars((string)($r['report_id'] ?? '')) ?></strong> |
    Event: <strong><?= htmlspecialchars((string)($quiz['event_name'] ?? '')) ?></strong> (<?= htmlspecialchars((string)($quiz['symposium_title'] ?? '')) ?>) |
    Generated: <?= htmlspecialchars((string)($r['generated_at'] ?? '')) ?>
</div>

<table class="kpi">
<tr>
    <td><strong>Prelims Attendance</strong><br>Registered: <?= (int)($att['total_registered'] ?? 0) ?> | Present: <?= (int)($att['present'] ?? 0) ?> | Late: <?= (int)($att['late'] ?? 0) ?> | Absent: <?= (int)($att['absent'] ?? 0) ?></td>
    <td><strong>Attempts &amp; Scores</strong><br>Total Attempts: <?= count($attempts) ?> | Avg Score: <?= htmlspecialchars((string)($analytics['avg_score'] ?? 0)) ?> | Highest: <?= htmlspecialchars((string)($analytics['max_score_achieved'] ?? 0)) ?></td>
    <td><strong>Finalist Selection</strong><br>Top-N Configured: <?= (int)($quiz['top_n_select'] ?? 0) ?> | Promoted: <?= count($selected) ?> | Tie-Break: Score &rarr; Correct &rarr; Time</td>
</tr>
</table>

<h2>1. Promoted Finalists (Advancing to Main Event)</h2>
<table class="data">
<thead><tr><th>Rank</th><th>App No</th><th>Student Name</th><th>Register No</th><th>Department / College</th><th>Score</th><th>Correct</th><th>Time</th></tr></thead>
<tbody>
<?php foreach ($selected as $f): ?>
<tr class="sel">
    <td>#<?= (int)$f['prelims_rank'] ?></td>
    <td><?= htmlspecialchars((string)$f['application_no']) ?></td>
    <td><?= htmlspecialchars((string)$f['student_name']) ?></td>
    <td><?= htmlspecialchars((string)$f['register_number']) ?></td>
    <td><?= htmlspecialchars((string)$f['department_name']) ?> / <?= htmlspecialchars((string)$f['college_name']) ?></td>
    <td><?= htmlspecialchars((string)$f['final_score']) ?> / <?= htmlspecialchars((string)$f['max_score']) ?></td>
    <td><?= (int)$f['correct_count'] ?></td>
    <td><?= gmdate('i:s', (int)$f['time_taken_seconds']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<h2>2. Complete Student Prelims Score Sheet</h2>
<table class="data">
<thead><tr><th>Rank</th><th>Register No</th><th>Student Name</th><th>Department</th><th>Correct/Wrong</th><th>Score</th><th>%</th><th>Time</th><th>Violations</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($attempts as $a): ?>
<tr>
    <td><?= $a['rank_position'] ? '#' . (int)$a['rank_position'] : '-' ?></td>
    <td><?= htmlspecialchars((string)$a['register_number']) ?></td>
    <td><?= htmlspecialchars((string)$a['student_name']) ?></td>
    <td><?= htmlspecialchars((string)$a['department_name']) ?></td>
    <td><?= (int)$a['correct_count'] ?> / <?= (int)$a['wrong_count'] ?></td>
    <td><?= htmlspecialchars((string)$a['raw_score']) ?> / <?= htmlspecialchars((string)$a['max_score']) ?></td>
    <td><?= htmlspecialchars((string)$a['percentage']) ?>%</td>
    <td><?= gmdate('i:s', (int)($a['time_taken_seconds'] ?? 0)) ?></td>
    <td><?= (int)$a['violation_count'] ?> (<?= htmlspecialchars((string)$a['integrity_level']) ?>)</td>
    <td><?= ($a['selection_status'] ?? '') === 'Selected' ? 'Selected' : 'Not Selected' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
