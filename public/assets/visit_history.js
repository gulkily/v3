(function () {
  'use strict';
  // Visit history (FORUM_VISIT_HISTORY_ENABLED, cycle nav_tree): every page
  // change the reader makes in this browser becomes a visit, and each visit
  // remembers the visit it came from, so history is a tree. Tools → History
  // lists it. Nothing here is sent to the site, and the usage log never reads it.
  //
  // Storage (internal browser state, JSON, like forum_nav_slots):
  //   localStorage forum_visit_history =
  //     { "v": 1, "visits": [ { "id": "…", "t": 1763150000, "url": "/threads/x", "title": "…", "parent": "…", "beside": true }, … ] }
  //   sessionStorage (one tab; the Beside pane shares it with its main page):
  //     forum_visit_cursor       this tab's current visit
  //     forum_visit_pane_cursor  the Beside pane's current visit
  //     forum_visit_pane_next    what the next pane load is: "open" or "restore"
  //     forum_visit_alone        set by Open alone for the next main page
  // Visits are only ever added (append-only), so syncing them later stays possible.
  // A visit whose parent was dropped by the cap is the top of its own branch.
  //
  // Parent rule: the tab's current visit; a tab with none (a new tab, a link
  // from another site) uses the latest visit whose address is the referrer.
  // Back and forward, page-cache restores and same-address loads move the
  // cursor and add nothing. In the Beside pane (open_beside.js): a page opened
  // from the main page hangs under the main page's visit, a link followed in
  // the pane under the pane's last visit; a restored pane and a theme reload
  // add nothing; Open alone moves the tab to the pane's visit.
  var KEY = 'forum_visit_history';
  var MAIN_CURSOR = 'forum_visit_cursor';
  var PANE_CURSOR = 'forum_visit_pane_cursor';
  var PANE_NEXT = 'forum_visit_pane_next';
  var ALONE = 'forum_visit_alone';
  var MAX_VISITS = 2000;
  var TITLE_LENGTH = 120;

  // Top-level pages and the Beside pane record visits; any other frame (the tour) records nothing.
  var framed = false;
  try { framed = window.top !== window; } catch (error) { framed = true; }
  var inPane = framed && window.name === 'forum-open-beside'
    && document.documentElement.hasAttribute('data-open-beside-framed');
  if (framed && !inPane) return;
  var CURSOR = inPane ? PANE_CURSOR : MAIN_CURSOR;

  function read() {
    try {
      var state = JSON.parse(localStorage.getItem(KEY) || 'null');
      return state && state.v === 1 && Array.isArray(state.visits) ? state.visits : [];
    } catch (error) {
      // A damaged key is replaced on the next write, not repaired.
      return [];
    }
  }
  function write(visits) {
    try {
      localStorage.setItem(KEY, JSON.stringify({ v: 1, visits: visits.slice(-MAX_VISITS) }));
      return true;
    } catch (error) {
      return false;
    }
  }
  function session(key) {
    try { return sessionStorage.getItem(key); } catch (error) { return null; }
  }
  function setSession(key, value) {
    try {
      if (value) sessionStorage.setItem(key, value); else sessionStorage.removeItem(key);
    } catch (error) {
    }
  }
  // Read once and forget.
  function take(key) {
    var value = session(key);
    if (value !== null) setSession(key, null);
    return value;
  }
  function cursor() { return session(CURSOR); }
  function setCursor(id) { setSession(CURSOR, id); }
  function find(visits, id) {
    if (!id) return null;
    for (var i = visits.length - 1; i >= 0; i--) if (visits[i] && visits[i].id === id) return visits[i];
    return null;
  }
  function latest(visits, test) {
    for (var i = visits.length - 1; i >= 0; i--) if (visits[i] && test(visits[i])) return visits[i];
    return null;
  }
  // An address is the path and query; a spot on the page (#…) is the same page.
  // The one-time hints url_hints.js drops (__v, created_post_id) are never kept.
  function address(href) {
    try {
      var url = new URL(href, location.href);
      if (url.origin !== location.origin) return null;
      url.searchParams.delete('__v');
      url.searchParams.delete('created_post_id');
      return url.pathname + url.search;
    } catch (error) {
      return null;
    }
  }
  function title() {
    return String(document.title || '').replace(/\s+/g, ' ').trim().slice(0, TITLE_LENGTH);
  }
  function newId() {
    return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
  }
  // Pages that are about history itself record nothing.
  function quiet(url) {
    return url.split('?')[0].replace(/\/$/, '') === '/tools/history';
  }

  // The page the reader came from, for a tab with no current visit.
  function referrerParent(visits) {
    var from = document.referrer ? address(document.referrer) : null;
    if (from === null) return null;
    var match = latest(visits, function (visit) { return visit.url === from; });
    return match ? match.id : null;
  }
  // Where a new visit hangs when this lane has no current visit: a pane page
  // under the main page's visit, a main page under the referrer's.
  function fallbackParent(visits) {
    if (inPane) {
      var main = find(visits, session(MAIN_CURSOR));
      return main ? main.id : null;
    }
    return referrerParent(visits);
  }

  function add(url, parent) {
    var visits = read();
    var visit = { id: newId(), t: Math.floor(Date.now() / 1000), url: url, title: title(), parent: parent || null };
    if (inPane) visit.beside = true;
    visits.push(visit);
    if (write(visits)) setCursor(visit.id);
    return visit;
  }

  // Back or forward to an address: prefer the current visit's parent, then
  // one of its children, then the latest visit there. Adds a visit only when
  // the tree has none for that address.
  function move(url) {
    var visits = read();
    var here = find(visits, cursor());
    if (here && here.url === url) return here;
    var parent = here ? find(visits, here.parent) : null;
    var target = parent && parent.url === url ? parent : null;
    if (!target && here) target = latest(visits, function (visit) { return visit.parent === here.id && visit.url === url; });
    if (!target) target = latest(visits, function (visit) { return visit.url === url; });
    if (!target) return add(url, here ? here.id : fallbackParent(visits));
    setCursor(target.id);
    return target;
  }

  // A page change by a load.
  function arrived() {
    var url = address(location.href);
    if (url === null || quiet(url)) return;
    var entry = window.performance && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
    var type = entry ? entry.type : 'navigate';
    var visits = read();
    if (inPane) {
      var next = take(PANE_NEXT);
      // The pane the last main page left open, shown again: already a visit.
      if (next === 'restore') {
        var shown = find(visits, cursor());
        if (!shown || shown.url !== url) {
          shown = latest(visits, function (visit) { return visit.beside && visit.url === url; });
          setCursor(shown ? shown.id : null);
        }
        if (shown) return;
      }
      // Opened from the main page: a new branch under the main page's visit.
      if (next === 'open') { add(url, fallbackParent(visits)); return; }
    } else {
      // Open alone: the main page becomes the pane's page, already a visit.
      var alone = find(visits, take(ALONE));
      if (alone && alone.url === url) {
        setCursor(alone.id);
        setSession(PANE_CURSOR, null);
        return;
      }
    }
    var here = find(visits, cursor());
    if (type === 'back_forward') { move(url); return; }
    // A load at the current visit's own address is no page change: a reload
    // (Firefox can report one as "navigate"), a theme reload in the pane, or a
    // link to this same page.
    if (here && here.url === url) return;
    add(url, here ? here.id : fallbackParent(visits));
  }

  // A page may name itself after it loads (the offline reader does); the
  // current visit follows, while this page is still its lane's current visit.
  function followTitle() {
    var head = document.querySelector('head');
    if (!head || !window.MutationObserver) return;
    new MutationObserver(function () {
      var visits = read();
      var here = find(visits, cursor());
      var name = title();
      if (!here || here.url !== address(location.href) || name === '' || here.title === name) return;
      here.title = name;
      write(visits);
    }).observe(head, { childList: true, subtree: true, characterData: true });
  }

  // A page change by a page script: pushState to another address is a visit
  // under the current one; replaceState moves the current visit to the new
  // address; back and forward between such entries (popstate) move the cursor.
  // The page scripts' own calls are left as they are.
  function changed(replacing) {
    var url = address(location.href);
    if (url === null || quiet(url)) return;
    var visits = read();
    var here = find(visits, cursor());
    if (here && here.url === url) return;
    if (replacing && here) {
      here.url = url;
      here.title = title();
      write(visits);
      return;
    }
    add(url, here ? here.id : fallbackParent(visits));
  }
  function wrap(name, replacing) {
    var original = history[name];
    if (typeof original !== 'function') return;
    history[name] = function () {
      var result = original.apply(this, arguments);
      try { changed(replacing); } catch (error) {}
      return result;
    };
  }
  wrap('pushState', false);
  wrap('replaceState', true);
  window.addEventListener('popstate', function () {
    var url = address(location.href);
    if (url !== null && !quiet(url)) move(url);
  });

  arrived();
  followTitle();
  // Restored from the browser's page cache by back or forward.
  window.addEventListener('pageshow', function (event) {
    if (!event.persisted) return;
    var url = address(location.href);
    if (url !== null && !quiet(url)) move(url);
  });

  window.forumVisitHistory = {
    visits: read,
    current: function () { return find(read(), cursor()); },
    clear: function () {
      try { localStorage.removeItem(KEY); } catch (error) {}
      [MAIN_CURSOR, PANE_CURSOR, PANE_NEXT, ALONE].forEach(function (key) { setSession(key, null); });
    },
    // Called by open_beside.js on the main page just before it loads a page
    // into the pane ("open" for a reader's click, "restore" for the pane the
    // last page left open), and when the reader presses Open alone.
    paneLoading: function (kind) {
      if (!inPane) setSession(PANE_NEXT, kind === 'restore' ? 'restore' : 'open');
    },
    paneAlone: function () {
      if (!inPane) setSession(ALONE, session(PANE_CURSOR));
    },
  };
})();
