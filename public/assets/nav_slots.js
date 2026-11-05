(function () {
  'use strict';
  // Personal slots on the phone tab bar. The browser remembers which More
  // destinations earned a tab; layout.php's head script applies that choice
  // before first paint by setting data-nav-slots on <html>, and this script
  // keeps the saved state and re-applies it when the nav is replaced.
  //
  // Storage (internal browser state, JSON): forum_nav_slots =
  //   { "slots": ["recent"], "pins": ["users"], "visits": { "recent": { "count": 3, "first": "2026-11-05", "last": "2026-11-05" } } }
  const key = 'forum_nav_slots';
  const root = document.documentElement;
  const section = /^[a-z]+$/;

  function read() {
    try {
      const state = JSON.parse(localStorage.getItem(key) || 'null');
      return state && typeof state === 'object' ? state : {};
    } catch (error) { return {}; }
  }
  function write(state) {
    try { localStorage.setItem(key, JSON.stringify(state)); } catch (error) {}
  }
  function list(value) {
    return Array.isArray(value) ? value.filter(item => typeof item === 'string' && section.test(item)) : [];
  }
  // layout.php's head script publishes the free slot count and the sections
  // that may fill one; both are the same for every viewer of a site.
  const config = () => window.__forumNavSlots || {};
  function count() { return Number(config().count) || 0; }
  function sections() { return list(config().sections); }
  function approved() { return root.getAttribute('data-member') === 'approved'; }

  // Mirrors the head script: known sections only, at most the free slot count, Messages only for members.
  function place(state) {
    const known = sections();
    const slots = list((state || read()).slots).filter(name => known.includes(name) && (name !== 'messages' || approved())).slice(0, count());
    if (slots.length) root.setAttribute('data-nav-slots', slots.join(' '));
    else root.removeAttribute('data-nav-slots');
    return slots;
  }

  window.ForumNavSlots = { key, read, write, list, count, sections, place };
  document.addEventListener('forum:nav-refreshed', () => place());
  window.addEventListener('forum:identity-changed', () => place());
})();
