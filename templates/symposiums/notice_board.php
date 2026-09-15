<?php
// Group events by category
$groupedEvents = [];
foreach ($events as $event) {
    $cat = $event['category'] ?? 'General';
    if (!isset($groupedEvents[$cat])) {
        $groupedEvents[$cat] = [];
    }
    $groupedEvents[$cat][] = $event;
}

// Sort categories (Technical first, then Non-Technical, then others)
$categoryOrder = ['Technical', 'Non-Technical'];
uksort($groupedEvents, function($a, $b) use ($categoryOrder) {
    $posA = array_search($a, $categoryOrder);
    $posB = array_search($b, $categoryOrder);
    if ($posA !== false && $posB !== false) return $posA <=> $posB;
    if ($posA !== false) return -1;
    if ($posB !== false) return 1;
    return strcmp($a, $b);
});
?>

<div class="mb-4 d-flex justify-content-between align-items-center no-print" style="font-family: 'Inter', sans-serif;">
    <div>
        <h4 class="mb-0 text-primary fw-bold">Report Preview</h4>
        <p class="text-muted small mb-0">Use the button below to print or save as PDF.</p>
    </div>
    <div>
        <?php if (in_array($symposium['status'], ['Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed'])): ?>
            <a href="<?= base_url('/symposiums/generate-pdf?id=' . $symposium['symposium_id'] . '&type=circular') ?>" class="btn btn-danger shadow-sm" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Circular PDF
            </a>
            <a href="<?= base_url('/symposiums/generate-pdf?id=' . $symposium['symposium_id'] . '&type=schedule') ?>" class="btn btn-success shadow-sm ms-2" target="_blank">
                <i class="bi bi-calendar-event me-1"></i> Schedule PDF
            </a>
        <?php endif; ?>
        <button class="btn btn-primary shadow-sm ms-2" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i> Print Notice
        </button>
        <button class="btn btn-outline-secondary ms-2" onclick="window.close()">Close</button>
    </div>
</div>

<div class="report-container">
    <?php if (empty($groupedEvents)): ?>
        <div class="text-center py-5">
            <h4 class="text-muted">No events have been scheduled yet.</h4>
        </div>
    <?php else: ?>
        <?php $catIndex = 0; ?>
        <?php foreach ($groupedEvents as $category => $catEvents): ?>
            <?php 
            // Use database values directly without hardcoding replacements
            $sympType = $symposium['symposium_type'] ?? 'Symposium';
            $academicYear = $symposium['academic_year'] ?? '';
            
            $subtitle = "({$sympType} Competition - {$category})";
            if ($category === 'General') {
                $subtitle = "({$sympType} Competition)";
            }
            ?>
            
            <div class="category-section <?= ($catIndex > 0) ? 'page-break' : '' ?>">
                
                <div class="report-header text-center mb-3">
                    <h2 class="fw-bold text-uppercase mb-2">
                        <?= htmlspecialchars($symposium['title']) ?> 
                        <?php if ($academicYear): ?>
                            <span class="fs-4 text-muted">(<?= htmlspecialchars($academicYear) ?>)</span>
                        <?php endif; ?>
                    </h2>
                    <h4 class="fw-bold mb-0"><?= htmlspecialchars($subtitle) ?></h4>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle report-table">
                        <thead class="text-center">
                            <tr>
                                <th style="width: 7%;" class="fw-bold">S.No</th>
                                <th style="width: 28%;" class="fw-bold">NAME OF THE EVENT</th>
                                <th class="fw-bold">RULES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catEvents as $index => $event): ?>
                                <?php
                                $rulesRaw = json_decode($event['snapshot_rules'] ?? '[]', true) ?: [];
                                $rulesList = is_array($rulesRaw) ? $rulesRaw : [];
                                $rulesText = '';
                                if (!empty($rulesList)) {
                                    foreach ($rulesList as $idx => $r) {
                                        $text = is_array($r) ? ($r['rule_text'] ?? '') : (is_string($r) ? $r : '');
                                        if (trim($text) !== '') {
                                            $rulesText .= rtrim($text) . "\n";
                                        }
                                    }
                                } else {
                                    $rulesText = "No specific rules provided.";
                                }
                                ?>
                                <tr>
                                    <td class="text-center" style="width: 7%;"><?= $index + 1 ?></td>
                                    <td style="width: 28%; padding-left: 10px;">
                                        <div class="fw-bold"><?= htmlspecialchars($event['event_name']) ?></div>
                                        <?php if (!empty($event['event_date'])): ?>
                                            <div class="small mt-1 text-primary fw-semibold" style="font-size: 11px;">
                                                <i class="bi bi-calendar-event me-1"></i><?= !empty($event['event_date']) ? date('d M Y', strtotime($event['event_date'])) : 'TBD' ?>
                                                <?php if (!empty($event['start_time'])): ?>
                                                    | <i class="bi bi-clock me-1"></i><?= !empty($event['start_time']) ? date('h:i A', strtotime($event['start_time'])) : '' ?> - <?= !empty($event['end_time']) ? date('h:i A', strtotime($event['end_time'])) : '' ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($event['description'])): ?>
                                            <div style="font-weight: normal; margin-top: 4px;"><?= htmlspecialchars($event['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="rules-cell p-3"><?= strip_tags(trim($rulesText), '<b><u><i><strong><em><br><ul><ol><li><div><p><span>') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="report-table-footer">
                            <tr>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php $catIndex++; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
/* Clean professional font throughout the report */
.report-container {
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #000;
}

/* Typography Hierarchy */
.report-header h2 {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 8px;
}
.report-header h4 {
    font-size: 15px;
    font-weight: bold;
    margin-top: 0;
    margin-bottom: 15px;
}
.report-table th {
    font-size: 12px;
    font-weight: bold;
    padding: 10px !important;
    vertical-align: top;
    background-color: #f8f9fa !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.report-table td {
    font-size: 12px;
    padding: 10px !important;
    vertical-align: top;
}

/* Rules content specific styling */
.rules-cell {
    white-space: pre-wrap; 
    tab-size: 4;
    -moz-tab-size: 4;
    word-break: normal;
    overflow-wrap: break-word;
    line-height: 1.6;
}

/* Table Layout & Print Handling */
.report-table {
    width: 100%;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    border: none !important;
    margin-bottom: 25px;
}
.report-table th, .report-table td {
    border: none !important;
}
.report-table th {
    border-top: 1px solid #000 !important;
    border-bottom: 1px solid #000 !important;
    border-right: 1px solid #000 !important;
}
.report-table td {
    border-top: 1px solid #000 !important;
    border-right: 1px solid #000 !important;
}
.report-table tbody tr:first-child td {
    border-top: none !important;
}
.report-table th:first-child, .report-table td:first-child {
    border-left: 1px solid #000 !important;
}

/* Proper page break handling for tables */
.report-table thead {
    display: table-header-group; /* Repeats header on new pages */
}
.report-table tfoot {
    display: table-footer-group;
}
.report-table tr {
    page-break-inside: avoid;
    break-inside: avoid;
}
.report-table td {
    page-break-inside: avoid;
    break-inside: avoid;
}

/* Prevent categories from splitting awkwardly */
.category-section {
    page-break-before: auto;
    page-break-inside: auto;
}
.page-break {
    page-break-before: always;
}

@media print {
    @page {
        margin: 15mm;
    }
    body { 
        background: #fff; 
        margin: 0; 
        padding: 0; 
    }
    .no-print { 
        display: none !important; 
    }
    .report-table {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        border: none !important;
    }
    .report-table th, .report-table td {
        border: none !important;
    }
    .report-table th {
        border-top: 1px solid #000 !important;
        border-bottom: 1px solid #000 !important;
        border-right: 1px solid #000 !important;
    }
    .report-table td {
        border-top: 1px solid #000 !important;
        border-right: 1px solid #000 !important;
    }
    .report-table tbody tr:first-child td {
        border-top: none !important;
    }
    .report-table th:first-child, .report-table td:first-child {
        border-left: 1px solid #000 !important;
    }
    /* Allow rows to break across pages to prevent large gaps */
    .report-table tr, .report-table td {
        page-break-inside: auto !important;
        break-inside: auto !important;
    }
    
    /* Footer to close the table on page breaks */
    .report-table tfoot {
        display: table-footer-group;
    }
    .report-table tfoot td {
        border-top: 1px solid #000 !important;
        border-bottom: none !important;
        border-left: none !important;
        border-right: none !important;
        height: 0 !important;
        padding: 0 !important;
    }
}
</style>
