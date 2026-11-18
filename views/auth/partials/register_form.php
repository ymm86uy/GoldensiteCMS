<?php require __DIR__.'/login_error.php'; ?>
<?php if ($verify): ?>
    <form action="/register" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="input-group">
            <label for="activation_code">Código de activación</label>
            <input class="gs-register-code" id="activation_code" name="activation_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
        </div>
        <button type="submit" class="btn-submit" name="action" value="verify">Activar cuenta</button>
    </form>
    <form action="/register" method="post" class="gs-register-actions">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="gs-register-resend" name="action" value="resend">Enviar otro código</button>
    </form>
    <p class="gs-register-link"><a href="/register">Volver al formulario</a></p>
<?php else: ?>
    <form action="/register" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="input-group">
            <label for="username">Nombre de usuario</label>
            <input type="text" id="username" name="username" minlength="3" maxlength="50" autocomplete="username" required autofocus>
        </div>
        <div class="input-group">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" maxlength="100" autocomplete="email" required>
        </div>
        <div class="input-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
        </div>
        <div class="input-group">
            <label for="confirm_password">Confirmar contraseña</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn-submit">Enviar código de activación</button>
    </form>
    <?php if ($pending): ?><p class="gs-register-link"><a href="/register?step=verify">Ya tengo un código</a></p><?php endif; ?>
<?php endif; ?>
<p class="gs-register-link"><a href="/login">Volver a iniciar sesión</a></p>
