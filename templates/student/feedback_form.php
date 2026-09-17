<?php
/**
 * templates/student/feedback_form.php
 * 
 * @var string $pageTitle
 * @var array $event
 */
use App\Core\Session;
?>
<style>
/* CSS-only accessible star rating */
.star-rating-group {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.star-rating-group input {
    display: none;
}
.star-rating-group label {
    cursor: pointer;
    color: #dee2e6;
    transition: color 0.2s ease-in-out;
}
/* When a label is hovered, or when a sibling preceding it in the DOM (which is a higher rating due to flex-direction: row-reverse) is hovered, color it */
.star-rating-group label:hover,
.star-rating-group label:hover ~ label {
    color: #ffc107;
}
/* When an input is checked, color its label and all siblings following it (which are lower ratings) */
.star-rating-group input:checked ~ label {
    color: #ffc107;
}
</style>

<div class="container py-4">
    <div class="mb-4">
        <a href="<?= base_url() ?>/student/feedback" class="btn btn-sm btn-outline-secondary mb-3">
            <i class="bi bi-arrow-left me-1"></i> Back to Feedback
        </a>
        <h2 class="h4 fw-bold text-dark mb-1">Submit Feedback</h2>
        <p class="text-muted">Share your thoughts on <strong class="text-dark"><?= htmlspecialchars($event['event_name'] ?? '') ?></strong></p>
    </div>

    <div class="row">
        <div class="col-lg-8 max-w-lg mx-auto mx-lg-0">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 fs-6 fw-bold"><i class="bi bi-star-half me-2"></i>Event Evaluation</h5>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert alert-info py-2 d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-shield-check fs-4"></i>
                        <span class="small">Your submission is anonymous.</span>
                    </div>

                    <form action="<?= base_url() ?>/student/feedback/submit" method="POST" id="feedbackForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Session::get('csrf_token') ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="symposium_event_id" value="<?= (int)$event['symposium_event_id'] ?>">

                        <fieldset class="mb-4">
                            <legend class="form-label fw-bold mb-2 h6">Overall Rating <span class="text-danger">*</span></legend>
                            <div class="bg-light p-3 rounded text-center">
                                <div class="star-rating-group justify-content-center" role="radiogroup" aria-label="Rating">
                                    <input type="radio" name="rating" id="star5" value="5" required>
                                    <label for="star5" class="fs-1 px-1" aria-label="5 stars"><i class="bi bi-star-fill"></i></label>
                                    
                                    <input type="radio" name="rating" id="star4" value="4">
                                    <label for="star4" class="fs-1 px-1" aria-label="4 stars"><i class="bi bi-star-fill"></i></label>
                                    
                                    <input type="radio" name="rating" id="star3" value="3">
                                    <label for="star3" class="fs-1 px-1" aria-label="3 stars"><i class="bi bi-star-fill"></i></label>
                                    
                                    <input type="radio" name="rating" id="star2" value="2">
                                    <label for="star2" class="fs-1 px-1" aria-label="2 stars"><i class="bi bi-star-fill"></i></label>
                                    
                                    <input type="radio" name="rating" id="star1" value="1">
                                    <label for="star1" class="fs-1 px-1" aria-label="1 star"><i class="bi bi-star-fill"></i></label>
                                </div>
                                <div class="small text-muted mt-2">Click to select your rating</div>
                            </div>
                        </fieldset>

                        <div class="mb-4">
                            <label for="reviewText" class="form-label fw-bold h6">Review (Optional)</label>
                            <textarea name="review" id="reviewText" class="form-control" rows="5" maxlength="2000" placeholder="What did you like? What could be improved?"></textarea>
                            <div class="form-text text-end">Max 2000 characters</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="submitBtn" class="btn btn-primary py-2 fw-bold">
                                <i class="bi bi-send-fill me-1"></i> Submit Feedback Anonymously
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('feedbackForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
});
</script>
