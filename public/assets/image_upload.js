(function () {
  'use strict';
  function notify(form) { form.dispatchEvent(new CustomEvent('forum:image-state')); }
  function clear(form) {
    const old = form.__imageState;
    if (old && old.url) URL.revokeObjectURL(old.url);
    form.__imageState = null;
    form.__imageInputNotice = null;
    delete form.__pendingImagePost;
    const input = form.querySelector('[name="image"]');
    if (input) input.value = '';
    notify(form);
  }
  function select(form, file) {
    if (form.__imageBusy || form.__imageEligible === false) return;
    clear(form);
    if (!file) return;
    const state = {file:null,error:null,promise:null,url:null};
    form.__imageState = state;
    state.promise = window.ForumImagePrepare.prepare(file).then(prepared => {
      if (form.__imageState !== state) return;
      Object.assign(state, prepared);
      state.url = URL.createObjectURL(prepared.file);
    }).catch(error => {
      if (form.__imageState === state) state.error = error;
    }).finally(() => { if (form.__imageState === state) notify(form); });
    notify(form);
  }
  function bind(form, identity) {
    const input = form.querySelector('[name="image"]');
    if (!input || form.__imageBound) return;
    form.__imageBound = true;
    input.addEventListener('change', () => select(form, input.files && input.files[0]));
    form.addEventListener('reset', () => { if (!form.__imageBusy) clear(form); });
    const fields = form.querySelector('.image-compose-fields');
    if (fields) {
      fields.querySelector('.image-upload-choose').addEventListener('click', () => input.click());
      fields.querySelector('.image-upload-remove').addEventListener('click', () => { if (!form.__imageBusy) clear(form); });
      form.querySelector('.image-upload-recovery button')?.addEventListener('click', () => { if (!form.__imageBusy) clear(form); });
      form.addEventListener('forum:image-state', () => render(form));
      const chooseFiles = files => {
        if (!form.__imageEligible || form.__imageBusy) return;
        if (files.length !== 1) { form.__imageInputNotice = 'Attach one image at a time.'; notify(form); return; }
        select(form, files[0]);
      };
      form.addEventListener('dragover', event => {
        if (Array.from(event.dataTransfer?.types || []).includes('Files')) {
          event.preventDefault();
          if (form.__imageEligible && !form.__imageBusy) fields.querySelector('.image-upload-dropzone').classList.add('is-dragging');
        }
      });
      form.addEventListener('dragleave', event => { if (!form.contains(event.relatedTarget)) fields.querySelector('.image-upload-dropzone').classList.remove('is-dragging'); });
      form.addEventListener('drop', event => {
        fields.querySelector('.image-upload-dropzone').classList.remove('is-dragging');
        if (event.dataTransfer?.files.length) { event.preventDefault(); chooseFiles(Array.from(event.dataTransfer.files)); }
      });
      form.addEventListener('paste', event => {
        if (event.clipboardData?.getData('text/plain')) return;
        const files = Array.from(event.clipboardData?.items || []).filter(item=>item.kind==='file').map(item=>item.getAsFile()).filter(Boolean);
        if (files.length && form.__imageEligible && !form.__imageBusy) { event.preventDefault(); chooseFiles(files); }
      });
      render(form);
    }
    if (identity) {
      form.__imageIdentity = identity;
      const refresh = () => { void refreshEligibility(form); };
      ['forum:identity-changed','storage','online','offline','focus'].forEach(name => window.addEventListener(name, refresh));
      document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
      refresh();
    }
  }
  function render(form) {
    const fields = form.querySelector('.image-compose-fields');
    if (!fields) return;
    const state = form.__imageState, busy = Boolean(form.__imageBusy);
    const preview = fields.querySelector('.image-upload-preview');
    const img = preview.querySelector('img');
    preview.hidden = !state?.url;
    if (state?.url) { if (img.getAttribute('src') !== state.url) img.src = state.url; }
    else img.removeAttribute('src');
    const status = fields.querySelector('.image-upload-status');
    status.textContent = form.__imageInputNotice || state?.error?.message || (state ? (state.file ? `${state.width} × ${state.height} · ${Math.ceil(state.file.size / 1024)} KiB. ${state.note}` : 'Preparing image on your device…') : '');
    status.dataset.error = String(Boolean(form.__imageInputNotice || state?.error));
    fields.querySelector('.image-upload-choose').textContent = state ? 'Replace image' : 'Choose an image';
    const remove = fields.querySelector('.image-upload-remove');remove.hidden = !state;
    fields.querySelectorAll('button').forEach(button=>{button.disabled=busy || form.__imageEligible===false;});
    const recovery = form.querySelector('.image-upload-recovery');
    if (recovery) { recovery.hidden = !state || form.__imageEligible !== false; recovery.querySelector('button').disabled = busy; }
  }
  async function ready(form) {
    const state = form.__imageState;
    if (!state) throw new Error('Select an image first.');
    await state.promise;
    if (form.__imageState !== state) throw new Error('The attachment changed. Review the preview and try again.');
    if (state.error) throw state.error;
    if (!state.file) throw new Error('Image preparation has not completed.');
    return state;
  }
  function lock(form, busy) {
    form.__imageBusy = busy;
    const input = form.querySelector('[name="image"]');
    if (input) input.disabled = busy || form.__imageEligible === false;
    notify(form);
  }
  function displayEligibility(form, allowed) {
    form.__imageEligible = allowed;
    const fields = form.querySelector('.image-compose-fields');
    if (fields) fields.hidden = !allowed;
    lock(form, Boolean(form.__imageBusy));
  }
  async function refreshEligibility(form) {
    const serial = form.__imageEligibilitySerial = (form.__imageEligibilitySerial || 0) + 1;
    displayEligibility(form, false);
    let timer;
    try {
      if (!navigator.onLine || !window.isSecureContext || !window.crypto?.subtle || document.body.dataset.imageContentState !== '1') return false;
      const identity = await form.__imageIdentity();
      if (!/^openpgp:[a-f0-9]{40,64}$/.test(identity)) return false;
      const controller = new AbortController();
      timer = setTimeout(() => controller.abort(), 8000);
      const response = await fetch('/api/get_profile?profile_slug=' + encodeURIComponent(identity.replace(':','-')), {credentials:'same-origin',cache:'no-store',signal:controller.signal});
      const text = await response.text();
      const values = Object.fromEntries(text.split('\n').filter(line=>line.includes(': ')).map(line=>{const at=line.indexOf(': ');return [line.slice(0,at),line.slice(at+2)];}));
      const allowed = response.ok && values['Identity-ID'] === identity && values['Approved'] === 'yes' && values['Image-Upload-Allowed'] === 'yes';
      if (serial !== form.__imageEligibilitySerial || identity !== await form.__imageIdentity()) return false;
      displayEligibility(form, allowed);
      return allowed;
    } catch { return false; } finally { clearTimeout(timer); }
  }
  async function assertEligible(form) {
    if (!await refreshEligibility(form)) throw new Error('Image uploading is unavailable for this identity or connection. Your draft is retained; reconnect or remove the attachment to post text.');
  }
  window.ForumImageUpload = {bind,select,clear,ready,lock,assertEligible,refreshEligibility,has:form=>Boolean(form.__imageState)};
})();
