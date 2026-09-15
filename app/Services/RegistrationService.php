<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\ApplicationModel;
use App\Models\AuditLogModel;
use App\Models\NotificationModel;
use App\Models\StudentModel;
use App\Models\SymposiumDepartmentModel;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Models\TeamMemberModel;
use App\Models\TeamModel;
use PDO;
use Throwable;

/**
 * Service class for handling event registrations.
 */
class RegistrationService
{
    /** @var ApplicationModel */
    private ApplicationModel $appModel;
    
    /** @var TeamModel */
    private TeamModel $teamModel;
    
    /** @var TeamMemberModel */
    private TeamMemberModel $memberModel;
    
    /** @var SymposiumEventModel */
    private SymposiumEventModel $eventModel;
    
    /** @var SymposiumModel */
    private SymposiumModel $sympModel;
    
    /** @var StudentModel */
    private StudentModel $studentModel;
    
    /** @var NotificationModel */
    private NotificationModel $notifModel;
    
    /** @var AuditLogModel */
    private AuditLogModel $auditModel;
    
    /** @var SymposiumDepartmentModel */
    private SymposiumDepartmentModel $deptModel;
    
    /** @var PDO */
    private PDO $db;

    /**
     * Constructor initializes models and database connection.
     */
    public function __construct()
    {
        $this->db           = Database::getConnection();
        $this->appModel     = new ApplicationModel();
        $this->teamModel    = new TeamModel();
        $this->memberModel  = new TeamMemberModel();
        $this->eventModel   = new SymposiumEventModel();
        $this->sympModel    = new SymposiumModel();
        $this->studentModel = new StudentModel();
        $this->notifModel   = new NotificationModel();
        $this->auditModel   = new AuditLogModel();
        $this->deptModel    = new SymposiumDepartmentModel();
    }

    /**
     * Helper method to return error response.
     *
     * @param string $message
     * @return array
     */
    private function error(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    /**
     * Registers a student for a symposium event.
     *
     * @param int $studentId
     * @param int $symposiumEventId
     * @param array $data Additional optional data
     * @return array
     */
    public function registerForEvent(int $studentId, int $symposiumEventId, array $data = []): array
    {
        try {
            // Gate 1 — Active Student
            $student = $this->studentModel->findById($studentId);
            if (!$student || $student['account_status'] !== 'Active') {
                return $this->error('Student account is not active or not found.');
            }

            // Gate 2 — Event Exists + Supports Registration
            $event = $this->eventModel->findById($symposiumEventId);
            if (!$event) {
                return $this->error('Event not found.');
            }
            if ((int)$event['supports_registration'] !== 1) {
                return $this->error('Registration is not enabled for this event.');
            }

            // Gate 3 — Symposium Registration Window
            $symposium = $this->sympModel->findById((int)$event['symposium_id']);
            if (!$symposium) {
                return $this->error('Symposium not found.');
            }
            $now = date('Y-m-d H:i:s');
            if ($now < $symposium['registration_start']) {
                return $this->error('Registration has not opened yet.');
            }
            if ($now > $symposium['registration_end']) {
                return $this->error('Registration period has ended.');
            }

            // Gate 4 — Event Status
            $validStatuses = ['Published', 'Registration Open'];
            if (!in_array($event['status'], $validStatuses, true)) {
                return $this->error('Registration is not currently open for this event.');
            }

            // Gate 5 — Intra-Department Eligibility
            if ($symposium['symposium_type'] === 'Intra Department') {
                $isOrganizer = $this->deptModel->isDepartmentOrganizer(
                    (int)$symposium['symposium_id'],
                    (int)$student['department_id']
                );
                
                // Exception: Group all Computer Science and Computer Application departments together
                if (!$isOrganizer) {
                    $stmt = $this->db->query("SELECT department_id FROM departments WHERE department_name LIKE '%Computer Science%' OR department_name LIKE '%Computer Application%'");
                    $csDeptIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                    
                    $organizers = $this->deptModel->getDepartmentIds((int)$symposium['symposium_id']);
                    $isCsCaOrganized = count(array_intersect($organizers, $csDeptIds)) > 0;
                    $isStudentCsCa = in_array((int)$student['department_id'], $csDeptIds, true);
                    
                    if ($isCsCaOrganized && $isStudentCsCa) {
                        $isOrganizer = true;
                    }
                }

                if (!$isOrganizer) {
                    return $this->error('This is an Intra-Department event. Only students from organizing departments may register.');
                }
            }

            // Gate 6 — Duplicate Check
            $existing = $this->appModel->findByStudentAndSymposiumEvent($studentId, $symposiumEventId);
            if ($existing) {
                return $this->error('You are already registered for this event.');
            }

            // Gate 7 — Team Member Conflict
            $isTeamEvent = in_array($event['participation_type'], ['Team', 'Both'], true);
            if ($isTeamEvent) {
                $inAnyTeam = $this->memberModel->isStudentInAnyTeamForEvent($studentId, $symposiumEventId);
                if ($inAnyTeam) {
                    return $this->error('You are already part of a team registered for this event.');
                }
            }

            // Gate 8 — Capacity Check
            $maxParticipants = (int)$event['max_participants'];
            if ($maxParticipants > 0) {
                $activeCount = $this->appModel->countActiveBySymposiumEvent($symposiumEventId);
                if ($activeCount >= $maxParticipants) {
                    return $this->error('This event has reached its registration limit.');
                }
            }

            // Execution — Transaction
            $this->db->beginTransaction();

            $appType = ($event['participation_type'] === 'Team' || ($event['participation_type'] === 'Both' && isset($data['application_type']) && $data['application_type'] === 'Team')) 
                       ? 'Team' : 'Individual';

            $appData = [
                'competition_id'       => null,
                'symposium_event_id'   => $symposiumEventId,
                'student_id'           => $studentId,
                'application_type'     => $appType,
                'application_status'   => 'Approved', // AUTO-APPROVE
                'approval_status'      => 'Approved', // AUTO-APPROVE
                'approved_at'          => date('Y-m-d H:i:s'),
                'approved_by'          => null,
                'remarks'              => ''
            ];

            // Insert application — auto-approve status set in appData
            $appId = $this->appModel->insert($appData);
            if (!$appId) {
                $this->db->rollBack();
                return $this->error('Failed to create application.');
            }

            // Generate permanent formatted application number (race-safe inside transaction)
            $appNo = $this->appModel->setApplicationNo($appId);

            if ($appType === 'Team') {
                $teamData = [
                    'application_id'     => $appId,
                    'creator_student_id' => $studentId // Creator stored here for FK; not displayed as Leader
                ];
                $teamId = $this->teamModel->create($teamData);
                if (!$teamId) {
                    $this->db->rollBack();
                    return $this->error('Failed to create team.');
                }
                
                // Add creator as equal member
                $this->memberModel->add($teamId, $studentId);
            }

            $this->db->commit();

            // After commit
            // Audit logs require a valid staff user_id, so we skip audit logging for student-initiated registrations.

            $this->notifModel->createForStudent(
                $studentId,
                'Registration Successful',
                "You have successfully registered for the event: {$event['event_name']}."
            );

            return [
                'success' => true,
                'message' => 'Successfully registered for the event.',
                'application_no' => $appNo
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Registration error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred during registration.');
        }
    }

    /**
     * Adds a member to a team.
     *
     * @param int $teamId
     * @param string $registerNumber
     * @param int $creatorStudentId
     * @return array
     */
    public function addTeamMember(int $teamId, string $registerNumber, int $creatorStudentId): array
    {
        try {
            $team = $this->teamModel->findById($teamId);
            if (!$team) {
                return $this->error('Team not found.');
            }

            $app = $this->appModel->findById((int)$team['application_id']);
            if (!$app) {
                return $this->error('Application not found for this team.');
            }

            // Authorization
            if ((int)$app['student_id'] !== $creatorStudentId) {
                return $this->error('Only the student who registered this team can add members.');
            }

            // Check if active
            $terminalStatuses = ['Withdrawn', 'Cancelled', 'Rejected'];
            if (in_array($app['application_status'], $terminalStatuses, true)) {
                return $this->error('Cannot modify members. Application is ' . $app['application_status'] . '.');
            }

            // Check registration window
            $now = date('Y-m-d H:i:s');
            if (!empty($app['registration_end']) && $now > $app['registration_end']) {
                return $this->error('Cannot add members. Registration window is closed.');
            }

            $symposiumEventId = (int)$app['symposium_event_id'];
            $event = $this->eventModel->findById($symposiumEventId);
            if (!$event) {
                return $this->error('Event not found.');
            }

            $maxTeamSize = (int)$event['max_team_size'];
            $currentMembers = $this->memberModel->getByTeam($teamId);
            
            if (count($currentMembers) >= $maxTeamSize) {
                return $this->error("Team has reached the maximum size of {$maxTeamSize} members.");
            }

            $targetStudent = $this->studentModel->findByRegisterNumber($registerNumber);
            if (!$targetStudent) {
                return $this->error('Student with this register number not found.');
            }

            $targetId = (int)$targetStudent['student_id'];
            if ($targetStudent['account_status'] !== 'Active') {
                return $this->error('Target student account is not active.');
            }

            if ($this->memberModel->isStudentInTeam($teamId, $targetId)) {
                return $this->error('Student is already in this team.');
            }

            if ($this->memberModel->isStudentInAnyTeamForEvent($targetId, $symposiumEventId)) {
                return $this->error('Student is already in another team for this event.');
            }

            if ($this->appModel->findByStudentAndSymposiumEvent($targetId, $symposiumEventId)) {
                return $this->error('Student is already registered individually for this event.');
            }

            $this->memberModel->add($teamId, $targetId);

            $this->notifModel->createForStudent(
                $targetId,
                'Added to Team',
                "You have been added to Team-{$team['team_id']} for event {$event['event_name']}."
            );

            return ['success' => true, 'message' => 'Team member added successfully.'];
            
        } catch (Throwable $e) {
            error_log('Add team member error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred while adding member.');
        }
    }

    /**
     * Removes a member from a team.
     *
     * @param int $teamId
     * @param int $memberStudentId
     * @param int $creatorStudentId
     * @return array
     */
    public function removeTeamMember(int $teamId, int $memberStudentId, int $creatorStudentId): array
    {
        try {
            $team = $this->teamModel->findById($teamId);
            if (!$team) {
                return $this->error('Team not found.');
            }

            $app = $this->appModel->findById((int)$team['application_id']);
            if (!$app) {
                return $this->error('Application not found.');
            }

            if ((int)$app['student_id'] !== $creatorStudentId && $memberStudentId !== $creatorStudentId) {
                return $this->error('You are not authorized to modify this team.');
            }

            if ((int)$app['student_id'] === $creatorStudentId && $memberStudentId !== $creatorStudentId) {
                return $this->error('You cannot remove other members from the team.');
            }

            if ((int)$app['student_id'] === $creatorStudentId && $memberStudentId === $creatorStudentId) {
                return $this->error('You cannot remove yourself. Withdraw the entire registration instead.');
            }

            $terminalStatuses = ['Withdrawn', 'Cancelled', 'Rejected'];
            if (in_array($app['application_status'], $terminalStatuses, true)) {
                return $this->error('Cannot modify members. Application is ' . $app['application_status'] . '.');
            }

            // Check registration window
            $now = date('Y-m-d H:i:s');
            if (!empty($app['registration_end']) && $now > $app['registration_end']) {
                return $this->error('Cannot remove members. Registration window is closed.');
            }

            if (!$this->memberModel->isStudentInTeam($teamId, $memberStudentId)) {
                return $this->error('Student is not a member of this team.');
            }

            $this->memberModel->remove($teamId, $memberStudentId);

            $this->notifModel->createForStudent(
                $memberStudentId,
                'Removed from Team',
                "You have been removed from Team-{$team['team_id']}."
            );

            return ['success' => true, 'message' => 'Team member removed successfully.'];
            
        } catch (Throwable $e) {
            error_log('Remove team member error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred while removing member.');
        }
    }

    /**
     * Withdraws a student's registration.
     *
     * @param int $studentId
     * @param int $applicationId
     * @return array
     */
    public function withdraw(int $studentId, int $applicationId): array
    {
        try {
            $app = $this->appModel->findById($applicationId);
            if (!$app) {
                return $this->error('Application not found.');
            }

            if ((int)$app['student_id'] !== $studentId) {
                return $this->error('You are not authorized to withdraw this application.');
            }

            $now = date('Y-m-d H:i:s');
            if (!empty($app['registration_end']) && $now > $app['registration_end']) {
                return $this->error('Withdrawal is not allowed because the registration window is closed.');
            }

            $terminalStatuses = ['Withdrawn', 'Cancelled', 'Rejected'];
            if (in_array($app['application_status'], $terminalStatuses, true)) {
                return $this->error('Application is already ' . $app['application_status'] . '.');
            }

            $this->appModel->updateStatus($applicationId, 'Withdrawn', null, 'Withdrawn by student');
            
            // Audit logs require a valid staff user_id, so we skip audit logging for student-initiated withdrawals.

            $this->notifModel->createForStudent(
                $studentId,
                'Registration Withdrawn',
                "You have successfully withdrawn your application #{$app['application_no']}."
            );

            return ['success' => true, 'message' => 'Registration withdrawn successfully.'];
            
        } catch (Throwable $e) {
            error_log('Withdraw error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred while withdrawing.');
        }
    }

    /**
     * Staff cancellation of an application.
     *
     * @param int $applicationId
     * @param int $staffUserId
     * @param string $remarks
     * @return array
     */
    public function cancel(int $applicationId, int $staffUserId, string $remarks = ''): array
    {
        try {
            $app = $this->appModel->findById($applicationId);
            if (!$app) {
                return $this->error('Application not found.');
            }

            $terminalStatuses = ['Withdrawn', 'Cancelled', 'Rejected'];
            if (in_array($app['application_status'], $terminalStatuses, true)) {
                return $this->error('Application is already ' . $app['application_status'] . '.');
            }

            $this->appModel->updateStatus($applicationId, 'Cancelled', $staffUserId, $remarks);
            
            $this->auditModel->log(
                'Event Registrations',
                $applicationId,
                'STAFF_CANCEL_EVENT_REGISTRATION',
                $staffUserId,
                "Staff cancelled application #{$app['application_no']}. Remarks: $remarks"
            );

            $this->notifModel->createForStudent(
                (int)$app['student_id'],
                'Registration Cancelled',
                "Your application #{$app['application_no']} has been cancelled. Remarks: $remarks"
            );

            return ['success' => true, 'message' => 'Application cancelled successfully.'];
            
        } catch (Throwable $e) {
            error_log('Cancel error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred while cancelling application.');
        }
    }
}
