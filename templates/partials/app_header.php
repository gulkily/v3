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
// Phones: bar destinations that do not fit the tab bar are listed in the same
// panel, which is labelled More there. Desktop hides this list; the links are in the bar.
$moreItems = array_values(array_filter($navItems, static fn (array $item): bool => ($item['placement'] ?? 'main') === 'main' && ($item['phone'] ?? 'bar') === 'more'));
$moreActive = array_filter($moreItems, static fn (array $item): bool => $item['section'] === $activeSection) !== [];
$canPost = array_filter($navItems, static fn (array $item): bool => $item['section'] === 'board') !== [];
?>
<header class="site-header site-header--app">
  <p class="eyebrow"><a class="site-brand" href="/"><span class="site-brand__mark" aria-hidden="true"></span><?= $e($siteName) ?></a></p>
  <div class="site-header-actions">
<?= $indent($partial('partials/nav.php'), 2) ?>
<?php if ($canPost): ?>
    <a class="new-post-button<?= $activeSection === 'compose' ? ' is-active' : '' ?>" href="/compose/thread" data-tab="compose"><span class="new-post-button__icon" aria-hidden="true">+</span><span class="new-post-button__label">New post</span></a>
<?php endif; ?>
    <details class="account-menu<?= $moreActive ? ' is-active' : '' ?>" data-role="account-menu" data-tab="account">
      <summary class="account-menu__trigger" aria-label="Menu"><span class="account-menu__icon" aria-hidden="true"></span><span class="account-menu__label account-menu__label--you">You</span><span class="account-menu__label account-menu__label--more">More</span></summary>
      <div class="account-menu__panel">
<?php if ($moreItems !== []): ?>
        <nav class="account-menu__links account-menu__more" aria-label="More destinations">
<?php foreach ($moreItems as $item): ?>
<?php $class = $item['section'] === $activeSection ? 'nav-link is-active' : 'nav-link'; ?>
          <a data-more="<?= $e($item['section']) ?>" class="<?= $e($class) ?>" href="<?= $e($item['href']) ?>"<?= !empty($item['member_only']) ? ' data-member-only' : '' ?>><?= $e($item['label']) ?></a>
<?php endforeach; ?>
        </nav>
<?php endif; ?>
        <nav class="account-menu__links" aria-label="Account">
<?php foreach ($menuItems as $item): ?>
<?php $class = $item['section'] === $activeSection ? 'nav-link is-active' : 'nav-link'; ?>
          <a data-tab="<?= $e($item['section']) ?>" class="<?= $e($class) ?>" href="<?= $e($item['href']) ?>"<?= !empty($item['member_only']) ? ' data-member-only' : '' ?><?= !empty($item['invite_action']) ? ' data-invite-navigation' : '' ?>><?= $e($item['label']) ?></a>
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
