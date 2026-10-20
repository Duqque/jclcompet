<?php foreach ($flash ?? [] as $f): ?>
<div class="alert alert-<?= e($f['type'] === 'error' ? 'error' : ($f['type'] === 'warning' ? 'warning' : ($f['type'] === 'info' ? 'info' : 'success'))) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?php if (!empty($GLOBALS['__errors'])): ?>
<div class="alert alert-error" role="alert"><ul style="margin:0;padding-left:1.1rem"><?php foreach ($GLOBALS['__errors'] as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
