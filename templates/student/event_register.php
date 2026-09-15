<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/student/symposiums') ?>">Symposiums</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('/student/symposiums/events?id=' . $event['symposium_id']) ?>">Events</a></li>
            <li class="breadcrumb-item active" aria-current="page">Register for <?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>

    <?php if ($errorMsg = \App\Core\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Section -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Registration Form</h5>
                </div>
                <div class="card-body">
                    <form action="<?= base_url('/student/events/register') ?>" method="post">
                        <input type="hidden" name="symposium_event_id" value="<?= htmlspecialchars((string)$event['symposium_event_id'], ENT_QUOTES, 'UTF-8') ?>">
                        
                        <h6 class="mb-3">Applicant Details</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted">Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Register Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($student['register_number'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Department</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($student['department_name'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted">Year</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string)academic_year_label($student['academic_year']), ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted">Semester</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string)$student['semester'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                            </div>
                        </div>

                        <?php if (in_array($event['participation_type'], ['Team', 'Both'])): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                You will be registering as a team. You can add members after registration from the <strong>My Registrations</strong> page.
                                <br><small>A team will be automatically created for you.</small>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4">

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="declaration" name="declaration" required>
                            <label class="form-check-label" for="declaration">
                                I confirm I am eligible and commit to participating on 
                                <strong><?= $event['event_date'] ? \App\Helpers\DateHelper::date($event['event_date']) : 'TBD' ?></strong> 
                                <?php if ($event['start_time'] && $event['end_time']): ?>
                                    at <strong><?= \App\Helpers\DateHelper::time($event['start_time']) ?>–<?= \App\Helpers\DateHelper::time($event['end_time']) ?></strong>
                                <?php endif; ?>.
                            </label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= base_url('/student/symposiums/events?id=' . $event['symposium_id']) ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Confirm Registration</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Event Summary Panel -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Event Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-muted d-block small">Event</span>
                        <h6 class="mb-0"><?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?> <small class="text-muted">(<?= htmlspecialchars($event['event_code'], ENT_QUOTES, 'UTF-8') ?>)</small></h6>
                    </div>
                    
                    <div class="mb-3">
                        <span class="badge bg-<?= $event['category'] === 'Technical' ? 'primary' : 'warning' ?> me-1">
                            <?= htmlspecialchars($event['category'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php
                        $pTypeBadge = 'secondary';
                        if ($event['participation_type'] === 'Individual') $pTypeBadge = 'info';
                        if ($event['participation_type'] === 'Team') $pTypeBadge = 'success';
                        ?>
                        <span class="badge bg-<?= $pTypeBadge ?>">
                            <?= htmlspecialchars($event['participation_type'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <?php if (in_array($event['participation_type'], ['Team', 'Both'])): ?>
                        <div class="mb-3">
                            <span class="text-muted d-block small"><i class="bi bi-people"></i> Team Size</span>
                            <span><?= (int)$event['min_team_size'] ?>–<?= (int)$event['max_team_size'] ?> members</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($event['event_date']): ?>
                    <div class="mb-3">
                        <span class="text-muted d-block small"><i class="bi bi-calendar-event"></i> Schedule</span>
                        <span><?= \App\Helpers\DateHelper::date($event['event_date']) ?></span><br>
                        <?php if ($event['start_time'] && $event['end_time']): ?>
                            <span class="small text-muted"><?= \App\Helpers\DateHelper::time($event['start_time']) ?> - <?= \App\Helpers\DateHelper::time($event['end_time']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($event['snapshot_instructions'])): ?>
                    <div class="mb-3">
                        <span class="text-muted d-block small">Description</span>
                        <p class="small mb-0"><?= htmlspecialchars($event['snapshot_instructions'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($event['snapshot_rules'])): ?>
                        <div class="accordion accordion-flush border-top pt-2" id="rulesAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed px-0 py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#rulesCollapse">
                                        <i class="bi bi-journal-text me-2"></i> Event Rules
                                    </button>
                                </h2>
                                <div id="rulesCollapse" class="accordion-collapse collapse" data-bs-parent="#rulesAccordion">
                                    <div class="accordion-body px-0 pt-2 pb-0 small">
                                        <?php 
                                        $rules = json_decode($event['snapshot_rules'], true);
                                        if (is_array($rules)):
                                            $groupedRules = [];
                                            foreach ($rules as $r) {
                                                $sec = !empty($r['section']) ? $r['section'] : 'General Rules';
                                                $text = $r['rule_text'] ?? ($r['rule'] ?? '');
                                                if ($text) $groupedRules[$sec][] = $text;
                                            }
                                            
                                            $allRuleText = implode('', array_merge(...array_values($groupedRules)));
                                            $isHtmlContent = preg_match('/<[a-z][\s\S]*>/i', $allRuleText);
                                            
                                            foreach ($groupedRules as $section => $secRules): ?>
                                                <?php if ($section !== 'General Rules' || count($groupedRules) > 1): ?>
                                                    <strong class="d-block mb-1"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php endif; ?>
                                                
                                                <?php if ($isHtmlContent): ?>
                                                    <div class="mb-2 rules-rendered-content" style="word-break: break-word;">
                                                        <?= strip_tags(implode('', $secRules), '<b><i><u><s><strong><em><code><mark><div><span><br><p><ul><ol><li><blockquote>') ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mb-2 text-muted">
                                                        <?php foreach ($secRules as $ruleText): ?>
                                                            <div class="mb-1"><?= htmlspecialchars($ruleText, ENT_QUOTES, 'UTF-8') ?></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; 
                                        endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
