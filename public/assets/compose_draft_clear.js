(function () {
  var storage = null;
  try {
    storage = window.sessionStorage;
  } catch (error) {
    storage = null;
  }

  var recentlyClearedDraftStorageKey = 'forum_recently_cleared_compose_draft';

  function readCookie(name) {
    if (!document || typeof document.cookie !== 'string' || document.cookie === '') {
      return '';
    }

    var prefix = name + '=';
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i += 1) {
      var cookie = String(cookies[i] || '').trim();
      if (cookie.indexOf(prefix) !== 0) {
        continue;
      }

      try {
        return decodeURIComponent(cookie.slice(prefix.length));
      } catch (error) {
        return cookie.slice(prefix.length);
      }
    }

    return '';
  }

  function expireCookie(name) {
    try {
      document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
    } catch (error) {
    }
  }

  function rememberRecentlyClearedDraft(key) {
    if (!storage || key === '') {
      return;
    }

    try {
      storage.setItem(recentlyClearedDraftStorageKey, key);
    } catch (error) {
    }
  }

  function consumePendingComposeDraftClear() {
    var draftKey = readCookie('forum_clear_compose_draft');
    if (draftKey === '') {
      return;
    }

    try {
      window.localStorage.removeItem(draftKey);
    } catch (error) {
    }

    rememberRecentlyClearedDraft(draftKey);
    expireCookie('forum_clear_compose_draft');
  }

  consumePendingComposeDraftClear();
})();
