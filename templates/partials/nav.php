<nav class="nav">
<?php foreach ($navItems as $item): ?>
<?php $class = $item['section'] === $activeSection ? 'nav-link is-active' : 'nav-link'; ?>
  <a class="<?= $e($class) ?>" href="<?= $e($item['href']) ?>"<?= !empty($item['invite_action']) ? ' data-invite-navigation' : '' ?><?= $item['section'] === 'messages' ? ' data-private-message-unread data-viewer="' . $e($item['viewer'] ?? '') . '" aria-label="Messages" aria-describedby="private-message-unread-status"' : '' ?>><?= $e($item['label']) ?><?php if ($item['section'] === 'messages'): ?> <span data-role="unread-count" aria-hidden="true">…</span><span id="private-message-unread-status" hidden>Unread status loading</span><?php endif; ?></a>
<?php endforeach; ?>
</nav>
