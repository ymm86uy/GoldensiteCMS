<?php $member=$members[(int)$post['author_id']]??['points'=>0,'badges'=>[]]; ?>
<p class="gs-forum-byline"><strong><?= $e($post['username']??'Cuenta retirada') ?></strong> · <time><?= $e($post['created_at']) ?></time>
<?php if ($post['author_id']): ?> · <?= (int)$member['points'] ?> puntos<?php endif ?>
<?php foreach ($member['badges'] as $badge): ?> · <span class="gs-forum-badge"><?= $e($badge) ?></span><?php endforeach ?></p>
