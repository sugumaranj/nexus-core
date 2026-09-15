<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/student/symposiums') ?>">Symposiums</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>

    <?php if ($successMsg = \App\Core\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg = \App\Core\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <span class="badge bg-secondary"><?= htmlspecialchars($symposium['symposium_type'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <?php if ($registrationOpen): ?>
        <div class="alert alert-success d-flex align-items-center mb-4">
            <i class="bi bi-info-circle-fill me-2"></i>
            <div>
                <strong>Registration is Open!</strong> Closes on <?= \App\Helpers\DateHelper::dateTime($symposium['registration_end']) ?>.
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>
                <strong>Registration Closed.</strong> Registration window: <?= \App\Helpers\DateHelper::date($symposium['registration_start']) ?> - <?= \App\Helpers\DateHelper::date($symposium['registration_end']) ?>.
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($events)): ?>
        <div class="alert alert-info">No events found for this symposium.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($events as $event): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold mb-0 text-truncate" title="<?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>
                                <span class="badge bg-<?= $event['category'] === 'Technical' ? 'primary' : 'warning' ?>">
                                    <?= htmlspecialchars($event['category'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <?php
                                $pTypeBadge = 'secondary';
                                if ($event['participation_type'] === 'Individual') $pTypeBadge = 'info';
                                if ($event['participation_type'] === 'Team') $pTypeBadge = 'success';
                                ?>
                                <span class="badge bg-<?= $pTypeBadge ?>"><?= htmlspecialchars($event['participation_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($event['participation_type'] !== 'Individual'): ?>
                                    <small class="text-muted ms-1"><i class="bi bi-people"></i> <?= (int)$event['min_team_size'] ?>–<?= (int)$event['max_team_size'] ?> members</small>
                                <?php endif; ?>
                            </div>

                            <p class="card-text text-muted mb-3 flex-grow-1" style="font-size: 0.9rem;">
                                <?= htmlspecialchars(mb_strimwidth((string)$event['snapshot_instructions'], 0, 120, '...'), ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <?php if (!empty($event['snapshot_rules'])): ?>
                                <div class="mb-3">
                                    <button class="btn btn-sm btn-outline-secondary w-100" type="button" data-bs-toggle="modal" data-bs-target="#rulesModal-<?= $event['symposium_event_id'] ?>">
                                        <i class="bi bi-card-text"></i> View Rules
                                    </button>
                                </div>
                            <?php endif; ?>

                            <div class="mt-auto">
                                <?php if (isset($registeredEvents[$event['symposium_event_id']])): ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" disabled>
                                            <i class="bi bi-check-circle"></i> Registered
                                        </button>
                                        <a href="<?= base_url('/student/my-registrations') ?>" class="btn btn-outline-primary btn-sm">Go to My Registrations</a>
                                    </div>
                                <?php elseif ($registrationOpen && $event['supports_registration'] && in_array($event['status'], ['Published', 'Registration Open'])): ?>
                                    <a href="<?= base_url('/student/events/register?event_id=' . $event['symposium_event_id']) ?>" class="btn btn-primary w-100">
                                        Register Now
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>Registration Closed</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($events)): ?>
    <?php foreach ($events as $event): ?>
        <?php if (!empty($event['snapshot_rules'])): ?>
            <div class="modal fade" id="rulesModal-<?= $event['symposium_event_id'] ?>" tabindex="-1" aria-labelledby="rulesModalLabel-<?= $event['symposium_event_id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="rulesModalLabel-<?= $event['symposium_event_id'] ?>">Rules: <?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-start" style="font-size: 0.9rem;">
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
                                
                                foreach ($groupedRules as $section => $secRules): 
                                    if ($section !== 'General Rules' || count($groupedRules) > 1): ?>
                                        <h6 class="fw-bold mt-2 mb-2"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></h6>
                                    <?php endif; ?>
                                    
                                    <?php if ($isHtmlContent): ?>
                                        <div class="mb-3 rules-rendered-content" style="word-break: break-word;">
                                            <?= strip_tags(implode('', $secRules), '<b><i><u><s><strong><em><code><mark><div><span><br><p><ul><ol><li><blockquote>') ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3 text-muted">
                                            <?php foreach ($secRules as $ruleText): ?>
                                                <div class="mb-2"><?= htmlspecialchars($ruleText, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; 
                            endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
