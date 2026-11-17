<?php
// Reading margins: one row of evenly spaced radios, each drawn at the viewport
// position it stands for, so the row is a ruler. The two checked radios are
// the text column's edges; a click moves the nearer edge. When a stop sits
// exactly midway between the edges, the edge clicked longer ago is outlined:
// a click there moves it. Each radio has its own name so that two can be
// checked at once. layout.php's head script applies a saved choice before
// paint (stored older first); the script below checks both radios and draws
// the outline before paint too. Keep the stops in step with layout.php.
$readingMarginStops = [5, 23, 41, 59, 77, 95];
?>
<div class="reading-margins" data-role="reading-margins">
  <fieldset class="reading-margins__row">
    <legend class="visually-hidden">Text column edges</legend>
<?php foreach ($readingMarginStops as $position): ?>
    <input class="reading-margins__stop" type="radio" name="reading-margin-<?= (int) $position ?>" value="<?= (int) $position ?>" aria-label="Column edge at <?= (int) $position ?>%" data-reading-margin-stop style="--at: <?= (int) $position ?>">
<?php endforeach; ?>
  </fieldset>
</div>
<script>
  (function () {
    var saved = (document.documentElement.getAttribute('data-reading-margins') || '').split(' ');
    if (saved.length !== 2) return;
    var stops = Array.prototype.map.call(document.querySelectorAll('[data-reading-margin-stop]'), function (stop) { return stop.value; });
    var tiePossible = (stops.indexOf(saved[1]) - stops.indexOf(saved[0])) % 2 === 0;
    saved.forEach(function (value, index) {
      var stop = document.querySelector('[data-reading-margin-stop][value="' + value + '"]');
      if (!stop) return;
      stop.checked = true;
      if (index === 0 && tiePossible) stop.setAttribute('data-reading-margin-next', '');
    });
  })();
</script>
