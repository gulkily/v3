(function () {
  'use strict';
  // Personal slots on the phone tab bar. The browser remembers which More
  // destinations earned a tab; layout.php's head script applies that choice
  // before first paint by setting data-nav-slots on <html>. This script keeps
  // the saved state, counts use, fills and holds slots, and handles pins.
  //
  // Storage (internal browser state, JSON): forum_nav_slots =
  //   { "slots": ["recent"], "pins": ["profiles"],
  //     "visits": { "recent": { "count": 3, "first": "2026-11-05", "last": "2026-11-06" } } }
  //
  // Rules: a free slot fills once a destination has FILL visits. A filled
  // slot changes only when a challenger leads it by LEAD visits and has been
  // used on at least two different days. A pin takes a slot first and never
  // moves. Slots keep the order they were earned; new ones go last.
  const key = 'forum_nav_slots';
  const FILL = 3;
  const LEAD = 3;
  const root = document.documentElement;
  const section = /^[a-z]+$/;
  const phone = window.matchMedia('(max-width: 639px)');

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
  function usable(name) { return sections().includes(name) && (name !== 'messages' || approved()); }
  function today() { return new Date().toISOString().slice(0, 10); }

  // Mirrors the head script: known sections only, at most the free slot count, Messages only for members.
  function place(state) {
    const slots = list((state || read()).slots).filter(usable).slice(0, count());
    if (slots.length) root.setAttribute('data-nav-slots', slots.join(' '));
    else root.removeAttribute('data-nav-slots');
    return slots;
  }

  function visit(state, name) {
    const visits = state.visits && typeof state.visits === 'object' ? state.visits : {};
    const entry = visits[name] && typeof visits[name] === 'object' ? visits[name] : { count: 0, first: today() };
    visits[name] = { count: (Number(entry.count) || 0) + 1, first: entry.first || today(), last: today() };
    state.visits = visits;
    return state;
  }

  function decide(state) {
    const free = count();
    const visits = state.visits && typeof state.visits === 'object' ? state.visits : {};
    const uses = name => Number(visits[name] && visits[name].count) || 0;
    const twoDays = name => Boolean(visits[name] && visits[name].first && visits[name].last && visits[name].first !== visits[name].last);
    const pins = list(state.pins).filter(usable);
    const slots = pins.slice(0, free);
    const auto = list(state.slots).filter(name => usable(name) && !pins.includes(name));
    const challengers = () => sections().filter(name => usable(name) && !pins.includes(name) && !slots.includes(name) && !auto.includes(name))
      .sort((a, b) => uses(b) - uses(a));
    for (const name of auto) {
      if (slots.length >= free) break;
      const best = challengers()[0];
      slots.push(best && uses(best) >= uses(name) + LEAD && twoDays(best) ? best : name);
    }
    for (const name of challengers()) {
      if (slots.length >= free) break;
      if (uses(name) >= FILL) slots.push(name);
    }
    state.slots = slots;
    state.pins = pins;
    return state;
  }

  function pin(name, on) {
    const state = read();
    const pins = list(state.pins).filter(item => item !== name);
    if (on) pins.unshift(name);
    state.pins = pins;
    write(decide(state));
    place(state);
    reflect();
  }

  // Pin buttons live in the More panel; their pressed state comes from storage.
  function reflect() {
    const pins = list(read().pins);
    document.querySelectorAll('[data-nav-pin]').forEach(button => {
      button.setAttribute('aria-pressed', pins.includes(button.dataset.navPin) ? 'true' : 'false');
    });
  }

  document.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;
    const button = target.closest('[data-nav-pin]');
    if (button) {
      event.preventDefault();
      pin(button.dataset.navPin, button.getAttribute('aria-pressed') !== 'true');
      return;
    }
    if (!phone.matches) return;
    // Use counts on phones: a More destination, or a tab that already holds a slot.
    const link = target.closest('.account-menu__more a[data-more], .site-header--app nav.nav > .nav-link[data-phone="more"]');
    const name = link && (link.dataset.more || link.dataset.tab);
    if (!name || !usable(name)) return;
    write(decide(visit(read(), name)));
  });

  window.ForumNavSlots = { key, read, write, list, count, sections, place, decide, visit, pin, reflect, FILL, LEAD };
  document.addEventListener('forum:nav-refreshed', () => place());
  window.addEventListener('forum:identity-changed', () => place());
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', reflect);
  else reflect();
})();
