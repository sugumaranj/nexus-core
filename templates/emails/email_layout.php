<?php
/**
 * =========================================================================
 * NexusCore EMS — Email Template
 * =========================================================================
 * File        : email_layout.php
 * Description : Master responsive HTML email layout.
 *
 * Variables available (passed via extract()):
 *   $collegeName  (string)  e.g. "Government Arts and Science College"
 *   $collegeLogoUrl (string|null)  absolute URL to college logo
 *   $eventLogoUrl   (string|null)  absolute URL to Nexus/event logo
 *   $headerTitle  (string)  headline inside the header band
 *   $preheader    (string)  short preview text (hidden)
 *   $content      (string)  the body HTML (rendered child template)
 *   $footerNote   (string|null)  optional footer blurb
 *
 * =========================================================================
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($headerTitle ?? 'Nexus Notification') ?></title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; font-family: 'Segoe UI', Arial, sans-serif; }
        body { margin: 0; padding: 0; background-color: #f0f4f8; }
        table { border-spacing: 0; border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; display: block; }
        .btn { display: inline-block; background-color: #1a237e; color: #ffffff !important; text-decoration: none; font-size: 15px; font-weight: bold; padding: 14px 38px; border-radius: 8px; }
        .salutation { font-size: 18px; font-weight: 600; color: #1a237e; margin: 0 0 12px; }
        .notice { background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; padding: 14px 18px; font-size: 13px; color: #5d4037; margin: 20px 0; line-height: 1.6; }
        .info-card { background: #f8f9fe; border-left: 4px solid #3f51b5; border-radius: 8px; padding: 18px 20px; margin: 20px 0; }
        .label { font-size: 11px; font-weight: 700; color: #7986cb; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; }
        .value { font-size: 15px; color: #1a237e; font-weight: 600; }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f0f4f8;">
    <!-- Preheader text (hidden, shown in inbox preview) -->
    <div style="display:none;font-size:1px;color:#f0f4f8;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;">
        <?= htmlspecialchars($preheader ?? '') ?>
        <?php echo str_repeat('&zwnj;&nbsp;', 150); ?>
    </div>
    
    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#f0f4f8; padding: 20px 0;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table width="600" border="0" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.10); max-width:600px; width: 100%;">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color:#ffffff; padding: 25px 20px; border-bottom: 5px solid #f9a825;">
                            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <!-- Left Logo -->
                                    <td width="25%" align="left" valign="middle">
                                        <?php if (!empty($collegeLogoUrl)): ?>
                                        <img src="cid:college_logo" alt="College Logo" style="max-width:90px; max-height:90px; height:auto; display:block; border:none;">
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Centered Text -->
                                    <td width="50%" align="center" valign="middle" style="color:#1a237e;">
                                        <p style="margin:0 0 4px; font-size:16px; font-weight:bold; line-height:1.3; text-transform:uppercase;">
                                            <?= htmlspecialchars($collegeName ?? 'Government Arts and Science College') ?>
                                        </p>
                                        <p style="margin:0 0 8px; font-size:13px; color:#5c6bc0;">Veerapandi, Theni</p>
                                        <h1 style="margin:0; font-size:20px; font-weight:bold; color:#1a237e; letter-spacing:0.5px;">
                                            <?= htmlspecialchars($headerTitle ?? 'Nexus Symposium') ?>
                                        </h1>
                                    </td>

                                    <!-- Right Logo -->
                                    <td width="25%" align="right" valign="middle">
                                        <?php if (!empty($eventLogoUrl)): ?>
                                        <img src="cid:event_logo" alt="Nexus Logo" style="max-width:90px; max-height:90px; height:auto; display:block; border:none;">
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    

                    
                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 35px 30px; font-size:15px; color:#37474f; line-height:1.6; background-color:#ffffff;">
                            <?= $content ?>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#f8f9fe; border-top:1px solid #e8eaf6; padding: 25px 30px;">
                            <p style="font-size:14px; font-weight:bold; color:#1a237e; margin:0 0 8px;"><?= htmlspecialchars($collegeName ?? 'Government Arts and Science College') ?></p>
                            <?php if (!empty($footerNote) && $footerNote !== $collegeName): ?>
                            <p style="font-size:12px; color:#5c6bc0; margin:0 0 10px;"><?= htmlspecialchars($footerNote) ?></p>
                            <?php endif; ?>
                            <p style="font-size:12px; color:#9fa8da; margin:0;">
                                This is an automated notification from NexusCore EMS.<br>
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
