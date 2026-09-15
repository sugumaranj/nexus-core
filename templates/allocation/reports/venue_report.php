<?php
declare(strict_types=1);
/**
 * -------------------------------------------------------------------------
 * NexusCore EMS — Venue Allocation Report
 * Location    : templates/allocation/reports/venue_report.php
 * Layout      : print (A4 portrait, print-ready)
 * Access      : Admin, Staff Coordinator, HOD, Principal
 * -------------------------------------------------------------------------
 */
$symposium   = $symposium   ?? [];
$events      = $events      ?? [];
$generatedBy = $generatedBy ?? 'System';
$generatedAt = $generatedAt ?? date('d M Y, h:i A');

$symTitle    = htmlspecialchars($symposium['title'] ?? '');
$symYear     = htmlspecialchars($symposium['academic_year'] ?? '');
$collegeName = config('college_name');

$totalEvents   = count($events);
$assignedCount = count(array_filter($events, fn($e) => !empty($e['venue_id'])));
$unassigned    = $totalEvents - $assignedCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Venue Allocation Report — <?= $symTitle ?></title>
    <style>
        @page { size: A4 portrait; margin: 18mm 15mm 22mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10.5pt; color: #1a202c; background: white; margin: 0; padding: 0; }
        .report-header { text-align: center; border-bottom: 2px solid #d97706; padding-bottom: 12px; margin-bottom: 16px; }
        .college-name { font-size: 13.5pt; font-weight: 700; color: #92400e; margin-bottom: 2px; }
        .report-title  { font-size: 11.5pt; font-weight: 700; color: #d97706; margin: 6px 0 2px; }
        .report-sub    { font-size: 9pt; color: #6c757d; }
        .meta-row { display: flex; justify-content: space-between; font-size: 9pt; color: #6c757d; margin-bottom: 14px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
        .summary-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; text-align: center; }
        .summary-num { font-size: 1.6rem; font-weight: 700; }
        .summary-lbl { font-size: 8pt; color: #6c757d; text-transform: uppercase; letter-spacing: .05em; }
        .num-total  { color: #4f46e5; }
        .num-ok     { color: #16a34a; }
        .num-warn   { color: #dc2626; }
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 14px; }
        th { background: #d97706; color: white; padding: 7px 10px; font-weight: 600; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .05em; text-align: left; }
        td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background: #fffbeb; }
        .unassigned-row td { background: #fef2f2; color: #991b1b; font-style: italic; }
        .venue-code-badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 7.5pt; font-weight: 700; background: #fde68a; color: #92400e; border: 1px solid #fcd34d; }
        .session-badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 8pt; font-weight: 600; }
        .session-fn   { background: #dbeafe; color: #1d4ed8; }
        .session-an   { background: #fef3c7; color: #92400e; }
        .session-full { background: #d1fae5; color: #065f46; }
        .missing-badge { color: #dc2626; font-size: 8pt; font-style: italic; }
        .computer-lab-badge { font-size: 7pt; padding: 1px 5px; background: #e0f2fe; color: #0369a1; border-radius: 4px; border: 1px solid #bae6fd; }
        .sig-block { margin-top: 40px; page-break-inside: avoid; }
        .sig-grid  { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }
        .sig-item  { border-top: 1px solid #374151; padding-top: 8px; font-size: 8.5pt; }
        .sig-label { font-weight: 700; color: #374151; }
        .sig-sub   { color: #6c757d; font-size: 8pt; }
        .sig-line  { height: 36px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8pt; color: #9ca3af; border-top: 1px solid #e2e8f0; padding: 4px 0; }
        .footer-content { max-width: 180mm; margin: 0 auto; display: flex; justify-content: space-between; }
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align:right; padding:8px 12px; background:#fef3c7; border-bottom:1px solid #fcd34d; margin-bottom:16px;">
    <button onclick="window.print()" style="background:#d97706; color:white; border:none; padding:6px 18px; border-radius:6px; cursor:pointer; font-size:10pt;">
        🖨 Print / Save PDF
    </button>
    <a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= (int)($symposium['symposium_id'] ?? 0) ?>"
       style="margin-left:8px; color:#6c757d; font-size:9pt; text-decoration:none;">← Back to Allocation</a>
</div>

<div class="report-header">
    <div class="college-name"><?= htmlspecialchars($collegeName) ?></div>
    <div style="font-size:9.5pt; color:#4b5563; margin-bottom:6px;">Department of Computer Science &amp; Applications</div>
    <div class="report-title">VENUE ALLOCATION REPORT</div>
    <div class="report-sub"><?= $symTitle ?> &bull; Academic Year: <?= $symYear ?></div>
</div>

<div class="meta-row">
    <span><strong>Generated by:</strong> <?= htmlspecialchars($generatedBy) ?></span>
    <span><strong>Date:</strong> <?= htmlspecialchars($generatedAt) ?></span>
</div>

<!-- Summary boxes -->
<div class="summary-grid">
    <div class="summary-box">
        <div class="summary-num num-total"><?= $totalEvents ?></div>
        <div class="summary-lbl">Total Events</div>
    </div>
    <div class="summary-box">
        <div class="summary-num num-ok"><?= $assignedCount ?></div>
        <div class="summary-lbl">Venue Assigned</div>
    </div>
    <div class="summary-box">
        <div class="summary-num num-warn"><?= $unassigned ?></div>
        <div class="summary-lbl">Venue Pending</div>
    </div>
</div>

<!-- Main table -->
<table>
    <thead>
        <tr>
            <th style="width:3%;">#</th>
            <th style="width:16%;">Event</th>
            <th style="width:9%;">Date</th>
            <th style="width:9%;">Time</th>
            <th style="width:7%;">Session</th>
            <th style="width:8%;">Venue Code</th>
            <th style="width:16%;">Venue Name</th>
            <th style="width:14%;">Building / Floor</th>
            <th style="width:6%;">Seats</th>
            <th style="width:12%;">FIC(s)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($events as $i => $e):
            $hasVenue = !empty($e['venue_id']);
            $rowClass = $hasVenue ? '' : 'unassigned-row';
            $session  = $e['session'] ?? '';
            $sessionClass = match($session) {
                'FN'       => 'session-fn',
                'AN'       => 'session-an',
                'Full Day' => 'session-full',
                default    => '',
            };
        ?>
        <tr class="<?= $rowClass ?>">
            <td><?= $i + 1 ?></td>
            <td>
                <strong><?= htmlspecialchars($e['event_name'] ?? '') ?></strong>
                <?php if (!empty($e['event_code'])): ?>
                <br><span style="font-size:7.5pt; color:#6c757d;"><?= htmlspecialchars($e['event_code']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($e['event_date'])): ?>
                <?= date('d M Y', strtotime($e['event_date'])) ?>
                <?php else: ?>
                <span class="missing-badge">TBA</span>
                <?php endif; ?>
            </td>
            <td style="font-size:9pt;">
                <?php if (!empty($e['start_time']) && !empty($e['end_time'])): ?>
                <?= substr($e['start_time'], 0, 5) ?>–<?= substr($e['end_time'], 0, 5) ?>
                <?php else: ?>
                <span class="missing-badge">TBA</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($session): ?>
                <span class="session-badge <?= $sessionClass ?>"><?= htmlspecialchars($session) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <?php if ($hasVenue && !empty($e['venue_code'])): ?>
                <span class="venue-code-badge"><?= htmlspecialchars($e['venue_code']) ?></span>
                <?php elseif (!$hasVenue): ?>
                <span class="missing-badge">Not Assigned</span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <?php if ($hasVenue): ?>
                <?= htmlspecialchars($e['venue_name'] ?? '—') ?>
                <?php if (!empty($e['is_computer_lab']) && $e['is_computer_lab']): ?>
                <br><span class="computer-lab-badge">💻 Lab</span>
                <?php endif; ?>
                <?php else: ?>
                <span class="missing-badge">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($hasVenue): ?>
                <?= htmlspecialchars($e['building_name'] ?? '') ?>
                <?php if (!empty($e['floor'])): ?><br><span style="font-size:8pt; color:#6c757d;">Floor <?= htmlspecialchars($e['floor']) ?></span><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?php if ($hasVenue && !empty($e['seating_capacity'])): ?>
                <?= (int)$e['seating_capacity'] ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td style="font-size:8.5pt;">
                <?= !empty($e['fic_names']) ? htmlspecialchars($e['fic_names']) : '<span class="missing-badge">Unassigned</span>' ?>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($events)): ?>
        <tr><td colspan="10" style="text-align:center; padding:20px; color:#9ca3af;">No events found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Signature block -->
<div class="sig-block">
    <div class="sig-grid">
        <div class="sig-item">
            <div class="sig-line"></div>
            <div class="sig-label">Staff Coordinator</div>
            <div class="sig-sub">Signature &amp; Date</div>
        </div>
        <div class="sig-item">
            <div class="sig-line"></div>
            <div class="sig-label">Head of Department</div>
            <div class="sig-sub">Signature &amp; Date</div>
        </div>
        <div class="sig-item">
            <div class="sig-line"></div>
            <div class="sig-label">Principal</div>
            <div class="sig-sub">Signature &amp; Date</div>
        </div>
    </div>
</div>

<div class="footer">
    <div class="footer-content">
        <span><?= htmlspecialchars($collegeName) ?> — NexusCore EMS</span>
        <span>Venue Allocation Report — <?= $symTitle ?></span>
        <span>Generated: <?= htmlspecialchars($generatedAt) ?></span>
    </div>
</div>

</body>
</html>
