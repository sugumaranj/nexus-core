<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : student.php
 * Location    : templates/layouts/
 * Description : Student Portal master layout.
 *
 * Loads:
 *   • Bootstrap CSS and JS
 *   • Bootstrap Icons
 *   • Application CSS (variables.css, app.css)
 *   • Dashboard CSS (reused for consistent look)
 *   • Student portal top navbar
 *   • Page content
 *   • Footer
 *
 * Variables Expected
 * -------------------------------------------------------------------------
 * $pageTitle   : Browser tab title
 * $contentFile : Absolute path to the content view
 * $student     : Authenticated student session array
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
use App\Models\NotificationModel;

$pageTitle = $pageTitle ?? config('name') . ' — Student Portal';
$student   = $student ?? Session::get('student', []);

// Ensure CSRF token exists for the layout
$studentId = $student['student_id'] ?? 0;
// Deterministic token based on student ID prevents stale-tab mismatch
$csrfToken = substr(hash_hmac('sha256', (string)$studentId, 'NexusCore_CSRF_Salt_2026'), 0, 32);
Session::set('csrf_token', $csrfToken);


// Unread notification count for badge
$unreadCount = 0;
if (!empty($student['student_id'])) {
    try {
        $notificationModel = new NotificationModel();
        $unreadCount       = count($notificationModel->getUnreadForStudent((int) $student['student_id']));
    } catch (\Throwable $e) {
        $unreadCount = 0;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Basic Meta -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="base-url" content="<?= base_url() ?>">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/bootstrap.min.css') ?>">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?= asset('assets/icons/bootstrap-icons/font/bootstrap-icons.css') ?>">

    <!-- Application Styles -->
    <link rel="stylesheet" href="<?= asset('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/student-chatbot.css?v=6') ?>">

    <!-- Flatpickr CSS for Date Formatting (local — no CDN dependency) -->
    <link rel="stylesheet" href="<?= asset('assets/css/flatpickr.min.css') ?>">

</head>

<body class="dashboard-body">

<!-- =============================================================== -->
<!-- Preloader -->
<!-- =============================================================== -->
<style>
    #nexus-preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #f8f9fa; /* Matches dashboard body bg */
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
    }
    .nexus-loader {
        width: 64px;
        height: 64px;
        position: relative;
        animation: loaderPulse 1.2s ease-in-out infinite;
    }
    .nexus-loader::before, .nexus-loader::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        inset: 0;
        border: 4px solid transparent;
    }
    .nexus-loader::before {
        border-top-color: #0d6efd; /* Primary blue */
        border-bottom-color: #0d6efd;
        animation: loaderSpin 2s linear infinite;
    }
    .nexus-loader::after {
        border-left-color: #6610f2; /* Deep purple */
        border-right-color: #6610f2;
        animation: loaderSpin 1.5s linear infinite reverse;
    }
    @keyframes loaderSpin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes loaderPulse {
        0%, 100% { transform: scale(1); filter: drop-shadow(0 0 10px rgba(13, 110, 253, 0.4)); }
        50% { transform: scale(1.1); filter: drop-shadow(0 0 20px rgba(102, 16, 242, 0.6)); }
    }
    .preloader-hidden {
        opacity: 0;
        visibility: hidden;
    }
</style>
<div id="nexus-preloader">
    <div class="nexus-loader"></div>
</div>

<!-- ============================================================
     Student Portal Navbar
============================================================ -->

<nav class="navbar navbar-expand-lg navbar-dark bg-primary px-4 py-2 shadow" style="position:sticky;top:0;z-index:1050;">

    <!-- Brand -->
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= base_url() ?>/student/dashboard">
        <i class="bi bi-mortarboard-fill fs-4"></i>
        <span>NexusCore</span>
        <span class="badge bg-light text-primary ms-1" style="font-size:0.65rem;">Student Portal</span>
    </a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#studentNavbar">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="studentNavbar">

        <!-- Nav Links -->
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">

            <li class="nav-item">
                <a class="nav-link" href="<?= base_url() ?>/student/symposiums">
                    <i class="bi bi-calendar3 me-1"></i> Symposiums
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="<?= base_url() ?>/student/my-registrations">
                    <i class="bi bi-clipboard-check me-1"></i> My Registrations
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="<?= base_url() ?>/student/feedback">
                    <i class="bi bi-chat-square-text me-1"></i> Feedback
                </a>
            </li>

        </ul>

        <!-- Right Side -->
        <ul class="navbar-nav ms-auto align-items-center gap-2">

            <!-- Notifications -->
            <li class="nav-item">
                <a class="nav-link position-relative" href="<?= base_url() ?>/student/notifications">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                            <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- Student Name -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center gap-1"
                   href="#" role="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="d-none d-md-inline">
                        <?= htmlspecialchars($student['full_name'] ?? 'Student', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <span class="dropdown-item-text text-muted small">
                            <?= htmlspecialchars($student['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= base_url() ?>/student/logout">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </li>

        </ul>

    </div>

</nav>

<!-- ============================================================
     Main Content
============================================================ -->

<main class="dashboard-main" style="padding-top: 1.5rem;">

    <?php require $contentFile; ?>

</main>

<!-- ============================================================
     Bootstrap JS
============================================================ -->

<script src="<?= asset('assets/js/vendor/bootstrap.bundle.min.js') ?>"></script>

<!-- Flatpickr JS for Date Formatting (local — no CDN dependency) -->
<script src="<?= asset('assets/js/vendor/flatpickr.min.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr('input[type="date"]', {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            allowInput: true
        });

        flatpickr('input[type="datetime-local"]', {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "d/m/Y h:i K",
            allowInput: true
        });
    });
</script>

<!-- ============================================================
     Chatbot Widget
============================================================ -->
<div class="chatbot-trigger" id="chatbotTrigger">
    <i class="bi bi-chat-dots-fill"></i>
</div>

<div class="chatbot-panel" id="chatbotPanel">
    <div class="chatbot-header">
        <div class="chatbot-header-title">
            <i class="bi bi-robot"></i> NexusCore Assistant
        </div>
        <button class="chatbot-close" id="chatbotClose"><i class="bi bi-x-lg"></i></button>
    </div>
    
    <div class="chatbot-messages" id="chatbotMessages">
        <div class="typing-indicator" id="typingIndicator">
            <span></span><span></span><span></span>
        </div>
    </div>
    
    <div class="chatbot-suggestions">
        <button class="suggestion-chip">List active symposiums</button>
        <button class="suggestion-chip">What events are available?</button>
        <button class="suggestion-chip">My Registrations</button>
        <button class="suggestion-chip">When is the event schedule?</button>
        <button class="suggestion-chip">My registration status</button>
        <button class="suggestion-chip">Who are my team members?</button>
        <button class="suggestion-chip">Do I have notifications?</button>
        <button class="suggestion-chip">Help me navigate</button>
        <button class="suggestion-chip">Help</button>
    </div>
    
    <div class="chatbot-input-area">
        <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Ask about symposiums, events..." autocomplete="off">
        <button class="chatbot-send" id="chatbotSend">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>

<script src="<?= asset('assets/js/student-chatbot.js?v=4') ?>"></script>

<script>
    // Hide Preloader on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        const preloader = document.getElementById('nexus-preloader');
        if (preloader) {
            preloader.classList.add('preloader-hidden');
            setTimeout(() => { preloader.style.display = 'none'; }, 500);
        }
    });
</script>

</body>

</html>
