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
<section class="panel login-panel">
    <div class="login-brand-block">
        <img src="assets/img/Logo_zame_2.png" alt="ZAME Blinds" class="brand-logo login-brand-logo">
        <h2 class="login-title">Cotizador interno ZAME Blinds</h2>
    </div>

    <?php if ($error !== ''): ?>
        <div class="message message-error login-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" class="form-grid">
        <div>
            <label for="username">Usuario</label>
            <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" required>
        </div>
        <div>
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <div class="actions login-actions">
            <button type="submit" class="button login-button">Ingresar</button>
        </div>
    </form>

    <p class="login-footnote">Desarrollo ZAME Blinds - Ingeniería</p>
</section>
<?php render_footer(); ?>
