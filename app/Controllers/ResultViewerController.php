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
        $db = \App\Database\Database::getConnection();
        
        $symposiumIdFilter = null;
        if (isset($_GET['symposium_id'])) {
            if ($_GET['symposium_id'] !== '') {
                $symposiumIdFilter = (int)$_GET['symposium_id'];
            }
            // If it's an empty string ("All Symposiums"), it remains null
        } else {
            // Default to the most recently created symposium that has locked events
            $latestStmt = $db->prepare("
                SELECT s.symposium_id
                FROM symposiums s
                JOIN symposium_events e ON s.symposium_id = e.symposium_id
                WHERE e.is_locked = 1
                ORDER BY s.created_at DESC
                LIMIT 1
            ");
            $latestStmt->execute();
            $latest = $latestStmt->fetch(\PDO::FETCH_ASSOC);
            if ($latest) {
                $symposiumIdFilter = (int)$latest['symposium_id'];
            }
        }

        // Fetch symposiums that have at least one locked event (for the filter dropdown)
        $symposiumStmt = $db->prepare("
            SELECT DISTINCT s.symposium_id, s.title
            FROM symposiums s
            JOIN symposium_events e ON s.symposium_id = e.symposium_id
            WHERE e.is_locked = 1
            ORDER BY s.title ASC
        ");
        $symposiumStmt->execute();
        $symposiums = $symposiumStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Base query for fetching events
        $query = "
            SELECT e.*, s.title as symposium_name
            FROM symposium_events e
            JOIN symposiums s ON e.symposium_id = s.symposium_id
            WHERE e.is_locked = 1
        ";
        $params = [];

        if ($symposiumIdFilter) {
            $query .= " AND e.symposium_id = :symposium_id";
            $params[':symposium_id'] = $symposiumIdFilter;
        }

        $query .= " ORDER BY e.event_date DESC, e.start_time DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $this->render('results.index', [
            'page_title' => 'Evaluation & Results',
            'events' => $events,
            'symposiums' => $symposiums,
            'selected_symposium_id' => $symposiumIdFilter
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
