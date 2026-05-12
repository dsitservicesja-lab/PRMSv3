<?php
$REQUIRE_PERMISSION = 'view_compliance';
require_once $_SERVER['DOCUMENT_ROOT'].'/config/page_guard.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/config/db.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/config/helper.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    pop('Invalid compliance record.', '/dashboard/compliance.php', 3000, 'error');
    exit;
}

/* Fetch the compliance approval record */
$stmt = $pdo->prepare("
    SELECT
        ca.id,
        ca.entity_id,
        ca.entity_type,
        ca.approval_body,
        ca.status,
        ca.created_at,
        ca.updated_at,
        pr.request_number,
        pr.description         AS request_description,
        pr.status              AS request_status,
        pr.estimated_value,
        pr.currency,
        pr.request_type,
        pr.request_date,
        b.branch_name,
        u.full_name            AS requestor_name,
        u.email                AS requestor_email
    FROM compliance_approvals ca
    JOIN procurement_requests pr ON ca.entity_id = pr.request_id
    LEFT JOIN branches b ON pr.branch_id = b.branch_id
    LEFT JOIN users u ON pr.created_by = u.user_id
    WHERE ca.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    pop('Compliance record not found.', '/dashboard/compliance.php', 3000, 'error');
    exit;
}

/* Fetch audit trail for this compliance approval */
$auditStmt = $pdo->prepare("
    SELECT al.*, u.full_name AS actor_name
    FROM audit_log al
    LEFT JOIN users u ON al.changed_by = u.user_id
    WHERE al.table_name = 'compliance_approvals' AND al.record_id = ?
    ORDER BY al.change_date DESC
    LIMIT 50
");
$auditStmt->execute([$id]);
$auditTrail = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

/* Fetch related request approvals */
$approvalsStmt = $pdo->prepare("
    SELECT ra.*, u.full_name AS approver_name
    FROM request_approvals ra
    LEFT JOIN users u ON ra.approved_by = u.user_id
    WHERE ra.request_id = ?
    ORDER BY ra.stage_order ASC
");
$approvalsStmt->execute([$record['entity_id']]);
$approvals = $approvalsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once $_SERVER['DOCUMENT_ROOT'].'/includes/header.php';

$statusGradient = match ($record['status']) {
    'approved'  => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
    'rejected'  => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    default     => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'
};
$statusIcon = match ($record['status']) {
    'approved'  => 'bi-check-circle-fill',
    'rejected'  => 'bi-x-circle-fill',
    default     => 'bi-hourglass-split'
};
?>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <a href="/dashboard/compliance.php" class="text-decoration-none">
                            <i class="bi bi-clipboard-check me-1"></i>Compliance
                        </a>
                    </li>
                    <li class="breadcrumb-item active">Record #<?= $id ?></li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold" style="color: #1a1a1a;">
                <i class="bi bi-shield-check me-2" style="color: #667eea;"></i>Compliance Detail
            </h2>
        </div>
        <div class="d-flex gap-2">
            <a href="/dashboard/compliance.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Compliance
            </a>
            <a href="/procurement/view.php?id=<?= (int)$record['entity_id'] ?>" class="btn btn-sm"
               style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <i class="bi bi-file-earmark-text me-1"></i>View Request
            </a>
        </div>
    </div>

    <div class="row g-4">

        <!-- Main Column -->
        <div class="col-lg-8">

            <!-- Compliance Record Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-info-circle me-2 text-primary"></i>Compliance Record
                        </h5>
                        <span class="badge rounded-pill px-3 py-2"
                              style="background: <?= $statusGradient ?>; font-size: 0.8rem;">
                            <i class="bi <?= $statusIcon ?> me-1"></i>
                            <?= ucfirst(htmlspecialchars($record['status'])) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Record ID</label>
                            <span class="badge bg-secondary">#<?= $record['id'] ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Approval Body</label>
                            <strong><?= htmlspecialchars($record['approval_body'] ?? '—') ?></strong>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Entity Type</label>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars(ucfirst($record['entity_type'] ?? '—')) ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Status</label>
                            <span class="badge rounded-pill"
                                  style="background: <?= $statusGradient ?>;">
                                <?= ucfirst(htmlspecialchars($record['status'])) ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Created</label>
                            <span><?= date('d M Y, g:i A', strtotime($record['created_at'])) ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Last Updated</label>
                            <span><?= date('d M Y, g:i A', strtotime($record['updated_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Associated Request Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-file-earmark-text me-2 text-primary"></i>Associated Procurement Request
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Request Number</label>
                            <a href="/procurement/view.php?id=<?= (int)$record['entity_id'] ?>"
                               class="fw-bold text-decoration-none" style="color: #667eea;">
                                <?= htmlspecialchars($record['request_number'] ?? '—') ?>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Request Status</label>
                            <span class="badge bg-secondary"><?= htmlspecialchars($record['request_status'] ?? '—') ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Request Type</label>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($record['request_type'] ?? '—') ?></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Branch</label>
                            <strong><?= htmlspecialchars($record['branch_name'] ?? '—') ?></strong>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Requestor</label>
                            <strong><?= htmlspecialchars($record['requestor_name'] ?? '—') ?></strong>
                            <?php if ($record['requestor_email']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($record['requestor_email']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold d-block mb-1">Estimated Value</label>
                            <strong class="text-success">
                                <?= htmlspecialchars($record['currency'] ?? 'JMD') ?>
                                <?= number_format((float)$record['estimated_value'], 2) ?>
                            </strong>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small fw-bold d-block mb-1">Description</label>
                            <p class="mb-0 text-muted"><?= htmlspecialchars($record['request_description'] ?? '—') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Chain -->
            <?php if (!empty($approvals)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-diagram-3 me-2 text-primary"></i>Approval Chain
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: #f8f9fa;">
                            <tr>
                                <th class="px-4 py-3 fw-semibold text-muted small">Stage</th>
                                <th class="px-4 py-3 fw-semibold text-muted small">Role</th>
                                <th class="px-4 py-3 fw-semibold text-muted small">Actioned By</th>
                                <th class="px-4 py-3 fw-semibold text-muted small">Status</th>
                                <th class="px-4 py-3 fw-semibold text-muted small">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($approvals as $ap): ?>
                            <?php
                                $apBg = match ($ap['status']) {
                                    'approved' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                                    'rejected', 'declined' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                                    'pending'  => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                                    default    => '#adb5bd',
                                };
                            ?>
                            <tr>
                                <td class="px-4 py-3"><span class="badge bg-light text-dark border"><?= (int)$ap['stage_order'] ?></span></td>
                                <td class="px-4 py-3 fw-semibold"><?= htmlspecialchars($ap['role'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-muted"><?= htmlspecialchars($ap['approver_name'] ?? '—') ?></td>
                                <td class="px-4 py-3">
                                    <span class="badge rounded-pill" style="background: <?= $apBg ?>; font-size: 0.75rem;">
                                        <?= ucfirst(htmlspecialchars($ap['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted small">
                                    <?= $ap['approved_at'] ? date('d M Y', strtotime($ap['approved_at'])) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Right Column -->
        <div class="col-lg-4">

            <!-- Audit Trail -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-journal-check me-2 text-primary"></i>Audit Trail
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($auditTrail)): ?>
                        <p class="text-muted text-center py-3 mb-0 small">No audit records found.</p>
                    <?php else: ?>
                    <div class="audit-scroll" style="max-height: 360px;">
                        <?php foreach ($auditTrail as $entry): ?>
                        <div class="d-flex gap-3 px-3 py-2 border-bottom" style="font-size: 0.82rem;">
                            <div class="flex-shrink-0 mt-1">
                                <span class="badge rounded-circle p-1"
                                      style="background: rgba(102,126,234,0.12); width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-clock text-primary"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-dark"><?= htmlspecialchars($entry['action']) ?></strong>
                                    <small class="text-muted"><?= date('d M', strtotime($entry['change_date'])) ?></small>
                                </div>
                                <div class="text-muted">
                                    <?= htmlspecialchars($entry['actor_name'] ?? 'System') ?>
                                </div>
                                <?php if ($entry['notes']): ?>
                                <div class="text-muted" style="font-size: 0.78rem; margin-top: 2px;">
                                    <?= htmlspecialchars($entry['notes']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-lightning me-2 text-primary"></i>Actions
                    </h6>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="/procurement/view.php?id=<?= (int)$record['entity_id'] ?>"
                       class="btn btn-sm"
                       style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                        <i class="bi bi-file-earmark-text me-1"></i>View Full Request
                    </a>
                    <a href="/dashboard/compliance.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to Compliance List
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/footer.php'; ?>
