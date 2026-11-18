<main class="gs-login">
    <div class="gs-login-stage" aria-hidden="true">
        <div class="gs-login-art">
            <span class="gs-login-shape"></span>
            <span class="gs-login-shape"></span>
            <span class="gs-login-shape"></span>
            <img class="gs-login-mark" src="/assets/admin/goldensite-mark.svg" alt="" width="64" height="64">
        </div>
    </div>
    <div class="gs-login-panel">
        <header>
            <?php if ($logoUrl !== ''): ?>
                <img class="gs-login-logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php endif; ?>
            <p class="gs-login-brand"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></p>
            <h1><?= $registrationPage ? $registrationTitle : 'Iniciar sesión' ?></h1>
            <?php if ($registrationPage || $companyTagline !== ''): ?><p class="gs-login-caption"><?= htmlspecialchars($registrationPage ? $registrationIntro : $companyTagline, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </header>
        <?php if ($registrationPage): require __DIR__.'/register_form.php'; else: ?>
        <form action="/login" method="post">
            <?php require __DIR__.'/login_error.php'; ?>
            <fieldset class="login-fields"<?= $isPreview ? ' disabled' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="input-group">
                    <label for="gs-login-user">Correo o usuario</label>
                    <input id="gs-login-user" type="text" name="email" autocomplete="username" autocapitalize="none" required>
                </div>
                <div class="input-group">
                    <label for="gs-login-password">Contraseña</label>
                    <input id="gs-login-password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-submit">Entrar <span aria-hidden="true">↗</span></button>
            </fieldset>
        </form>
        <?php require __DIR__.'/register_link.php'; ?>
        <?php endif; ?>
        <?php if ($samlAvailable): ?><a class="gs-login-saml" href="/login/saml">Entrar con SAML corporativo</a><?php endif; ?>
    </div>
</main>
