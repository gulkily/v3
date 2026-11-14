<?php
// Reading margins: each radio sits at the viewport position it stands for, so
// the row is a ruler. The left group picks where the text column starts, the
// right group where it ends. layout.php's head script applies a saved choice
// before paint; the script below checks the matching radios before paint too.
$readingMarginEdges = [
    'left' => ['label' => 'Left margin', 'positions' => [4, 14, 24, 34]],
    'right' => ['label' => 'Right margin', 'positions' => [66, 76, 86, 96]],
];
?>
<div class="reading-margins" data-role="reading-margins">
<?php foreach ($readingMarginEdges as $edge => $readingMarginEdge): ?>
  <fieldset class="reading-margins__edge">
    <legend class="visually-hidden"><?= $e($readingMarginEdge['label']) ?></legend>
<?php foreach ($readingMarginEdge['positions'] as $position): ?>
    <input class="reading-margins__stop" type="radio" name="reading-margin-<?= $e($edge) ?>" value="<?= (int) $position ?>" aria-label="<?= (int) $position ?>%" data-reading-margin-edge="<?= $e($edge) ?>" style="--at: <?= (int) $position ?>">
<?php endforeach; ?>
  </fieldset>
<?php endforeach; ?>
</div>
<script>
  (function () {
    var saved = (document.documentElement.getAttribute('data-reading-margins') || '').split(' ');
    if (saved.length !== 2) return;
    ['left', 'right'].forEach(function (edge, index) {
      var stop = document.querySelector('[data-reading-margin-edge="' + edge + '"][value="' + saved[index] + '"]');
      if (stop) stop.checked = true;
    });
  })();
</script>
