<?php
/** @var list<array> $rows */
if ($rows === []): ?>
    <div class="empty-state py-4">
        <i class="bi bi-inbox"></i>
        <p class="mb-0">Tidak ada case pada kategori ini.</p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table master-table align-middle mb-0">
            <thead>
            <tr>
                <th>Nomor</th>
                <th>WP</th>
                <th>Petugas</th>
                <th>Jatuh Tempo</th>
                <th>Indikator</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/cases/' . $row['id'])) ?>"><?= e($row['case_number']) ?></a></td>
                    <td><?= e($row['taxpayer_name']) ?></td>
                    <td><?= e($row['officer_name']) ?></td>
                    <td><?= e(format_date_id($row['due_date'])) ?></td>
                    <td><?= deadline_badge($row['deadline']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
