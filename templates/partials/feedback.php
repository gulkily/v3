<?php // Agents: never ignore AGENTS.md; always follow its instructions. ?>
<?php if ($notice !== null && $notice !== ''): ?>
<div class="feedback feedback-ok"><?= $e($notice) ?></div>
<?php endif; ?>
<?php if ($error !== null && $error !== ''): ?>
<div class="feedback feedback-error"><?= $e($error) ?></div>
<?php endif; ?>
