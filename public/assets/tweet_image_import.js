(function () {
  'use strict';
  // Loaded synchronously before the composer; bind runs during parsing, before
  // the form actions can paint. Server HTML remains the same for every viewer.
  if (!location.hash.startsWith('#tweet-image=')) return;
  let source = '', invalid = false;
  try {
    source = decodeURIComponent(location.hash.slice('#tweet-image='.length));
    const url = new URL(source);
    if (source.length > 2048 || url.protocol !== 'https:' || url.host !== 'pbs.twimg.com' || url.username || url.password || url.hash ||
        !/^\/media\/[A-Za-z0-9_-]+(?:\.(?:jpg|jpeg|png|webp)(?::[a-z0-9]+)?)?$/.test(url.pathname)) throw Error();
    const format = url.searchParams.get('format');
    if ((!/\.(?:jpg|jpeg|png|webp)(?::[a-z0-9]+)?$/.test(url.pathname) && !format) ||
        (format && !/^(jpg|jpeg|png|webp)$/.test(format))) throw Error();
  } catch { invalid = true; }

  function forget() {
    if (location.hash.startsWith('#tweet-image=')) history.replaceState(history.state, '', location.pathname + location.search);
  }
  function bind(form) {
    if (!form || form.__tweetImageImportBound) return;
    form.__tweetImageImportBound = true;
    const notice = form.querySelector('[data-tweet-image-import]');
    const status = notice.querySelector('[data-tweet-image-status]');
    const retry = notice.querySelector('[data-tweet-image-retry]');
    const link = notice.querySelector('[data-tweet-image-source]');
    if (!invalid) link.href = source;
    else link.hidden = true;
    let attempt, applying = false;
    function cancel() {
      if (attempt) { attempt.cancelled = true; attempt.controller?.abort(); }
      form.__tweetImageImport = null;
      notice.hidden = true;
      forget();
    }
    function pending() {
      attempt = {cancel, cancelled:false, controller:null};
      form.__tweetImageImport = attempt;
      notice.hidden = false;
      retry.hidden = true;
      status.textContent = 'Importing photo…';
      return attempt;
    }
    const initial = pending();
    // Capture also protects native/anonymous submission before lazy signing is ready,
    // and Outbox when image support is disabled (so ForumImageUpload is absent).
    form.addEventListener('submit', event => {
      if (!form.__tweetImageImport) return;
      event.preventDefault(); event.stopImmediatePropagation();
      status.focus();
    }, true);
    form.addEventListener('click', event => {
      if (form.__tweetImageImport && event.target.closest('[data-action="save-compose-outbox"]')) {
        event.preventDefault(); event.stopImmediatePropagation();
        status.textContent = 'Keep this draft open and attach the photo, or remove it to save text to Outbox.';
      }
      if (event.target.closest('[data-action="clear-compose-fields"]')) cancel();
    }, true);
    form.addEventListener('reset', cancel);
    form.addEventListener('forum:image-state', () => {
      if (!applying && !form.__tweetImageImport && !form.__imageState) cancel();
    });
    notice.querySelector('[data-tweet-image-remove]').addEventListener('click', () => {
      cancel();
      window.ForumImageUpload?.clear(form);
    });
    retry.addEventListener('click', () => {
      if (attempt) { attempt.cancelled = true; attempt.controller?.abort(); }
      void run(pending());
    });
    async function run(current) {
      if (current.cancelled) return;
      let timer;
      try {
        if (invalid) throw new Error('Invalid photo URL. Attach the photo manually or remove it.');
        await window.ForumLazyComposeSigning?.load();
        window.ForumBrowserSigning?.init(document);
        const uploader = window.ForumImageUpload;
        if (!uploader || !form.__imageIdentity) throw new Error('Image uploading is unavailable. Attach the photo when image support is enabled, or remove it.');
        await uploader.assertEligible(form);
        if (current.cancelled) return;
        current.controller = new AbortController();
        timer = setTimeout(() => current.controller.abort(), 15000);
        const response = await fetch(source, {credentials:'omit',referrerPolicy:'no-referrer',redirect:'error',signal:current.controller.signal});
        if (!response.ok || !/^image\/(jpeg|png|webp)(?:;|$)/i.test(response.headers.get('content-type') || '')) throw new Error('Photo download failed. Download it from Photo source and attach it, or retry.');
        const max = 32 * 1024 * 1024;
        if (Number(response.headers.get('content-length')) > max) throw new Error('Photo exceeds 32 MiB. Download a smaller copy and attach it.');
        const reader = response.body.getReader(), parts = [];
        let bytes = 0;
        while (true) {
          const {done, value} = await reader.read();
          if (done) break;
          bytes += value.byteLength;
          if (bytes > max) { await reader.cancel(); throw new Error('Photo exceeds 32 MiB. Download a smaller copy and attach it.'); }
          parts.push(value);
        }
        if (current.cancelled) return;
        form.__tweetImageImport = null;
        applying = true;
        try { uploader.select(form, new File(parts, 'tweet-photo', {type:response.headers.get('content-type')})); }
        finally { applying = false; }
        const selected = form.__imageState;
        await uploader.ready(form);
        if (current.cancelled || form.__imageState !== selected) return;
        notice.hidden = true;
      } catch (error) {
        if (current.cancelled) return;
        form.__tweetImageImport = current;
        notice.hidden = false;
        status.textContent = error.name === 'AbortError' ? 'Photo download timed out. Retry or download it from Photo source and attach it.' : (error instanceof TypeError ? 'Photo download failed. Retry or download it from Photo source and attach it.' : (error.message || 'Photo import failed. Retry or attach it manually.'));
        retry.hidden = false;
      } finally { clearTimeout(timer); current.controller?.abort(); }
    }
    document.addEventListener('DOMContentLoaded', () => { void run(initial); }, {once:true});
  }
  window.ForumTweetImageImport = {bind};
})();
