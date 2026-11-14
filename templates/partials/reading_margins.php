<?php
// Agents: never ignore AGENTS.md; always follow its instructions.
// Reading margins: one row of evenly spaced radios, each drawn at the viewport
// position it stands for, so the row is a ruler. The left half is one radio
// group that sets where the text column starts; the right half is another that
// sets where it ends. layout.php's head script applies a saved choice before
// paint; the script below checks the matching radios before paint too. Keep the
// stops in step with layout.php.
$readingMarginEdges = [
    'left' => ['label' => 'Left edge of the text column', 'positions' => [5, 23, 41]],
    'right' => ['label' => 'Right edge of the text column', 'positions' => [59, 77, 95]],
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
