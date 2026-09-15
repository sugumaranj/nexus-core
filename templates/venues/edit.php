<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : edit.php
 * Location    : templates/venues/
 * Description : Edit Venue Page
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

$errors = $errors ?? [];
$venue = $venue ?? [];

?>

<div class="container-fluid">

    <!-- ================================================================ -->
    <!-- Page Header -->
    <!-- ================================================================ -->

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Edit Venue</h2>
            <p class="text-muted mb-0">Modify venue details.</p>
        </div>
        <a href="<?= base_url() ?>/venues" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- Error Message -->
    <!-- ================================================================ -->

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- Venue Form -->
    <!-- ================================================================ -->

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="<?= base_url() ?>/venues/update" method="POST">
                <input type="hidden" name="venue_id" value="<?= htmlspecialchars((string) ($venue['venue_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <div class="row">
                    <!-- Venue Code -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Venue Code</label>
                        <input type="text" name="venue_code" 
                               class="form-control <?= isset($errors['venue_code']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($venue['venue_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Example: LAB1" maxlength="20">
                        <?php if (isset($errors['venue_code'])) : ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['venue_code']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Venue Name -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Venue Name</label>
                        <input type="text" name="venue_name" 
                               class="form-control <?= isset($errors['venue_name']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($venue['venue_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Example: Main Computer Lab" maxlength="120">
                        <?php if (isset($errors['venue_name'])) : ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['venue_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Building Name -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Building Name</label>
                        <input type="text" name="building_name" 
                               class="form-control <?= isset($errors['building_name']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($venue['building_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Example: Block A" maxlength="100">
                        <?php if (isset($errors['building_name'])) : ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['building_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Floor -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Floor (Optional)</label>
                        <input type="text" name="floor" 
                               class="form-control <?= isset($errors['floor']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($venue['floor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Example: Ground Floor" maxlength="50">
                        <?php if (isset($errors['floor'])) : ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['floor']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Seating Capacity -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Seating Capacity</label>
                        <input type="number" name="seating_capacity" 
                               class="form-control <?= isset($errors['seating_capacity']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars((string) ($venue['seating_capacity'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                               min="0">
                        <?php if (isset($errors['seating_capacity'])) : ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['seating_capacity']) ?></div>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="row mt-3">
                    <!-- Is Computer Lab -->
                    <div class="col-md-6 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_computer_lab" name="is_computer_lab" 
                                   <?= !empty($venue['is_computer_lab']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_computer_lab">Is Computer Lab</label>
                        </div>
                    </div>

                    <!-- Is Active -->
                    <div class="col-md-6 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" 
                                   <?= !empty($venue['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Status (Active)</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Update Venue
                    </button>
                    <a href="<?= base_url() ?>/venues" class="btn btn-outline-secondary ms-2">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>
