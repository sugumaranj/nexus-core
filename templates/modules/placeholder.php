<?php

declare(strict_types=1);

$moduleTitle = $moduleTitle ?? 'Module';
$moduleDescription = $moduleDescription ?? 'This module is ready to be built.';
$moduleIcon = $moduleIcon ?? 'bi-grid';

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                <?= htmlspecialchars($moduleTitle, ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <p class="text-muted mb-0">
                <?= htmlspecialchars($moduleDescription, ENT_QUOTES, 'UTF-8') ?>
            </p>

        </div>

        <a
            href="<?= base_url() ?>/dashboard"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>

            Dashboard

        </a>

    </div>

    <div class="card shadow-sm border-0">

        <div class="card-body text-center py-5">

            <div class="module-placeholder-icon mx-auto mb-3">

                <i class="bi <?= htmlspecialchars($moduleIcon, ENT_QUOTES, 'UTF-8') ?>"></i>

            </div>

            <h4 class="fw-semibold mb-2">
                <?= htmlspecialchars($moduleTitle, ENT_QUOTES, 'UTF-8') ?> module connected
            </h4>

            <p class="text-muted mb-0">
                The route, sidebar link, and dashboard layout are working. Feature screens can now be added here.
            </p>

        </div>

    </div>

</div>
