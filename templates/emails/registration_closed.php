<?php
/**
 * Email Template — Trigger 4: Registration Closed
 * Variables: $studentName, $symposiumTitle, $eventStartDate,
 *            $isRegistered, $competitionNames (string, comma-sep), $baseUrl
 */
?>
<p class="salutation">Dear <?= htmlspecialchars($studentName ?? 'Student') ?>,</p>

<?php if ($isRegistered ?? false): ?>
<p>
    ✅ Registration for <strong><?= htmlspecialchars($symposiumTitle) ?></strong> is now
    <span style="color:#1b5e20;font-weight:700;">closed</span>. You are successfully registered!
    Get ready to showcase your talent.
</p>

<div class="info-card">
    <div class="label">Your Registered Events</div>
    <div class="value" style="font-size:14px;"><?= htmlspecialchars($competitionNames ?? 'See portal for details') ?></div>
    <?php if (!empty($eventStartDate)): ?>
    <div style="margin-top:10px;">
        <div class="label">Event Date</div>
        <div class="value" style="font-size:14px;"><?= htmlspecialchars($eventStartDate) ?></div>
    </div>
    <?php endif; ?>
</div>

<p style="font-size:14px;color:#37474f;">
    Please check the Student Portal for your event schedule, venue details, and reporting time.
    Best of luck — we are rooting for you! 🎯
</p>

<div class="btn-wrap">
    <a href="<?= htmlspecialchars($baseUrl . '/student/my-registrations') ?>" class="btn">
        📋 &nbsp; View My Registrations
    </a>
</div>

<?php else: ?>
<p>
    Registration for <strong><?= htmlspecialchars($symposiumTitle) ?></strong> has now
    <strong>closed</strong>. Unfortunately, we did not receive a registration from you this time.
</p>

<p style="font-size:14px;color:#37474f;">
    We hope to see your enthusiastic participation in the next edition of <?= htmlspecialchars($symposiumTitle) ?>.
    Keep an eye on the Student Portal for upcoming events.
</p>
<?php endif; ?>
