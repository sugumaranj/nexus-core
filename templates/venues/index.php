<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/venues/
 * Description : Venue Listing Page
 *
 * Displays all venues with search and filter capabilities.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

$search = $search ?? '';
$status = $status ?? '';
?>

<div class="container-fluid">

    <!-- =============================================================== -->
    <!-- Page Header -->
    <!-- =============================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Venue Management</h2>
            <p class="text-muted mb-0">Create, update and manage venues and computer labs.</p>
        </div>
        <a href="<?= base_url() ?>/venues/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Venue
        </a>
    </div>

    <!-- =============================================================== -->
    <!-- Filter Card -->
    <!-- =============================================================== -->

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form action="<?= base_url() ?>/venues" method="GET" class="row g-3">
                <div class="col-md-5">
                    <label for="search" class="form-label text-muted small">Search Venues</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name, code or building..." 
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label text-muted small">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-dark w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <?php if ($search !== '' || $status !== ''): ?>
                        <a href="<?= base_url() ?>/venues" class="btn btn-outline-secondary w-100">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================================== -->
    <!-- Venue Table -->
    <!-- =============================================================== -->

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Venue Name</th>
                            <th>Building</th>
                            <th>Floor</th>
                            <th>Capacity</th>
                            <th>Lab</th>
                            <th>Status</th>
                            <th width="180">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($venues)) : ?>
                        <?php foreach ($venues as $venue) : ?>
                            <tr>
                                <td class="fw-bold">
                                    <?= htmlspecialchars($venue['venue_code'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($venue['venue_name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($venue['building_name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($venue['floor'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= (int) $venue['seating_capacity'] ?>
                                </td>
                                <td>
                                    <?php if ($venue['is_computer_lab']): ?>
                                        <span class="badge bg-info text-dark">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($venue['is_active']) : ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            Active
                                        </span>
                                    <?php else : ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="<?= base_url() ?>/venues/edit?id=<?= $venue['venue_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary"
                                           title="Edit Venue">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form action="<?= base_url() ?>/venues/delete" method="POST" 
                                              onsubmit="return confirm('Are you sure you want to delete this venue?');">
                                            <input type="hidden" name="venue_id" value="<?= $venue['venue_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Venue">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <div class="mb-2">
                                    <i class="bi bi-geo-alt fs-1 text-secondary opacity-50"></i>
                                </div>
                                No venues found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
