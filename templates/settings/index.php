<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/settings/
 * Description : Settings Dashboard View
 * -------------------------------------------------------------------------
 */

$user        = $user ?? [];
$collegeLogo = $collegeLogo ?? null;
$eventLogo   = $eventLogo ?? null;
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url() ?>/dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Settings</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <div>
        <h2 class="fw-bold mb-1">System Settings</h2>
        <p class="text-muted mb-0">Manage global configurations and branding assets.</p>
    </div>
</div>

<div class="row g-4">
    
    <!-- College Logo Card -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-building me-2"></i>College Logo
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    This logo appears on the left side of official reports and participant registers.
                </p>
                
                <div class="mb-4 text-center p-4 rounded bg-light border border-dashed">
                    <?php if ($collegeLogo): ?>
                        <img src="<?= asset($collegeLogo) ?>" alt="College Logo" style="max-height: 120px; max-width: 100%; object-fit: contain;">
                    <?php else: ?>
                        <div class="text-muted d-flex flex-column align-items-center justify-content-center" style="height: 120px;">
                            <i class="bi bi-image fs-1 text-secondary mb-2"></i>
                            <span>No logo uploaded yet</span>
                        </div>
                    <?php endif; ?>
                </div>

                <form action="<?= base_url() ?>/settings/update" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="college_logo" class="form-label fw-semibold">Upload New Logo</label>
                        <input class="form-control" type="file" id="college_logo" name="college_logo" accept="image/png" required>
                        <div class="form-text">Allowed formats: PNG (Transparent background). Max size: 2MB.</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Update College Logo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Event Logo Card -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-flag me-2"></i>Nexus Event Logo
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    This logo represents the Nexus Symposium and appears on the right side of reports.
                </p>
                
                <div class="mb-4 text-center p-4 rounded bg-light border border-dashed">
                    <?php if ($eventLogo): ?>
                        <img src="<?= asset($eventLogo) ?>" alt="Event Logo" style="max-height: 120px; max-width: 100%; object-fit: contain;">
                    <?php else: ?>
                        <div class="text-muted d-flex flex-column align-items-center justify-content-center" style="height: 120px;">
                            <i class="bi bi-image fs-1 text-secondary mb-2"></i>
                            <span>No logo uploaded yet</span>
                        </div>
                    <?php endif; ?>
                </div>

                <form action="<?= base_url() ?>/settings/update" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="event_logo" class="form-label fw-semibold">Upload New Logo</label>
                        <input class="form-control" type="file" id="event_logo" name="event_logo" accept="image/png" required>
                        <div class="form-text">Allowed formats: PNG (Transparent background). Max size: 2MB.</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Update Event Logo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<?php $es = $emailSettings ?? []; $enabled = strtoupper($es['MAIL_ENABLED'] ?? 'NO') === 'YES'; ?>
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-envelope-at me-2"></i>Email / SMTP Configuration</h5>
                    <p class="text-muted small mb-0 mt-1">Set the SMTP server and the <strong>From Address</strong> visible to students.</p>
                </div>
                <span class="badge <?= $enabled ? 'bg-success' : 'bg-secondary' ?> fs-6">
                    <i class="bi bi-<?= $enabled ? 'check-circle' : 'pause-circle' ?> me-1"></i><?= $enabled ? 'Email Enabled' : 'Email Disabled' ?>
                </span>
            </div>
            <div class="card-body p-4">
                <form id="emailSettingsForm" action="<?= base_url() ?>/settings/email" method="POST">

                    <div class="mb-4 p-3 rounded bg-light border">
                        <div class="form-check form-switch d-flex align-items-center gap-3">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="mail_enabled_check" name="mail_enabled" value="YES"
                                   <?= $enabled ? 'checked' : '' ?> style="width:2.5em;height:1.4em;">
                            <label class="form-check-label fw-semibold" for="mail_enabled_check">
                                Enable Automated Email Notifications
                                <small class="text-muted d-block fw-normal">When disabled, no emails will be sent even if SMTP settings are configured.</small>
                            </label>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="mail_from_address" class="form-label fw-semibold">
                                <i class="bi bi-at text-primary me-1"></i>From Email Address
                                <span class="badge bg-warning text-dark ms-1 small">Visible to Students</span>
                            </label>
                            <input type="email" class="form-control" id="mail_from_address" name="mail_from_address"
                                   value="<?= htmlspecialchars($es['MAIL_FROM_ADDRESS'] ?? '') ?>" placeholder="nexus@gasc.edu">
                            <div class="form-text">This is the "From" address students see in their inbox.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="mail_from_name" class="form-label fw-semibold"><i class="bi bi-person-badge text-primary me-1"></i>From Display Name</label>
                            <input type="text" class="form-control" id="mail_from_name" name="mail_from_name"
                                   value="<?= htmlspecialchars($es['MAIL_FROM_NAME'] ?? 'Nexus Symposium') ?>" placeholder="Nexus Symposium — GASC">
                        </div>
                        <div class="col-md-5">
                            <label for="mail_host" class="form-label fw-semibold"><i class="bi bi-server text-primary me-1"></i>SMTP Host</label>
                            <input type="text" class="form-control" id="mail_host" name="mail_host"
                                   value="<?= htmlspecialchars($es['MAIL_HOST'] ?? 'smtp.gmail.com') ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-3">
                            <label for="mail_port" class="form-label fw-semibold"><i class="bi bi-hash text-primary me-1"></i>SMTP Port</label>
                            <select class="form-select" id="mail_port" name="mail_port">
                                <option value="587" <?= ($es['MAIL_PORT'] ?? '587') === '587' ? 'selected' : '' ?>>587 — TLS (recommended)</option>
                                <option value="465" <?= ($es['MAIL_PORT'] ?? '') === '465' ? 'selected' : '' ?>>465 — SSL</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="mail_encryption" class="form-label fw-semibold"><i class="bi bi-shield-lock text-primary me-1"></i>Encryption</label>
                            <select class="form-select" id="mail_encryption" name="mail_encryption">
                                <option value="tls" <?= strtolower($es['MAIL_ENCRYPTION'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                                <option value="ssl" <?= strtolower($es['MAIL_ENCRYPTION'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="mail_username" class="form-label fw-semibold"><i class="bi bi-person text-primary me-1"></i>SMTP Username</label>
                            <input type="email" class="form-control" id="mail_username" name="mail_username"
                                   value="<?= htmlspecialchars($es['MAIL_USERNAME'] ?? '') ?>" placeholder="your-email@gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label for="mail_password" class="form-label fw-semibold"><i class="bi bi-key text-primary me-1"></i>SMTP App Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="mail_password" name="mail_password"
                                       placeholder="<?= !empty($es['MAIL_PASSWORD']) ? '(saved — enter to change)' : 'Enter App Password' ?>">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="const f=document.getElementById('mail_password');f.type=f.type==='password'?'text':'password'">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Leave blank to keep existing password. Gmail: <a href="https://myaccount.google.com/apppasswords" target="_blank">Generate App Password ↗</a></div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4 pt-3 border-top flex-wrap align-items-center">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Email Configuration</button>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="email" id="testEmailAddr" class="form-control"
                                   placeholder="Test recipient email"
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" style="width:220px;">
                            <button type="button" class="btn btn-outline-info" onclick="sendTestEmail()">
                                <i class="bi bi-send me-2"></i>Send Test Email
                            </button>
                        </div>
                    </div>
                </form>
                <div id="testEmailResult" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
function sendTestEmail() {
    const addr   = document.getElementById('testEmailAddr').value.trim();
    const result = document.getElementById('testEmailResult');
    if (!addr) { alert('Enter a test recipient email first.'); return; }
    result.innerHTML = '<div class="alert alert-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Sending test email…</div>';
    result.style.display = 'block';
    fetch('<?= base_url() ?>/settings/email/test', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'test_email=' + encodeURIComponent(addr)
    }).then(r => r.json()).then(data => {
        result.innerHTML = '<div class="alert alert-' + (data.success ? 'success' : 'danger') + ' mb-0">'
            + '<i class="bi bi-' + (data.success ? 'check-circle-fill' : 'x-circle-fill') + ' me-2"></i>' + data.message + '</div>';
    }).catch(e => { result.innerHTML = '<div class="alert alert-danger mb-0">Request failed: ' + e + '</div>'; });
}
</script>
