// Builds each <template data-screen> into a desktop frame and a phone frame,
// wrapping it in the redesigned app shell. Mockups only; no app code.
(function () {
  var ICONS = {
    board: '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M8 13h8M8 16h5"/>',
    users: '<circle cx="9" cy="8" r="3.2"/><path d="M3 19c.6-3.3 3-5 6-5s5.4 1.7 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16.5 14c2.4.2 4 1.7 4.5 4.5"/>',
    message: '<path d="M4 5h16v11H9l-5 4z"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    person: '<circle cx="12" cy="8" r="4"/><path d="M4 20c.8-4 4-6 8-6s7.2 2 8 6"/>',
    heart: '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
    heartfill: '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" fill="currentColor"/>',
    flag: '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
    reply: '<path d="M10 8 5 12l5 4"/><path d="M5 12h9a5 5 0 0 1 5 5v1"/>',
    more: '<circle cx="6" cy="12" r="1.3" fill="currentColor"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/><circle cx="18" cy="12" r="1.3" fill="currentColor"/>',
    pin: '<path d="M9 4h6l-1 6 3 3H7l3-3zM12 13v7"/>',
    image: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m21 16-5-5-8 8"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    tag: '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/>',
    lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    search: '<circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5"/>',
    chev: '<path d="m9 6 6 6-6 6"/>',
    chevdown: '<path d="m6 9 6 6 6-6"/>',
    back: '<path d="m15 6-6 6 6 6"/>',
    check: '<path d="m5 12 5 5 9-10"/>',
    x: '<path d="M6 6l12 12M18 6 6 18"/>',
    wrench: '<path d="M14 6a4 4 0 0 0 5 5l-9 9a2 2 0 0 1-3-3l9-9a4 4 0 0 1-2-2z"/>',
    download: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
    upload: '<path d="M12 20V9M7 14l5-5 5 5M5 4h14"/>',
    key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M15 8l2 2"/>',
    shield: '<path d="M12 3 5 6v6c0 4 3 7.5 7 9 4-1.5 7-5 7-9V6z"/>',
    sparkle: '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
    send: '<path d="M12 19V5M6 11l6-6 6 6"/>',
    clock: '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    inbox: '<path d="M4 13h5l1 3h4l1-3h5M4 13l2-8h12l2 8v6H4z"/>',
    offline: '<path d="M3 3l18 18M8.5 8.6A8 8 0 0 0 5 11M12 6a11 11 0 0 1 9 4.5M15.5 13a5 5 0 0 0-7 0M12 18h.01"/>',
    bookmark: '<path d="M6 4h12v17l-6-4-6 4z"/>',
    activity: '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
    chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    toggle: '<rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="16" cy="12" r="3"/>',
    database: '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"/>',
    doc: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
    code: '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/>',
    columns: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/>',
    server: '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
    invite: '<circle cx="9" cy="8" r="3.5"/><path d="M3 20c.6-3.5 3-5.5 6-5.5 1.6 0 3 .5 4 1.4M18 13v6M15 16h6"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
    signout: '<path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/>',
    palette: '<path d="M12 3a9 9 0 1 0 0 18c1.2 0 1.7-1 1.2-2-.6-1.2.2-2.5 1.5-2.5H17a4 4 0 0 0 4-4C21 7 17 3 12 3z"/><circle cx="7.5" cy="11" r="1.2"/><circle cx="10" cy="7" r="1.2"/><circle cx="15" cy="7.5" r="1.2"/>',
    retry: '<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
    hash: '<path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16"/>',
    star: '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z"/>',
    starfill: '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z" fill="currentColor"/>'
  };
  function icon(name, cls) {
    return '<svg class="i' + (cls ? ' ' + cls : '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
  }
  var PALETTE = ['#4f7cff', '#e0662f', '#16a085', '#b5479b', '#7a6ff0', '#d4a20b', '#2f8fd8', '#c0392b', '#5d9c42', '#8e6c4a'];
  function avColor(name) {
    var h = 0;
    for (var i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
    return PALETTE[h % PALETTE.length];
  }

  var NAV = [
    { key: 'board', label: 'Board', icon: 'board' },
    { key: 'users', label: 'Users', icon: 'users' },
    { key: 'messages', label: 'Messages', icon: 'message', badge: '2' }
  ];

  function topbar(t) {
    var active = t.getAttribute('data-active') || '';
    var menuOpen = t.getAttribute('data-menu') === 'open';
    var guest = t.hasAttribute('data-guest');
    var noMessages = guest || t.hasAttribute('data-unapproved');
    var nav = NAV.filter(function (n) { return !(noMessages && n.key === 'messages'); }).map(function (n) {
      return '<a href="#" data-tab="' + n.key + '"' + (active === n.key ? ' aria-current="page"' : '') + '>' + n.label +
        (n.badge ? ' <span class="badge">' + n.badge + '</span>' : '') + '</a>';
    }).join('');
    var who = t.getAttribute('data-viewer') || (guest ? 'guest' : 'ilyag');
    var pop = !menuOpen ? '' :
      '<div class="popover" role="menu">' +
        '<div class="who"><span class="avatar md" data-av="' + who + '"></span><div><b>' + who + '</b><small>' + (guest ? 'Not set up' : (t.hasAttribute('data-unapproved') ? 'Waiting for approval' : 'Approved')) + '</small></div></div>' +
        '<a href="#" data-tone="person" data-person="' + who + '"><span class="ic">' + icon('person') + '</span>Profile</a>' +
        '<a href="#" data-tone="account"><span class="ic">' + icon('key') + '</span>Account</a>' +
        '<a href="#" data-tone="access"><span class="ic">' + icon('invite') + '</span>Invite someone</a>' +
        '<hr>' +
        '<a href="#" data-tone="status"><span class="ic">' + icon('wrench') + '</span>Tools</a>' +
        '<a href="#" data-tone="docs"><span class="ic">' + icon('info') + '</span>About ZenMemes</a>' +
        '<hr>' +
        '<div class="row-btn" data-tone="activity"><span class="ic">' + icon('palette') + '</span>Theme<span style="flex:1"></span><span class="faint small">Auto</span></div>' +
        '<div class="swatches">' +
          ['#e9edf3|Light', '#1c1c1e|Dark', '#0f2d16|Console', '#c8d6a0|LCD', '#bfbfbf|Chicago', '#2a1f4f|Vapor', '#3b2a1e|Forge', '#ffd84d|Sticker', '#1d3b6e|Word 97', '#0b1026|Midnight'].map(function (s, i) {
            var p = s.split('|');
            return '<span class="swatch" title="' + p[1] + '" style="background:' + p[0] + '"' + (i === 0 ? ' aria-pressed="true"' : '') + '></span>';
          }).join('') +
        '</div>' +
      '</div>';
    return '<header class="topbar">' +
      '<span class="wordmark"><span class="mark" aria-hidden="true"></span>ZenMemes</span>' +
      '<nav class="topnav">' + nav + '</nav>' +
      '<span class="spacer"></span>' +
      '<span class="btn primary">' + icon('plus', 'sm') + 'New post</span>' +
      '<span class="menu-anchor"><span class="avatar" data-av="' + who + '" title="Account menu"></span>' + pop + '</span>' +
    '</header>';
  }

  function phonebar(t) {
    var mode = t.getAttribute('data-phonebar') || 'large';
    if (mode === 'none') return '';
    var title = t.getAttribute('data-title') || '';
    var back = t.getAttribute('data-back');
    var trail = (t.getAttribute('data-trail') || '').split(' ').filter(Boolean).map(function (tok) {
      if (tok.indexOf('i:') === 0) return icon(tok.slice(2));
      if (tok.indexOf('av:') === 0) return '<span class="avatar xs" data-av="' + tok.slice(3) + '"></span>';
      return '<span>' + tok.replace(/_/g, ' ') + '</span>';
    }).join('');
    var lead = back ? '<span class="back">' + icon('back') + back.replace(/_/g, ' ') + '</span>' : '<span></span>';
    if (mode === 'inline') {
      return '<header class="phonebar inline"><div class="row">' + lead + '<h1>' + title + '</h1><span class="trail">' + trail + '</span></div></header>';
    }
    return '<header class="phonebar"><div class="row">' + lead + '<span class="trail">' + trail + '</span></div><h1>' + title + '</h1></header>';
  }

  function tabbar(t) {
    if (t.getAttribute('data-tabbar') === 'none') return '';
    var active = t.getAttribute('data-active') || '';
    var guest = t.hasAttribute('data-guest') || t.hasAttribute('data-unapproved');
    function tab(key, label, ic, badge) {
      return '<a href="#" data-tab="' + key + '"' + (active === key ? ' aria-current="page"' : '') + '>' + icon(ic) + label + (badge ? '<span class="badge">' + badge + '</span>' : '') + '</a>';
    }
    return '<nav class="tabbar">' +
      tab('board', 'Board', 'board') +
      tab('users', 'Users', 'users') +
      '<a href="#" class="compose-tab"><span class="plus">' + icon('plus') + '</span></a>' +
      (guest ? tab('about', 'About', 'info') : tab('messages', 'Messages', 'message', '2')) +
      tab('account', 'You', 'person') +
    '</nav>';
  }

  function build(t, device, mode) {
    var app = document.createElement('div');
    app.className = 'app';
    app.setAttribute('data-mode', mode);
    if (skin) app.setAttribute('data-skin', skin);
    // Backdrop layer for skins that paint the whole screen; hidden in the base.
    var sky = '<div class="sky" aria-hidden="true"><span class="neb a"></span><span class="neb b"></span><span class="neb c"></span><span class="stars s1"></span><span class="stars s2"></span><span class="shoot"></span>' +
      '<span class="petal"></span><span class="petal"></span><span class="petal"></span><span class="petal"></span><span class="petal"></span><span class="petal"></span><span class="petal"></span></div>';
    app.innerHTML = sky + topbar(t) + phonebar(t) + '<div class="content"></div>' + tabbar(t);
    if (t.getAttribute('data-sky')) app.style.setProperty('--sky-person', avColor(t.getAttribute('data-sky')));
    if (t.getAttribute('data-pulse')) app.setAttribute('data-pulse', t.getAttribute('data-pulse'));
    app.querySelector('.content').appendChild(t.content.cloneNode(true));
    var overlay = t.getAttribute('data-overlay');
    if (overlay) {
      var o = document.getElementById(overlay);
      if (o) app.appendChild(o.content.cloneNode(true));
    }
    hydrate(app);
    var frame = document.createElement('div');
    frame.className = 'frame frame--' + device;
    frame.appendChild(app);
    var scroll = document.createElement('div');
    scroll.className = 'frame-scroll';
    scroll.appendChild(frame);
    return scroll;
  }

  function hydrate(root) {
    root.querySelectorAll('i[data-i]').forEach(function (el) {
      el.outerHTML = icon(el.getAttribute('data-i'), el.className || '');
    });
    root.querySelectorAll('[data-av]').forEach(function (el) {
      var name = el.getAttribute('data-av');
      if (name === 'reply-agent') { el.classList.add('agent'); el.innerHTML = icon('sparkle', 'sm'); return; }
      if (!el.style.getPropertyValue('--av')) el.style.setProperty('--av', avColor(name));
      if (!el.textContent) el.textContent = name.charAt(0);
      // A fixed geometric cut per name, for skins that draw avatars as shapes.
      var g = hash(name);
      el.style.setProperty('--av-rot', (g % 4) * 90 + 'deg');
      el.setAttribute('data-cut', ['half', 'quarter', 'moon'][(g >>> 4) % 3]);
      // Expose the author's color to the surrounding item so a theme can use it.
      var scope = el.closest('.thread, .reply, .post, .row, .profile-head, .id-card');
      if (scope && !scope.style.getPropertyValue('--person')) scope.style.setProperty('--person', el.style.getPropertyValue('--av'));
    });
    root.querySelectorAll('[data-person]').forEach(function (el) {
      el.style.setProperty('--person', avColor(el.getAttribute('data-person')));
    });
    root.querySelectorAll('.starfield[data-seed]').forEach(function (el) {
      el.insertAdjacentHTML('afterbegin', starfield(el.getAttribute('data-seed'), el.hasAttribute('data-constellation')) + flowfield(el.getAttribute('data-seed')));
    });
  }

  function hash(str) {
    var h = 2166136261;
    for (var i = 0; i < str.length; i++) h = Math.imul(h ^ str.charCodeAt(i), 16777619) >>> 0;
    return h;
  }
  function seeded(seed) {
    var h = hash(seed);
    return function () { h = (h + 0x6D2B79F5) >>> 0; var t = h; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
  }

  // Seeded flow field: brush lines that follow one smooth field, so a key
  // always draws the same picture. Mostly the person's color, some ink, and
  // rarely one vermilion stroke.
  function flowfield(seed) {
    var rnd = seeded(seed + ':flow');
    var a = rnd() * 6.28, f1 = 0.004 + rnd() * 0.008, f2 = 0.006 + rnd() * 0.01, p1 = rnd() * 6.28, p2 = rnd() * 6.28, amp = 0.9 + rnd() * 1.1;
    function angle(x, y) { return a + amp * (Math.sin(x * f1 + p1) + Math.cos(y * f2 + p2) + 0.5 * Math.sin((x + y) * f1 * 1.7)); }
    var out = '';
    for (var n = 0; n < 78; n++) {
      var x = rnd() * 640 - 20, y = rnd() * 220 - 10, pts = [], steps = 18 + Math.floor(rnd() * 34);
      for (var k = 0; k < steps; k++) {
        pts.push(x.toFixed(1) + ',' + y.toFixed(1));
        var t = angle(x, y);
        x += Math.cos(t) * 5; y += Math.sin(t) * 5;
      }
      var r = rnd(), w = r > 0.9 ? 5 + rnd() * 4 : r > 0.6 ? 2 + rnd() * 2 : 0.6 + rnd();
      var cls = rnd() > 0.985 ? 'r' : rnd() > 0.72 ? 'k' : 'p';
      out += '<polyline class="' + cls + '" points="' + pts.join(' ') + '" stroke-width="' + w.toFixed(1) + '" opacity="' + (0.35 + rnd() * 0.6).toFixed(2) + '"/>';
    }
    return '<svg class="flow" viewBox="0 0 600 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">' + out + '</svg>';
  }

  // Seeded starfield, after the Starfield backup palette (white, blue, yellow,
  // red stars). Decorative: the same seed always draws the same sky.
  function starfield(seed, constellation) {
    var h = 2166136261;
    for (var i = 0; i < seed.length; i++) h = Math.imul(h ^ seed.charCodeAt(i), 16777619) >>> 0;
    function rnd() { h = (h + 0x6D2B79F5) >>> 0; var t = h; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; }
    var hues = ['#ffffff', '#d9e2ff', '#fff1c2', '#ffd2c2'], out = '', bright = [];
    for (var n = 0; n < 140; n++) {
      var x = rnd() * 600, y = rnd() * 200, k = rnd(), r = k > 0.97 ? 1.9 : k > 0.85 ? 1.2 : 0.7, c = hues[Math.floor(rnd() * 4)];
      out += '<circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="' + r + '" fill="' + c + '" opacity="' + (0.45 + rnd() * 0.55).toFixed(2) + '"/>';
      if (r > 1.5 && x > 40 && x < 560) bright.push([x, y]);
    }
    var lines = '';
    if (constellation && bright.length > 2) {
      bright.sort(function (a, b) { return a[0] - b[0]; });
      lines = '<polyline points="' + bright.slice(0, 6).map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ') + '" fill="none" stroke="currentColor" stroke-width="0.8" opacity="0.55"/>';
    }
    return '<svg class="sky" viewBox="0 0 600 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">' + lines + out + '</svg>';
  }

  // Night Ma: a message decrypting scrambles through hex, then resolves, once,
  // when it scrolls into view. Screen readers get the final text throughout.
  var HEX = '0123456789ABCDEF';
  var seen = window.IntersectionObserver ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) { if (e.isIntersecting) { seen.unobserve(e.target); decrypt(e.target); } });
  }, { threshold: 0.6 }) : null;
  function decrypt(el) {
    var final = el.getAttribute('data-scramble');
    var app = el.closest('.app');
    if (!app || app.getAttribute('data-skin') !== 'ma' || app.getAttribute('data-mode') !== 'dark' ||
        (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) { el.textContent = final; return; }
    el.style.width = el.getBoundingClientRect().width + 'px';
    el.classList.add('decrypting');
    var start = performance.now(), dur = 1200;
    (function tick(now) {
      var p = Math.min(1, (now - start) / dur), n = Math.floor(final.length * p), out = final.slice(0, n);
      for (var i = n; i < final.length; i++) out += final[i] === ' ' ? ' ' : HEX[Math.floor(Math.random() * 16)];
      el.textContent = out;
      if (p < 1) requestAnimationFrame(tick); else { el.classList.remove('decrypting'); el.style.width = ''; }
    })(start);
  }
  function armDecrypt() {
    document.querySelectorAll('.app [data-scramble]').forEach(function (el) {
      el.setAttribute('aria-label', el.getAttribute('data-scramble'));
      if (seen) seen.observe(el); else decrypt(el);
    });
  }

  function preferredSkin() {
    var base = document.body.getAttribute('data-skin');
    if (!base) return '';
    try {
      var saved = localStorage.getItem('zm-mockup-skin-r5');
      if (saved && document.querySelector('.skin-toggle button[data-skin="' + saved + '"]')) return saved;
    } catch (e) {}
    return base;
  }
  var skin = '';

  function preferredMode() {
    try {
      var saved = localStorage.getItem('zm-mockup-mode');
      if (saved === 'light' || saved === 'dark') return saved;
    } catch (e) {}
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function render() {
    var stage = document.querySelector('.stage');
    if (!stage) return;
    var mode = preferredMode();
    skin = preferredSkin();
    stage.innerHTML = '';
    document.querySelectorAll('template[data-screen]').forEach(function (t) {
      var row = document.createElement('div');
      row.className = 'stage-row';
      row.innerHTML = '<div class="stage-label">' + t.getAttribute('data-screen') + '</div><div class="stage-frames"></div>';
      var frames = row.querySelector('.stage-frames');
      (t.getAttribute('data-devices') || 'desktop phone').split(' ').forEach(function (d) {
        frames.appendChild(build(t, d, mode));
      });
      stage.appendChild(row);
    });
    document.querySelectorAll('.mode-toggle button').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-mode') === mode ? 'true' : 'false');
    });
    document.querySelectorAll('.skin-toggle button').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-skin') === skin ? 'true' : 'false');
    });
    armDecrypt();
  }

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('.mode-toggle button');
    if (b) {
      var m = b.getAttribute('data-mode');
      try { localStorage.setItem('zm-mockup-mode', m); } catch (e) {}
      document.querySelectorAll('.app').forEach(function (a) { a.setAttribute('data-mode', m); });
      document.querySelectorAll('.mode-toggle button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      armDecrypt();
      return;
    }
    var k = ev.target.closest('.skin-toggle button');
    if (k) {
      skin = k.getAttribute('data-skin');
      try { localStorage.setItem('zm-mockup-skin-r5', skin); } catch (e) {}
      document.querySelectorAll('.app').forEach(function (a) { a.setAttribute('data-skin', skin); });
      document.querySelectorAll('.skin-toggle button').forEach(function (x) { x.setAttribute('aria-pressed', x === k ? 'true' : 'false'); });
      armDecrypt();
      return;
    }
    if (ev.target.closest('.app a')) ev.preventDefault();
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', render); else render();
})();
