<?php

require __DIR__ . '/includes/bootstrap.php';

require_login();

$width = '';
$height = '';
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $width = trim($_POST['ancho'] ?? '');
    $height = trim($_POST['alto'] ?? '');

    if ($width === '' || $height === '') {
        $error = 'Captura ancho y alto.';
    } elseif (!is_numeric($width) || !is_numeric($height)) {
        $error = 'Ancho y alto deben ser valores numéricos.';
    } elseif ((float) $width < 0.20 || (float) $height < 0.20) {
        $error = 'El ancho y el alto mínimos son 0.20.';
    } else {
        $result = (float) $width * (float) $height;
    }
}

render_header('Cotizador base');
?>
<section class="panel">
    <p>Base inicial para una aplicación web de cotización en PHP. En esta fase solo calcula el área.</p>

    <?php if ($error !== ''): ?>
        <div class="message message-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <div class="result">Área calculada: <strong><?php echo htmlspecialchars(number_format($result, 2), ENT_QUOTES, 'UTF-8'); ?> m²</strong></div>
    <?php endif; ?>

    <form method="post" action="cotizador.php" class="form-grid columns-2">
        <div>
            <label for="ancho">Ancho</label>
            <input id="ancho" name="ancho" type="number" min="0.20" step="0.01" value="<?php echo htmlspecialchars($width, ENT_QUOTES, 'UTF-8'); ?>" required>
            <p class="help-text">Mínimo capturable: 0.20</p>
        </div>
        <div>
            <label for="alto">Alto</label>
            <input id="alto" name="alto" type="number" min="0.20" step="0.01" value="<?php echo htmlspecialchars($height, ENT_QUOTES, 'UTF-8'); ?>" required>
            <p class="help-text">Mínimo capturable: 0.20</p>
        </div>
        <div class="actions">
            <button type="submit" class="button">Calcular</button>
        </div>
    </form>
</section>
<?php render_footer(); ?>
