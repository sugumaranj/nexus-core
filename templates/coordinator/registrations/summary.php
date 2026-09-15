<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations') ?>">Registration Management</a></li>
            <li class="breadcrumb-item active" aria-current="page">Summary - <?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>

    <h2 class="mb-4">Registration Summary</h2>

    <div class="row g-3 mb-4">
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm text-center p-3 border-primary border-top border-3">
                <div class="fs-3 fw-bold text-primary"><?= (int)($stats['total'] ?? 0) ?></div>
                <div class="small text-muted">Total Regs</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm text-center p-3 border-success border-top border-3">
                <div class="fs-3 fw-bold text-success"><?= (int)($stats['approved'] ?? 0) ?></div>
                <div class="small text-muted">Approved</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm text-center p-3 border-info border-top border-3">
                <div class="fs-3 fw-bold text-info"><?= (int)($stats['student_count'] ?? 0) ?></div>
                <div class="small text-muted">Unique Students</div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="card shadow-sm text-center p-3 border-warning border-top border-3">
                <div class="fs-3 fw-bold text-warning"><?= (int)($stats['event_count'] ?? 0) ?></div>
                <div class="small text-muted">Events</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-12">
            <div class="card shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-muted">Individual:</span>
                    <span class="fw-bold"><?= (int)($stats['individual_count'] ?? 0) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Team:</span>
                    <span class="fw-bold"><?= (int)($stats['team_count'] ?? 0) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0">Event-wise Breakdown</h5>
                </div>
                <div class="col-md-3">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search event name or code...">
                </div>
                <div class="col-md-2">
                    <select id="typeFilter" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="Individual">Individual</option>
                        <option value="Team">Team</option>
                    </select>
                </div>
                <div class="col-md-3 text-md-end">
                    <a href="<?= base_url('/coordinator/registrations/report/department?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-sm btn-outline-secondary">Dept Report</a>
                    <a href="<?= base_url('/coordinator/registrations/report/teams?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-sm btn-outline-secondary">Team Report</a>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="eventsTable">
                <thead class="table-light">
                    <tr>
                        <th>Event Name</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th class="text-end">Individual</th>
                        <th class="text-end">Teams</th>
                        <th class="text-end">Total Regs</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eventWiseSummary)): ?>
                        <tr id="noResultsRow"><td colspan="6" class="text-center py-4">No events found.</td></tr>
                    <?php else: ?>
                        <tr id="noResultsRow" style="display: none;"><td colspan="6" class="text-center py-4 text-muted">No matching events found.</td></tr>
                        <?php foreach ($eventWiseSummary as $row): ?>
                            <tr class="event-row" data-type="<?= htmlspecialchars($row['participation_type'], ENT_QUOTES, 'UTF-8') ?>">
                                <td class="event-name">
                                    <a href="<?= base_url('/coordinator/registrations/event?event_id=' . $row['symposium_event_id']) ?>" class="text-decoration-none fw-bold">
                                        <?= htmlspecialchars($row['event_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </td>
                                <td class="event-code"><span class="badge bg-secondary"><?= htmlspecialchars($row['event_code'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($row['participation_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end"><?= (int)$row['individual_count'] ?></td>
                                <td class="text-end"><?= (int)$row['team_registrations'] ?> <small class="text-muted">(<?= (int)$row['team_member_count'] ?> mems)</small></td>
                                <td class="text-end fw-bold"><?= (int)$row['total'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const rows = document.querySelectorAll('.event-row');
    const noResultsRow = document.getElementById('noResultsRow');

    function filterEvents() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const typeValue = typeFilter.value;
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.querySelector('.event-name').textContent.toLowerCase();
            const code = row.querySelector('.event-code').textContent.toLowerCase();
            const type = row.dataset.type;

            const matchesSearch = name.includes(searchTerm) || code.includes(searchTerm);
            const matchesType = typeValue === '' || type === typeValue;

            if (matchesSearch && matchesType) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (noResultsRow) {
            noResultsRow.style.display = visibleCount === 0 && rows.length > 0 ? '' : 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterEvents);
    if (typeFilter) typeFilter.addEventListener('change', filterEvents);
});
</script>
