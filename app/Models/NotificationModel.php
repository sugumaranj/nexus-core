<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : NotificationModel.php
 * Location    : app/Models/
 * Description : Handles all notification database operations.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Create notifications for Users  (recipient_type = 'User')
 * • Create notifications for Students (recipient_type = 'Student')
 * • Retrieve unread notifications for Users and Students
 * • Mark notifications as read for Users and Students
 *
 * Fix (Issue 1):
 *   - Replaced invalid PDO::query($sql, $params) calls with
 *     proper prepare/execute prepared statements.
 *   - Aligned INSERT/SELECT columns with the actual notifications table:
 *     notification_id, recipient_type, recipient_id, notification_title,
 *     notification_message, delivery_channel, delivery_status, sent_at,
 *     read_at, created_at
 *
 * Extended (Registration Module):
 *   - Added createForStudent(), getUnreadForStudent(), markStudentNotificationRead()
 *   - Existing create() / getUnreadForUser() / markAsRead() are unchanged.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class NotificationModel extends BaseModel
{
    /**
     * Create a notification for a user.
     *
     * Maps the service-layer signature (recipientId, title, message, targetUrl, senderId)
     * onto the actual notifications table columns.
     *
     * @param int         $recipientId   User ID of the recipient
     * @param string      $title         Short notification title
     * @param string      $message       Full notification message
     * @param string|null $targetUrl     Optional URL (not stored — table has no target_url column)
     * @param int|null    $senderId      Sender user ID (not stored — table has no sender column)
     *
     * @return bool
     */
    public function create(
        int $recipientId,
        string $title,
        string $message,
        ?string $targetUrl = null,
        ?int $senderId = null
    ): bool {
        $sql = "INSERT INTO notifications
                    (recipient_type, recipient_id, notification_title, notification_message,
                     delivery_channel, delivery_status)
                VALUES
                    ('User', :recipient_id, :notification_title, :notification_message,
                     'System', 'Sent')";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'recipient_id'          => $recipientId,
            'notification_title'    => $title,
            'notification_message'  => $message,
        ]);
    }

    /**
     * Create notification linked to a Symposium Event.
     *
     * @param string $recipientType 'User' or 'Student'
     * @param int $recipientId
     * @param int $symposiumEventId
     * @param string $title
     * @param string $message
     * @return bool
     */
    public function createForEvent(
        string $recipientType,
        int $recipientId,
        int $symposiumEventId,
        string $title,
        string $message
    ): bool {
        $sql = "INSERT INTO notifications
                    (recipient_type, recipient_id, symposium_event_id, notification_title, notification_message,
                     delivery_channel, delivery_status)
                VALUES
                    (:recipient_type, :recipient_id, :symposium_event_id, :notification_title, :notification_message,
                     'System', 'Sent')";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'recipient_type'       => $recipientType,
            'recipient_id'         => $recipientId,
            'symposium_event_id'   => $symposiumEventId,
            'notification_title'   => $title,
            'notification_message' => $message,
        ]);
    }

    /**
     * Create a notification for a User (FIC/Judge) that appears in the bell.
     *
     * Uses delivery_status = 'Pending' so getUnreadForUser() picks it up.
     *
     * @param int    $recipientId        User ID
     * @param int    $symposiumEventId
     * @param string $title
     * @param string $message
     * @return bool
     */
    public function createForEventPending(
        int $recipientId,
        int $symposiumEventId,
        string $title,
        string $message
    ): bool {
        $sql = "INSERT INTO notifications
                    (recipient_type, recipient_id, symposium_event_id, notification_title, notification_message,
                     delivery_channel, delivery_status)
                VALUES
                    ('User', :recipient_id, :symposium_event_id, :notification_title, :notification_message,
                     'System', 'Pending')";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'recipient_id'         => $recipientId,
            'symposium_event_id'   => $symposiumEventId,
            'notification_title'   => $title,
            'notification_message' => $message,
        ]);
    }

    /**
     * Create a notification with an idempotency key to prevent duplicates.
     *
     * Uses the UNIQUE KEY (recipient_type, recipient_id, notification_type, notification_ref_key)
     * to silently skip duplicate inserts (INSERT IGNORE).
     *
     * @param string $recipientType  'User' or 'Student'
     * @param int    $recipientId
     * @param int    $symposiumEventId
     * @param string $notificationType  e.g. 'venue_assigned', 'venue_reminder'
     * @param string $refKey            Unique reference key (e.g. 'assign-123', 'remind-2026-01-15')
     * @param string $title
     * @param string $message
     * @param string $deliveryStatus   'Pending' (default) shows in bell; 'Sent' is silent
     * @return bool
     */
    public function createWithIdempotencyKey(
        string $recipientType,
        int $recipientId,
        int $symposiumEventId,
        string $notificationType,
        string $refKey,
        string $title,
        string $message,
        string $deliveryStatus = 'Pending'
    ): bool {
        $sql = "INSERT IGNORE INTO notifications
                    (recipient_type, recipient_id, symposium_event_id,
                     notification_title, notification_message,
                     notification_type, notification_ref_key,
                     delivery_channel, delivery_status)
                VALUES
                    (:recipient_type, :recipient_id, :symposium_event_id,
                     :notification_title, :notification_message,
                     :notification_type, :notification_ref_key,
                     'System', :delivery_status)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'recipient_type'       => $recipientType,
            'recipient_id'         => $recipientId,
            'symposium_event_id'   => $symposiumEventId,
            'notification_title'   => $title,
            'notification_message' => $message,
            'notification_type'    => $notificationType,
            'notification_ref_key' => $refKey,
            'delivery_status'      => $deliveryStatus,
        ]);
    }

    /**
     * Get unread (Pending) notifications for a user.
     *
     * @param int $userId
     *
     * @return array
     */
    public function getUnreadForUser(int $userId): array
    {
        $sql = "SELECT *
                FROM notifications
                WHERE recipient_type = 'User'
                  AND recipient_id   = :recipient_id
                  AND delivery_status = 'Pending'
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['recipient_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark a notification as read.
     *
     * @param int $notificationId
     * @param int $userId
     *
     * @return bool
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $sql = "UPDATE notifications
                SET delivery_status = 'Sent', read_at = CURRENT_TIMESTAMP
                WHERE notification_id = :notification_id
                  AND recipient_id    = :recipient_id
                  AND recipient_type  = 'User'";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'notification_id' => $notificationId,
            'recipient_id'    => $userId,
        ]);
    }

    // =========================================================================
    // STUDENT NOTIFICATIONS (Registration Module)
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Create a notification for a Student.
     *
     * Uses recipient_type = 'Student' to distinguish from User notifications.
     * The student_id is stored in recipient_id.
     *
     * @param int    $studentId
     * @param string $title
     * @param string $message
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function createForStudent(int $studentId, string $title, string $message): bool
    {
        $sql = "INSERT INTO notifications
                    (recipient_type, recipient_id, notification_title, notification_message,
                     delivery_channel, delivery_status)
                VALUES
                    ('Student', :recipient_id, :notification_title, :notification_message,
                     'System', 'Pending')";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'recipient_id'         => $studentId,
            'notification_title'   => $title,
            'notification_message' => $message,
        ]);
    }

    /**
     * -------------------------------------------------------------------------
     * Get unread (Pending) notifications for a student.
     *
     * @param int $studentId
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getUnreadForStudent(int $studentId): array
    {
        $sql = "SELECT *
                FROM notifications
                WHERE recipient_type  = 'Student'
                  AND recipient_id    = :recipient_id
                  AND delivery_status = 'Pending'
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['recipient_id' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get all notifications for a student (history, paginated).
     *
     * @param int $studentId
     * @param int $limit
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getAllForStudent(int $studentId, int $limit = 30): array
    {
        $sql = "SELECT *
                FROM notifications
                WHERE recipient_type = 'Student'
                  AND recipient_id   = :recipient_id
                ORDER BY created_at DESC
                LIMIT :limit_val";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':recipient_id', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_val', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Mark all student notifications as read.
     *
     * @param int $studentId
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function markAllStudentNotificationsRead(int $studentId): bool
    {
        $sql = "UPDATE notifications
                SET delivery_status = 'Sent', read_at = CURRENT_TIMESTAMP
                WHERE recipient_id = :recipient_id
                  AND recipient_type = 'Student'
                  AND delivery_status = 'Pending'";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['recipient_id' => $studentId]);
    }

    /**
     * -------------------------------------------------------------------------
     * Mark a student notification as read.
     *
     * Sets delivery_status = 'Sent' and read_at = now.
     * The recipient_id check prevents students from marking others' notifications.
     *
     * @param int $notificationId
     * @param int $studentId
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function markStudentNotificationRead(int $notificationId, int $studentId): bool
    {
        $sql = "UPDATE notifications
                SET delivery_status = 'Sent', read_at = CURRENT_TIMESTAMP
                WHERE notification_id = :notification_id
                  AND recipient_id    = :recipient_id
                  AND recipient_type  = 'Student'";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'notification_id' => $notificationId,
            'recipient_id'    => $studentId,
        ]);
    }
}
