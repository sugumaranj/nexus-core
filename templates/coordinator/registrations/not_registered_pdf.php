<?php
declare(strict_types=1);

// Group students
$grouped = [];
foreach ($unregisteredStudents as $student) {
    $dept = $student['department_name'];
    $year = $student['academic_year'];
    $grouped[$dept][$year][] = $student;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Students Not Registered Report</title>
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
        .section-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            page-break-after: avoid;
        }
        .dept-header {
            background-color: #343a40;
            color: #fff;
            padding: 5px;
            font-weight: bold;
            margin-bottom: 5px;
            page-break-after: avoid;
        }
        .year-header {
            background-color: #e9ecef;
            padding: 4px;
            font-weight: bold;
            margin-bottom: 5px;
            border-left: 2px solid #000;
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
                <div style="font-size: 16px; font-weight: bold; margin-top: 5px; text-decoration: underline;">STUDENTS NOT REGISTERED REPORT</div>
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
                Dept: <?= $filters['department_id'] === 'All' ? 'All' : 'Selected' ?>, 
                Year: <?= $filters['academic_year'] === 'All' ? 'All' : academic_year_label($filters['academic_year']) ?>
            </td>
        </tr>
    </table>

    <div class="section-title">1. OVERALL SUMMARY</div>
    
    <table class="data-table text-center" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th>Total Eligible Students</th>
                <th>Registered Students</th>
                <th>Not Registered Students</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="fw-bold"><?= $stats['total_eligible'] ?? 0 ?></td>
                <td class="fw-bold"><?= $stats['total_registered'] ?? 0 ?></td>
                <td class="fw-bold" style="color: red;"><?= $stats['total_unregistered'] ?? 0 ?></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">2. DETAILED LIST (FILTERED)</div>

    <?php if(empty($grouped)): ?>
        <p class="text-center fw-bold">All eligible students for this filter have registered!</p>
    <?php else: ?>
        <?php foreach($grouped as $dept => $years): ?>
            <div class="dept-header"><?= htmlspecialchars($dept) ?></div>
            <?php foreach($years as $year => $students): ?>
                <div class="year-header"><?= academic_year_label($year) ?></div>
                <table class="data-table text-center" style="margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th style="width:5%;">S.No</th>
                            <th style="width:25%;">Register No</th>
                            <th style="width:50%;">Student Name</th>
                            <th style="width:20%;">Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sno = 1; foreach($students as $student): ?>
                            <tr>
                                <td><?= $sno++ ?></td>
                                <td><?= htmlspecialchars($student['register_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-left"><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= academic_year_label($student['academic_year']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
