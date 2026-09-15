<?php declare(strict_types=1); ?>
<?php
/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : verify.php
 * Location    : templates/certificate/
 * View        : certificate.verify
 * Layout      : master (unauthenticated, public)
 * Description : Public certificate verification result page.
 *
 * Variables injected
 * -------------------------------------------------------------------------
 *   $result  — array from CertificateVerificationService::verify()
 *   $token   — sanitized token string (for display)
 *
 * Security
 * -------------------------------------------------------------------------
 *   All output is escaped with htmlspecialchars().
 *   No internal IDs, file paths, or private data appear anywhere.
 * -------------------------------------------------------------------------
 */

$status              = $result['status'] ?? 'NOT_FOUND';
$certificateNumber   = $result['certificate_number'] ?? null;
$recipientName       = $result['recipient_name'] ?? null;
$eventName           = $result['event_name'] ?? null;
$rankPosition        = $result['rank_position'] ?? null;
$resultStatus        = $result['result_status'] ?? null;
$generatedAt         = $result['generated_at'] ?? null;
$certHash            = $result['certificate_hash'] ?? null;
$fileHash            = $result['file_hash'] ?? null;
$certHashVerified    = $result['certificate_hash_verified'] ?? false;
$pdfHashVerified     = $result['pdf_hash_verified'] ?? false;

// Map status to UI configuration
$statusConfig = [
    'VERIFIED'             => ['color' => '#10b981', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'icon' => 'bi-shield-fill-check',     'title' => 'Certificate Verified',          'message' => 'This certificate is authentic. Both the certificate identity and the PDF document have been cryptographically verified.'],
    'HASH_MISMATCH'        => ['color' => '#ef4444', 'bg' => '#fef2f2', 'border' => '#fca5a5', 'icon' => 'bi-shield-fill-x',          'title' => 'Verification Failed',           'message' => 'The certificate data has been altered after issuance. This certificate cannot be trusted.'],
    'PDF_MISMATCH'         => ['color' => '#f59e0b', 'bg' => '#fffbeb', 'border' => '#fcd34d', 'icon' => 'bi-shield-exclamation',     'title' => 'Document Integrity Alert',      'message' => 'The certificate identity is valid, but the PDF document on record has been modified after issuance.'],
    'DOCUMENT_UNAVAILABLE' => ['color' => '#6366f1', 'bg' => '#eef2ff', 'border' => '#a5b4fc', 'icon' => 'bi-shield-fill-check',     'title' => 'Certificate Verified (PDF Unavailable)', 'message' => 'The certificate identity is valid. The certificate data has been verified, but the PDF file is currently unavailable.'],
    'SUPERSEDED'           => ['color' => '#8b5cf6', 'bg' => '#f5f3ff', 'border' => '#c4b5fd', 'icon' => 'bi-shield-fill-exclamation','title' => 'Certificate Superseded',        'message' => 'This certificate has been superseded by a regenerated certificate. Contact the issuing institution for the current certificate.'],
    'LEGACY'               => ['color' => '#6b7280', 'bg' => '#f9fafb', 'border' => '#d1d5db', 'icon' => 'bi-shield-minus',           'title' => 'Legacy Certificate',            'message' => 'This certificate was issued before the cryptographic verification system was deployed. Identity verification is not available.'],
    'NOT_FOUND'            => ['color' => '#ef4444', 'bg' => '#fef2f2', 'border' => '#fca5a5', 'icon' => 'bi-shield-fill-x',          'title' => 'Certificate Not Found',         'message' => 'No certificate was found for this verification code. Ensure you are scanning the original, unmodified certificate QR code.'],
];

$ui = $statusConfig[$status] ?? $statusConfig['NOT_FOUND'];

function esc(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $dt): string {
    if (!$dt) return '—';
    try {
        $d = new DateTimeImmutable($dt);
        return $d->format('d F Y, h:i A');
    } catch (\Throwable) {
        return esc($dt);
    }
}
?>

<style>
    :root {
        --status-color:  <?= $ui['color'] ?>;
        --status-bg:     <?= $ui['bg'] ?>;
        --status-border: <?= $ui['border'] ?>;
    }

    .verify-page {
        min-height: 100vh;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f172a 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .verify-card {
        background: #ffffff;
        border-radius: 1.5rem;
        box-shadow: 0 25px 50px rgba(0,0,0,0.35);
        width: 100%;
        max-width: 680px;
        overflow: hidden;
    }

    /* Header band */
    .verify-header {
        background: var(--status-bg);
        border-bottom: 2px solid var(--status-border);
        padding: 2.5rem 2rem;
        text-align: center;
    }

    .verify-icon {
        font-size: 3.5rem;
        color: var(--status-color);
        line-height: 1;
        margin-bottom: 0.75rem;
    }

    .verify-header h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--status-color);
        margin: 0 0 0.4rem;
    }

    .verify-header p {
        font-size: 0.95rem;
        color: #374151;
        margin: 0;
        line-height: 1.5;
    }

    /* NexusCore branding strip */
    .verify-brand {
        background: #0f172a;
        padding: 0.6rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        justify-content: center;
    }

    .verify-brand img {
        height: 20px;
        opacity: 0.7;
    }

    .verify-brand span {
        font-size: 0.78rem;
        color: #94a3b8;
        letter-spacing: 0.04em;
    }

    /* Certificate details table */
    .verify-body {
        padding: 2rem;
    }

    .verify-section-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #9ca3af;
        margin: 0 0 0.75rem;
    }

    .verify-field-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .verify-field-grid.single {
        grid-template-columns: 1fr;
    }

    .verify-field {
        background: #f8fafc;
        border-radius: 0.75rem;
        padding: 0.85rem 1rem;
        border: 1px solid #e2e8f0;
    }

    .verify-field label {
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        margin-bottom: 0.3rem;
    }

    .verify-field .val {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
        word-break: break-word;
    }

    .verify-field .val.rank-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    /* Hash integrity section */
    .verify-hashes {
        background: #f8fafc;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        padding: 1rem 1.2rem;
        margin-bottom: 1.5rem;
    }

    .hash-row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.6rem 0;
    }

    .hash-row + .hash-row {
        border-top: 1px solid #e2e8f0;
    }

    .hash-status-icon {
        font-size: 1.1rem;
        margin-top: 0.1rem;
        flex-shrink: 0;
    }

    .hash-status-icon.ok   { color: #10b981; }
    .hash-status-icon.warn { color: #f59e0b; }
    .hash-status-icon.fail { color: #ef4444; }
    .hash-status-icon.na   { color: #9ca3af; }

    .hash-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.15rem;
    }

    .hash-value {
        font-family: 'Courier New', monospace;
        font-size: 0.65rem;
        color: #64748b;
        word-break: break-all;
        line-height: 1.5;
    }

    /* Footer */
    .verify-footer {
        border-top: 1px solid #e2e8f0;
        padding: 1.25rem 2rem;
        text-align: center;
        background: #f8fafc;
    }

    .verify-footer p {
        font-size: 0.8rem;
        color: #94a3b8;
        margin: 0;
    }

    .verify-footer a {
        color: #6366f1;
        text-decoration: none;
    }

    @media (max-width: 540px) {
        .verify-field-grid {
            grid-template-columns: 1fr;
        }
        .verify-body { padding: 1.5rem 1.25rem; }
    }
</style>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

<div class="verify-page">
    <div class="verify-card">

        <!-- Brand strip -->
        <div class="verify-brand">
            <span><i class="bi bi-shield-lock-fill" style="color:#6366f1"></i> &nbsp; NexusCore EMS — Certificate Verification</span>
        </div>

        <!-- Status header -->
        <div class="verify-header">
            <div class="verify-icon"><i class="bi <?= esc($ui['icon']) ?>"></i></div>
            <h1><?= esc($ui['title']) ?></h1>
            <p><?= esc($ui['message']) ?></p>
        </div>

        <div class="verify-body">

            <?php if ($certificateNumber || $recipientName): ?>

            <!-- Certificate Details -->
            <p class="verify-section-title">Certificate Details</p>
            <div class="verify-field-grid">

                <?php if ($certificateNumber): ?>
                <div class="verify-field">
                    <label>Certificate No.</label>
                    <div class="val"><?= esc($certificateNumber) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($recipientName): ?>
                <div class="verify-field">
                    <label>Recipient</label>
                    <div class="val"><?= esc($recipientName) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($eventName): ?>
                <div class="verify-field">
                    <label>Event</label>
                    <div class="val"><?= esc($eventName) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($rankPosition): ?>
                <div class="verify-field">
                    <label>Rank</label>
                    <div class="val rank-badge">
                        <?php
                        $rankMedal = ['1' => '🥇', '2' => '🥈', '3' => '🥉'];
                        echo ($rankMedal[(string)$rankPosition] ?? '') . ' ' . esc($rankPosition);
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($resultStatus): ?>
                <div class="verify-field">
                    <label>Status</label>
                    <div class="val"><?= esc(ucfirst(strtolower($resultStatus))) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($generatedAt): ?>
                <div class="verify-field">
                    <label>Issued On</label>
                    <div class="val"><?= formatDate($generatedAt) ?></div>
                </div>
                <?php endif; ?>

            </div>

            <?php endif; ?>

            <!-- Cryptographic integrity section -->
            <?php if ($certHash || $fileHash || $status === 'HASH_MISMATCH' || $status === 'PDF_MISMATCH'): ?>
            <p class="verify-section-title" style="margin-top:0.5rem">Cryptographic Integrity</p>
            <div class="verify-hashes">

                <!-- Certificate hash row -->
                <div class="hash-row">
                    <?php
                    if ($certHashVerified)         { $iClass = 'ok';   $iIcon = 'bi-check-circle-fill'; }
                    elseif ($status === 'HASH_MISMATCH') { $iClass = 'fail'; $iIcon = 'bi-x-circle-fill'; }
                    elseif ($certHash)             { $iClass = 'na';   $iIcon = 'bi-dash-circle'; }
                    else                           { $iClass = 'na';   $iIcon = 'bi-dash-circle'; }
                    ?>
                    <div class="hash-status-icon <?= $iClass ?>"><i class="bi <?= $iIcon ?>"></i></div>
                    <div>
                        <div class="hash-label">
                            Certificate Identity Hash
                            <?php if ($certHashVerified): ?><span style="color:#10b981;font-weight:700"> — Verified</span><?php endif; ?>
                            <?php if ($status === 'HASH_MISMATCH'): ?><span style="color:#ef4444;font-weight:700"> — Mismatch</span><?php endif; ?>
                        </div>
                        <?php if ($certHash): ?>
                        <div class="hash-value"><?= esc($certHash) ?></div>
                        <?php else: ?>
                        <div class="hash-value" style="color:#9ca3af;font-style:italic">Not available for this certificate</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PDF hash row -->
                <div class="hash-row">
                    <?php
                    if ($pdfHashVerified)             { $iClass = 'ok';   $iIcon = 'bi-check-circle-fill'; }
                    elseif ($status === 'PDF_MISMATCH') { $iClass = 'warn'; $iIcon = 'bi-exclamation-circle-fill'; }
                    elseif ($status === 'DOCUMENT_UNAVAILABLE') { $iClass = 'na'; $iIcon = 'bi-dash-circle'; }
                    elseif ($fileHash)                { $iClass = 'na';   $iIcon = 'bi-dash-circle'; }
                    else                              { $iClass = 'na';   $iIcon = 'bi-dash-circle'; }
                    ?>
                    <div class="hash-status-icon <?= $iClass ?>"><i class="bi <?= $iIcon ?>"></i></div>
                    <div>
                        <div class="hash-label">
                            PDF Document Hash
                            <?php if ($pdfHashVerified): ?><span style="color:#10b981;font-weight:700"> — Verified</span><?php endif; ?>
                            <?php if ($status === 'PDF_MISMATCH'): ?><span style="color:#f59e0b;font-weight:700"> — Mismatch</span><?php endif; ?>
                            <?php if ($status === 'DOCUMENT_UNAVAILABLE'): ?><span style="color:#6366f1"> — File unavailable</span><?php endif; ?>
                        </div>
                        <?php if ($fileHash): ?>
                        <div class="hash-value"><?= esc($fileHash) ?></div>
                        <?php else: ?>
                        <div class="hash-value" style="color:#9ca3af;font-style:italic">Not available for this certificate</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endif; ?>

        </div><!-- /verify-body -->

        <div class="verify-footer">
            <p>
                Powered by <strong>NexusCore EMS</strong> &mdash;
                Certificates are issued by the department and carry cryptographic integrity verification.
                <?php if ($token): ?>
                <br><span style="font-size:0.72rem;color:#cbd5e1">Token: <?= esc(substr($token, 0, 8)) ?>…</span>
                <?php endif; ?>
            </p>
        </div>

    </div><!-- /verify-card -->
</div><!-- /verify-page -->
