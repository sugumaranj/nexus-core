<?php declare(strict_types=1); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-award-fill text-primary me-2"></i> My Certificates
        </h1>
    </div>

    <?php if (empty($certificates)): ?>
    <div class="card shadow">
        <div class="card-body text-center py-5">
            <i class="bi bi-award fs-1 text-muted"></i>
            <p class="mt-3 text-muted">No certificates available yet.<br>
               Certificates will appear here once they are issued by the coordinator.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card shadow">
        <div class="card-header py-3 bg-light">
            <span class="fw-bold">Your Certificates (<?= count($certificates) ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Certificate #</th>
                            <th>Event</th>
                            <th>Symposium</th>
                            <th>Type</th>
                            <th>Achievement</th>
                            <th>Issued On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($certificates as $cert): ?>
                        <tr>
                            <td class="fw-semibold text-monospace small"><?= htmlspecialchars($cert['certificate_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($cert['event_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($cert['symposium_title'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if (($cert['certificate_type'] ?? '') === 'Winner'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-trophy-fill me-1"></i>Winner</span>
                                <?php elseif (($cert['certificate_type'] ?? '') === 'Participant'): ?>
                                    <span class="badge bg-primary"><i class="bi bi-person-check-fill me-1"></i>Participant</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Certificate</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($cert['display_label'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small"><?= htmlspecialchars(
                                !empty($cert['generated_at'])
                                    ? date('d M Y', strtotime($cert['generated_at']))
                                    : '-',
                                ENT_QUOTES, 'UTF-8'
                            ) ?></td>
                            <td>
                                <a href="<?= base_url('/student/certificates/view?cert_id=' . (int)$cert['certificate_id']) ?>"
                                   class="btn btn-sm btn-outline-secondary" target="_blank" title="View PDF">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="<?= base_url('/student/certificates/download?cert_id=' . (int)$cert['certificate_id']) ?>"
                                   class="btn btn-sm btn-outline-primary ms-1" title="Download PDF">
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <?php if (!empty($cert['verification_token'])): ?>
                                <a href="<?= base_url('/certificates/verify?token=' . urlencode($cert['verification_token'])) ?>"
                                   class="btn btn-sm btn-outline-success ms-1" target="_blank" title="Verify Certificate">
                                    <i class="bi bi-patch-check"></i> Verify
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
