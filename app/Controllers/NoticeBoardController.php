<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SymposiumModel;
use App\Models\SymposiumEventModel;

final class NoticeBoardController extends BaseController
{
    private SymposiumModel $symposiumModel;
    private SymposiumEventModel $eventModel;

    public function __construct()
    {
        $this->symposiumModel = new SymposiumModel();
        $this->eventModel = new SymposiumEventModel();
    }

    public function index(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        if ($symposiumId <= 0) {
            $this->error('Invalid Symposium ID.');
            $this->redirect('/');
        }

        $symposium = $this->symposiumModel->findById($symposiumId);
        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/');
        }

        // Determine if user is coordinator/manager of this symposium
        $isManager = false;
        $user = $this->user();
        
        if (!empty($user)) {
            // Allow if Admin, HOD, or the creator of the symposium
            if (in_array($user['role'] ?? '', ['Administrator', 'Admin', 'HOD']) || (int)$symposium['created_by'] === (int)($user['user_id'] ?? 0)) {
                $isManager = true;
            }
        }

        // Public check — allow access once approved
        $publicStatuses = ['Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed'];
        if (!$isManager && !in_array($symposium['status'], $publicStatuses, true)) {
            $this->error('This symposium notice board is not yet available to the public.');
            $this->redirect('/');
        }

        $events = $this->eventModel->getBySymposium($symposiumId);

        $this->render('symposiums/notice_board', [
            'symposium' => $symposium,
            'events'    => $events,
            'isManager' => $isManager
        ], 'print');
    }
}
