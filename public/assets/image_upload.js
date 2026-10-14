(function () {
  'use strict';
  function notify(form) { form.dispatchEvent(new CustomEvent('forum:image-state')); }
  function clear(form) {
    const old = form.__imageState;
    if (old && old.url) URL.revokeObjectURL(old.url);
    form.__imageState = null;
    delete form.__pendingImagePost;
    const input = form.querySelector('[name="image"]');
    if (input) input.value = '';
    notify(form);
  }
  function select(form, file) {
    if (form.__imageBusy) return;
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
  function bind(form) {
    const input = form.querySelector('[name="image"]');
    if (!input || form.__imageBound) return;
    form.__imageBound = true;
    input.addEventListener('change', () => select(form, input.files && input.files[0]));
    form.addEventListener('reset', () => { if (!form.__imageBusy) clear(form); });
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
    if (input) input.disabled = busy;
    notify(form);
  }
  window.ForumImageUpload = {bind,select,clear,ready,lock,has:form=>Boolean(form.__imageState)};
})();
