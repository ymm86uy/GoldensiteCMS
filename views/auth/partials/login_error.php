<?php if (!empty($error)): ?>
<div role="alert" style="margin-bottom:1.25rem;padding:0.9rem 1rem;border:1px solid #f87171;border-radius:0.75rem;background:#451a1a;color:#fecaca;text-align:left;font-size:0.9rem;line-height:1.5;">
    <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
<?php if (!empty($notice)): ?>
<div role="status" style="margin-bottom:1.25rem;padding:0.9rem 1rem;border:1px solid #34d399;border-radius:0.75rem;background:#052e24;color:#a7f3d0;text-align:left;font-size:0.9rem;">
    <?= htmlspecialchars((string) $notice, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
