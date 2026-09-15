<?php
/**
 * =========================================================================
 * Email Template — Trigger 1: Principal Approved
 * =========================================================================
 * File        : symposium_announcement.php
 * Description : Sent to eligible students when Principal approves a symposium.
 *
 * Variables:
 *   $studentName      (string)
 *   $symposiumTitle   (string)
 *   $symposiumCode    (string)
 *   $academicYear     (string)
 *   $deptNames        (string)  comma-separated organizing departments
 *   $registrationStart (string) formatted date
 *   $registrationEnd   (string) formatted date
 *   $eventStartDate    (string) formatted date
 *   $eventEndDate      (string|null)
 *   $scheduleReady    (bool)   true if all events are scheduled
 *   $baseUrl          (string)
 * =========================================================================
 */
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" style="font-family: Arial, Helvetica, sans-serif; color: #37474f;">
    <tr>
        <td style="padding-bottom: 20px;">
            <p style="font-size: 18px; font-weight: bold; color: #1a237e; margin: 0 0 10px;">Dear <?= htmlspecialchars($studentName ?? 'Student') ?>,</p>
            <p style="font-size: 15px; line-height: 1.6; margin: 0;">We are delighted to inform you that <strong><?= htmlspecialchars($symposiumTitle) ?></strong> has received <strong style="color: #1b5e20;">official approval</strong> from the Principal. The symposium is now open for student participation.</p>
        </td>
    </tr>

    <!-- Important Information Card -->
    <tr>
        <td style="padding-bottom: 25px;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: #f8f9fe; border-left: 4px solid #3f51b5; border-radius: 8px;">
                <tr>
                    <td style="padding: 20px;">
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding-bottom: 12px;">
                                    <p style="font-size: 11px; font-weight: bold; color: #7986cb; text-transform: uppercase; margin: 0 0 4px; letter-spacing: 1px;">Symposium</p>
                                    <p style="font-size: 15px; font-weight: bold; color: #1a237e; margin: 0;"><?= htmlspecialchars($symposiumTitle) ?> <span style="font-size: 13px; color: #5c6bc0; font-weight: normal;">(<?= htmlspecialchars($symposiumCode) ?>)</span></p>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td width="50%" valign="top">
                                                <p style="font-size: 11px; font-weight: bold; color: #7986cb; text-transform: uppercase; margin: 0 0 4px; letter-spacing: 1px;">Organizing Department(s)</p>
                                                <p style="font-size: 14px; color: #1a237e; font-weight: bold; margin: 0;"><?= htmlspecialchars($deptNames) ?></p>
                                            </td>
                                            <td width="50%" valign="top">
                                                <p style="font-size: 11px; font-weight: bold; color: #7986cb; text-transform: uppercase; margin: 0 0 4px; letter-spacing: 1px;">Academic Year</p>
                                                <p style="font-size: 14px; color: #1a237e; font-weight: bold; margin: 0;"><?= htmlspecialchars($academicYear) ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Important Dates -->
    <tr>
        <td style="padding-bottom: 25px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #1a237e; margin: 0 0 15px; border-bottom: 2px solid #e8eaf6; padding-bottom: 8px;">Important Dates</h3>
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="33%" align="center" style="background-color: #e8f5e9; border-radius: 8px; padding: 15px 10px; border: 1px solid #c8e6c9;">
                        <p style="font-size: 11px; font-weight: bold; color: #2e7d32; text-transform: uppercase; margin: 0 0 5px;">Registration Opens</p>
                        <p style="font-size: 14px; font-weight: bold; color: #1b5e20; margin: 0;"><?= htmlspecialchars($registrationStart) ?></p>
                    </td>
                    <td width="2%">&nbsp;</td>
                    <td width="30%" align="center" style="background-color: #ffebee; border-radius: 8px; padding: 15px 10px; border: 1px solid #ffcdd2;">
                        <p style="font-size: 11px; font-weight: bold; color: #c62828; text-transform: uppercase; margin: 0 0 5px;">Registration Closes</p>
                        <p style="font-size: 14px; font-weight: bold; color: #b71c1c; margin: 0;"><?= htmlspecialchars($registrationEnd) ?></p>
                    </td>
                    <td width="2%">&nbsp;</td>
                    <td width="33%" align="center" style="background-color: #fff8e1; border-radius: 8px; padding: 15px 10px; border: 1px solid #ffecb3;">
                        <p style="font-size: 11px; font-weight: bold; color: #f57f17; text-transform: uppercase; margin: 0 0 5px;">Event Date</p>
                        <p style="font-size: 14px; font-weight: bold; color: #e65100; margin: 0;"><?= htmlspecialchars($eventStartDate) ?></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- How to Participate -->
    <tr>
        <td style="padding-bottom: 30px;">
            <h3 style="font-size: 16px; font-weight: bold; color: #1a237e; margin: 0 0 15px; border-bottom: 2px solid #e8eaf6; padding-bottom: 8px;">How to Participate</h3>
            <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: #fafafa; border: 1px solid #eeeeee; border-radius: 8px;">
                <tr>
                    <td align="center" style="padding: 20px;">
                        <p style="font-size: 14px; font-weight: bold; color: #37474f; margin: 0 0 8px;">Login to NexusCore</p>
                        <p style="font-size: 16px; color: #90a4ae; margin: 0 0 8px;">&darr;</p>
                        
                        <p style="font-size: 14px; font-weight: bold; color: #37474f; margin: 0 0 8px;">Student Login</p>
                        <p style="font-size: 16px; color: #90a4ae; margin: 0 0 8px;">&darr;</p>
                        
                        <p style="font-size: 14px; font-weight: bold; color: #37474f; margin: 0 0 8px;">Official Notice Board</p>
                        <p style="font-size: 16px; color: #90a4ae; margin: 0 0 8px;">&darr;</p>
                        
                        <p style="font-size: 14px; font-weight: bold; color: #37474f; margin: 0 0 8px;">Download Brochure &amp; Circular</p>
                        <p style="font-size: 16px; color: #90a4ae; margin: 0 0 8px;">&darr;</p>
                        
                        <p style="font-size: 14px; font-weight: bold; color: #37474f; margin: 0 0 8px;">Read Event Details</p>
                        <p style="font-size: 16px; color: #90a4ae; margin: 0 0 8px;">&darr;</p>
                        
                        <p style="font-size: 15px; font-weight: bold; color: #1b5e20; margin: 0;">Register for Events</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Actionable Buttons -->
    <tr>
        <td align="center">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="center" style="padding-bottom: 15px;">
                        <a href="<?= $baseUrl ?>/login.php" style="display: inline-block; background-color: #1a237e; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: bold; padding: 14px 30px; border-radius: 6px; width: 220px; text-align: center;">Login to NexusCore</a>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding-bottom: 15px;">
                        <a href="<?= $baseUrl ?>/student/notice_board.php" style="display: inline-block; background-color: #3f51b5; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: bold; padding: 12px 25px; border-radius: 6px; width: 230px; text-align: center;">View Notice Board</a>
                    </td>
                </tr>
                <tr>
                    <td align="center">
                        <table border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="padding-right: 10px;">
                                    <a href="<?= $baseUrl ?>/public/brochure.php?id=<?= $symposiumId ?? 0 ?>" style="display: inline-block; background-color: #f5f5f5; border: 1px solid #e0e0e0; color: #37474f; text-decoration: none; font-size: 13px; font-weight: bold; padding: 10px 20px; border-radius: 6px;">Download Brochure</a>
                                </td>
                                <td align="center">
                                    <a href="<?= $baseUrl ?>/public/circular.php?id=<?= $symposiumId ?? 0 ?>" style="display: inline-block; background-color: #f5f5f5; border: 1px solid #e0e0e0; color: #37474f; text-decoration: none; font-size: 13px; font-weight: bold; padding: 10px 20px; border-radius: 6px;">Download Circular</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
