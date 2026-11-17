(function () {
  var form = document.querySelector('[data-local-import]');
  if (!form) return;
  window.addEventListener('pageshow', function () {
    form.removeAttribute('aria-busy');
    form.querySelector('[data-local-import-pending]').hidden = true;
    form.querySelector('button[type="submit"]').removeAttribute('aria-disabled');
  });
  form.addEventListener('submit', function (event) {
    if (form.getAttribute('aria-busy') === 'true') {
      event.preventDefault();
      return;
    }
    form.setAttribute('aria-busy', 'true');
    form.querySelector('[data-local-import-pending]').hidden = false;
    // Preserve the submitter's action in the native request, including Resume.
    form.querySelector('button[type="submit"]').setAttribute('aria-disabled', 'true');
  });
})();
