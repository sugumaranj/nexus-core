<?php
declare(strict_types=1);

$user = $user ?? [];
$event = $event ?? [];
$participants = $participants ?? [];
$givenMarks = $givenMarks ?? [];
$supportsAttendance = $supportsAttendance ?? false;
$attendanceLocked = $attendanceLocked ?? false;
$attendancePendingMsg = $attendancePendingMsg ?? '';
$windowClosesAt = $windowClosesAt ?? null;
$windowExpired = $windowExpired ?? false;
$hasFinalSubmission = $hasFinalSubmission ?? false;
$isLocked = $isLocked ?? false;

$eventId = (int)($event['symposium_event_id'] ?? 0);
$eventName = htmlspecialchars($event['event_name'] ?? '');
$maxScore = 100.00;
if (!empty($event['snapshot_evaluation'])) {
    $snap = json_decode($event['snapshot_evaluation'], true);
    $maxScore = (float)($snap['maximum_score'] ?? 100.00);
}
?>

<style>
.evaluate-hero {
    background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%);
    border-radius: 16px; color: #fff; padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(59,130,246,.25);
}
.marks-input {
    width: 100px !important;
    text-align: center;
}
.eval-table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
}
.eval-table td {
    vertical-align: middle;
}
.eval-table tbody tr:hover {
    background-color: #f1f5f9;
}
.timer-badge {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
}
</style>

<div class="evaluate-hero">
    <div class="row align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-1"><i class="bi bi-clipboard-check me-2"></i> Evaluate Participants</h2>
            <p class="mb-0 opacity-75">
                Event: <?= $eventName ?> 
                <span class="mx-2">&bull;</span> Judging Method: <?= htmlspecialchars($snap['judging_method'] ?? 'Marks') ?>
                <span class="mx-2">&bull;</span> Max Score: <?= number_format($maxScore, 2) ?>
            </p>
        </div>
        <div class="col-auto text-end">
            <a href="<?= base_url() ?>/judge/dashboard" class="btn btn-light rounded-pill px-4 btn-sm fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($supportsAttendance && !$attendanceLocked): ?>
    <div class="alert alert-warning mb-4 rounded-3 border-0 d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
        <div>
            <strong>Evaluation Locked!</strong><br>
            <?= htmlspecialchars($attendancePendingMsg) ?>
        </div>
    </div>
<?php elseif ($hasFinalSubmission): ?>
    <div class="alert alert-success mb-4 rounded-3 border-0 d-flex align-items-center">
        <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
        <div>
            <strong>Evaluation Completed!</strong><br>
            You have finalized your submission for this event. No further changes can be made.
        </div>
    </div>
<?php elseif ($windowExpired): ?>
    <div class="alert alert-danger mb-4 rounded-3 border-0 d-flex align-items-center">
        <i class="bi bi-clock-history fs-3 me-3 text-danger"></i>
        <div>
            <strong>Time Window Expired!</strong><br>
            The 42-hour evaluation window for this event has expired. You can no longer submit or change marks.
        </div>
    </div>
<?php elseif ($isLocked): ?>
    <div class="alert alert-secondary mb-4 rounded-3 border-0 d-flex align-items-center">
        <i class="bi bi-lock-fill fs-3 me-3 text-secondary"></i>
        <div>
            <strong>Evaluation Locked!</strong><br>
            The results for this event have already been published or locked by an administrator.
        </div>
    </div>
<?php elseif ($windowClosesAt): ?>
    <?php 
        $timeLeft = strtotime($windowClosesAt) - time();
        $hoursLeft = floor($timeLeft / 3600);
        $badgeClass = $hoursLeft < 12 ? 'bg-danger' : 'bg-warning text-dark';
    ?>
    <div class="alert alert-info mb-4 rounded-3 border-0 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle-fill fs-3 me-3 text-info"></i>
            <div>
                <strong>Evaluation is Open</strong><br>
                Please enter your marks. You can save as a draft and return later.
            </div>
        </div>
        <div class="timer-badge <?= $badgeClass ?> fw-bold shadow-sm">
            <i class="bi bi-stopwatch"></i> Window closes in <?= $hoursLeft ?> hours
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-4 mb-5">
    <div class="card-header bg-white pt-4 pb-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2" style="color:#3b82f6;"></i> Eligible Participants (<?= count($participants) ?>)</h5>
    </div>
    
    <div class="card-body p-0">
        <?php if (empty($participants)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                <p>No eligible participants found for evaluation.</p>
            </div>
        <?php else: ?>
            <form id="bulk-eval-form" onsubmit="return false;">
                <div class="table-responsive">
                    <table class="table eval-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 5%;">S.No</th>
                                <th style="width: 15%;">Reg. Number</th>
                                <th style="width: 35%;">Student Name</th>
                                <th class="text-center" style="width: 10%;">Year</th>
                                <th style="width: 20%;">Department</th>
                                <th class="text-center" style="width: 15%;">Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sno = 1;
                            foreach ($participants as $appId => $p): 
                                $markData = $givenMarks[$appId] ?? null;
                                $currentMark = $markData ? (float)$markData['mark'] : '';
                                
                                $resolved = $resolvedParticipants[$appId] ?? null;
                                $members = $resolved ? $resolved['members'] : [$p];
                                $rowspan = count($members);
                                
                                foreach ($members as $idx => $member):
                                    $yrRaw = (string)($member['academic_year'] ?? $member['student_year'] ?? $member['member_year'] ?? '');
                                    $yrFmt = $yrRaw;
                                    if ($yrRaw === '1') $yrFmt = 'I Year';
                                    elseif ($yrRaw === '2') $yrFmt = 'II Year';
                                    elseif ($yrRaw === '3') $yrFmt = 'III Year';
                                    elseif ($yrRaw === '4') $yrFmt = 'IV Year';
                            ?>
                                <tr>
                                    <?php if ($idx === 0): ?>
                                    <td class="text-center fw-bold text-muted" rowspan="<?= $rowspan ?>"><?= $sno++ ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($member['register_number'] ?? $member['member_reg_no'] ?? '') ?></span>
                                    </td>
                                    <td class="fw-semibold text-primary"><?= htmlspecialchars($member['name'] ?? $member['student_name'] ?? $member['member_name'] ?? 'Unknown') ?></td>
                                    <td class="text-center"><?= $yrFmt ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($member['department'] ?? $member['department_name'] ?? $member['member_dept'] ?? '') ?></small></td>
                                    <?php if ($idx === 0): ?>
                                    <td class="text-center" rowspan="<?= $rowspan ?>">
                                        <div class="d-flex justify-content-center">
                                            <input type="hidden" name="app_ids[]" value="<?= $appId ?>">
                                            <input type="number" step="0.01" min="0" max="<?= $maxScore ?>" 
                                                   name="marks[<?= $appId ?>]" 
                                                   class="form-control marks-input <?= $currentMark !== '' ? 'border-success bg-light' : '' ?>" 
                                                   placeholder="0-<?= $maxScore ?>" 
                                                   value="<?= $currentMark ?>" 
                                                   <?= $isLocked ? 'disabled' : '' ?>>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$isLocked): ?>
                    <div class="card-footer bg-light p-4 text-end rounded-bottom-4 border-top">
                        <button type="button" class="btn btn-outline-secondary px-4 me-2" onclick="submitBulkEvaluation('Draft')" id="btn-draft">
                            <i class="bi bi-save me-1"></i> Save Draft
                        </button>
                        <button type="button" class="btn btn-primary px-4 shadow-sm" onclick="confirmFinalSubmit()" id="btn-submit">
                            <i class="bi bi-check-circle-fill me-1"></i> Final Submit
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================= -->
<!-- Feedback Summary Section                                -->
<!-- ======================================================= -->
<div class="row mt-4">
    <div class="col-12">
        <?php if (!($feedbackFinalized ?? false)): ?>
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body text-center py-4">
                    <i class="bi bi-chat-square-text text-muted fs-2 d-block mb-2"></i>
                    <h6 class="fw-bold mb-1">Feedback Unavailable</h6>
                    <p class="text-muted small mb-0">Feedback data will be available after attendance is finalized.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-bottom py-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="bi bi-bar-chart-line me-2"></i>Event Feedback Summary
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php
                    $subData = [
                        'event'        => $event,
                        'summary'      => $feedbackSummary ?? [],
                        'feedbackList' => $feedbackList ?? [],
                        'hideHeader'   => true,
                    ];
                    extract($subData);
                    require dirname(__DIR__) . '/feedback/event_detail.php';
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Final Submit Confirmation Modal -->
<div class="modal fade" id="finalSubmitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-circle-fill me-2"></i>Confirm Final Submission</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center" id="finalSubmitModalBody">
                <i class="bi bi-lock-fill text-warning" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Are you sure?</h5>
                <p class="text-muted mb-0">Once you click Final Submit, your marks will be locked and you will not be able to change them.</p>
            </div>
            <div class="modal-footer border-0 bg-light justify-content-center">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" onclick="executeFinalSubmit()">Yes, Lock & Submit</button>
            </div>
        </div>
    </div>
</div>

<script>
let submitModal = null;
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('finalSubmitModal')) {
        submitModal = new bootstrap.Modal(document.getElementById('finalSubmitModal'));
    }
});

function confirmFinalSubmit() {
    let missingCount = 0;
    const inputs = document.querySelectorAll('.marks-input');
    inputs.forEach(input => {
        if (input.value.trim() === '') {
            missingCount++;
        }
    });

    const modalBody = document.getElementById('finalSubmitModalBody');
    if (missingCount > 0) {
        modalBody.innerHTML = `
            <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-danger">Missing Marks!</h5>
            <p class="text-muted mb-0">You have not entered marks for <b>${missingCount}</b> participant(s).</p>
            <p class="text-muted mt-2 mb-0">Are you absolutely sure you want to finalize your submission? You will not be able to change them later.</p>
        `;
    } else {
        modalBody.innerHTML = `
            <i class="bi bi-lock-fill text-warning" style="font-size: 3rem;"></i>
            <h5 class="mt-3">Are you sure?</h5>
            <p class="text-muted mb-0">Once you click Final Submit, your marks will be locked and you will not be able to change them.</p>
        `;
    }
    
    submitModal.show();
}

function executeFinalSubmit() {
    submitModal.hide();
    submitBulkEvaluation('Submitted');
}

async function submitBulkEvaluation(status) {
    const form = document.getElementById('bulk-eval-form');
    const btnDraft = document.getElementById('btn-draft');
    const btnSubmit = document.getElementById('btn-submit');
    
    if (btnDraft) btnDraft.disabled = true;
    if (btnSubmit) btnSubmit.disabled = true;
    
    let activeBtn = status === 'Submitted' ? btnSubmit : btnDraft;
    const originalText = activeBtn.innerHTML;
    activeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

    // Gather data
    const payload = {
        status: status,
        marks: []
    };

    const appIds = form.querySelectorAll('input[name="app_ids[]"]');
    appIds.forEach(input => {
        const appId = input.value;
        const markInput = form.querySelector(`input[name="marks[${appId}]"]`);
        if (markInput && markInput.value.trim() !== '') {
            payload.marks.push({
                application_id: parseInt(appId),
                mark: parseFloat(markInput.value),
                remarks: '' 
            });
        }
    });

    try {
        const response = await fetch('<?= base_url() ?>/judge/evaluate/bulk-submit?id=<?= $eventId ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();

        if (result.success) {
            window.nexusUI?.showToast(result.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            window.nexusUI?.showToast(result.message || 'An error occurred.', 'error');
            if (btnDraft) btnDraft.disabled = false;
            if (btnSubmit) btnSubmit.disabled = false;
            activeBtn.innerHTML = originalText;
        }
    } catch (err) {
        window.nexusUI?.showToast('Network error. Please try again.', 'error');
        if (btnDraft) btnDraft.disabled = false;
        if (btnSubmit) btnSubmit.disabled = false;
        activeBtn.innerHTML = originalText;
    }
}
</script>
