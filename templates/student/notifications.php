<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : notifications.php
 * Location    : templates/student/
 * Description : Lists all notifications for the student.
 *
 * Variables:
 * - $notifications (array)
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-0">Notifications</h4>
            <p class="text-muted mb-0">Stay updated on your registration statuses and announcements.</p>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-5 text-center text-muted">
                <i class="bi bi-bell-slash fs-1 mb-3 d-block"></i>
                <h5>No Notifications</h5>
                <p class="mb-0">You have not received any notifications yet.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="list-group list-group-flush rounded">
                <?php foreach ($notifications as $n): ?>
                    <?php 
                        $isRead = !empty($n['read_at']) || ($n['delivery_status'] ?? '') === 'Sent';
                        $bgClass = $isRead ? 'bg-white' : 'bg-primary bg-opacity-10';
                        $iconColor = $isRead ? 'text-secondary' : 'text-primary';
                    ?>
                    <div class="list-group-item list-group-item-action <?= $bgClass ?> p-4 border-bottom">
                        <div class="d-flex gap-3">
                            <div class="mt-1">
                                <i class="bi bi-info-circle-fill fs-4 <?= $iconColor ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 <?= $isRead ? 'text-dark' : 'text-primary' ?>">
                                        <?= htmlspecialchars($n['notification_title'], ENT_QUOTES, 'UTF-8') ?>
                                    </h6>
                                    <small class="text-muted" title="<?= \App\Helpers\DateHelper::dateTime($n['created_at']) ?>">
                                        <?= \App\Helpers\DateHelper::dateTime($n['created_at']) ?>
                                    </small>
                                </div>
                                <p class="mb-0 <?= $isRead ? 'text-muted' : 'text-dark fw-medium' ?>">
                                    <?= nl2br(htmlspecialchars($n['notification_message'], ENT_QUOTES, 'UTF-8')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
