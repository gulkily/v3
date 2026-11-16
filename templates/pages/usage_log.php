<section class="stack" data-usage-log data-usage-names="<?= $e($usageNamesJson) ?>">
  <article class="card">
    <h1>Usage log</h1>
    <div class="usage-status" data-role="usage-log-status-card" data-state="unknown">
      <span class="usage-status__dot" aria-hidden="true"></span>
      <div class="usage-status__text">
        <strong data-role="usage-log-state">Usage log</strong>
        <span class="meta" data-role="usage-log-count">This page needs JavaScript to read the log in this browser.</span>
      </div>
      <label class="usage-log-switch usage-status__switch">
        <input type="checkbox" data-role="usage-log-recording" disabled>
        Record
      </label>
    </div>
    <p class="meta" data-role="usage-log-status" aria-live="polite"></p>
    <p>Your steps stay in this browser until you upload them. They show which pages you open and what you click, never what you type. This page itself is never recorded.</p>

    <h2 class="usage-log-heading">Latest steps</h2>
    <p class="meta" data-role="usage-log-empty">No steps yet. Turn on recording and browse the site.</p>
    <ol class="usage-log-steps" data-role="usage-log-steps"></ol>
    <button type="button" class="account-key-secondary-button usage-log-more" data-action="usage-log-show-all" hidden>Show all steps</button>

    <details class="usage-log-exact">
      <summary>Show exact text (what Upload sends)</summary>
      <p class="meta">One line per step: the kind of page with names and numbers replaced by <code>*</code> (a thread is <code>/threads/*</code>), the part of the page, the button or link's label, and the seconds since the line before. The log keeps the last 2,000 lines from the last 30 days.</p>
      <label for="usage-log-text">Exact text</label>
      <textarea id="usage-log-text" data-role="usage-log-text" rows="10" readonly spellcheck="false"></textarea>
    </details>

    <div class="usage-log-actions">
      <button type="button" class="usage-log-upload" data-action="usage-log-upload" disabled>Upload</button>
      <button type="button" class="account-key-secondary-button" data-action="usage-log-copy" disabled>Copy</button>
      <button type="button" class="account-key-secondary-button" data-action="usage-log-clear" disabled>Clear</button>
    </div>
    <p class="meta usage-log-who">Uploads go to this site's operators <?php if (($uploaderName ?? null) !== null): ?>as <strong><?= $e($uploaderName) ?></strong><?php else: ?>anonymously (you are not signed in)<?php endif; ?>.</p>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
