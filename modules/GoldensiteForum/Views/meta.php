<?php if (isset($forumAssets)): ?>
<link rel="stylesheet" href="/forum/assets/style">
<script defer src="/forum/assets/script"></script>
<?= \Modules\GoldensiteForum\Appearance::css($branding) ?>
<?php endif ?>
<?php if (isset($meta['description'])): ?>
<meta name="description" content="<?= $e($meta['description']) ?>">
<meta property="og:title" content="<?= $e($siteTitle) ?>">
<meta property="og:description" content="<?= $e($meta['description']) ?>">
<meta property="og:type" content="article">
<?php if (isset($meta['url'])): ?>
<link rel="canonical" href="<?= $e($meta['url']) ?>">
<meta property="og:url" content="<?= $e($meta['url']) ?>">
<script type="application/ld+json"><?= json_encode($meta['structured'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<?php endif ?>
<?php endif ?>
