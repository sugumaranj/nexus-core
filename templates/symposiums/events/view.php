<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : view.php
 * Location    : templates/symposiums/events/
 * Description : View Scheduled Event Details & Frozen Snapshot Rules.
 * -------------------------------------------------------------------------
 */

$event         = $event ?? [];
$snapshotRules = $snapshotRules ?? [];
$snapshotEval  = $snapshotEval ?? [];
$canManage     = $canManage ?? false;

// Group snapshot rules by section
$groupedRules = [];
foreach ($snapshotRules as $r) {
    $sec = $r['section'] ?? 'General';
    $groupedRules[$sec][] = $r['rule_text'];
}

?>

<!-- Header -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2 small">
                        <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums">Symposiums</a></li>
                        <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $event['symposium_id'] ?>"><?= htmlspecialchars($event['symposium_title']) ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['event_code']) ?></li>
                    </ol>
                </nav>

                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($event['event_code']) ?></span>
                    <?php if ($event['category'] === 'Technical'): ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Technical</span>
                    <?php else: ?>
                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill" style="background-color: #f3e8ff; color: #7e22ce;">Non-Technical</span>
                    <?php endif; ?>

                    <?php
                        $statusBadge = match($event['status']) {
                            'Scheduled'          => 'bg-info text-dark',
                            'Registration Open'  => 'bg-success',
                            'Registration Closed'=> 'bg-warning text-dark',
                            'Running'            => 'bg-primary',
                            'Completed'          => 'bg-secondary',
                            'Cancelled'          => 'bg-danger',
                            default              => 'bg-secondary',
                        };
                    ?>
                    <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($event['status']) ?></span>
                </div>

                <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($event['event_name']) ?></h2>
                <p class="text-muted mb-0">Part of <strong><?= htmlspecialchars($event['symposium_title']) ?></strong> (<?= htmlspecialchars($event['symposium_code']) ?>)</p>
            </div>

            <div class="mt-3 mt-md-0 d-flex gap-2">
                <?php if ($canManage): ?>
                    <a href="<?= base_url() ?>/symposiums/events/edit?id=<?= $event['symposium_event_id'] ?>" class="btn btn-primary btn-sm shadow-sm">
                        <i class="bi bi-pencil me-1"></i> Edit Schedule
                    </a>
                <?php endif; ?>
                <a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $event['symposium_id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Schedule
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    
    <!-- Left Column -->
    <div class="col-lg-8">
        
        <!-- Frozen Snapshot Rules (Req #12 Snapshot Integrity) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-snowflake text-info me-2"></i>Frozen Snapshot Rules & Guidelines
                </h5>
                <span class="badge bg-info-subtle text-info border">
                    <i class="bi bi-shield-check me-1"></i>Historical Integrity Protected
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    These rules were frozen when this event was attached to the symposium. Future edits to the Master Event Library will not alter this snapshot.
                </p>

                <?php if (empty($groupedRules)): ?>
                    <p class="text-muted mb-0">No specific rules snapshotted for this event.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($groupedRules as $secName => $ruleItems): ?>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-primary mb-2">
                                        <i class="bi bi-bookmark-fill me-1"></i><?= htmlspecialchars($secName) ?>
                                    </h6>
                                    <div class="small" style="white-space: pre-wrap; tab-size: 4; -moz-tab-size: 4; line-height: 1.6; word-break: break-word; color: #495057;"><?php
                                        $lines = array_map(function($line) {
                                            return strip_tags($line, '<b><i><u><s><strong><em><code><mark><div><span><br><p><ul><ol><li><blockquote>');
                                        }, $ruleItems);
                                        echo implode("\n", $lines);
                                    ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($event['custom_notes'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-sticky me-2"></i>Symposium Notes</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($event['custom_notes'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        
        <!-- Schedule Info Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar3 me-2"></i>Schedule Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless align-middle mb-0 small">
                    <tbody>
                        <tr>
                            <td class="text-muted fw-semibold">Event Date:</td>
                            <td class="fw-bold"><?= !empty($event['event_date']) ? \App\Helpers\DateHelper::date($event['event_date']) : '<span class="badge bg-secondary">TBA</span>' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Timings:</td>
                            <td class="fw-bold">
                                <?= !empty($event['start_time']) && !empty($event['end_time']) 
                                    ? \App\Helpers\DateHelper::time($event['start_time']) . ' - ' . \App\Helpers\DateHelper::time($event['end_time']) 
                                    : '<span class="badge bg-secondary">TBA</span>' ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Reporting Time:</td>
                            <td class="fw-bold text-primary"><?= !empty($event['reporting_time']) ? \App\Helpers\DateHelper::time($event['reporting_time']) : '<span class="badge bg-secondary">TBA</span>' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Session:</td>
                            <td class="fw-bold"><?= htmlspecialchars((string)($event['session'] ?? 'TBA')) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Venue:</td>
                            <td class="fw-bold text-dark"><i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars((string)($event['venue_name'] ?? 'TBA')) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Faculty In-Charge:</td>
                            <td class="fw-bold">
                                <?php if (!empty($facultyList)): ?>
                                    <?php foreach ($facultyList as $idx => $f): ?>
                                        <?= htmlspecialchars($f['full_name']) ?><?= $idx < count($facultyList) - 1 ? '<br>' : '' ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Unassigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-semibold">Judges:</td>
                            <td class="fw-bold">
                                <?php if (!empty($judgeList)): ?>
                                    <?php foreach ($judgeList as $idx => $j): ?>
                                        <?= htmlspecialchars($j['full_name']) ?><?= $idx < count($judgeList) - 1 ? '<br>' : '' ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Unassigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Prelims & Stages Card -->
        <?php if (($event['prelim_decision'] ?? 'Pending') === 'Required'): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-list-task text-warning me-2"></i>Prelims & Stages
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($stages)): ?>
                        <div class="p-3 text-center text-muted small">
                            <i class="bi bi-exclamation-circle text-warning me-1"></i> Stages are required but have not been configured yet.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush small">
                            <?php foreach ($stages as $stage): ?>
                                <div class="list-group-item px-3 py-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-dark">
                                            <?= htmlspecialchars(!empty($stage['custom_name']) ? $stage['custom_name'] : $stage['stage_name']) ?>
                                        </strong>
                                        <span class="badge bg-light text-muted border">Stage <?= (int)$stage['stage_order'] ?></span>
                                    </div>
                                    <div class="d-flex flex-column gap-1 text-muted" style="font-size: 0.8rem;">
                                        <div><i class="bi bi-calendar2-event me-2"></i><?= !empty($stage['stage_date']) ? \App\Helpers\DateHelper::date($stage['stage_date']) : 'Date TBA' ?></div>
                                        <div><i class="bi bi-clock me-2"></i><?= !empty($stage['start_time']) && !empty($stage['end_time']) ? \App\Helpers\DateHelper::time($stage['start_time']) . ' - ' . \App\Helpers\DateHelper::time($stage['end_time']) : 'Time TBA' ?></div>
                                        <?php if (!empty($stage['venue_id'])): ?>
                                            <div><i class="bi bi-geo-alt me-2 text-danger"></i>Venue ID: <?= (int)$stage['venue_id'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Registration Window Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i>Registration Window</h5>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted d-block">Start Date & Time:</small>
                    <strong class="text-dark"><?= !empty($event['registration_start']) ? \App\Helpers\DateHelper::dateTime($event['registration_start']) : 'TBA' ?></strong>
                </div>
                <div>
                    <small class="text-muted d-block">Deadline:</small>
                    <strong class="text-danger"><?= !empty($event['registration_end']) ? \App\Helpers\DateHelper::dateTime($event['registration_end']) : 'TBA' ?></strong>
                </div>
            </div>
        </div>

    </div>

</div>
