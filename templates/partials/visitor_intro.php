<?php
// First-visit intro: what the site is, for browsers with no saved identity.
// layout.php's head script hides it before paint for everyone else.
?>
<?php if (($siteDescription ?? '') !== ''): ?>
<article class="card visitor-intro" data-visitor-intro>
  <p><?= $e($siteDescription) ?> <a href="/about/">About</a></p>
</article>
<?php endif; ?>
