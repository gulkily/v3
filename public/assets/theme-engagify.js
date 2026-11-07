/*
 * Engagify theme script: a parody of the engagement-optimized web.
 * Registered with theme_toggle.js; runs only while Engagify is the active
 * theme. Each nag is a function in NAGS that receives a context and adds its
 * elements and timers through it, so stop() removes everything at once.
 */
(function () {
  var registry = window.forumThemeScripts;
  if (!registry) return;

  var NAGS = [];
  var context = null;

  function createContext() {
    var nodes = [];
    var timers = [];
    var stopped = false;

    function el(tag, attributes, children) {
      var node = document.createElement(tag);
      Object.keys(attributes || {}).forEach(function (name) {
        if (name === "text") node.textContent = attributes[name];
        else if (name === "onclick") node.addEventListener("click", attributes[name]);
        else node.setAttribute(name, attributes[name]);
      });
      (children || []).forEach(function (child) {
        if (child) node.appendChild(child);
      });
      return node;
    }

    return {
      el: el,
      add: function (node) {
        if (stopped) return node;
        nodes.push(node);
        // Inside the app shell, nags inherit its phone tab bar offset.
        (document.querySelector("[data-shell]") || document.body).appendChild(node);
        return node;
      },
      remove: function (node) {
        if (node && node.parentNode) node.parentNode.removeChild(node);
      },
      later: function (callback, delay) {
        if (stopped) return;
        timers.push(window.setTimeout(function () {
          if (!stopped) callback();
        }, delay));
      },
      exit: function () {
        if (typeof window.forumExitTheme === "function") window.forumExitTheme();
      },
      exitButton: function (label) {
        return el("button", { type: "button", class: "engagify-link", text: label || "Exit Engagify", onclick: function () {
          if (typeof window.forumExitTheme === "function") window.forumExitTheme();
        } });
      },
      stop: function () {
        stopped = true;
        timers.forEach(function (timer) { window.clearTimeout(timer); });
        nodes.forEach(function (node) {
          if (node.parentNode) node.parentNode.removeChild(node);
        });
        nodes = [];
        timers = [];
      }
    };
  }

  // Exit: always on screen while Engagify is active.
  NAGS.push(function exitPill(ctx) {
    ctx.add(ctx.el("button", {
      type: "button",
      class: "engagify-exit",
      "data-engagify": "exit",
      text: "Exit Engagify",
      onclick: ctx.exit
    }));
  });

  registry.register("engagify", {
    start: function () {
      if (context) context.stop();
      context = createContext();
      NAGS.forEach(function (nag) {
        try {
          nag(context);
        } catch (error) {
          if (window.console) window.console.error(error);
        }
      });
    },
    stop: function () {
      if (context) context.stop();
      context = null;
    }
  });

})();
