<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\ApplicationModel;
use App\Models\AuditLogModel;
use App\Models\EventFeeModel;
use App\Models\NotificationModel;
use App\Models\PaymentTransactionModel;
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
    private ApplicationModel $appModel;
    private TeamModel $teamModel;
    private TeamMemberModel $memberModel;
    private SymposiumEventModel $eventModel;
    private SymposiumModel $sympModel;
    private StudentModel $studentModel;
    private NotificationModel $notifModel;
    private AuditLogModel $auditModel;
    private SymposiumDepartmentModel $deptModel;
    private EventFeeModel $feeModel;
    private PaymentTransactionModel $paymentModel;
    private PDO $db;

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
        $this->feeModel     = new EventFeeModel();
        $this->paymentModel = new PaymentTransactionModel();
    }

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

            // Gate 5 — Symposium Type Eligibility
            $sympType = $symposium['symposium_type'] ?? 'Intra Department';
            $isExternal = ($student['student_type'] ?? 'Internal') === 'External';

            if ($sympType === 'Intra Department') {
                if ($isExternal) {
                    return $this->error('This is an Intra-Department event. Only students from organizing departments of this college may register.');
                }

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

            } elseif ($sympType === 'Inter Department') {
                if ($isExternal) {
                    return $this->error('This is an Inter-Department event restricted to internal college students.');
                }
                // All internal departments eligible by default

            } elseif ($sympType === 'Inter College') {
                // Open to External and Internal students
                if ($isExternal && ($student['affiliation_status'] ?? 'Verified') === 'Rejected') {
                    return $this->error('Your college affiliation has been rejected. Please update your profile or contact support.');
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

            // Execution — Start Transaction & Enforce Capacity with Row Lock (Race Condition Elimination)
            $this->db->beginTransaction();

            // Lock the target symposium_events row to serialize concurrent capacity evaluations
            $lockStmt = $this->db->prepare("
                SELECT symposium_event_id, max_participants, status, is_locked 
                FROM symposium_events 
                WHERE symposium_event_id = :id 
                FOR UPDATE
            ");
            $lockStmt->execute(['id' => $symposiumEventId]);
            $lockedEvent = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedEvent) {
                $this->db->rollBack();
                return $this->error('Scheduled event not found.');
            }

            if (!in_array($lockedEvent['status'], ['Published', 'Registration Open'], true) || (int)($lockedEvent['is_locked'] ?? 0) === 1) {
                $this->db->rollBack();
                return $this->error('Registration is not currently open for this event.');
            }

            // Gate 8 — Capacity Check (Serialized under exclusive row lock)
            $maxParticipants = (int)($lockedEvent['max_participants'] ?? 0);
            if ($maxParticipants > 0) {
                $activeCount = $this->appModel->countActiveBySymposiumEvent($symposiumEventId);
                if ($activeCount >= $maxParticipants) {
                    $this->db->rollBack();
                    return $this->error('This event has reached its registration limit.');
                }
            }

            $appType = ($event['participation_type'] === 'Team' || ($event['participation_type'] === 'Both' && isset($data['application_type']) && $data['application_type'] === 'Team')) 
                       ? 'Team' : 'Individual';

            // Check if fee is required
            $feeCalc = $this->feeModel->calculateFee($symposiumEventId, $appType, 1);
            $isPaidEvent = $feeCalc['is_fee_required'] && $feeCalc['amount'] > 0;

            $appStatus      = $isPaidEvent ? 'Pending' : 'Approved';
            $approvalStatus = $isPaidEvent ? 'Pending' : 'Approved';
            $paymentStatus  = $isPaidEvent ? 'Pending' : 'Not Required';

            $appData = [
                'competition_id'       => null,
                'symposium_event_id'   => $symposiumEventId,
                'student_id'           => $studentId,
                'application_type'     => $appType,
                'application_status'   => $appStatus,
                'approval_status'      => $approvalStatus,
                'payment_status'       => $paymentStatus,
                'approved_at'          => $isPaidEvent ? null : date('Y-m-d H:i:s'),
                'approved_by'          => null,
                'remarks'              => ''
            ];

            // Insert application
            $appId = $this->appModel->insert($appData);
            if (!$appId) {
                $this->db->rollBack();
                return $this->error('Failed to create application.');
            }

            // Generate permanent formatted application number
            $appNo = $this->appModel->setApplicationNo($appId);

            if ($appType === 'Team') {
                $teamData = [
                    'application_id'     => $appId,
                    'creator_student_id' => $studentId
                ];
                $teamId = $this->teamModel->create($teamData);
                if (!$teamId) {
                    $this->db->rollBack();
                    return $this->error('Failed to create team.');
                }
                
                // Add creator as equal member
                $this->memberModel->add($teamId, $studentId);
            }

            // If event is paid, create initial pending payment transaction
            $paymentRef = null;
            if ($isPaidEvent) {
                $paymentRef = $this->paymentModel->generateReference();
                $this->paymentModel->create([
                    'payment_reference' => $paymentRef,
                    'application_id'    => $appId,
                    'symposium_event_id'=> $symposiumEventId,
                    'student_id'        => $studentId,
                    'amount'            => $feeCalc['amount'],
                    'currency'          => $feeCalc['currency'],
                    'gateway_provider'  => 'mock',
                    'gateway_order_id'  => 'order_' . strtolower(str_replace('-', '_', $paymentRef)),
                    'payment_status'    => 'Pending',
                    'idempotency_key'   => $paymentRef
                ]);
            }

            $this->db->commit();

            if ($isPaidEvent) {
                $this->notifModel->createForStudent(
                    $studentId,
                    'Payment Pending',
                    "Your application {$appNo} for {$event['event_name']} is submitted. Please complete payment of {$feeCalc['currency']} {$feeCalc['amount']} to confirm your registration."
                );

                return [
                    'success'           => true,
                    'requires_payment'  => true,
                    'payment_required'  => true,
                    'team_id'           => $teamId ?? null,
                    'payment_reference' => $paymentRef,
                    'application_id'    => $appId,
                    'application_no'    => $appNo,
                    'amount'            => $feeCalc['amount'],
                    'currency'          => $feeCalc['currency'],
                    'message'           => 'Application submitted! Please complete the fee payment to confirm registration.'
                ];
            }

            $this->notifModel->createForStudent(
                $studentId,
                'Registration Successful',
                "You have successfully registered for the event: {$event['event_name']}."
            );

            return [
                'success'          => true,
                'requires_payment' => false,
                'payment_required' => false,
                'team_id'          => $teamId ?? null,
                'message'          => 'Successfully registered for the event.',
                'application_id'   => $appId,
                'application_no'   => $appNo
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Friendly error for duplicate submissions / constraint violation
            if ($e instanceof \PDOException && ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) === 1062)) {
                return $this->error('You are already registered for this event or an active application already exists.');
            }
            error_log('Registration error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred during registration.');
        }
    }

    /**
     * Adds a member to a team.
     * Enforces same-college membership for inter-college competitions via canonical college_id.
     *
     * @param int $teamId
     * @param string $identifier Register number or Email
     * @param int $creatorStudentId
     * @return array
     */
    public function addTeamMember(int $teamId, string $identifier, int $creatorStudentId): array
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

            // Lookup target student by register number or email
            $creatorStudent = $this->studentModel->findById($creatorStudentId);
            $targetStudent = $this->studentModel->findByEmailOrRegisterNumber(
                $identifier, 
                isset($creatorStudent['college_id']) ? (int)$creatorStudent['college_id'] : null
            );

            if (!$targetStudent) {
                return $this->error('Student with this register number/email not found.');
            }

            $targetId = (int)$targetStudent['student_id'];
            if ($targetStudent['account_status'] !== 'Active') {
                return $this->error('Target student account is not active.');
            }

            // Check Institution match for Inter-College competitions
            $symposium = $this->sympModel->findById((int)$event['symposium_id']);
            if ($symposium && ($symposium['symposium_type'] ?? '') === 'Inter College') {
                $creatorCollegeId = (int)($creatorStudent['college_id'] ?? 0);
                $targetCollegeId  = (int)($targetStudent['college_id'] ?? 0);
                if ($creatorCollegeId !== $targetCollegeId) {
                    $colName = $creatorStudent['college_name'] ?? 'your institution';
                    return $this->error("All team members must belong to the same institution ({$colName}).");
                }
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

            return ['success' => true, 'message' => 'Member removed successfully.'];

        } catch (Throwable $e) {
            error_log('Remove team member error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred while removing member.');
        }
    }

    /**
     * Withdraws a registration.
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

            $terminalStatuses = ['Withdrawn', 'Cancelled', 'Rejected'];
            if (in_array($app['application_status'], $terminalStatuses, true)) {
                return $this->error('This application is already ' . $app['application_status'] . '.');
            }

            $now = date('Y-m-d H:i:s');
            if (!empty($app['registration_end']) && $now > $app['registration_end']) {
                return $this->error('Cannot withdraw application. Registration window is closed.');
            }

            $this->db->beginTransaction();

            $updated = $this->appModel->updateStatus($applicationId, 'Withdrawn', 'Withdrawn by student');
            if (!$updated) {
                $this->db->rollBack();
                return $this->error('Failed to withdraw application.');
            }

            if ($app['application_type'] === 'Team') {
                $team = $this->teamModel->findByApplication($applicationId);
                if ($team) {
                    $this->memberModel->removeAll((int)$team['team_id']);
                }
            }

            $this->db->commit();

            return ['success' => true, 'message' => 'Application withdrawn successfully.'];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Withdraw application error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred while withdrawing application.');
        }
    }
}
