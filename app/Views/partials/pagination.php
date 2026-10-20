<?php /** @var int $page @var int $pages @var array $query */ if (($pages ?? 1) > 1): ?>
<nav class="pagination" aria-label="Pagination">
<?php for ($p = 1; $p <= $pages; $p++): ?>
  <?php if ($p === $page): ?><span class="current" aria-current="page"><?= $p ?></span>
  <?php elseif ($p <= 2 || $p > $pages - 2 || abs($p - $page) <= 2): ?><a href="?<?= e(http_build_query(array_merge($query, ['page' => $p]))) ?>"><?= $p ?></a>
  <?php elseif ($p === 3 || $p === $pages - 2): ?><span>…</span><?php endif; ?>
<?php endfor; ?>
</nav>
<?php endif; ?>
