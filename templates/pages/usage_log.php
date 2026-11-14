<section class="stack" data-usage-log>
  <article class="card">
    <h1>Usage log</h1>
    <p>Help us find the places where getting somewhere takes more clicks than it should. While recording is on, this browser notes each page you open and each link or button you click. Nothing leaves this browser until you press Upload or copy the log yourself. An upload goes to this site's operators with your username if you are signed in, or anonymously if not.</p>
    <p class="meta">Each line holds the kind of page with names and numbers replaced by <code>*</code> (a thread is <code>/threads/*</code>), the part of the page you clicked, the button or link's label, and the seconds since the line before. It never holds anything you type. The log keeps the last 2,000 lines from the last 30 days, and this page itself is never recorded.</p>
    <p class="meta" data-role="usage-log-status" aria-live="polite">This page needs JavaScript to read the log in this browser.</p>
    <label class="usage-log-switch">
      <input type="checkbox" data-role="usage-log-recording" disabled>
      Record my page views and clicks in this browser
    </label>
    <label for="usage-log-text">Your log</label>
    <textarea id="usage-log-text" data-role="usage-log-text" rows="12" readonly spellcheck="false"></textarea>
    <div class="button-row">
      <button type="button" data-action="usage-log-upload" disabled>Upload</button>
      <button type="button" class="account-key-secondary-button" data-action="usage-log-copy" disabled>Copy</button>
      <button type="button" class="account-key-secondary-button" data-action="usage-log-clear" disabled>Clear</button>
    </div>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
