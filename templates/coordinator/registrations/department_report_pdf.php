<?php
declare(strict_types=1);
$overall = $reportData['overall'];
$deptData = $reportData['departments'];
$yearData = $reportData['years'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Department Registration Report</title>
    <style>
        @page {
            margin: 20px 25px;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            color: #000;
            line-height: 1.3;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .mb-2 { margin-bottom: 5px; }
        
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table th { text-align: left; width: 120px; }
        
        .summary-box {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            page-break-inside: auto;
        }
        table.data-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #000;
            padding: 5px;
        }
        table.data-table thead {
            display: table-header-group;
            background-color: #f0f0f0;
        }
        table.data-table tfoot {
            display: table-footer-group;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            page-break-after: avoid;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 20%; text-align: left;">
                <?php if($collegeLogoBase64): ?>
                    <img src="<?= $collegeLogoBase64 ?>" style="height: 70px;">
                <?php endif; ?>
            </td>
            <td style="width: 60%; text-align: center;">
                <div style="font-size: 18px; font-weight: bold;"><?= mb_strtoupper($collegeName) ?></div>
                <div style="font-size: 14px;">VEERAPANDI, THENI</div>
                <div style="font-size: 16px; font-weight: bold; margin-top: 5px; text-decoration: underline;">DEPARTMENT-WISE REGISTRATION REPORT</div>
            </td>
            <td style="width: 20%; text-align: right;">
                <?php if($nexusLogoBase64): ?>
                    <img src="<?= $nexusLogoBase64 ?>" style="height: 60px;">
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <th>Symposium:</th>
            <td><?= htmlspecialchars($symposium['title']) ?> (<?= htmlspecialchars($symposium['symposium_code']) ?>)</td>
            <th>Date Generated:</th>
            <td><?= date('d-M-Y H:i A') ?></td>
        </tr>
        <tr>
            <th>Academic Year:</th>
            <td><?= htmlspecialchars($symposium['academic_year']) ?></td>
            <th>Filters Applied:</th>
            <td>
                Dept: <?= $filters['department_id'] ? 'Yes' : 'All' ?>, 
                Year: <?= $filters['academic_year'] ?: 'All' ?>, 
                Type: <?= $filters['application_type'] ?>
            </td>
        </tr>
    </table>

    <div class="section-title">1. OVERALL REGISTRATION SUMMARY</div>
    
    <table class="data-table text-center" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Total Applications</th>
                <th>Total Participants</th>
                <th>Individual</th>
                <th>Team</th>
                <th>Male</th>
                <th>Female</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="fw-bold"><?= $overall['total_applications'] ?></td>
                <td class="fw-bold"><?= $overall['total_participants'] ?></td>
                <td><?= $overall['individual'] ?></td>
                <td><?= $overall['team'] ?></td>
                <td><?= $overall['male'] ?></td>
                <td><?= $overall['female'] ?></td>
            </tr>
        </tbody>
    </table>

    <table class="data-table text-center" style="margin-bottom: 30px;">
        <thead>
            <tr>
                <th>Approved</th>
                <th>Pending</th>
                <th>Rejected</th>
                <th>Withdrawn</th>
                <th>Cancelled</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $overall['approved'] ?></td>
                <td><?= $overall['pending'] ?></td>
                <td><?= $overall['rejected'] ?></td>
                <td><?= $overall['withdrawn'] ?></td>
                <td><?= $overall['cancelled'] ?></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">2. DEPARTMENT-WISE SUMMARY</div>
    <table class="data-table text-center">
        <thead>
            <tr>
                <th rowspan="2" style="width:5%;">S.No</th>
                <th rowspan="2" class="text-left" style="width:25%;">Department Name</th>
                <th colspan="2">Applications</th>
                <th colspan="3">Participants</th>
                <th colspan="4">Status (Participants)</th>
            </tr>
            <tr>
                <th>Total</th>
                <th>Team</th>
                <th>Total</th>
                <th>Male</th>
                <th>Female</th>
                <th>Appr</th>
                <th>Pend</th>
                <th>Rej</th>
                <th>Wthd</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($deptData)): ?>
                <tr><td colspan="11">No data available.</td></tr>
            <?php else: $sno = 1; foreach($deptData as $d): ?>
                <tr>
                    <td><?= $sno++ ?></td>
                    <td class="text-left fw-bold"><?= htmlspecialchars($d['department_name']) ?></td>
                    <td class="fw-bold"><?= $d['total_applications'] ?></td>
                    <td><?= $d['team'] ?></td>
                    <td class="fw-bold"><?= $d['total_participants'] ?></td>
                    <td><?= $d['male'] ?></td>
                    <td><?= $d['female'] ?></td>
                    <td><?= $d['approved'] ?></td>
                    <td><?= $d['pending'] ?></td>
                    <td><?= $d['rejected'] ?></td>
                    <td><?= $d['withdrawn'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="section-title">3. ACADEMIC YEAR-WISE SUMMARY</div>
    <table class="data-table text-center" style="width: 70%;">
        <thead>
            <tr>
                <th>Academic Year</th>
                <th>Total Apps</th>
                <th>Total Participants</th>
                <th>Male</th>
                <th>Female</th>
                <th>Approved</th>
                <th>Pending</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($yearData)): ?>
                <tr><td colspan="7">No data available.</td></tr>
            <?php else: foreach($yearData as $y): ?>
                <tr>
                    <td class="fw-bold"><?= academic_year_label($y['academic_year']) ?></td>
                    <td><?= $y['total_applications'] ?></td>
                    <td class="fw-bold"><?= $y['total_participants'] ?></td>
                    <td><?= $y['male'] ?></td>
                    <td><?= $y['female'] ?></td>
                    <td><?= $y['approved'] ?></td>
                    <td><?= $y['pending'] ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="section-title">4. DETAILED DEPARTMENT & YEAR BREAKDOWN</div>
    <table class="data-table text-center">
        <thead>
            <tr>
                <th class="text-left">Department</th>
                <th>Academic Year</th>
                <th>Male</th>
                <th>Female</th>
                <th>Total Participants</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($deptData)): ?>
                <tr><td colspan="5">No data available.</td></tr>
            <?php else: 
                foreach($deptData as $d): 
                    $yearsCount = count($d['years']);
                    $first = true;
                    foreach($d['years'] as $y):
            ?>
                <tr>
                    <?php if($first): ?>
                        <td class="text-left fw-bold" rowspan="<?= $yearsCount ?>" style="vertical-align: middle;">
                            <?= htmlspecialchars($d['department_name']) ?>
                        </td>
                    <?php $first = false; endif; ?>
                    <td><?= academic_year_label($y['academic_year']) ?></td>
                    <td><?= $y['male'] ?></td>
                    <td><?= $y['female'] ?></td>
                    <td class="fw-bold"><?= $y['total'] ?></td>
                </tr>
            <?php endforeach; endforeach; endif; ?>
        </tbody>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
            $size = 9;
            $font = $fontMetrics->getFont("Times-Roman");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 25;
            $pdf->page_text($x, $y, $text, $font, $size, array(0,0,0));
            
            $textL = "Generated by NexusCore EMS";
            $pdf->page_text(25, $y, $textL, $font, $size, array(0.5,0.5,0.5));
        }
    </script>
</body>
</html>
