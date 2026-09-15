<?php

declare(strict_types=1);

/**
 * Schedule Report Template (Print Layout)
 * Groups events by Day -> Morning (FN) -> Afternoon (AN)
 * Professional printable report.
 */

$symposium   = $symposium   ?? [];
$grouped_days = $grouped_days ?? [];
$dashboard   = $dashboard   ?? [];

$title       = htmlspecialchars($symposium['title'] ?? 'Symposium');
$symCode     = htmlspecialchars($symposium['symposium_code'] ?? '');
$symStart    = $symposium['event_start_date'] ?? '';
$symEnd      = $symposium['event_end_date'] ?? '';
$total       = (int) ($dashboard['total'] ?? 0);
$scheduled   = (int) ($dashboard['scheduled'] ?? 0);
$pct         = (int) ($dashboard['completion_pct'] ?? 0);

$sessionNames  = ['FN' => 'Morning Session (Forenoon)', 'AN' => 'Afternoon Session', 'Full Day' => 'Full Day'];
?>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #212529; margin: 0; padding: 20px; }

    /* Action Bar */
    .report-action-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding: 10px 16px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 10px;
    }
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        background: #fff;
        border: 1.5px solid #0d6efd;
        border-radius: 7px;
        color: #0d6efd;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s, color .15s;
    }
    .btn-back:hover { background: #0d6efd; color: #fff; }
    .btn-print {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        background: #0d6efd;
        border: 1.5px solid #0d6efd;
        border-radius: 7px;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-print:hover { background: #0b5ed7; border-color: #0b5ed7; }
    .bar-sep { flex: 1; }
    .bar-title { font-size: 12px; color: #6c757d; }

    .report-header { text-align: center; border-bottom: 3px double #0d6efd; padding-bottom: 16px; margin-bottom: 24px; }
    .report-header h1 { font-size: 22px; font-weight: 800; margin: 0 0 4px; color: #0d47a1; }
    .report-header .code { font-size: 12px; color: #6c757d; letter-spacing: 1px; }
    .summary-strip { display: flex; gap: 20px; justify-content: center; margin-bottom: 28px; flex-wrap: wrap; }
    .summary-item { text-align: center; padding: 10px 20px; background: #f0f4ff; border-radius: 10px; min-width: 100px; }
    .summary-item .num { font-size: 24px; font-weight: 800; color: #0d6efd; }
    .summary-item .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; font-weight: 600; }
    .day-block { margin-bottom: 28px; page-break-inside: avoid; }
    .day-header { display: flex; align-items: center; gap: 12px; background: #0d6efd; color: #fff; padding: 10px 16px; border-radius: 10px 10px 0 0; }
    .day-header .day-num { font-size: 18px; font-weight: 800; }
    .day-header .day-date { font-size: 13px; opacity: .9; }
    .session-block { margin-bottom: 0; }
    .session-header { background: #f8f9fa; padding: 6px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #495057; border-left: 4px solid #0d6efd; }
    .session-header.an { border-left-color: #fd7e14; }
    .session-header.fullday { border-left-color: #6f42c1; }
    .event-row { display: flex; gap: 16px; padding: 10px 16px; border-bottom: 1px solid #e9ecef; align-items: flex-start; }
    .event-row:last-child { border-bottom: none; }
    .event-time { min-width: 120px; }
    .event-time .from { font-weight: 700; font-size: 14px; }
    .event-time .to   { font-size: 11px; color: #6c757d; }
    .event-name { font-weight: 600; }
    .event-code { font-size: 11px; color: #6c757d; font-family: monospace; }
    .badge-rescheduled { background: #fff3cd; color: #856404; font-size: 10px; padding: 2px 7px; border-radius: 20px; font-weight: 600; margin-left: 6px; }
    .day-wrapper { border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden; }
    .no-events { text-align: center; padding: 40px; color: #adb5bd; }
    .footer { text-align: center; margin-top: 32px; padding-top: 12px; border-top: 1px solid #dee2e6; font-size: 11px; color: #6c757d; }
    @media print {
        body { padding: 10px; }
        .day-block { page-break-inside: avoid; }
        .report-action-bar { display: none !important; }
    }
</style>

<!-- Action Bar (hidden on print) -->
<div class="report-action-bar">
    <a href="javascript:void(0);" onclick="if(window.history.length > 1 && document.referrer.indexOf(window.location.host) !== -1) { window.history.back(); } else { window.close(); window.location.href = '<?= base_url() ?>/symposiums/scheduling?symposium_id=<?= (int)($symposium['symposium_id'] ?? 0) ?>'; }" class="btn-back">&#8592; Back</a>
    <a href="<?= base_url() ?>/symposiums/scheduling?symposium_id=<?= (int)($symposium['symposium_id'] ?? 0) ?>" class="btn-back">&#128197; Schedule Dashboard</a>
    <span class="bar-sep"></span>
    <span class="bar-title"><?= $symCode ?> &bull; Schedule Report</span>
    <button class="btn-print" onclick="window.print()">&#128438; Print / Save PDF</button>
</div>

<div class="report-header">
    <div class="code"><?= $symCode ?> &nbsp;&bull;&nbsp; Event Schedule Report</div>
    <h1><?= $title ?></h1>
    <div style="font-size:12px;color:#6c757d;margin-top:4px;">
        <?php if ($symStart): ?>
            <?= \App\Helpers\DateHelper::date($symStart) ?>
            <?php if ($symStart !== $symEnd): ?> &mdash; <?= \App\Helpers\DateHelper::date($symEnd) ?><?php endif; ?>
        <?php endif; ?>
        &nbsp;&bull;&nbsp; Generated: <?= \App\Helpers\DateHelper::dateTime('now') ?>
    </div>
</div>

<!-- Summary -->
<div class="summary-strip">
    <div class="summary-item">
        <div class="num"><?= $total ?></div>
        <div class="lbl">Total Events</div>
    </div>
    <div class="summary-item">
        <div class="num" style="color:#198754"><?= $scheduled ?></div>
        <div class="lbl">Scheduled</div>
    </div>
    <div class="summary-item">
        <div class="num" style="color:<?= $total - $scheduled > 0 ? '#dc3545' : '#198754' ?>"><?= $total - $scheduled ?></div>
        <div class="lbl">Pending</div>
    </div>
    <div class="summary-item">
        <div class="num" style="color:<?= $pct === 100 ? '#198754' : '#0d6efd' ?>"><?= $pct ?>%</div>
        <div class="lbl">Progress</div>
    </div>
</div>

<?php if (empty($grouped_days)): ?>
    <div class="no-events">
        <div style="font-size:40px">&#128197;</div>
        <p>No events have been scheduled yet.</p>
    </div>
<?php else: ?>

<?php $dayNum = 1; foreach ($grouped_days as $date => $sessions): ?>
<div class="day-block">
    <div class="day-wrapper">
        <div class="day-header">
            <div class="day-num">Day <?= $dayNum ?></div>
            <div class="day-date"><?= \App\Helpers\DateHelper::date($date) ?></div>
            <div style="margin-left:auto;font-size:12px;opacity:.8;">
                <?php $dc = array_sum(array_map('count', $sessions)); ?>
                <?= $dc ?> event<?= $dc !== 1 ? 's' : '' ?>
            </div>
        </div>

        <?php foreach ($sessions as $session => $events): ?>
            <div class="session-block">
                <div class="session-header <?= strtolower(str_replace(' ', '', $session)) ?>">
                    <?= htmlspecialchars($sessionNames[$session] ?? $session) ?>
                </div>
                <?php foreach ($events as $ev): ?>
                    <div class="event-row">
                        <div class="event-time">
                            <div class="from"><?= \App\Helpers\DateHelper::time($ev['start_time']) ?></div>
                            <div class="to">to <?= \App\Helpers\DateHelper::time($ev['end_time']) ?></div>
                        </div>
                        <div style="flex:1">
                            <div class="event-name">
                                <?= htmlspecialchars($ev['event_name']) ?>
                                <?php if (($ev['schedule_status'] ?? '') === 'Rescheduled'): ?>
                                    <span class="badge-rescheduled">Rescheduled</span>
                                <?php endif; ?>
                            </div>
                            <div class="event-code"><?= htmlspecialchars($ev['event_code'] ?? '') ?></div>
                            <?php if (!empty($ev['reschedule_reason'])): ?>
                                <div style="font-size:11px;color:#856404;margin-top:2px;">
                                    <em>Reschedule reason: <?= htmlspecialchars($ev['reschedule_reason']) ?></em>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $dayNum++; endforeach; ?>

<?php endif; ?>

<div class="footer">
    NexusCore EMS &mdash; <?= $title ?> &mdash; Schedule Report &mdash; <?= \App\Helpers\DateHelper::date('now') ?>
</div>

<script>
// Print is now manual — click the "Print / Save PDF" button in the action bar.
</script>