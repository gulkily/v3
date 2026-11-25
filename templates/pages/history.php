<section class="stack" data-visit-history>
  <article class="card">
    <h1>History</h1>
    <p class="meta" data-role="visit-history-empty" hidden>No pages yet.</p>
    <noscript><p class="meta">History is kept by JavaScript in this browser.</p></noscript>
    <ul class="visit-tree" data-role="visit-history-tree"></ul>
    <div class="visit-history-actions">
      <button type="button" class="account-key-secondary-button" data-action="visit-history-clear" hidden>Clear</button>
    </div>
    <script>
      // Tools → History (visit_history.js keeps the visits). Drawn here, before
      // paint, from this browser's own storage; the server page is the same for
      // everyone. A page reached from another sits below it; where one page led
      // to several, each branch is indented under it. Newest branch first.
      (function () {
        var page = document.querySelector('[data-visit-history]');
        var tree = page.querySelector('[data-role="visit-history-tree"]');
        var empty = page.querySelector('[data-role="visit-history-empty"]');
        var clear = page.querySelector('[data-action="visit-history-clear"]');
        function visits() {
          try {
            var state = JSON.parse(localStorage.getItem('forum_visit_history') || 'null');
            return state && state.v === 1 && Array.isArray(state.visits) ? state.visits.filter(function (visit) {
              return visit && typeof visit.id === 'string' && typeof visit.url === 'string' && visit.url.charAt(0) === '/' && visit.url.charAt(1) !== '/';
            }) : [];
          } catch (error) {
            return [];
          }
        }
        function when(seconds) {
          var date = new Date(seconds * 1000);
          var today = new Date();
          return date.toDateString() === today.toDateString()
            ? date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
            : date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ' + date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }
        function draw() {
          var all = visits();
          var byId = {};
          var children = {};
          all.forEach(function (visit) { byId[visit.id] = visit; children[visit.id] = []; });
          var roots = [];
          all.forEach(function (visit) {
            if (visit.parent && byId[visit.parent] && visit.parent !== visit.id) children[visit.parent].push(visit);
            else roots.push(visit);
          });
          // Visits are stored oldest first, so the newest visit anywhere in a
          // branch is the one with the highest place; the newest branch comes first.
          var place = {};
          all.forEach(function (visit, index) { place[visit.id] = index; });
          var latestIn = {};
          function latest(visit) {
            if (latestIn[visit.id] !== undefined) return latestIn[visit.id];
            var best = place[visit.id];
            var stack = [visit];
            var walked = {};
            while (stack.length) {
              var at = stack.pop();
              if (walked[at.id]) continue;
              walked[at.id] = true;
              if (place[at.id] > best) best = place[at.id];
              children[at.id].forEach(function (child) { stack.push(child); });
            }
            return (latestIn[visit.id] = best);
          }
          function row(visit) {
            var li = document.createElement('li');
            var link = document.createElement('a');
            link.href = visit.url;
            link.textContent = visit.title || visit.url;
            var time = document.createElement('span');
            time.className = 'meta visit-tree__when';
            time.textContent = when(visit.t);
            li.appendChild(link);
            if (visit.beside) {
              var mark = document.createElement('span');
              mark.className = 'meta visit-tree__beside';
              mark.textContent = 'beside';
              li.appendChild(mark);
            }
            li.appendChild(time);
            return li;
          }
          // A visit with one next page continues in the same list; a visit
          // that led to several opens an indented list, one branch each.
          function branch(list, visit, seen) {
            while (visit && !seen[visit.id]) {
              seen[visit.id] = true;
              var li = row(visit);
              list.appendChild(li);
              var next = children[visit.id];
              if (next.length === 1) { visit = next[0]; continue; }
              if (next.length > 1) {
                var nested = document.createElement('ul');
                next.slice().sort(function (a, b) { return latest(b) - latest(a); }).forEach(function (child) {
                  var item = document.createElement('li');
                  item.className = 'visit-tree__branch';
                  var inner = document.createElement('ul');
                  branch(inner, child, seen);
                  item.appendChild(inner);
                  nested.appendChild(item);
                });
                li.appendChild(nested);
              }
              visit = null;
            }
          }
          tree.replaceChildren();
          var seen = {};
          roots.sort(function (a, b) { return latest(b) - latest(a); }).forEach(function (visit) {
            var item = document.createElement('li');
            item.className = 'visit-tree__branch';
            var inner = document.createElement('ul');
            branch(inner, visit, seen);
            item.appendChild(inner);
            tree.appendChild(item);
          });
          empty.hidden = all.length > 0;
          clear.hidden = all.length === 0;
        }
        clear.addEventListener('click', function () {
          if (window.forumVisitHistory) window.forumVisitHistory.clear();
          else try { localStorage.removeItem('forum_visit_history'); } catch (error) {}
          draw();
        });
        draw();
        // Another tab may add visits while this page is open.
        window.addEventListener('storage', function (event) { if (event.key === 'forum_visit_history' || event.key === null) draw(); });
      })();
    </script>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
