// Instagram clip: the bookmarklet opens /compose/thread with the post's link in the
// body and ?instagram_post= (plus ?instagram_image=, the image it saw on the page).
// The site fetches that image from Instagram (the browser can't read Instagram's
// image host) and it is attached like a chosen file. The link stays in the body,
// so a failed fetch still leaves a post with the Instagram preview.
(function () {
  'use strict';
  const params = new URLSearchParams(window.location.search);
  const post = params.get('instagram_post');
  if (!post) return;
  const image = params.get('instagram_image') || '';
  // Fetch once: a reload or a draft restore must not attach it again.
  params.delete('instagram_post');
  params.delete('instagram_image');
  try {
    const rest = params.toString();
    history.replaceState(history.state, '', window.location.pathname + (rest ? '?' + rest : '') + window.location.hash);
  } catch {}

  function boundForm() {
    return new Promise(resolve => {
      const started = Date.now();
      (function check() {
        const form = document.querySelector('[data-compose-root] form');
        if (form && form.__imageBound && window.ForumImageUpload) return resolve(form);
        if (Date.now() - started > 30000) return resolve(null);
        setTimeout(check, 200);
      })();
    });
  }

  function notice(form, text) {
    form.__imageInputNotice = text;
    form.dispatchEvent(new CustomEvent('forum:image-state'));
  }

  async function run() {
    const fetched = fetch('/internal/instagram-clip/image?post=' + encodeURIComponent(post) + (image ? '&image=' + encodeURIComponent(image) : ''),
      {credentials: 'same-origin', cache: 'no-store'})
      .then(async response => response.ok ? response.blob() : Promise.reject(new Error(await response.text())));
    const form = await boundForm();
    if (!form) return;
    let blob;
    try {
      blob = await fetched;
    } catch {
      notice(form, "Couldn't get the image from Instagram. Save it and attach it instead.");
      return;
    }
    if (form.__imageState || form.__imageBusy) return; // the poster already attached something
    const type = blob.type || 'image/jpeg';
    const extension = type.split('/')[1] === 'jpeg' ? 'jpg' : (type.split('/')[1] || 'jpg');
    window.ForumImageUpload.select(form, new File([blob], 'instagram.' + extension, {type}));
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else void run();
})();
