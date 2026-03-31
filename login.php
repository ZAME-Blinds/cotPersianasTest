<?php

require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    header('Location: cotizador.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Ingresa usuario y contraseña para continuar.';
    } elseif (!attempt_login($username, $password, $users)) {
        $error = 'Credenciales inválidas. Verifica tus datos e intenta nuevamente.';
    } else {
        header('Location: cotizador.php');
        exit;
    }
}

render_header('Acceso al cotizador');
?>
<div class="login-stage">
    <section class="panel login-panel">
        <div class="login-brand-block">
            <img src="assets/img/Logo_zame_2.png" alt="ZAME Blinds" class="brand-logo login-brand-logo">
            <h2 class="login-title">Cotizador interno ZAME Blinds</h2>
        </div>

        <?php if ($error !== ''): ?>
            <div class="message message-error login-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="form-grid login-form">
            <div>
                <label for="username">Usuario</label>
                <div class="input-minimal">
                    <span class="input-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5Zm0 2c-3.34 0-10 1.68-10 5v1a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-1c0-3.32-6.66-5-10-5Z"/></svg>
                    </span>
                    <input id="username" name="username" class="login-input" type="text" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" required>
                </div>
            </div>
            <div>
                <label for="password">Contraseña</label>
                <div class="input-minimal">
                    <span class="input-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><path d="M17 10h-1V7a4 4 0 1 0-8 0v3H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 0V7a2 2 0 1 1 4 0v3h-4Z"/></svg>
                    </span>
                    <input id="password" name="password" class="login-input" type="password" autocomplete="current-password" required>
                </div>
            </div>
            <div class="actions login-actions">
                <button type="submit" class="button login-button">Ingresar</button>
            </div>
        </form>

        <p class="login-footnote">Desarrollo ZAME Blinds - Ingeniería</p>
    </section>
</div>
<?php render_footer(); ?>
