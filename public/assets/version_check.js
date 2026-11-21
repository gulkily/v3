(function () {
  var storage = null;
  try {
    storage = window.sessionStorage;
  } catch (error) {
    storage = null;
  }

  var storageKeyMeta = document.querySelector('meta[name="app-version-storage-key"]');
  var pendingVersionStorageKey = storageKeyMeta
    ? String(storageKeyMeta.getAttribute('content') || 'forum_pending_app_version') : 'forum_pending_app_version';
  var versionMeta = document.querySelector('meta[name="app-version"]');
  if (!versionMeta) {
    return;
  }

  var currentVersion = String(versionMeta.getAttribute('content') || '').trim();
  var pageVersionMeta = document.querySelector('meta[name="page-version"]');
  var currentPageVersion = pageVersionMeta ? String(pageVersionMeta.getAttribute('content') || '').trim() : '';
  var pendingPageVersionStorageKey = 'forum_pending_page_version';
  if (currentVersion === '') {
    return;
  }

  var endpointMeta = document.querySelector('meta[name="app-version-endpoint"]');
  var versionEndpoint = endpointMeta ? String(endpointMeta.getAttribute('content') || '').trim() : '/api/version';
  if (versionEndpoint === '' || typeof window.fetch !== 'function') {
    return;
  }

  var banner = document.querySelector('[data-role="app-version-banner"]');
  var reloadButton = banner ? banner.querySelector('[data-action="reload-for-new-version"]') : null;
  var nextVersion = null;
  var nextVersionStorageKey = pendingVersionStorageKey;
  var requestInFlight = false;
  var versionCheckDelay = 15000;
  var maximumVersionCheckDelay = 120000;
  var versionCheckTimer = null;

  function storedPendingVersion(storageKey) {
    if (!storage) {
      return '';
    }

    try {
      return String(storage.getItem(storageKey || pendingVersionStorageKey) || '').trim();
    } catch (error) {
      return '';
    }
  }

  function persistPendingVersion(version, storageKey) {
    if (!storage) {
      return;
    }

    try {
      if (version === '') {
        storage.removeItem(storageKey || pendingVersionStorageKey);
      } else {
        storage.setItem(storageKey || pendingVersionStorageKey, version);
      }
    } catch (error) {
    }
  }

  function withVersionBypass(url, version) {
    var target = new URL(url, window.location.origin);
    target.searchParams.set('__v', version);
    return target.toString();
  }

  function showBanner(version, storageKey) {
    nextVersion = version;
    nextVersionStorageKey = storageKey || pendingVersionStorageKey;
    persistPendingVersion(version, nextVersionStorageKey);
    if (!banner) {
      return;
    }

    banner.hidden = false;
  }

  function reloadForNewVersion() {
    var version = nextVersion || currentVersion;
    persistPendingVersion(version, nextVersionStorageKey);
    window.location.assign(withVersionBypass(window.location.href, version));
  }

  function ensurePendingVersionSatisfied(version, storageKey) {
    version = version || currentVersion;
    var pendingVersion = storedPendingVersion(storageKey);
    if (pendingVersion === '') {
      return false;
    }

    if (pendingVersion === version) {
      persistPendingVersion('', storageKey);
      return false;
    }

    var currentUrl = new URL(window.location.href, window.location.origin);
    if (currentUrl.searchParams.get('__v') === pendingVersion) {
      // We already reloaded specifically to pick up pendingVersion. The
      // server's live version can keep moving between the poll that set
      // pendingVersion and this reload landing, so currentVersion may never
      // equal that exact target. Treat the reload as satisfied rather than
      // re-showing the banner forever, and re-poll immediately so a
      // genuinely stale response (e.g. a cached page) is caught right away.
      persistPendingVersion('', storageKey);
      checkForNewVersion(true);
      return false;
    }

    // Navbar pages from last copy: this page may be an older copy shown on purpose.
    // Offer the newer version instead of replacing the page the reader is looking at.
    var root = document.documentElement;
    if (root && typeof root.getAttribute === 'function' && root.getAttribute('data-nav-cached-copy') === '1') {
      showBanner(pendingVersion, storageKey);
      return false;
    }

    // url_hints.js runs next; tell it not to touch the URL while this navigation is pending.
    if (document.documentElement) document.documentElement.setAttribute('data-version-redirect', '');
    window.location.replace(withVersionBypass(currentUrl.toString(), pendingVersion));
    return true;
  }

  // For nav_cached_copy.js: raise the notice when a background copy turns out newer.
  window.ForumAppVersion = {
    current: currentVersion,
    show: function (version) {
      var value = String(version || '').trim();
      if (value !== '' && value !== currentVersion) showBanner(value);
    },
    showPage: function (version) {
      var value = String(version || '').trim();
      // An older worker only knows app-version; an equal code revision is not fresh content.
      if (currentPageVersion !== '' && value !== '' && value !== currentPageVersion && value !== currentVersion) {
        showBanner(value, pendingPageVersionStorageKey);
      }
    }
  };

  if (ensurePendingVersionSatisfied()) {
    return;
  }
  if (currentPageVersion !== '' && ensurePendingVersionSatisfied(currentPageVersion, pendingPageVersionStorageKey)) {
    return;
  }

  function scheduleNextVersionCheck() {
    if (versionCheckTimer !== null) {
      return;
    }

    var delay = versionCheckDelay;
    versionCheckDelay = Math.min(versionCheckDelay * 2, maximumVersionCheckDelay);
    versionCheckTimer = window.setTimeout(function () {
      versionCheckTimer = null;
      checkForNewVersion(true);
    }, delay);
  }

  function checkForNewVersion(scheduleAfterCheck) {
    if (requestInFlight) {
      if (scheduleAfterCheck) {
        scheduleNextVersionCheck();
      }
      return;
    }

    requestInFlight = true;

    window.fetch(withVersionBypass(versionEndpoint, Date.now().toString()), {
      cache: 'no-store',
      headers: {
        Accept: 'text/plain'
      }
    }).then(function (response) {
      if (!response.ok) {
        return '';
      }

      return response.text();
    }).then(function (text) {
      var latestVersion = String(text || '').trim();
      if (latestVersion !== '' && latestVersion !== currentVersion) {
        showBanner(latestVersion);
      }
    }).catch(function () {
    }).finally(function () {
      requestInFlight = false;
      if (scheduleAfterCheck) {
        scheduleNextVersionCheck();
      }
    });
  }

  if (reloadButton) {
    reloadButton.addEventListener('click', reloadForNewVersion);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      checkForNewVersion(false);
    }
  });

  window.addEventListener('pageshow', function () {
    ensurePendingVersionSatisfied();
    if (currentPageVersion !== '') ensurePendingVersionSatisfied(currentPageVersion, pendingPageVersionStorageKey);
  });

  scheduleNextVersionCheck();
})();
