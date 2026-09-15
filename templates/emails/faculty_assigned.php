<?php
declare(strict_types=1);
/**
 * Email Template: Faculty Assigned
 * Location: templates/emails/faculty_assigned.php
 */
?>
<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #0d6efd;">Faculty In-Charge Assignment</h2>
    <p>Dear <strong><?= htmlspecialchars($recipient_name) ?></strong>,</p>
    <p>You have been assigned as <strong>Faculty In-Charge</strong> for the following event:</p>
    <table style="width: 100%; max-width: 600px; border-collapse: collapse; margin: 20px 0; background: #f8f9fa;">
        <tr>
            <td style="padding: 10px; border: 1px solid #dee2e6; width: 30%;"><strong>Event Name</strong></td>
            <td style="padding: 10px; border: 1px solid #dee2e6;"><strong><?= htmlspecialchars($event_name) ?></strong></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>Date</strong></td>
            <td style="padding: 10px; border: 1px solid #dee2e6;"><?= htmlspecialchars($event_date) ?></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>Session</strong></td>
            <td style="padding: 10px; border: 1px solid #dee2e6;"><?= htmlspecialchars($session) ?></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #dee2e6;"><strong>Venue</strong></td>
            <td style="padding: 10px; border: 1px solid #dee2e6;"><?= htmlspecialchars($venue) ?></td>
        </tr>
    </table>
    <p>Please log in to your dashboard to view complete details and manage attendance.</p>
    <p style="margin-top: 30px; font-size: 0.9em; color: #6c757d;">
        This is an automated notification. Please do not reply to this email.
    </p>
</div>
