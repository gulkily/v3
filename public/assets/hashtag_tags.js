/*
 * Shows "Tags: #a #b" under a composer's text box: the labels the post will give its thread.
 * Mirrors ThreadLabels::selfTags in PHP; both are tested against
 * tests/fixtures/hashtag_self_tags_examples.txt. Loaded only while
 * FORUM_HASHTAG_SELF_TAGS_ENABLED is on, and the server renders the first state.
 */
(function () {
  "use strict";

  // Code spans, Markdown links, entities and URLs: the protected text of View\PostHashtags.
  var protectedText = /(`[^`]*(?:`|$)|\[[^\]]*\]\([^)]*\)|&(?:amp;)?#(?:[0-9]+|x[0-9a-f]+);|(?:[a-z][a-z0-9+.-]*:\/\/|www\.)\S+)/i;
  var linkHashtag = /(?<![\p{L}\p{N}\p{M}_/#&-])#([a-z0-9]+(?:-[a-z0-9]+)*)(?![\p{L}\p{N}\p{M}_-])/u;
  var fence = /^ {0,3}(?:```|~~~)/;
  var quoted = /^\s*>/;
  var neverTags = /^(?:[0-9]+|[0-9a-f]{3}|[0-9a-f]{6})$/;
  // An image's Image-v1 line and the photo details after it (ImageReference::withoutReference).
  var imageLine = /^Image-v1: [a-f0-9]{64} (?:png|jpg) [0-9]+ [0-9]+ [0-9]+(?: \S+)?$/;
  var photoDetail = /^Photo-(?:Camera|Lens|Exposure|Taken|Location): [^\x00-\x1f\x7f]{1,120}$/;

  function withoutImage(lines) {
    for (var i = 0; i < lines.length; i++) {
      if (!imageLine.test(lines[i])) continue;
      var end = i + 1;
      while (end < lines.length && photoDetail.test(lines[end])) end++;
      return lines.slice(0, i).concat(lines.slice(end));
    }
    return lines;
  }

  function lineTokens(line) {
    var tokens = [];
    var parts = line.split(protectedText);
    for (var i = 0; i < parts.length; i++) {
      if (i % 2 === 1) { tokens.push(["", parts[i]]); continue; }
      var pieces = parts[i].split(linkHashtag);
      for (var j = 0; j < pieces.length; j++) {
        if (j % 2 === 1) tokens.push(["#", pieces[j]]);
        else if (pieces[j] !== "") tokens.push(["", pieces[j]]);
      }
    }
    return tokens;
  }

  function isHashtagLine(tokens) {
    var found = false;
    for (var i = 0; i < tokens.length; i++) {
      if (tokens[i][0] === "#") found = true;
      else if (tokens[i][1].trim() !== "") return false;
    }
    return found;
  }

  function trailingRun(tokens) {
    var run = [];
    for (var i = tokens.length - 1; i >= 0; i--) {
      if (tokens[i][0] === "#") { run.unshift(tokens[i]); continue; }
      if (tokens[i][1].trim() !== "") break;
    }
    return run;
  }

  function selfTags(body) {
    var lines = withoutImage(String(body || "").split(/\r\n|\r|\n/));
    var last = -1;
    for (var i = 0; i < lines.length; i++) if (lines[i].trim() !== "") last = i;
    var labels = [];
    var inFence = false;
    for (var k = 0; k < lines.length; k++) {
      if (fence.test(lines[k])) { inFence = !inFence; continue; }
      if (inFence || quoted.test(lines[k])) continue;
      var tokens = lineTokens(lines[k]);
      var tagging = isHashtagLine(tokens) ? tokens : (k === last ? trailingRun(tokens) : []);
      for (var t = 0; t < tagging.length; t++) {
        var label = tagging[t][1];
        if (tagging[t][0] === "#" && !neverTags.test(label) && labels.indexOf(label) < 0) labels.push(label);
      }
    }
    return labels;
  }

  function bind(form) {
    var body = form.querySelector('textarea[name="body"]');
    var line = form.querySelector('[data-role="compose-tags"]');
    var list = line && line.querySelector('[data-role="compose-tags-list"]');
    if (!body || !list || line.dataset.hashtagTagsBound === "1") return;
    line.dataset.hashtagTagsBound = "1";
    function sync() {
      var labels = selfTags(body.value);
      list.textContent = labels.map(function (label) { return "#" + label; }).join(" ");
      line.hidden = labels.length === 0;
    }
    body.addEventListener("input", sync);
    form.addEventListener("click", function (event) {
      var target = event.target;
      if (target && target.closest && target.closest('[data-action="clear-compose-fields"]')) setTimeout(sync, 0);
    });
    sync();
  }

  function bindAll(root) {
    var forms = (root || document).querySelectorAll("form[data-compose-form]");
    for (var i = 0; i < forms.length; i++) bind(forms[i]);
  }

  window.ForumHashtagTags = { selfTags: selfTags, bind: bind, bindAll: bindAll };
  if (typeof document !== "undefined" && document.addEventListener) {
    document.addEventListener("DOMContentLoaded", function () { bindAll(document); });
    document.addEventListener("focusin", function (event) {
      var form = event.target && event.target.closest && event.target.closest("form[data-compose-form]");
      if (form) bind(form);
    });
  }
})();
