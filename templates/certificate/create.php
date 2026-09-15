<?php

declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title ?? 'Upload Template', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="<?= base_url('/certificates') ?>" class="btn btn-secondary shadow-sm"><i class="bi bi-arrow-left"></i> Back to Library</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Template Details</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('/certificates/store') ?>" enctype="multipart/form-data">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="mb-3">
                            <label for="template_name" class="form-label fw-bold">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="template_name" name="template_name" required placeholder="e.g. CodeQuest Winner Certificate 2024">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label fw-bold">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Optional details about this template"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Rank Display Mode <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rank_display_mode" id="rank_checkboxes" value="checkboxes" required>
                                <label class="form-check-label" for="rank_checkboxes">
                                    <strong>Checkboxes</strong> - Displays separate checkboxes for 1st, 2nd, 3rd place.
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rank_display_mode" id="rank_label" value="label" required>
                                <label class="form-check-label" for="rank_label">
                                    <strong>Label</strong> - Displays the rank as a text label (e.g. "1st Place").
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rank_display_mode" id="rank_none" value="none" required checked>
                                <label class="form-check-label" for="rank_none">
                                    <strong>None</strong> - No rank displayed (useful for participation certificates).
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Team Certificate Mode <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="team_cert_mode" id="team_per_member" value="per_member" required checked>
                                <label class="form-check-label" for="team_per_member">
                                    <strong>Per Member</strong> - Generates a separate certificate for each team member.
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="team_cert_mode" id="team_per_team" value="per_team" required>
                                <label class="form-check-label" for="team_per_team">
                                    <strong>Per Team</strong> - Generates one certificate per team.
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="team_cert_mode" id="team_not_set" value="" required>
                                <label class="form-check-label" for="team_not_set">
                                    <strong>Not Set</strong> - Only for individual events.
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="pdf_file" class="form-label fw-bold">Template PDF File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
                            <div class="form-text">Upload a single-page PDF document. Max file size: 10 MB.</div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload & Continue</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow mb-4 bg-light border-primary">
                <div class="card-body">
                    <h5 class="card-title text-primary"><i class="bi bi-info-circle-fill"></i> Instructions</h5>
                    <p class="card-text">Upload your pre-designed certificate PDF. You'll configure field positions in the visual designer after upload.</p>
                    <ul>
                        <li>Ensure the PDF is a single page.</li>
                        <li>Leave blank spaces where dynamic text (names, ranks) should appear.</li>
                        <li>The system will overlay text onto the PDF you upload.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
