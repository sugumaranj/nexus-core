<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Models\SymposiumModel;
use App\Models\SymposiumEventModel;
use App\Models\ApplicationModel;
use App\Models\TeamModel;
use App\Models\TeamMemberModel;
use App\Models\FacultyAssignmentModel;
use App\Models\NotificationModel;
use Throwable;

final class ChatbotQueryService
{
    private SymposiumModel $symposiumModel;
    private SymposiumEventModel $symposiumEventModel;
    private ApplicationModel $applicationModel;
    private TeamModel $teamModel;
    private TeamMemberModel $teamMemberModel;
    private FacultyAssignmentModel $facultyAssignmentModel;
    private NotificationModel $notificationModel;

    public function __construct()
    {
        $this->symposiumModel = new SymposiumModel();
        $this->symposiumEventModel = new SymposiumEventModel();
        $this->applicationModel = new ApplicationModel();
        $this->teamModel = new TeamModel();
        $this->teamMemberModel = new TeamMemberModel();
        $this->facultyAssignmentModel = new FacultyAssignmentModel();
        $this->notificationModel = new NotificationModel();
    }

    public function getVisibleSymposiums(): array
    {
        try {
            return $this->symposiumModel->getApprovedForStudents();
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getVisibleSymposiums): " . $e->getMessage());
            return [];
        }
    }

    public function getSymposiumById(int $id)
    {
        try {
            $symposiums = $this->symposiumModel->getApprovedForStudents();
            foreach ($symposiums as $s) {
                if ((int)$s['symposium_id'] === $id) {
                    return $s;
                }
            }
            return false;
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getSymposiumById): " . $e->getMessage());
            return false;
        }
    }

    public function getEventsForSymposium(int $id): array
    {
        try {
            return $this->symposiumEventModel->getBySymposium($id);
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getEventsForSymposium): " . $e->getMessage());
            return [];
        }
    }

    public function getEventById(int $id)
    {
        try {
            return $this->symposiumEventModel->findById($id);
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getEventById): " . $e->getMessage());
            return false;
        }
    }

    public function getStudentRegistrations(int $studentId): array
    {
        try {
            return $this->applicationModel->getByStudentEventBased($studentId);
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getStudentRegistrations): " . $e->getMessage());
            return [];
        }
    }

    public function getStudentRegistrationForEvent(int $studentId, int $eventId)
    {
        try {
            $registrations = $this->applicationModel->getByStudentEventBased($studentId);
            foreach ($registrations as $reg) {
                if ((int)$reg['symposium_event_id'] === $eventId) {
                    return $reg;
                }
            }
            return false;
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getStudentRegistrationForEvent): " . $e->getMessage());
            return false;
        }
    }

    public function getTeamForApplication(int $applicationId)
    {
        try {
            return $this->teamModel->findByApplicationId($applicationId);
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getTeamForApplication): " . $e->getMessage());
            return false;
        }
    }

    public function getTeamMembers(int $teamId): array
    {
        try {
            return $this->teamMemberModel->getByTeam($teamId);
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getTeamMembers): " . $e->getMessage());
            return [];
        }
    }

    public function getFacultyForEvent(int $eventId): array
    {
        try {
            return $this->facultyAssignmentModel->getForEvent($eventId);
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getFacultyForEvent): " . $e->getMessage());
            return [];
        }
    }

    public function getStudentNotifications(int $studentId, int $limit = 10): array
    {
        try {
            return $this->notificationModel->getAllForStudent($studentId, $limit);
        } catch (Throwable $e) {
            error_log("ChatbotQueryService error (getStudentNotifications): " . $e->getMessage());
            return [];
        }
    }
}
