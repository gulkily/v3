<?php
// Reading margins: one row of evenly spaced radios, each drawn at the position
// it stands for, so the row is a ruler. The two checked radios are the text
// column's edges. Positions count fifths of the site's normal column from its
// left edge: 0 and 5 are the normal edges (the home stops, checked until the
// reader chooses a column), and the same step continues outside the column.
// A click moves the nearer edge; when a stop sits exactly midway between the
// edges, the edge clicked longer ago is ringed: a click there moves it. Each
// radio has its own name so that two can be checked at once. layout.php's head
// script applies a saved choice before paint (stored older first); the script
// below shows the stops that fit the window, checks the radios and draws the
// ring before paint too. Keep the range in step with layout.php.
$readingMarginStops = range(-8, 13);
$readingMarginLabel = static function (int $position): string {
    if ($position === 0) {
        return 'Normal left edge';
    }
    if ($position === 5) {
        return 'Normal right edge';
    }
    if ($position < 0) {
        return 'Column edge ' . -$position . ' left of normal';
    }
    if ($position > 5) {
        return 'Column edge ' . ($position - 5) . ' right of normal';
    }

    return 'Column edge ' . $position . ' fifths across';
};
?>
<div class="reading-margins" data-role="reading-margins">
  <span class="reading-margins__column" aria-hidden="true"></span>
  <fieldset class="reading-margins__row">
    <legend class="visually-hidden">Text column edges</legend>
<?php foreach ($readingMarginStops as $index => $position): ?>
    <input class="reading-margins__stop" type="radio" name="reading-margin-<?= (int) $index ?>" value="<?= (int) $position ?>" aria-label="<?= $e($readingMarginLabel($position)) ?>" data-reading-margin-stop style="--at: <?= (int) $position ?>"<?= $position === 0 || $position === 5 ? ' checked' : '' ?><?= $position < 0 || $position > 5 ? ' hidden' : '' ?>>
<?php endforeach; ?>
  </fieldset>
</div>
<script>
  (function () {
    var root = document.documentElement;
    var ruler = document.querySelector('[data-role="reading-margins"]');
    var stops = Array.prototype.slice.call(ruler.querySelectorAll('[data-reading-margin-stop]'));
    // Shows the stops that are inside the window and returns which ones are.
    // reading_margins.js calls it again when the window is resized.
    window.forumReadingMarginsFit = function () {
      var box = ruler.getBoundingClientRect();
      var fits = {};
      stops.forEach(function (stop) {
        var x = box.left + stop.value * box.width / 5;
        fits[stop.value] = box.width > 0 && x >= 12 && x <= root.clientWidth - 12;
        stop.hidden = !fits[stop.value];
      });
      return fits;
    };
    var fits = window.forumReadingMarginsFit();
    var saved = (root.getAttribute('data-reading-margins') || '').split(' ');
    if (saved.length !== 2) return;
    // A column saved in a wider window may not fit this one: use the normal
    // column here and leave the saved choice alone.
    if (!fits[saved[0]] || !fits[saved[1]]) {
      root.removeAttribute('data-reading-margins');
      root.style.removeProperty('--reading-left');
      root.style.removeProperty('--reading-right');
      return;
    }
    var tiePossible = (saved[1] - saved[0]) % 2 === 0;
    stops.forEach(function (stop) {
      stop.checked = saved.indexOf(stop.value) !== -1;
      if (stop.value === saved[0] && tiePossible) stop.setAttribute('data-reading-margin-next', '');
    });
  })();
</script>
