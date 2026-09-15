<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumEventStatusSyncService.php
 * Location    : app/Services/
 * Description : Automatic lifecycle status engine for Symposium Events.
 *
 * Lifecycle (for symposium_events table only — NOT master_events):
 * ─────────────────────────────────────────────────────────────────
 *  Draft             → set on event creation (default)
 *  Published         → set by bulkPublishBySymposium() when the
 *                       parent symposium is Principal-Approved
 *  Registration Open → auto, when NOW() >= registration_start
 *  Registration Closed→ auto, when NOW() > registration_end
 *  Running           → auto, when NOW() >= event_date + start_time
 *  Completed         → auto, when NOW() > event_date + end_time
 *
 * Notes
 * ─────
 * • Terminal states (Cancelled, Completed) are never auto-changed.
 * • Draft events are never auto-changed — they need explicit publish.
 * • This service is intentionally side-effect-free (no notifications).
 * • One sync run per minute maximum (throttled via session timestamp).
 *
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\SymposiumEventModel;

final class SymposiumEventStatusSyncService
{
    // ── Status constants ──────────────────────────────────────────────────
    public const STATUS_DRAFT               = 'Draft';
    public const STATUS_PUBLISHED           = 'Published';
    public const STATUS_REGISTRATION_OPEN   = 'Registration Open';
    public const STATUS_REGISTRATION_CLOSED = 'Registration Closed';
    public const STATUS_RUNNING             = 'Running';
    public const STATUS_COMPLETED           = 'Completed';
    public const STATUS_CANCELLED           = 'Cancelled';

    /**
     * Throttle key stored in $_SESSION to avoid running more than once/minute.
     */
    private const THROTTLE_KEY = '_event_sync_last_run';

    /**
     * Minimum seconds between two automatic sync runs per user session.
     */
    private const THROTTLE_SECONDS = 60;

    private SymposiumEventModel $eventModel;

    public function __construct()
    {
        $this->eventModel = new SymposiumEventModel();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Run the sync only if the throttle window has passed.
     *
     * Safe to call on every page load — will silently no-op if already run
     * within the last THROTTLE_SECONDS.
     *
     * @return int  Number of event rows whose status was updated (0 if throttled)
     */
    public function runIfDue(): int
    {
        $now      = time();
        $lastRun  = (int) ($_SESSION[self::THROTTLE_KEY] ?? 0);

        if (($now - $lastRun) < self::THROTTLE_SECONDS) {
            return 0; // throttled — not yet due
        }

        $_SESSION[self::THROTTLE_KEY] = $now;
        return $this->syncAll();
    }

    /**
     * Force a full sync regardless of throttle.
     *
     * Use this after critical actions (e.g., right after Principal approval
     * or after an event schedule is edited).
     *
     * @return int  Number of event rows updated
     */
    public function syncAll(): int
    {
        $events  = $this->eventModel->getEventsNeedingStatusSync();
        $nowStr  = date('Y-m-d H:i:s');
        $updated = 0;

        foreach ($events as $event) {
            $newStatus = $this->computeStatus($event, $nowStr);

            if ($newStatus !== null && $newStatus !== $event['status']) {
                if ($this->eventModel->updateStatus((int)$event['symposium_event_id'], $newStatus)) {
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /**
     * Sync a single event by its ID immediately (used after schedule edits).
     *
     * @param int $symposiumEventId
     * @return bool  True if the status was changed
     */
    public function syncOne(int $symposiumEventId): bool
    {
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return false;
        }

        // Never auto-transition Draft or terminal statuses
        if (in_array($event['status'], [self::STATUS_DRAFT, self::STATUS_CANCELLED, self::STATUS_COMPLETED], true)) {
            return false;
        }

        $newStatus = $this->computeStatus($event, date('Y-m-d H:i:s'));
        if ($newStatus !== null && $newStatus !== $event['status']) {
            return $this->eventModel->updateStatus($symposiumEventId, $newStatus);
        }

        return false;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Compute the correct status for an event given the current datetime.
     *
     * Returns null if no transition is needed (status is already correct).
     *
     * @param array  $event   Row from symposium_events
     * @param string $nowStr  Current datetime as 'Y-m-d H:i:s'
     * @return string|null    New status or null
     */
    private function computeStatus(array $event, string $nowStr): ?string
    {
        $currentStatus = $event['status'];

        // Treat the legacy empty-string bug as if it's Published
        // (it happens when 'Scheduled' was stored, which is not a valid ENUM value)
        if ($currentStatus === '') {
            $currentStatus = self::STATUS_PUBLISHED;
        }

        // Build datetime strings for the event schedule boundaries
        $eventDate   = $event['event_date']   ?? null; // '2026-08-15'
        $startTime   = $event['start_time']   ?? null; // 'HH:MM:SS'
        $endTime     = $event['end_time']     ?? null; // 'HH:MM:SS'
        $regStart    = $event['registration_start'] ?? null; // full datetime (may come from parent symposium)
        $regEnd      = $event['registration_end']   ?? null; // full datetime (may come from parent symposium)

        // Combine event date + time into full datetime strings
        $eventStartDt = ($eventDate && $startTime) ? $eventDate . ' ' . $startTime : null;
        $eventEndDt   = ($eventDate && $endTime)   ? $eventDate . ' ' . $endTime   : null;

        // ── Priority order (highest to lowest) ────────────────────────────
        // 1. Event has ended → Completed
        if ($eventEndDt && $nowStr > $eventEndDt) {
            return self::STATUS_COMPLETED;
        }

        // 2. Event is currently happening → Running
        if ($eventStartDt && $eventEndDt && $nowStr >= $eventStartDt && $nowStr <= $eventEndDt) {
            return self::STATUS_RUNNING;
        }

        // 3. Registration window has closed → Registration Closed
        if ($regEnd && $nowStr > $regEnd) {
            return self::STATUS_REGISTRATION_CLOSED;
        }

        // 4. Registration window is open → Registration Open
        //    This fires even if event_date/time not yet scheduled — registration
        //    can open before the full schedule is finalized.
        if ($regStart && $nowStr >= $regStart && ($regEnd === null || $nowStr <= $regEnd)) {
            return self::STATUS_REGISTRATION_OPEN;
        }

        // 5. If the status was empty/Scheduled (old bug) and no time windows apply,
        //    formally set it to Published now
        if ($event['status'] === '' || $event['status'] === 'Scheduled') {
            return self::STATUS_PUBLISHED;
        }

        // 6. If currently Registration Open or Closed or Running but time windows
        //    no longer match (e.g., schedule was edited), revert to Published
        if (
            in_array($currentStatus, [self::STATUS_REGISTRATION_OPEN, self::STATUS_REGISTRATION_CLOSED, self::STATUS_RUNNING], true) &&
            ($regStart === null || $nowStr < $regStart)
        ) {
            return self::STATUS_PUBLISHED;
        }

        return null; // no change needed
    }
}
