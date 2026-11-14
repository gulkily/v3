<?php
// Reading margins: one row of evenly spaced radios, each drawn at the viewport
// position it stands for, so the row is a ruler. The text column spans the
// last two radios clicked; the older of the two is outlined as the edge the
// next click moves. Each radio has its own name so that two can be checked at
// once. layout.php's head script applies a saved choice before paint (stored
// older first); the script below checks both radios and outlines the older one
// before paint too. Keep the stops in step with layout.php.
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
    saved.forEach(function (value, index) {
      var stop = document.querySelector('[data-reading-margin-stop][value="' + value + '"]');
      if (!stop) return;
      stop.checked = true;
      if (index === 0) stop.setAttribute('data-reading-margin-next', '');
    });
  })();
</script>
