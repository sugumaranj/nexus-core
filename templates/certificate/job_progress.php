<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS — Real-time Background Job Progress View
 * =========================================================================
 * Location: templates/certificate/job_progress.php
 * =========================================================================
 */

$status = $job['status'] ?? 'Pending';
$isFinished = in_array($status, ['Completed', 'Failed'], true);
$targetLabel = !empty($event_name) ? ('Event: ' . htmlspecialchars($event_name, ENT_QUOTES, 'UTF-8')) : (!empty($symposium_name) ? ('Symposium: ' . htmlspecialchars($symposium_name, ENT_QUOTES, 'UTF-8')) : 'Batch Job');
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="bi bi-gear-wide-connected text-primary me-2"></i><?= htmlspecialchars($page_title ?? 'Certificate Generation Progress', ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Job ID: <strong>#<?= (int)$job_id ?></strong> &bull; <?= $targetLabel ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('/certificates/generate') ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Generate Page
            </a>
            <a href="<?= base_url('/certificates/generated') ?>" class="btn btn-outline-primary btn-sm shadow-sm">
                <i class="bi bi-award me-1"></i> View All Certificates
            </a>
        </div>
    </div>

    <!-- Resilience Banner -->
    <div class="alert alert-info border-info shadow-sm d-flex align-items-center mb-4 p-3" role="alert">
        <i class="bi bi-shield-check display-6 me-3 text-info"></i>
        <div>
            <div class="fw-bold text-dark">Offline &amp; Background Resilient Generation</div>
            <div class="small text-muted">
                This process is executed by an independent server-side background worker (PHP 8.5). You can safely <strong>close this tab</strong> or disconnect from the internet &mdash; all certificates will finish generating on the server. You can return at any time to view reports and download certificates.
            </div>
        </div>
    </div>

    <!-- Main Progress Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
            <h6 class="m-0 fw-bold text-primary">
                <i class="bi bi-activity me-1"></i> Live Generation Status
            </h6>
            <span id="statusBadge" class="badge rounded-pill fs-6 px-3 py-2 <?= $status === 'Completed' ? 'bg-success' : ($status === 'Failed' ? 'bg-danger' : 'bg-primary') ?>">
                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <div class="card-body p-4">
            <!-- Progress Bar -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark">Overall Progress</span>
                    <span id="progressPercentText" class="fw-bold fs-5 text-primary"><?= (int)$progress ?>%</span>
                </div>
                <div class="progress" style="height: 28px; border-radius: 8px; background-color: #e9ecef;">
                    <div id="progressBar"
                         class="progress-bar <?= $status === 'Completed' ? 'bg-success' : ($status === 'Failed' ? 'bg-danger' : 'progress-bar-striped progress-bar-animated bg-primary') ?> fs-6 fw-bold"
                         role="progressbar"
                         style="width: <?= (int)$progress ?>%; min-width: 2em;"
                         aria-valuenow="<?= (int)$progress ?>"
                         aria-valuemin="0"
                         aria-valuemax="100">
                        <?= (int)$progress ?>%
                    </div>
                </div>
            </div>

            <!-- Detailed Metrics -->
            <div class="row g-3 text-center my-3">
                <div class="col-sm-4">
                    <div class="p-3 border rounded bg-light">
                        <div class="text-muted small text-uppercase fw-semibold">Processed Items</div>
                        <div id="metricProcessed" class="fs-4 fw-bold text-dark mt-1">
                            <?= (int)$job['processed_items'] ?>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-3 border rounded bg-light">
                        <div class="text-muted small text-uppercase fw-semibold">Total Expected</div>
                        <div id="metricTotal" class="fs-4 fw-bold text-dark mt-1">
                            <?= (int)$job['total_items'] ?>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="p-3 border rounded bg-light">
                        <div class="text-muted small text-uppercase fw-semibold">Worker State</div>
                        <div id="metricState" class="fs-4 fw-bold text-dark mt-1">
                            <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Network warning toast container -->
            <div id="networkWarning" class="alert alert-warning py-2 px-3 small d-none mt-3" role="alert">
                <i class="bi bi-wifi-off me-1"></i> Network connection temporarily unavailable. Generation continues unaffected on the server; reconnecting live display...
            </div>

            <!-- Completion Actions (Shown when Completed) -->
            <div id="completionContainer" class="<?= $status === 'Completed' ? '' : 'd-none' ?> mt-4 pt-3 border-top">
                <div class="alert alert-success d-flex align-items-center p-3 mb-3" role="alert">
                    <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
                    <div>
                        <div class="fw-bold">All Certificates Generated Successfully!</div>
                        <div class="small">PDF files have been created, digitally signed with verification tokens, and archived.</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a id="btnReport" href="<?= base_url('/certificates/report?job_id=' . (int)$job_id) ?>" class="btn btn-primary">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i> View Generation Report
                    </a>
                    <?php if (!empty($job['symposium_event_id'])): ?>
                        <a id="btnCerts" href="<?= base_url('/certificates/generated?event_id=' . (int)$job['symposium_event_id']) ?>" class="btn btn-success">
                            <i class="bi bi-award me-1"></i> View Certificates
                        </a>
                        <a id="btnZip" href="<?= base_url('/certificates/download/event-zip?event_id=' . (int)$job['symposium_event_id']) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-zip me-1"></i> Download Event ZIP
                        </a>
                    <?php elseif (!empty($job['symposium_id'])): ?>
                        <a id="btnCertsSymp" href="<?= base_url('/certificates/generated?symposium_id=' . (int)$job['symposium_id']) ?>" class="btn btn-success">
                            <i class="bi bi-award me-1"></i> View Certificates
                        </a>
                        <a id="btnZipSymp" href="<?= base_url('/certificates/download/symposium-package?symposium_id=' . (int)$job['symposium_id']) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-zip me-1"></i> Download Symposium ZIP
                        </a>
                    <?php else: ?>
                        <a href="<?= base_url('/certificates/generated') ?>" class="btn btn-success">
                            <i class="bi bi-award me-1"></i> View Certificates
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Failure Actions (Shown when Failed) -->
            <div id="failureContainer" class="<?= $status === 'Failed' ? '' : 'd-none' ?> mt-4 pt-3 border-top">
                <div class="alert alert-danger p-3 mb-3" role="alert">
                    <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Generation Job Encountered an Error</div>
                    <div id="failureErrorMessage" class="small mt-1"><?= htmlspecialchars((string)($job['error_message'] ?? 'An unexpected error occurred during execution.'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div>
                    <a href="<?= base_url('/certificates/generate' . (!empty($job['symposium_event_id']) ? ('?event_id=' . (int)$job['symposium_event_id']) : '')) ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Return &amp; Retry
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const jobId = <?= (int)$job_id ?>;
    const statusUrl = "<?= base_url('/certificates/job-status?job_id=' . (int)$job_id) ?>";
    let isFinished = <?= $isFinished ? 'true' : 'false' ?>;
    let pollInterval = null;

    const progressBar = document.getElementById('progressBar');
    const progressPercentText = document.getElementById('progressPercentText');
    const statusBadge = document.getElementById('statusBadge');
    const metricProcessed = document.getElementById('metricProcessed');
    const metricTotal = document.getElementById('metricTotal');
    const metricState = document.getElementById('metricState');
    const completionContainer = document.getElementById('completionContainer');
    const failureContainer = document.getElementById('failureContainer');
    const failureErrorMessage = document.getElementById('failureErrorMessage');
    const networkWarning = document.getElementById('networkWarning');

    async function pollJobStatus() {
        if (isFinished) {
            if (pollInterval) clearInterval(pollInterval);
            return;
        }

        try {
            const response = await fetch(statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                return;
            }

            networkWarning.classList.add('d-none');
            const data = await response.json();

            if (!data || !data.success) {
                return;
            }

            const pct = Math.max(0, Math.min(100, parseInt(data.progress, 10) || 0));
            progressBar.style.width = pct + '%';
            progressBar.setAttribute('aria-valuenow', pct);
            progressBar.textContent = pct + '%';
            progressPercentText.textContent = pct + '%';

            metricProcessed.textContent = data.processed || 0;
            metricTotal.textContent = data.total_items || 0;
            metricState.textContent = data.status || 'Processing';

            if (data.status === 'Completed') {
                isFinished = true;
                if (pollInterval) clearInterval(pollInterval);

                progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated', 'bg-primary');
                progressBar.classList.add('bg-success');
                statusBadge.className = 'badge rounded-pill fs-6 px-3 py-2 bg-success';
                statusBadge.textContent = 'Completed';

                completionContainer.classList.remove('d-none');
                failureContainer.classList.add('d-none');
            } else if (data.status === 'Failed') {
                isFinished = true;
                if (pollInterval) clearInterval(pollInterval);

                progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated', 'bg-primary');
                progressBar.classList.add('bg-danger');
                statusBadge.className = 'badge rounded-pill fs-6 px-3 py-2 bg-danger';
                statusBadge.textContent = 'Failed';

                if (data.error_message) {
                    failureErrorMessage.textContent = data.error_message;
                }
                failureContainer.classList.remove('d-none');
                completionContainer.classList.add('d-none');
            } else {
                statusBadge.className = 'badge rounded-pill fs-6 px-3 py-2 bg-primary';
                statusBadge.textContent = data.status || 'Processing';
            }
        } catch (err) {
            // Client network dropped temporarily; show non-intrusive banner
            networkWarning.classList.remove('d-none');
        }
    }

    if (!isFinished) {
        pollInterval = setInterval(pollJobStatus, 1500);
        // Initial immediate poll
        setTimeout(pollJobStatus, 300);
    }
})();
</script>
