<?php
declare(strict_types=1);
$r         = $report ?? [];
$event     = $r['event'] ?? [];
$pAtt      = $r['prelims_attendance'] ?? [];
$mAtt      = $r['main_attendance'] ?? [];
$finalists = $r['prelims_finalists'] ?? [];
$results   = $r['final_results'] ?? [];
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
</style>
</head>
<body>
<h1>NexusCore — Official Main Event &amp; Prelims-to-Finals Report</h1>
<div class="meta">
    Report ID: <strong><?= htmlspecialchars((string)($r['report_id'] ?? '')) ?></strong> |
    Event: <strong><?= htmlspecialchars((string)($event['event_name'] ?? '')) ?></strong> |
    Generated: <?= htmlspecialchars((string)($r['generated_at'] ?? '')) ?>
</div>
<table class="kpi">
<tr>
    <td><strong>Prelims Attendance</strong><br>Present+Late: <?= (int)($pAtt['present'] ?? 0) + (int)($pAtt['late'] ?? 0) ?> / <?= (int)($pAtt['total_registered'] ?? 0) ?></td>
    <td><strong>Prelims Qualified</strong><br><?= count($finalists) ?> Finalist(s)</td>
    <td><strong>Main Event Attendance</strong><br>Present+Late: <?= (int)($mAtt['present'] ?? 0) + (int)($mAtt['late'] ?? 0) ?> / <?= (int)($mAtt['total_registered'] ?? 0) ?></td>
    <td><strong>Final Results</strong><br><?= count($results) ?> Ranked</td>
</tr>
</table>

<h2>Main Event Final Rankings</h2>
<table class="data">
<thead><tr><th>Rank</th><th>App No</th><th>Participant</th><th>Register No</th><th>Department / College</th><th>Total Score</th><th>Avg</th><th>Result</th></tr></thead>
<tbody>
<?php foreach ($results as $res): ?>
<tr>
    <td>#<?= (int)($res['rank_position'] ?? 0) ?></td>
    <td><?= htmlspecialchars((string)$res['application_no']) ?></td>
    <td><?= htmlspecialchars((string)$res['student_name']) ?></td>
    <td><?= htmlspecialchars((string)$res['register_number']) ?></td>
    <td><?= htmlspecialchars((string)$res['department_name']) ?> / <?= htmlspecialchars((string)$res['college_name']) ?></td>
    <td><?= htmlspecialchars((string)$res['total_score']) ?></td>
    <td><?= htmlspecialchars((string)$res['average_score']) ?></td>
    <td><?= htmlspecialchars((string)$res['result_status']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
