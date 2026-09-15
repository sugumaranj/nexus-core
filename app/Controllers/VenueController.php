<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : VenueController.php
 * Location    : app/Controllers/
 * Description : Handles all Venue HTTP requests.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\VenueService;
use App\Validators\VenueValidator;

final class VenueController extends BaseController
{
    private VenueService $venueService;
    private VenueValidator $validator;

    public function __construct()
    {
        AuthMiddleware::handle();

        $this->venueService = new VenueService();
        $this->validator = new VenueValidator();
    }

    public function index(): void
    {
        // All authenticated users may view the venue list (read-only)
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator', 'HOD', 'Principal', 'Staff');

        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        if ($search !== '' || $status !== '') {
            $venues = $this->venueService->searchVenues($search, $status);
        } else {
            $venues = $this->venueService->getAllVenues();
        }

        $this->render('venues.index', [
            'pageTitle' => 'Venue Management',
            'venues'    => $venues,
            'search'    => $search,
            'status'    => $status
        ]);
    }

    public function create(): void
    {
        // Admin and Staff Coordinator can add new venues
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');

        $this->render('venues.create', [
            'pageTitle' => 'Add Venue'
        ]);
    }

    public function store(): void
    {
        // Admin and Staff Coordinator can save new venues
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');
        $errors = $this->validator->validate($_POST);

        if (!empty($errors)) {
            $this->render('venues.create', [
                'pageTitle' => 'Add Venue',
                'errors'    => $errors,
                'old'       => $_POST
            ]);
            return;
        }

        $data = [
            'venue_code'       => $_POST['venue_code'] ?? '',
            'venue_name'       => $_POST['venue_name'] ?? '',
            'building_name'    => $_POST['building_name'] ?? '',
            'floor'            => $_POST['floor'] ?? '',
            'seating_capacity' => (int) ($_POST['seating_capacity'] ?? 0),
            'is_computer_lab'  => isset($_POST['is_computer_lab']) ? 1 : 0,
            'is_active'        => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = $this->venueService->createVenue($data);

        if (!$result['success']) {
            $this->render('venues.create', [
                'pageTitle' => 'Add Venue',
                'error'     => $result['message'],
                'old'       => $_POST
            ]);
            return;
        }

        $this->success($result['message']);
        $this->redirect('/venues');
    }

    public function edit(): void
    {
        // Only Admin can edit/update venues
        RoleMiddleware::requireRole('Admin');

        $venueId = (int) ($_GET['id'] ?? 0);
        $venue = $this->venueService->getVenueById($venueId);

        if (!$venue) {
            $this->error('Venue not found.');
            $this->redirect('/venues');
        }

        $this->render('venues.edit', [
            'pageTitle' => 'Edit Venue',
            'venue'     => $venue
        ]);
    }

    public function update(): void
    {
        // Only Admin can update venues
        RoleMiddleware::requireRole('Admin');

        $venueId = (int) ($_POST['venue_id'] ?? 0);
        $venue = $this->venueService->getVenueById($venueId);

        if (!$venue) {
            $this->error('Venue not found.');
            $this->redirect('/venues');
        }

        $errors = $this->validator->validate($_POST);

        if (!empty($errors)) {
            $this->render('venues.edit', [
                'pageTitle' => 'Edit Venue',
                'errors'    => $errors,
                'venue'     => array_merge($venue, $_POST)
            ]);
            return;
        }

        $data = [
            'venue_code'       => $_POST['venue_code'] ?? '',
            'venue_name'       => $_POST['venue_name'] ?? '',
            'building_name'    => $_POST['building_name'] ?? '',
            'floor'            => $_POST['floor'] ?? '',
            'seating_capacity' => (int) ($_POST['seating_capacity'] ?? 0),
            'is_computer_lab'  => isset($_POST['is_computer_lab']) ? 1 : 0,
            'is_active'        => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = $this->venueService->updateVenue($venueId, $data);

        if (!$result['success']) {
            $this->render('venues.edit', [
                'pageTitle' => 'Edit Venue',
                'error'     => $result['message'],
                'venue'     => array_merge($venue, $_POST)
            ]);
            return;
        }

        $this->success($result['message']);
        $this->redirect('/venues');
    }

    public function delete(): void
    {
        // Only Admin can delete venues
        RoleMiddleware::requireRole('Admin');

        $venueId = (int) ($_POST['venue_id'] ?? 0);

        $result = $this->venueService->deleteVenue($venueId);

        if (!$result['success']) {
            $this->error($result['message']);
        } else {
            $this->success($result['message']);
        }

        $this->redirect('/venues');
    }
}
