<footer class="qdb-footer">
<?php if (($qdbPendingCount ?? null) !== null): ?>
  <p class="qdb-footer-counts"><?= (int) $qdbQuoteCount ?> <?= (int) $qdbQuoteCount === 1 ? 'quote' : 'quotes' ?> approved; <a href="/queue"><?= (int) $qdbPendingCount ?> pending</a></p>
<?php else: ?>
  <p class="qdb-footer-counts"><?= (int) $qdbQuoteCount ?> <?= (int) $qdbQuoteCount === 1 ? 'quote' : 'quotes' ?></p>
<?php endif; ?>
  <p class="qdb-footer-copyright">&copy; QDB 1999&ndash;<?= date('Y') ?>. All rights reserved.</p>
</footer>
