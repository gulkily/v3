<div class="stack" data-tweet-image-import hidden>
  <p class="meta" role="status" aria-live="polite" data-tweet-image-status tabindex="-1">Importing photo…</p>
  <div class="compose-form-actions">
    <a data-tweet-image-source target="_blank" rel="noopener noreferrer">Photo source</a>
    <button type="button" data-tweet-image-retry hidden>Retry</button>
    <button type="button" data-tweet-image-remove>Remove photo</button>
  </div>
</div>
<script>
(function (form) {
  if (window.ForumTweetImageImport) { window.ForumTweetImageImport.bind(form); return; }
  if (location.hash.indexOf('#tweet-image=') !== 0) return;
  // Retain attachment intent even when the import asset itself cannot load.
  var notice = form.querySelector('[data-tweet-image-import]');
  notice.hidden = false;
  notice.querySelector('[data-tweet-image-source]').hidden = true;
  notice.querySelector('[data-tweet-image-status]').textContent = 'Photo import is unavailable. Attach the photo manually, or remove it to post text.';
  function cancel() {
    form.__tweetImageImport = null;
    notice.hidden = true;
    history.replaceState(history.state, '', location.pathname + location.search);
  }
  form.__tweetImageImport = {cancel: cancel};
  notice.querySelector('[data-tweet-image-remove]').addEventListener('click', cancel);
  form.addEventListener('reset', cancel);
  form.addEventListener('submit', function (event) {
    if (form.__tweetImageImport) { event.preventDefault(); event.stopImmediatePropagation(); }
  }, true);
  form.addEventListener('click', function (event) {
    if (event.target.closest('[data-action="clear-compose-fields"]')) cancel();
    if (form.__tweetImageImport && event.target.closest('[data-action="save-compose-outbox"]')) {
      event.preventDefault(); event.stopImmediatePropagation();
    }
  }, true);
})(document.currentScript.closest('form'));
</script>
