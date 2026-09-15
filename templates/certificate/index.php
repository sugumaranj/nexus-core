<?php

declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title ?? 'Template Library', ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="d-flex gap-2">
            <a href="<?= base_url('/certificates/generated') ?>" class="btn btn-info text-white shadow-sm">
                <i class="bi bi-list-check"></i> View Generated Certificates
            </a>
            <a href="<?= base_url('/certificates/create') ?>" class="btn btn-primary shadow-sm">
                <i class="bi bi-upload"></i> Upload New Template
            </a>
        </div>
    </div>

    <?php if (empty($templates)): ?>
        <div class="card shadow mb-4 text-center py-5">
            <div class="card-body">
                <i class="bi bi-file-earmark-pdf display-1 text-muted mb-3"></i>
                <h4 class="text-secondary">No Templates Found</h4>
                <p class="text-muted mb-4">You haven't uploaded any certificate templates yet.</p>
                <a href="<?= base_url('/certificates/create') ?>" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Upload Your First Template
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($templates as $template): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-body">
                            <h5 class="card-title fw-bold">
                                <?= htmlspecialchars($template['template_name'], ENT_QUOTES, 'UTF-8') ?>
                            </h5>
                            
                            <div class="mb-2">
                                <?php if (!empty($template['is_archived'])): ?>
                                    <span class="badge bg-secondary">Archived</span>
                                <?php elseif (!empty($template['is_active'])): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>

                                <?php
                                $rankModeLabel = match($template['rank_display_mode'] ?? '') {
                                    'checkboxes' => 'Checkboxes',
                                    'label' => 'Label',
                                    'none' => 'None',
                                    default => 'Not Set'
                                };
                                ?>
                                <span class="badge bg-info text-dark">Rank: <?= htmlspecialchars($rankModeLabel, ENT_QUOTES, 'UTF-8') ?></span>

                                <?php if (!empty($template['team_cert_mode'])): ?>
                                    <span class="badge bg-warning text-dark">Team: <?= htmlspecialchars($template['team_cert_mode'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>

                            <p class="card-text small text-muted mb-1">
                                <strong>Orientation:</strong> <?= htmlspecialchars($template['page_orientation'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p class="card-text small text-muted mb-3">
                                <strong>Created:</strong> <?= htmlspecialchars($template['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                                <strong>Versions:</strong> <?= htmlspecialchars((string)($template['version_count'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= base_url('/certificates/designer?template_id=' . urlencode((string)$template['certificate_template_id'])) ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-palette2"></i> Designer
                                </a>
                                <a href="<?= base_url('/certificates/preview?template_id=' . urlencode((string)$template['certificate_template_id'])) ?>" class="btn btn-sm btn-light border">
                                    <i class="bi bi-eye"></i> Preview
                                </a>
                                <a href="<?= base_url('/certificates/generate?template_id=' . urlencode((string)$template['certificate_template_id'])) ?>" class="btn btn-sm btn-success">
                                    <i class="bi bi-play-circle"></i> Generate
                                </a>
                            </div>
                        </div>
                        <div class="card-footer bg-light border-top-0 py-2 d-flex justify-content-between align-items-center">
                            <form method="post" action="<?= base_url(empty($template['is_active']) ? '/certificates/template/activate' : '/certificates/template/deactivate') ?>" class="d-inline m-0 p-0">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="template_id" value="<?= htmlspecialchars((string)$template['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn-sm btn-link text-decoration-none">
                                    <?= !empty($template['is_active']) ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <div class="d-flex gap-2">
                                <?php if (empty($template['is_archived'])): ?>
                                    <form method="post" action="<?= base_url('/certificates/template/archive') ?>" class="d-inline m-0 p-0">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="template_id" value="<?= htmlspecialchars((string)$template['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-muted" title="Archive"><i class="bi bi-archive"></i></button>
                                    </form>
                                    <form method="post" action="<?= base_url('/certificates/template/delete') ?>" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="template_id" value="<?= htmlspecialchars((string)$template['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= base_url('/certificates/template/unarchive') ?>" class="d-inline m-0 p-0">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="template_id" value="<?= htmlspecialchars((string)$template['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-warning" title="Unarchive"><i class="bi bi-box-arrow-up"></i></button>
                                    </form>
                                    <form method="post" action="<?= base_url('/certificates/template/delete') ?>" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this archived template?');">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="template_id" value="<?= htmlspecialchars((string)$template['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= base_url('/certificates/template/duplicate') ?>" class="d-inline m-0 p-0">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="template_id" value="<?= htmlspecialchars((string)$template['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-secondary" title="Duplicate"><i class="bi bi-files"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
