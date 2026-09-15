<?php
/**
 * Email Template — Trigger 2: Registration is Now Open
 * Variables: $studentName, $symposiumTitle, $registrationEnd,
 *            $eventStartDate, $competitionCount, $baseUrl
 */
?>
<p class="salutation">Dear <?= htmlspecialchars($studentName ?? 'Student') ?>,</p>

<p>
    🎉 Great news! Registration for <strong><?= htmlspecialchars($symposiumTitle) ?></strong> is
    <span style="color:#1b5e20;font-weight:700;">now officially open</span>.
    The event schedule has been finalised — find your competitions, register, and prepare to shine!
</p>

<!-- Urgency banner -->
<div style="background:linear-gradient(135deg,#e65100,#bf360c);border-radius:10px;padding:18px 22px;margin:20px 0;text-align:center;">
    <div style="font-size:12px;font-weight:700;color:#ffccbc;text-transform:uppercase;letter-spacing:1px;">Registration Closes On</div>
    <div style="font-size:24px;font-weight:800;color:#ffffff;margin:6px 0;"><?= htmlspecialchars($registrationEnd) ?></div>
    <div style="font-size:12px;color:#ffccbc;">Don't miss your chance to participate!</div>
</div>

<div class="info-card">
    <div style="display:table;width:100%;">
        <div style="display:table-cell;width:50%;vertical-align:top;padding-right:10px;">
            <div class="label">Symposium Date</div>
            <div class="value" style="font-size:14px;"><?= htmlspecialchars($eventStartDate ?? 'TBA') ?></div>
        </div>
        <div style="display:table-cell;width:50%;vertical-align:top;">
            <div class="label">Total Competitions</div>
            <div class="value" style="font-size:14px;"><?= (int)($competitionCount ?? 0) ?> Events</div>
        </div>
    </div>
</div>

<p style="font-size:14px;font-weight:700;color:#1a237e;margin:20px 0 10px;">How to Register</p>
<ol class="step-list">
    <li><span class="step-num">1</span>Log in to the <strong>NexusCore Student Portal</strong>.</li>
    <li><span class="step-num">2</span>Go to <strong>Symposiums → <?= htmlspecialchars($symposiumTitle) ?></strong>.</li>
    <li><span class="step-num">3</span>Browse the list of competitions and read the rules.</li>
    <li><span class="step-num">4</span>Click <strong>Register</strong> on your chosen event(s).</li>
</ol>

<div class="btn-wrap">
    <a href="<?= htmlspecialchars($baseUrl . '/student/symposiums') ?>" class="btn">
        🏆 &nbsp; Register Now
    </a>
</div>
