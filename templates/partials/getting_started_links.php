<?php
// Where to go on a new instance: shared by the getting started page (/start/) and the
// local start page (/local/). $part is 'browse' (button row) or 'try' (forum actions).
?>
<?php if ($part === 'browse'): ?>
<div class="button-row button-row-natural">
  <a href="/threads/?view=all">Browse threads</a>
  <a href="/recent/">Recent posts</a>
  <a href="/themes/">Themes</a>
  <a href="/docs/">Documentation</a>
</div>
<?php else: ?>
<p><a href="/compose/thread">Start a thread</a>, reply to a post, or <a href="/search/">search</a>.</p>
<p><?php if ($agentPageEnabled): ?><a href="/agent/start/">Work with an agent</a> · <?php endif; ?><a href="/tools/">Tools</a> · <a href="/about/">About the project</a></p>
<?php endif; ?>
