<?php
declare(strict_types=1);
/**
 * -------------------------------------------------------------------------
 * NexusCore EMS — Faculty Assignment Report
 * Location    : templates/allocation/reports/faculty_report.php
 * Layout      : print (A4 portrait, print-ready)
 * -------------------------------------------------------------------------
 */
$symposium   = $symposium   ?? [];
$assignments = $assignments ?? [];
$generatedBy = $generatedBy ?? 'System';
$generatedAt = $generatedAt ?? \App\Helpers\DateHelper::dateTime('now');

$symTitle     = htmlspecialchars($symposium['title'] ?? '');
$symYear      = htmlspecialchars($symposium['academic_year'] ?? '');
$collegeName  = config('college_name');

// Group assignments by event
$byEvent = [];
foreach ($assignments as $a) {
    $eid = $a['symposium_event_id'];
    $byEvent[$eid]['event']      = $a;
    $byEvent[$eid]['faculty'][]  = $a;
}

$totalFaculty = count($assignments);
$totalEvents  = count($byEvent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Faculty Assignment Report — <?= $symTitle ?></title>
    <style>
        @page { size: A4 portrait; margin: 18mm 15mm 22mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10.5pt; color: #1a202c; background: white; margin: 0; padding: 0; }
        .report-header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 16px; }
        .college-name { font-size: 13.5pt; font-weight: 700; color: #1a237e; margin-bottom: 2px; }
        .report-title  { font-size: 11.5pt; font-weight: 700; color: #1d4ed8; margin: 6px 0 2px; }
        .report-sub    { font-size: 9pt; color: #6c757d; }
        .meta-row { display: flex; justify-content: space-between; font-size: 9pt; color: #6c757d; margin-bottom: 14px; }
        .section-title { font-size: 9.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #1d4ed8; margin: 14px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 14px; }
        th { background: #1d4ed8; color: white; padding: 7px 10px; font-weight: 600; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .05em; text-align: left; }
        td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background: #f8faff; }
        .event-row td { background: #eef2ff; font-weight: 700; color: #1d4ed8; font-size: 9pt; }
        .session-badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 8pt; font-weight: 600; }
        .session-fn   { background: #dbeafe; color: #1d4ed8; }
        .session-an   { background: #fef3c7; color: #92400e; }
        .session-full { background: #d1fae5; color: #065f46; }
        .sig-block { margin-top: 40px; page-break-inside: avoid; }
        .sig-grid  { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
        .sig-item  { border-top: 1px solid #374151; padding-top: 8px; font-size: 8.5pt; }
        .sig-label { font-weight: 700; color: #374151; }
        .sig-sub   { color: #6c757d; font-size: 8pt; }
        .sig-line  { height: 36px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8pt; color: #9ca3af; border-top: 1px solid #e2e8f0; padding: 4px 0; }
        .footer-content { max-width: 180mm; margin: 0 auto; display: flex; justify-content: space-between; }
        .total-row td { font-weight: 700; background: #eef2ff !important; color: #1d4ed8; }
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- Print Button (hidden on print) -->
<div class="no-print" style="text-align:right; padding:8px 12px; background:#f8f9fa; border-bottom:1px solid #e2e8f0; margin-bottom:16px;">
    <button onclick="window.print()" style="background:#1d4ed8; color:white; border:none; padding:6px 18px; border-radius:6px; cursor:pointer; font-size:10pt;">
        🖨 Print / Save PDF
    </button>
    <a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= (int)($symposium['symposium_id'] ?? 0) ?>" style="margin-left:8px; color:#6c757d; font-size:9pt; text-decoration:none;">← Back</a>
</div>

<!-- College Header -->
<div class="report-header">
    <div class="college-name"><?= htmlspecialchars($collegeName) ?></div>
    <div style="font-size:9.5pt; color:#4b5563; margin-bottom:6px;">Department of Computer Science &amp; Applications</div>
    <div class="report-title">FACULTY IN-CHARGE ASSIGNMENT REPORT</div>
    <div class="report-sub"><?= $symTitle ?> &bull; Academic Year: <?= $symYear ?></div>
</div>

<!-- Report Meta -->
<div class="meta-row">
    <div><strong>Report ID:</strong> FAC-<?= date('Ymd') ?>-<?= str_pad((string)($symposium['symposium_id'] ?? 0), 4, '0', STR_PAD_LEFT) ?></div>
    <div><strong>Total Events:</strong> <?= $totalEvents ?> &nbsp;|&nbsp; <strong>Total Faculty:</strong> <?= $totalFaculty ?></div>
    <div><strong>Generated By:</strong> <?= htmlspecialchars($generatedBy) ?></div>
    <div><strong>Generated On:</strong> <?= htmlspecialchars($generatedAt) ?></div>
</div>

<!-- Assignments Table -->
<div class="section-title">Faculty In-Charge Assignments</div>

<table>
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:22%">Event</th>
            <th style="width:9%">Date</th>
            <th style="width:8%">Session</th>
            <th style="width:10%">Time</th>
            <th style="width:14%">Venue</th>
            <th style="width:20%">Faculty In-Charge</th>
            <th style="width:13%">Department</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($assignments)): ?>
        <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:20px;">No faculty assignments found for this symposium.</td></tr>
        <?php else: ?>
        <?php $n = 1; foreach ($byEvent as $eid => $row):
            $event = $row['event'];
            $facList = $row['faculty'];
            $sessBadge = match($event['session'] ?? '') {
                'FN'       => '<span class="session-badge session-fn">FN</span>',
                'AN'       => '<span class="session-badge session-an">AN</span>',
                'Full Day' => '<span class="session-badge session-full">Full</span>',
                default    => htmlspecialchars($event['session'] ?? '')
            };
            foreach ($facList as $idx => $fac):
        ?>
        <tr>
            <td><?= $n++ ?></td>
            <?php if ($idx === 0): ?>
            <td rowspan="<?= count($facList) ?>"><?= htmlspecialchars($event['event_name'] ?? '') ?></td>
            <td rowspan="<?= count($facList) ?>" style="white-space:nowrap;"><?= $event['event_date'] ? \App\Helpers\DateHelper::date($event['event_date']) : '—' ?></td>
            <td rowspan="<?= count($facList) ?>"><?= $sessBadge ?></td>
            <td rowspan="<?= count($facList) ?>" style="white-space:nowrap;"><?php
                $st = \App\Helpers\DateHelper::time($event['start_time'] ?? null, '');
                $et = \App\Helpers\DateHelper::time($event['end_time'] ?? null, '');
                echo htmlspecialchars(trim($st . ($st && $et ? ' – ' : '') . $et));
            ?></td>
            <td rowspan="<?= count($facList) ?>"><?= htmlspecialchars($event['venue_name'] ?? '—') ?></td>
            <?php endif; ?>
            <td><strong><?= htmlspecialchars($fac['full_name'] ?? '') ?></strong><br><span style="font-size:8pt;color:#6c757d;"><?= htmlspecialchars($fac['user_role'] ?? '') ?></span></td>
            <td><?= htmlspecialchars($fac['department_name'] ?? '—') ?></td>
        </tr>
        <?php endforeach; endforeach; ?>
        <tr class="total-row">
            <td colspan="6" style="text-align:right;">Total</td>
            <td colspan="2"><?= $totalFaculty ?> assignment(s) across <?= $totalEvents ?> event(s)</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Signatures -->
<div class="sig-block">
    <div class="section-title">Signatures</div>
    <div class="sig-grid">
        <!-- Faculty signatures (first 3) -->
        <?php foreach (array_slice($assignments, 0, 3) as $fac): ?>
        <div class="sig-item">
            <div class="sig-line"></div>
            <div class="sig-label"><?= htmlspecialchars($fac['full_name'] ?? '') ?></div>
            <div class="sig-sub">Faculty In-Charge</div>
            <div class="sig-sub"><?= htmlspecialchars($fac['event_name'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
        <!-- If no faculty, show empty blocks -->
        <?php if (empty($assignments)): ?>
        <div class="sig-item"><div class="sig-line"></div><div class="sig-label">Faculty In-Charge</div></div>
        <div class="sig-item"><div class="sig-line"></div><div class="sig-label">Faculty In-Charge</div></div>
        <?php endif; ?>
    </div>
    <div class="sig-grid" style="margin-top:32px;">
        <div class="sig-item">
            <div class="sig-line"></div>
            <div class="sig-label">Coordinator</div>
            <div class="sig-sub">Staff Coordinator</div>
        </div>
        <div class="sig-item">
            <div class="sig-line"></div>
            <div class="sig-label">Verified By</div>
            <div class="sig-sub">HOD</div>
        </div>
        <div class="sig-item">
            <div class="sig-line"></div>
            <div class="sig-label">Approved By</div>
            <div class="sig-sub">Principal</div>
        </div>
    </div>
</div>

<!-- Fixed Footer -->
<div class="footer">
    <div class="footer-content">
        <span><?= htmlspecialchars($collegeName) ?> — NexusCore EMS</span>
        <span>Faculty Assignment Report &bull; <?= $symTitle ?></span>
        <span>Generated: <?= htmlspecialchars($generatedAt) ?></span>
    </div>
</div>

<script>window.onload = function() { /* Auto-print if ?print=1 */ if (new URLSearchParams(location.search).get('print') === '1') window.print(); }</script>
</body>
</html>
