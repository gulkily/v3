<?php
// First-visit intro: what the site is, for browsers with no saved identity.
// layout.php's head script hides it before paint for everyone else; Hide
// remembers the choice in this browser when storage allows.
?>
<?php if (($siteDescription ?? '') !== ''): ?>
<article class="card visitor-intro" data-visitor-intro>
  <p><?= $e($siteDescription) ?> <a href="/about/">About</a></p>
  <button type="button" data-visitor-intro-hide>Hide</button>
</article>
<script>
  document.querySelector('[data-visitor-intro-hide]').addEventListener('click', function () {
    document.documentElement.setAttribute('data-visitor', 'known');
    try { localStorage.setItem('forum_visitor_intro_hidden', '1'); } catch (error) {}
  });
</script>
<?php endif; ?>
