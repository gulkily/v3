<?php
/**
 * Header for the `app` navigation slot: main destinations in one nav (top
 * bar on desktop, tab bar on phones), one New post, and an account menu that
 * holds the remaining destinations and the theme controls. The account menu
 * is a native disclosure, so it opens without JavaScript.
 *
 * @var list<array<string, mixed>> $navItems
 */
$menuItems = array_values(array_filter($navItems, static fn (array $item): bool => ($item['placement'] ?? 'main') === 'menu'));
$canPost = array_filter($navItems, static fn (array $item): bool => $item['section'] === 'board') !== [];
?>
<header class="site-header site-header--app">
  <p class="eyebrow"><a class="site-brand" href="/"><span class="site-brand__mark" aria-hidden="true"></span><?= $e($siteName) ?></a></p>
  <div class="site-header-actions">
<?= $indent($partial('partials/nav.php'), 2) ?>
<?php if ($canPost): ?>
    <a class="new-post-button<?= $activeSection === 'compose' ? ' is-active' : '' ?>" href="/compose/thread" data-tab="compose"><span class="new-post-button__icon" aria-hidden="true">+</span><span class="new-post-button__label">New post</span></a>
<?php endif; ?>
    <details class="account-menu" data-role="account-menu" data-tab="account">
      <summary class="account-menu__trigger" aria-label="You, account menu"><span class="account-menu__icon" aria-hidden="true"></span><span class="account-menu__label">You</span></summary>
      <div class="account-menu__panel">
        <nav class="account-menu__links" aria-label="Account and more">
<?php foreach ($menuItems as $item): ?>
<?php $class = $item['section'] === $activeSection ? 'nav-link is-active' : 'nav-link'; ?>
          <a data-tab="<?= $e($item['section']) ?>" class="<?= $e($class) ?>" href="<?= $e($item['href']) ?>"<?= !empty($item['invite_action']) ? ' data-invite-navigation' : '' ?>><?= $e($item['label']) ?></a>
<?php endforeach; ?>
        </nav>
        <div class="account-menu__controls">
<?php if ($showThreadDensityToggle): ?>
<?= $indent($partial('partials/thread_density_toggle.php'), 5) ?>
<?php endif; ?>
<?= $indent($partial('partials/theme_menu.php'), 5) ?>
        </div>
      </div>
    </details>
  </div>
</header>
