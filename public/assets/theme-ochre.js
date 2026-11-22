/*
 * Ochre theme script: the reading habits of a compact text forum.
 * Registered with theme_toggle.js; runs only while Ochre is the active theme.
 * On a thread page, each reply's grey line gains "parent" and "next" links and
 * a [–] toggle that folds the reply and every reply below it into [+N].
 * It reads the cards as they are: a flat list in page order whose
 * data-reply-depth gives the nesting, so a branch is the run of following
 * cards that are deeper. Nothing is stored; a fold lasts until the page
 * reloads. stop() removes every element, class and observer start() added.
 */
(function () {
  var registry = window.forumThemeScripts;
  if (!registry) return;

  var DASH = "–";
  var state = null;

  function depthOf(card) {
    var value = parseInt(card.getAttribute("data-reply-depth") || "0", 10);
    return isNaN(value) || value < 0 ? 0 : value;
  }

  function directMeta(card) {
    var children = card.children || [];
    for (var i = 0; i < children.length; i++) {
      var child = children[i];
      if (child.classList && child.classList.contains("meta") && !child.hasAttribute("hidden")) return child;
    }
    return null;
  }

  function text(value) {
    return document.createTextNode(value);
  }

  function link(label, target) {
    var anchor = document.createElement("a");
    anchor.className = "ochre-link";
    anchor.setAttribute("href", "#" + target.id);
    anchor.textContent = label;
    return anchor;
  }

  // Removes everything the last build added, keeping the folds.
  function teardown() {
    if (!state) return;
    state.added.forEach(function (node) {
      if (node.parentNode) node.parentNode.removeChild(node);
    });
    state.marked.forEach(function (node) {
      node.classList.remove("ochre-keep");
    });
    state.cards.forEach(function (entry) {
      entry.card.classList.remove("ochre-collapsed");
      entry.card.classList.remove("ochre-hidden");
    });
    state.added = [];
    state.marked = [];
    state.cards = [];
  }

  // Shows or hides each card from the folds, and relabels the toggles.
  function apply() {
    var skip = -1;
    state.cards.forEach(function (entry) {
      var hidden = skip >= 0 && entry.depth > skip;
      if (!hidden) skip = -1;
      var folded = !hidden && state.folded[entry.id] === true;
      if (folded) skip = entry.depth;
      entry.card.classList.toggle("ochre-hidden", hidden);
      entry.card.classList.toggle("ochre-collapsed", folded);
      entry.toggle.textContent = folded ? "[+" + entry.size + "]" : "[" + DASH + "]";
      entry.toggle.setAttribute("aria-expanded", folded ? "false" : "true");
      entry.toggle.setAttribute("aria-label", folded
        ? (entry.size === 1 ? "Show this reply" : "Show " + entry.size + " replies")
        : "Fold this reply and its replies");
    });
  }

  function build() {
    teardown();
    var root = document.querySelector(".thread-root-card");
    if (!root) return;
    var cards = Array.prototype.slice.call(document.querySelectorAll(".post-card[data-post-id]")).filter(function (card) {
      return card !== root && card.id;
    });
    var entries = cards.map(function (card) {
      return { card: card, id: card.getAttribute("data-post-id"), depth: depthOf(card) };
    });

    entries.forEach(function (entry, index) {
      var parent = null;
      if (entry.depth === 0) {
        parent = root.id ? root : null;
      } else {
        for (var back = index - 1; back >= 0; back--) {
          if (entries[back].depth === entry.depth - 1) { parent = entries[back].card; break; }
          if (entries[back].depth < entry.depth - 1) break;
        }
      }
      var next = null;
      for (var ahead = index + 1; ahead < entries.length; ahead++) {
        if (entries[ahead].depth < entry.depth) break;
        if (entries[ahead].depth === entry.depth) { next = entries[ahead].card; break; }
      }
      entry.size = 1;
      for (var below = index + 1; below < entries.length && entries[below].depth > entry.depth; below++) entry.size++;

      var nav = document.createElement("span");
      nav.className = "ochre-nav";
      if (parent) { nav.appendChild(text(" | ")); nav.appendChild(link("parent", parent)); }
      if (next) { nav.appendChild(text(" | ")); nav.appendChild(link("next", next)); }
      nav.appendChild(text(" "));
      var toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "ochre-toggle";
      toggle.addEventListener("click", function () {
        state.folded[entry.id] = !state.folded[entry.id];
        apply();
      });
      nav.appendChild(toggle);
      entry.toggle = toggle;

      var line = directMeta(entry.card);
      if (line) {
        line.classList.add("ochre-keep");
        state.marked.push(line);
        line.appendChild(nav);
        state.added.push(nav);
      } else {
        line = document.createElement("p");
        line.className = "meta ochre-keep ochre-line";
        line.appendChild(nav);
        entry.card.insertBefore(line, entry.card.firstChild);
        state.added.push(line);
      }
    });

    state.cards = entries;
    apply();
  }

  function start() {
    state = { added: [], marked: [], cards: [], folded: {}, observer: null, pending: false };
    build();
    // A reply posted without a reload adds a card; rebuild once per frame.
    var first = state.cards.length > 0 ? state.cards[0].card : document.querySelector(".thread-root-card");
    var container = first && first.parentNode;
    if (container && typeof window.MutationObserver === "function") {
      state.observer = new window.MutationObserver(function (records) {
        var changed = records.some(function (record) {
          return Array.prototype.some.call(record.addedNodes, function (node) {
            return node.classList && node.classList.contains("post-card");
          }) || Array.prototype.some.call(record.removedNodes, function (node) {
            return node.classList && node.classList.contains("post-card");
          });
        });
        if (!changed || !state || state.pending) return;
        state.pending = true;
        window.requestAnimationFrame(function () {
          if (!state) return;
          state.pending = false;
          build();
        });
      });
      state.observer.observe(container, { childList: true });
    }
  }

  function stop() {
    if (!state) return;
    if (state.observer) state.observer.disconnect();
    teardown();
    state = null;
  }

  registry.register("ochre", { start: start, stop: stop });
})();
