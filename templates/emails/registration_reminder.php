<?php
/**
 * Email Template — Trigger 3: Registration Reminder (3 days before close)
 * Sent ONLY to students who have NOT yet registered.
 * Variables: $studentName, $symposiumTitle, $registrationEnd,
 *            $daysLeft, $baseUrl
 */
?>
<p class="salutation">Dear <?= htmlspecialchars($studentName ?? 'Student') ?>,</p>

<p>
    ⏰ This is a friendly reminder that registration for
    <strong><?= htmlspecialchars($symposiumTitle) ?></strong> is closing
    <span style="color:#bf360c;font-weight:700;">in just <?= (int)($daysLeft ?? 3) ?> day(s)</span>.
    You have <strong>not yet registered</strong> for any competition — don't miss out!
</p>

<!-- Countdown banner -->
<div style="background:linear-gradient(135deg,#f9a825,#ffd600);border-radius:10px;padding:20px;margin:20px 0;text-align:center;">
    <div style="font-size:13px;font-weight:700;color:#5d4037;text-transform:uppercase;letter-spacing:1px;">⚠ Registration Closes</div>
    <div style="font-size:26px;font-weight:800;color:#bf360c;margin:6px 0;"><?= htmlspecialchars($registrationEnd) ?></div>
    <div style="font-size:13px;color:#5d4037;font-weight:600;">Only <?= (int)($daysLeft ?? 3) ?> day(s) remaining!</div>
</div>

<p style="font-size:14px;color:#37474f;">
    Log in to the <strong>NexusCore Student Portal</strong> now, choose your competitions, and secure your spot!
</p>

<div class="btn-wrap">
    <a href="<?= htmlspecialchars($baseUrl . '/student/symposiums') ?>" class="btn"
       style="background:linear-gradient(135deg,#bf360c,#e64a19);">
        ⏰ &nbsp; Register Before It's Too Late
    </a>
</div>

<div class="notice">
    You are receiving this reminder because you are enrolled in an eligible department
    and have not yet registered for <strong><?= htmlspecialchars($symposiumTitle) ?></strong>.
</div>
