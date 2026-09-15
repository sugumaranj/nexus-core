<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\SymposiumEventModel;
use App\Models\EvaluationModel;

final class ResultViewerController extends BaseController
{
    private SymposiumEventModel $eventModel;
    private EvaluationModel $evalModel;

    public function __construct()
    {
        AuthMiddleware::handle();
        // Check if the user is a Staff Coordinator or higher. 
        // We'll allow any logged-in user with appropriate role (e.g., Staff Coordinator, HOD, Principal, Admin).
        $loggedInUser = \App\Core\Session::get('user', []);
        $role = $loggedInUser['role'] ?? '';
        $allowedRoles = ['Admin', 'Principal', 'HOD', 'Staff Coordinator'];
        
        if (!in_array($role, $allowedRoles)) {
            $this->error('Access Denied: You do not have permission to view evaluation results.');
            $this->redirect('/dashboard');
        }

        $this->eventModel = new SymposiumEventModel();
        $this->evalModel = new EvaluationModel();
    }

    /**
     * Dashboard listing all published events.
     */
    public function index(): void
    {
        // For a Staff Coordinator, they might want to see events in their department or all events depending on the rules.
        // For simplicity and transparency, we fetch all events that have been completed/published.
        // If we want to strictly filter by department, we can check $_SESSION['department_id'].
        // Let's fetch all events for now, since it's a global module.
        $db = \App\Database\Database::getConnection();
        
        // Fetch symposium events that are locked or completed
        $stmt = $db->prepare("
            SELECT e.*, s.title as symposium_name
            FROM symposium_events e
            JOIN symposiums s ON e.symposium_id = s.symposium_id
            WHERE e.is_locked = 1
            ORDER BY e.event_date DESC, e.start_time DESC
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->render('results.index', [
            'page_title' => 'Evaluation & Results',
            'events' => $events
        ], 'dashboard');
    }

    /**
     * Detailed view of a specific event's results.
     */
    public function viewEvent(): void
    {
        $eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($eventId <= 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/evaluation/results');
        }

        $event = $this->eventModel->findById($eventId);
        
        if (!$event) {
            $this->error('Event not found.');
            $this->redirect('/evaluation/results');
        }

        if (!(bool)$event['is_locked']) {
            $this->error('Results for this event have not been published yet.');
            $this->redirect('/evaluation/results');
        }

        $results = $this->evalModel->getPublishedResults($eventId);
        
        $resolver = new \App\Services\TeamResolverService();
        $resolvedParticipants = $resolver->resolveParticipantsForApplications(array_values($results));
        
        $engineSnapshot = [];
        if (!empty($results) && !empty($results[0]['statistical_snapshot'])) {
            $engineSnapshot = json_decode($results[0]['statistical_snapshot'], true) ?: [];
        }

        $snapshot = [];
        if (!empty($event['snapshot_evaluation'])) {
            $snapshot = json_decode($event['snapshot_evaluation'], true) ?: [];
        }

        $this->render('results.view_event', [
            'page_title' => 'Results: ' . htmlspecialchars($event['event_name']),
            'event' => $event,
            'results' => $results,
            'resolvedParticipants' => $resolvedParticipants,
            'snapshot' => $snapshot,
            'engineSnapshot' => $engineSnapshot
        ], 'dashboard');
    }
}
