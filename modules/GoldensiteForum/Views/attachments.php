<?php if ($files): ?><ul class="gs-forum-list"><?php foreach ($files as $file): ?>
<li><?php if (str_starts_with($file['mime'],'image/')): ?><a href="/forum/file/<?= $e($file['id']) ?>"><img loading="lazy" src="/forum/file/<?= $e($file['id']) ?>" alt="<?= $e($file['label']) ?>"></a><?php else: ?><a href="/forum/file/<?= $e($file['id']) ?>"><?= $e($file['label']) ?></a><?php endif ?></li>
<?php endforeach ?></ul><?php endif ?>
