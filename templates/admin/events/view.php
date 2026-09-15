<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : view.php
 * Location    : templates/admin/events/
 * Description : Master Event Template Detail View.
 * -------------------------------------------------------------------------
 */

$event     = $event ?? [];
$rules     = $rules ?? [];

// Group rules by section
$groupedRules = [];
foreach ($rules as $r) {
    $sec = $r['section'] ?? 'General';
    $groupedRules[$sec][] = $r['rule_text'];
}

?>

<!-- Header -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($event['event_code']) ?></span>
                    <?php if ($event['category'] === 'Technical'): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Technical</span>
                    <?php else: ?>
                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill" style="background-color: #f3e8ff; color: #7e22ce;">Non-Technical</span>
                    <?php endif; ?>

                    <?php
                        $statusBadge = match($event['status']) {
                            'Published' => 'bg-success',
                            'Draft'     => 'bg-warning text-dark',
                            'Archived'  => 'bg-secondary',
                            default     => 'bg-info',
                        };
                    ?>
                    <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($event['status']) ?></span>

                </div>

                <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($event['event_name']) ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars($event['description'] ?: 'No description provided for this event template.') ?></p>
            </div>

            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="<?= base_url() ?>/admin/events/edit?id=<?= $event['event_id'] ?>" class="btn btn-primary btn-sm shadow-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Template
                </a>
                <button type="button" class="btn btn-outline-success btn-sm shadow-sm" 
                        onclick="openCloneModal(<?= $event['event_id'] ?>, '<?= htmlspecialchars(addslashes($event['event_name'])) ?>')">
                    <i class="bi bi-copy me-1"></i> Clone
                </button>
                <a href="<?= base_url() ?>/admin/events" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Library
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs border-bottom mb-4" id="eventTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
            <i class="bi bi-info-circle me-1"></i> Specifications
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="rules-tab" data-bs-toggle="tab" data-bs-target="#rules" type="button" role="tab">
            <i class="bi bi-card-checklist me-1"></i> Rules & Guidelines (<?= count($rules) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold" id="flags-tab" data-bs-toggle="tab" data-bs-target="#flags" type="button" role="tab">
            <i class="bi bi-toggle-on me-1"></i> Module Flags & Audit
        </button>
    </li>
</ul>

<!-- Tab Contents -->
<div class="tab-content" id="eventTabsContent">

    <!-- Tab 1: General Specifications -->
    <div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="row g-4">
            
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-gear me-2"></i>Format & Timing</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted fw-semibold" style="width: 40%;">Category:</td>
                                    <td class="fw-bold"><?= htmlspecialchars($event['category']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Participation:</td>
                                    <td>
                                        <span class="fw-bold"><?= htmlspecialchars($event['participation_type']) ?></span>
                                        <?php if ($event['participation_type'] !== 'Individual'): ?>
                                            <span class="badge bg-info-subtle text-info border ms-1">
                                                Min <?= (int)$event['min_team_size'] ?> - Max <?= (int)$event['max_team_size'] ?> Members
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Time Duration:</td>
                                    <td class="fw-bold">
                                        <?php
                                            $durType = $event['duration_type'] ?? 'minutes';
                                            if ($durType === 'minutes') {
                                                $mins = (int)($event['duration_minutes'] ?? 0);
                                                echo '<i class="bi bi-stopwatch text-primary me-1"></i>';
                                                echo '<strong>' . $mins . ' mins</strong>';
                                            } else {
                                                $st = $event['start_time'] ?? '';
                                                $et = $event['end_time'] ?? '';
                                                $dd = (int)($event['duration_days'] ?? 1);
                                                $fmt = function($t) {
                                                    if (!$t) return '';
                                                    [$h, $m] = explode(':', $t);
                                                    $h = (int)$h;
                                                    $h12 = $h % 12 ?: 12;
                                                    return "$h12:$m " . ($h < 12 ? 'AM' : 'PM');
                                                };
                                                if ($st && $et) {
                                                    [$sh,$sm] = array_map('intval', explode(':', $st));
                                                    [$eh,$em] = array_map('intval', explode(':', $et));
                                                    $totalMins = (($eh * 60 + $em) - ($sh * 60 + $sm)) * $dd;
                                                    echo '<i class="bi bi-calendar-range text-primary me-1"></i>';
                                                    echo '<strong>' . htmlspecialchars($fmt($st)) . ' – ' . htmlspecialchars($fmt($et)) . '</strong>';
                                                    echo ' &nbsp;·&nbsp; <span class="badge bg-primary">' . $dd . ' ' . ($dd > 1 ? 'Days' : 'Day') . '</span>';
                                                    if ($totalMins > 0) echo ' &nbsp;<span class="badge bg-light text-dark border">' . $totalMins . ' mins</span>';
                                                } else {
                                                    echo '<span class="text-muted">Not set</span>';
                                                }
                                            }
                                        ?>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-award me-2"></i>Prelims & Evaluation</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted fw-semibold" style="width: 40%;">Prelims Round:</td>
                                    <td>
                                        <?php if (!empty($event['requires_prelims'])): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-check-circle me-1"></i>Required</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Required</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Judging Method:</td>
                                    <td class="fw-bold"><?= htmlspecialchars($event['judging_method']) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Maximum Score:</td>
                                    <td class="fw-bold text-success fs-5"><?= number_format((float)$event['maximum_score'], 2) ?> Points</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Created By:</td>
                                    <td class="fw-bold"><?= htmlspecialchars($event['creator_name'] ?: 'System Admin') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Tab 2: Rules & Guidelines -->
    <div class="tab-pane fade" id="rules" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-card-checklist me-2"></i>Rules & Guidelines</h5>
                <span class="badge bg-primary-subtle text-primary border"><?= count($rules) ?> lines</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($groupedRules)): ?>
                    <p class="text-muted p-4 mb-0">No specific rules defined for this event template yet.</p>
                <?php else: ?>
                    <?php
                        // Detect if the stored rules are HTML (new WYSIWYG format) or plain text (legacy)
                        $allRuleText = implode('', array_merge(...array_values($groupedRules)));
                        $isHtmlContent = preg_match('/<[a-z][\s\S]*>/i', $allRuleText);
                    ?>
                    <?php foreach ($groupedRules as $secName => $ruleItems): ?>
                        <div class="border-bottom">
                            <?php if (count($groupedRules) > 1 && !$isHtmlContent): ?>
                                <div class="px-4 pt-3 pb-1 d-flex align-items-center gap-2">
                                    <span class="bg-primary rounded-circle flex-shrink-0" style="width:7px;height:7px;display:inline-block;"></span>
                                    <span class="fw-bold text-primary text-uppercase" style="font-size:0.72rem;letter-spacing:0.08em;"><?= htmlspecialchars($secName) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($isHtmlContent): ?>
                                <?php /* HTML content from WYSIWYG editor — render directly with safe tag allowlist */ ?>
                                <div class="px-4 py-3 rules-rendered-content" style="font-family: 'Segoe UI', system-ui, sans-serif; font-size: 0.93rem; line-height: 1.8; word-break: break-word; color: #212529;">
                                    <?= strip_tags(implode('', $ruleItems), '<b><i><u><s><strong><em><code><mark><div><span><br><p><ul><ol><li><blockquote>') ?>
                                </div>
                            <?php else: ?>
                                <?php /* Legacy plain-text format */ ?>
                                <div class="px-4 py-3" style="font-family: 'Segoe UI', system-ui, sans-serif; font-size: 0.93rem; line-height: 1.8; white-space: pre-wrap; tab-size: 4; -moz-tab-size: 4; word-break: break-word; color: #212529;"><?php
                                    echo htmlspecialchars(implode("\n", $ruleItems), ENT_QUOTES, 'UTF-8');
                                ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- Tab 4: Module Flags -->
    <div class="tab-pane fade" id="flags" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-toggle-on me-2"></i>Feature Integration Flags</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="p-3 border rounded text-center <?= !empty($event['supports_registration']) ? 'bg-success-subtle border-success' : 'bg-light' ?>">
                            <i class="bi bi-person-check display-6 <?= !empty($event['supports_registration']) ? 'text-success' : 'text-muted' ?>"></i>
                            <h6 class="fw-bold mt-2 mb-0">Registration</h6>
                            <small class="<?= !empty($event['supports_registration']) ? 'text-success' : 'text-muted' ?>">
                                <?= !empty($event['supports_registration']) ? 'Enabled' : 'Disabled' ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded text-center <?= !empty($event['supports_attendance']) ? 'bg-info-subtle border-info' : 'bg-light' ?>">
                            <i class="bi bi-calendar-check display-6 <?= !empty($event['supports_attendance']) ? 'text-info' : 'text-muted' ?>"></i>
                            <h6 class="fw-bold mt-2 mb-0">Attendance</h6>
                            <small class="<?= !empty($event['supports_attendance']) ? 'text-info' : 'text-muted' ?>">
                                <?= !empty($event['supports_attendance']) ? 'Enabled' : 'Disabled' ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded text-center <?= !empty($event['supports_evaluation']) ? 'bg-primary-subtle border-primary' : 'bg-light' ?>">
                            <i class="bi bi-award display-6 <?= !empty($event['supports_evaluation']) ? 'text-primary' : 'text-muted' ?>"></i>
                            <h6 class="fw-bold mt-2 mb-0">Evaluation</h6>
                            <small class="<?= !empty($event['supports_evaluation']) ? 'text-primary' : 'text-muted' ?>">
                                <?= !empty($event['supports_evaluation']) ? 'Enabled' : 'Disabled' ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 border rounded text-center <?= !empty($event['supports_certificates']) ? 'bg-warning-subtle border-warning' : 'bg-light' ?>">
                            <i class="bi bi-file-earmark-pdf display-6 <?= !empty($event['supports_certificates']) ? 'text-warning' : 'text-muted' ?>"></i>
                            <h6 class="fw-bold mt-2 mb-0">Certificates</h6>
                            <small class="<?= !empty($event['supports_certificates']) ? 'text-warning' : 'text-muted' ?>">
                                <?= !empty($event['supports_certificates']) ? 'Enabled' : 'Disabled' ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal: Clone Event Template -->
<div class="modal fade" id="cloneEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>/admin/events/clone">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-copy text-success me-2"></i>Clone Master Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="source_event_id" id="clone_source_id">
                    <p class="text-muted small mb-3">
                        Creating a duplicate copy of <strong id="clone_source_name"></strong>. Rules and resource requirements will be copied automatically.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Event Name <span class="text-danger">*</span></label>
                        <input type="text" name="new_event_name" id="clone_new_name" class="form-control" required placeholder="e.g. AI Coding Challenge">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-copy me-1"></i> Clone Template</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openCloneModal(id, name) {
    document.getElementById('clone_source_id').value = id;
    document.getElementById('clone_source_name').innerText = name;
    document.getElementById('clone_new_name').value = name + ' (Copy)';
    var modal = new bootstrap.Modal(document.getElementById('cloneEventModal'));
    modal.show();
}
</script>
