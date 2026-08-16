<?php
// Prefer explicitly passed vars (from controller), else consume session flash once.
$flashSuccess = $success ?? get_flash('success');
$flashError = $error ?? get_flash('error');
?>
<?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e((string) $flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($flashError)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e((string) $flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
