<?php if ($notice !== null && $notice !== ''): ?>
<div class="feedback feedback-ok"><?= $notice ?></div>
<?php endif; ?>
<?php if ($error !== null && $error !== ''): ?>
<div class="feedback feedback-error"><?= $e($error) ?></div>
<?php endif; ?>
