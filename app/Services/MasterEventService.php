<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : MasterEventService.php
 * Location    : app/Services/
 * Description : Business logic layer for Master Event Templates Library.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Enforce Admin role access control
 * • Manage creation, updates, rules, resources, cloning, and deletion
 * • Apply registration cascade logic
 * • DB Transaction handling across master_events, rules, and resources
 * • Audit log recording
 *
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Database\Database;
use App\Models\AuditLogModel;
use App\Models\MasterEventModel;
use App\Models\VenueModel;
use App\Validators\MasterEventValidator;
use PDO;

final class MasterEventService
{
    private MasterEventModel     $model;
    private MasterEventValidator $validator;
    private VenueModel           $venueModel;
    private AuditLogModel        $auditModel;
    private PDO                  $db;

    public function __construct()
    {
        $this->model      = new MasterEventModel();
        $this->validator  = new MasterEventValidator();
        $this->venueModel  = new VenueModel();
        $this->auditModel  = new AuditLogModel();
        $this->db          = Database::getConnection();
    }

    /**
     * Can user manage master event library? (Admin only).
     *
     * @param array $user
     * @return bool
     */
    public function canManage(array $user): bool
    {
        return in_array($user['role'] ?? '', ['Administrator', 'Admin', 'Staff Coordinator'], true);
    }

    /**
     * Get lookup form options for Master Event views.
     *
     * @return array
     */
    public function getFormData(): array
    {
        return [
            'categories'          => ['Technical', 'Non-Technical'],
            'participation_types' => ['Individual', 'Team', 'Both'],
            'prelim_types'        => ['MCQ', 'File Submission', 'Coding Test', 'Abstract Screening', 'Custom'],
            'judging_methods'     => ['Marks', 'Rubrics', 'Voting', 'Mixed'],
            'statuses'            => ['Draft', 'Published', 'Archived'],
            'rule_sections'       => ['Eligibility', 'Topics', 'Materials Required', 'Judging Criteria', 'Restrictions', 'Disqualification', 'Notes'],
            'venues'              => $this->venueModel->getAllActive(),
        ];
    }

    /**
     * Apply registration cascade rule to input data.
     *
     * @param array $data
     * @return array
     */
    private function applyRegistrationCascade(array $data): array
    {
        if (empty($data['supports_registration'])) {
            $data['supports_registration'] = 0;
            $data['supports_attendance']   = 0;
            $data['supports_evaluation']   = 0;
            $data['supports_certificates'] = 0;
        }

        if (empty($data['requires_prelims'])) {
            $data['requires_prelims'] = 0;
            $data['prelim_type']      = null;
        }

        if (($data['participation_type'] ?? 'Individual') === 'Individual') {
            $data['min_team_size'] = 1;
            $data['max_team_size'] = 1;
        }

        return $data;
    }

    /**
     * Create a new Master Event template with rules & resources.
     *
     * @param array $data
     * @param array $user
     * @return array ['success' => bool, 'errors' => array, 'event_id' => int|null, 'message' => string]
     */
    public function createMasterEvent(array $data, array $user): array
    {
        if (!$this->canManage($user)) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized action. Admin role required.'];
        }

        $data = $this->applyRegistrationCascade($data);
        $errors = $this->validator->validate($data, true);

        // Check for duplicate event name
        if (empty($errors['event_name'])) {
            $existing = $this->model->findByName(trim($data['event_name']));
            if ($existing) {
                $errors['event_name'] = 'An event with this name already exists in the master library.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Validation failed. Please correct errors.'];
        }

        // Auto-generate Event Code if missing
        if (empty(trim($data['event_code'] ?? ''))) {
            $data['event_code'] = $this->model->generateEventCode($data['event_name']);
        }

        $data['created_by'] = (int)$user['user_id'];

        try {
            $this->db->beginTransaction();

            $eventId = $this->model->create($data);
            if (!$eventId) {
                $this->db->rollBack();
                return ['success' => false, 'errors' => [], 'message' => 'Failed to create Master Event record.'];
            }

            // Save Rules
            if (!empty($data['rules_text']) && is_string($data['rules_text'])) {
                $rulesArray = $this->parseRulesText($data['rules_text']);
                $this->model->saveRules($eventId, $rulesArray);
            } elseif (!empty($data['rules']) && is_array($data['rules'])) {
                $this->model->saveRules($eventId, $data['rules']);
            }


            // Log Audit
            $this->auditModel->log(
                'Master Events',
                $eventId,
                'CREATE_MASTER_EVENT',
                (int)$user['user_id'],
                "Created master event: {$data['event_name']} ({$data['event_code']})"
            );

            $this->db->commit();

            return [
                'success'  => true,
                'errors'   => [],
                'event_id' => $eventId,
                'message'  => "Master Event '{$data['event_name']}' created successfully with code {$data['event_code']}.",
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'errors' => [], 'message' => 'Database Error: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing Master Event.
     *
     * @param int $eventId
     * @param array $data
     * @param array $user
     * @return array
     */
    public function updateMasterEvent(int $eventId, array $data, array $user): array
    {
        if (!$this->canManage($user)) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized action. Admin role required.'];
        }

        $existing = $this->model->findById($eventId);
        if (!$existing) {
            return ['success' => false, 'errors' => [], 'message' => 'Master Event not found.'];
        }

        $data = $this->applyRegistrationCascade($data);
        $errors = $this->validator->validate($data, false);

        // Check for duplicate event name
        if (empty($errors['event_name'])) {
            $duplicate = $this->model->findByName(trim($data['event_name']), $eventId);
            if ($duplicate) {
                $errors['event_name'] = 'An event with this name already exists in the master library.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Validation failed. Please correct errors.'];
        }

        try {
            $this->db->beginTransaction();

            $updated = $this->model->update($eventId, $data);
            if (!$updated) {
                $this->db->rollBack();
                return ['success' => false, 'errors' => [], 'message' => 'Failed to update Master Event record.'];
            }

            // Update Rules
            if (isset($data['rules_text']) && is_string($data['rules_text'])) {
                $rulesArray = $this->parseRulesText($data['rules_text']);
                $this->model->saveRules($eventId, $rulesArray);
            } elseif (isset($data['rules']) && is_array($data['rules'])) {
                $this->model->saveRules($eventId, $data['rules']);
            }


            // Audit log
            $this->auditModel->log(
                'Master Events',
                $eventId,
                'UPDATE_MASTER_EVENT',
                (int)$user['user_id'],
                "Updated master event: {$data['event_name']} ({$existing['event_code']})"
            );

            $this->db->commit();

            return [
                'success'  => true,
                'errors'   => [],
                'event_id' => $eventId,
                'message'  => "Master Event '{$data['event_name']}' updated successfully.",
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'errors' => [], 'message' => 'Database Error: ' . $e->getMessage()];
        }
    }

    /**
     * Clone a Master Event.
     *
     * @param int $sourceEventId
     * @param string $newName
     * @param array $user
     * @return array
     */
    public function cloneMasterEvent(int $sourceEventId, string $newName, array $user): array
    {
        if (!$this->canManage($user)) {
            return ['success' => false, 'message' => 'Unauthorized action. Admin role required.'];
        }

        $newName = trim($newName);
        if ($newName === '') {
            return ['success' => false, 'message' => 'New event name is required for cloning.'];
        }

        $newId = $this->model->cloneEvent($sourceEventId, $newName, (int)$user['user_id']);

        if ($newId) {
            $this->auditModel->log(
                'Master Events',
                $newId,
                'CLONE_MASTER_EVENT',
                (int)$user['user_id'],
                "Cloned master event ID {$sourceEventId} into new event: {$newName}"
            );

            return ['success' => true, 'event_id' => $newId, 'message' => "Event successfully cloned as '{$newName}'."];
        }

        return ['success' => false, 'message' => 'Failed to clone event template.'];
    }

    /**
     * Soft delete a Master Event template.
     *
     * @param int $eventId
     * @param array $user
     * @return array
     */
    public function deleteMasterEvent(int $eventId, array $user): array
    {
        if (!$this->canManage($user)) {
            return ['success' => false, 'message' => 'Unauthorized action. Admin role required.'];
        }

        $event = $this->model->findById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Master Event not found.'];
        }

        $deleted = $this->model->softDelete($eventId, (int)$user['user_id']);

        if ($deleted) {
            $this->auditModel->log(
                'Master Events',
                $eventId,
                'DELETE_MASTER_EVENT',
                (int)$user['user_id'],
                "Soft deleted master event: {$event['event_name']} ({$event['event_code']})"
            );

            return ['success' => true, 'message' => "Master Event '{$event['event_name']}' deleted successfully."];
        }

        return ['success' => false, 'message' => 'Failed to delete Master Event.'];
    }

    /**
     * Convert rules textarea/WYSIWYG input into structured rules array.
     *
     * Supports both the new HTML-based WYSIWYG format and the legacy plain-text format.
     * If the input contains HTML tags, the whole content is stored as a single HTML rule entry.
     * If it is plain text (legacy), it is split by newlines as before.
     *
     * @param string $text
     * @return array
     */
    public function parseRulesText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Detect WYSIWYG HTML content (contains any HTML tags)
        if (preg_match('/<[a-z][\s\S]*>/i', $text)) {
            // If the text comes from the textarea, it uses \n for newlines.
            // We should convert \n to <br> so that it renders correctly as HTML,
            // but only if it doesn't already use block tags like <div> or <p>.
            if (!preg_match('/<(div|p|br)/i', $text)) {
                $text = nl2br($text);
            }
            return [
                [
                    'section'       => 'Rules & Guidelines',
                    'rule_text'     => $text,
                    'display_order' => 1,
                ],
            ];
        }

        // Legacy plain-text format: split by newlines
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $rules = [];
        $order = 1;
        $currentSection = 'Rules & Guidelines';

        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\s*\[(.*?)\]\s*$/', $line, $m) || preg_match('/^\s*([A-Za-z\s]+):\s*$/', $line, $m)) {
                $currentSection = trim($m[1]);
                continue;
            }

            $rules[] = [
                'section'       => $currentSection,
                'rule_text'     => $line,
                'display_order' => $order++,
            ];
        }

        return $rules;
    }

    public function formatRulesText(array $rules): string
    {
        if (empty($rules)) {
            return '';
        }

        if (count($rules) === 1 && preg_match('/<[a-z][\s\S]*>/i', $rules[0]['rule_text'] ?? '')) {
            $html = $rules[0]['rule_text'];
            // Clean up block tags for the textarea display
            $html = preg_replace('/<div>(.*?)<\/div>/i', "$1\n", $html);
            $html = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $html);
            return trim($html);
        }

        $lines = [];
        $lastSection = '';

        foreach ($rules as $r) {
            $section = $r['section'] ?? 'Rules & Guidelines';
            if ($section !== $lastSection && !in_array($section, ['Rules & Guidelines', 'General Rules', 'General'], true)) {
                $lines[] = '[' . $section . ']';
                $lastSection = $section;
            }
            $lines[] = $r['rule_text'];
        }

        return implode("\n", $lines);
    }
}
