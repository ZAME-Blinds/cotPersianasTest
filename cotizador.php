<?php

require __DIR__ . '/includes/bootstrap.php';

require_login();

$operationModes = get_operation_modes($catalog);
$items = get_quote_items();
$summary = get_quote_summary($items);
$storedMeta = get_quote_meta();

$dbError = '';
$types = [];
$typeMap = [];

try {
    $pdo = get_pdo_connection();
    $types = get_blind_product_types($pdo, 1);
    $typeMap = get_type_map($types);
} catch (Throwable $exception) {
    $dbError = 'No se pudo cargar el catálogo de persianas desde la base de datos.';
}

$formData = [
    'cliente' => $storedMeta['cliente'],
    'telefono' => $storedMeta['telefono'],
    'direccion' => $storedMeta['direccion'] ?? '',
    'vigencia' => $storedMeta['vigencia'],
    'observaciones' => $storedMeta['observaciones'],
    'tipo' => '',
    'modelo' => '',
    'ancho' => '',
    'alto' => '',
    'accionamiento' => 'Manual',
    'motor' => '',
    'control' => 'none',
];

$messages = [
    'errors' => [],
    'success' => '',
    'info' => '',
];
$currentQuote = null;
$action = '';
$modelMap = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['cliente'] = trim($_POST['cliente'] ?? $formData['cliente']);
    $formData['telefono'] = trim($_POST['telefono'] ?? $formData['telefono']);
    $formData['direccion'] = trim($_POST['direccion'] ?? $formData['direccion']);
    $formData['vigencia'] = trim($_POST['vigencia'] ?? $formData['vigencia']);
    $formData['observaciones'] = trim($_POST['observaciones'] ?? $formData['observaciones']);
    $formData['tipo'] = trim($_POST['tipo'] ?? '');
    $formData['modelo'] = trim($_POST['modelo'] ?? '');
    $formData['ancho'] = trim($_POST['ancho'] ?? '');
    $formData['alto'] = trim($_POST['alto'] ?? '');
    $formData['accionamiento'] = trim($_POST['accionamiento'] ?? 'Manual');
    $formData['motor'] = trim($_POST['motor'] ?? '');
    $formData['control'] = trim($_POST['control'] ?? 'none');
    $action = trim($_POST['form_action'] ?? '');

    save_quote_meta([
        'cliente' => $formData['cliente'],
        'telefono' => $formData['telefono'],
        'direccion' => $formData['direccion'],
        'vigencia' => $formData['vigencia'],
        'observaciones' => $formData['observaciones'],
    ]);

    if ($action === 'refresh') {
        $messages['info'] = 'Se actualizaron las opciones compatibles según la selección actual.';
    } elseif ($action === 'quote_single' || $action === 'add_piece' || $action === 'download_single') {
        $selectedTypeForPost = $typeMap[$formData['tipo']] ?? null;

        if ($selectedTypeForPost) {
            try {
                $modelMap = get_fabric_map(get_active_fabrics_by_product_type($pdo, $selectedTypeForPost['id']));
            } catch (Throwable $exception) {
                $dbError = 'No se pudieron cargar las telas desde la base de datos.';
            }
        }

        $quoteResult = build_quote_from_input($formData, $catalog, $typeMap, $modelMap);
        $messages['errors'] = $quoteResult['errors'];
        $currentQuote = $quoteResult['quote'];

        if ($currentQuote && $action === 'quote_single') {
            $messages['success'] = 'Cotización individual calculada correctamente.';
        }

        if ($currentQuote && $action === 'add_piece') {
            add_quote_item($currentQuote);
            $items = get_quote_items();
            $summary = get_quote_summary($items);
            $messages['success'] = 'La persiana se agregó a la tabla temporal.';
        }

        if ($currentQuote && $action === 'download_single') {
            $documentMeta = build_document_meta($formData);
            $messages['errors'] = array_merge($messages['errors'], validate_document_meta($documentMeta));

            if (empty($messages['errors'])) {
                try {
                    output_docx_download([$currentQuote], $documentMeta, $company);
                } catch (RuntimeException $exception) {
                    $messages['errors'][] = $exception->getMessage();
                }
            }
        }
    } elseif ($action === 'quote_all') {
        $documentMeta = build_document_meta($formData);
        $messages['errors'] = validate_document_meta($documentMeta);

        if ($summary['count'] <= 2) {
            $messages['errors'][] = 'Agrega al menos tres persianas antes de usar "Cotizar PERSIANAS".';
        }

        if (empty($messages['errors'])) {
            try {
                output_docx_download($items, $documentMeta, $company);
            } catch (RuntimeException $exception) {
                $messages['errors'][] = $exception->getMessage();
            }
        }
    }
}

$selectedType = $typeMap[$formData['tipo']] ?? null;
$models = [];
$modelMap = [];

if ($selectedType) {
    try {
        $models = get_active_fabrics_by_product_type($pdo, $selectedType['id']);
        $modelMap = get_fabric_map($models);
    } catch (Throwable $exception) {
        $dbError = 'No se pudieron cargar las telas desde la base de datos.';
    }
}

if ($formData['modelo'] !== '' && !isset($modelMap[$formData['modelo']])) {
    $formData['modelo'] = '';
}

$motors = get_motors_for_type($catalog, $selectedType['name'] ?? '');
if ($formData['motor'] !== '' && !isset($motors[$formData['motor']])) {
    $formData['motor'] = '';
}

$selectedMotor = $formData['motor'] !== '' ? ($motors[$formData['motor']] ?? null) : null;
$controls = $formData['accionamiento'] === 'Motorizado' && !empty($motors) ? get_controls_for_motor($catalog, $selectedMotor) : [];
if ($formData['control'] !== '' && $formData['control'] !== 'none' && !isset($controls[$formData['control']])) {
    $formData['control'] = 'none';
}

$typeNote = get_type_note($catalog, $selectedType['name'] ?? '');
$disableQuoteAll = $summary['count'] <= 2;

render_header('Cotizador de persianas - Fase 3');
?>
<section class="panel panel-spacing">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Fase 3</p>
            <h2>Cotización profesional y descarga DOCX</h2>
        </div>
        <div class="summary-chip-group">
            <span class="summary-chip">Piezas agregadas: <?php echo htmlspecialchars((string) $summary['count'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="summary-chip">Total acumulado: $<?php echo htmlspecialchars(format_money($summary['total']), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <p>Captura los datos del cliente, arma la cotización y genera el DOCX final directamente desde backend PHP.</p>

    <?php foreach ($messages['errors'] as $error): ?>
        <div class="message message-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <?php if ($messages['success'] !== ''): ?>
        <div class="message message-success"><?php echo htmlspecialchars($messages['success'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($messages['info'] !== ''): ?>
        <div class="message message-info"><?php echo htmlspecialchars($messages['info'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($dbError !== ''): ?>
        <div class="message message-error"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($typeNote !== ''): ?>
        <div class="message message-warning"><?php echo htmlspecialchars($typeNote, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="cotizador.php" class="form-grid">
        <input type="hidden" name="form_action" value="quote_single" id="form_action">

        <section class="subsection-card">
            <div class="section-heading compact-heading">
                <div>
                    <p class="eyebrow">Cliente</p>
                    <h3>Datos para la cotización DOCX</h3>
                </div>
            </div>
            <div class="form-grid columns-2 columns-4">
                <div>
                    <label for="cliente">Nombre del cliente</label>
                    <input id="cliente" name="cliente" type="text" value="<?php echo htmlspecialchars($formData['cliente'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div>
                    <label for="telefono">Teléfono o WhatsApp</label>
                    <input id="telefono" name="telefono" type="text" value="<?php echo htmlspecialchars($formData['telefono'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div>
                    <label for="direccion">Dirección</label>
                    <input id="direccion" name="direccion" type="text" value="<?php echo htmlspecialchars($formData['direccion'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="vigencia">Vigencia</label>
                    <input id="vigencia" name="vigencia" type="text" value="<?php echo htmlspecialchars($formData['vigencia'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div>
                    <label for="observaciones">Observaciones generales</label>
                    <textarea id="observaciones" name="observaciones" rows="3"><?php echo htmlspecialchars($formData['observaciones'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        </section>

        <section class="subsection-card">
            <div class="section-heading compact-heading">
                <div>
                    <p class="eyebrow">Pieza actual</p>
                    <h3>Configuración de persiana</h3>
                </div>
            </div>
            <div class="form-grid columns-2 columns-4">
                <div>
                    <label for="tipo">Tipo de persiana</label>
                    <select id="tipo" name="tipo">
                        <option value="">Selecciona una opción</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo htmlspecialchars((string) $type['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['tipo'] === (string) $type['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="button button-secondary inline-button" onclick="document.getElementById('form_action').value='refresh';">Actualizar modelos</button></noscript>
                </div>
                <div>
                    <label for="modelo">Tela / modelo</label>
                    <select id="modelo" name="modelo" <?php echo empty($models) ? 'disabled' : ''; ?>>
                        <option value=""><?php echo empty($models) ? 'Selecciona primero un tipo de persiana' : 'Selecciona una opción'; ?></option>
                        <?php foreach ($models as $model): ?>
                            <option value="<?php echo htmlspecialchars((string) $model['fabric_model_id'], ENT_QUOTES, 'UTF-8'); ?>" data-price="<?php echo htmlspecialchars((string) $model['price_value'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['modelo'] === (string) $model['fabric_model_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($model['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="model_price_value" name="model_price_value" value="">
                </div>
                <div>
                    <label for="accionamiento">Accionamiento</label>
                    <select id="accionamiento" name="accionamiento" onchange="document.getElementById('form_action').value='refresh'; this.form.submit();">
                        <?php foreach ($operationModes as $operationMode): ?>
                            <option value="<?php echo htmlspecialchars($operationMode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['accionamiento'] === $operationMode ? 'selected' : ''; ?>><?php echo htmlspecialchars($operationMode, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Color</label>
                    <div class="static-field">Por definir</div>
                    <p class="help-text">No afecta el precio en esta fase.</p>
                </div>
            </div>

            <div class="form-grid columns-2 columns-4">
                <div>
                    <label for="ancho">Ancho (m)</label>
                    <input id="ancho" name="ancho" type="number" min="0.20" step="0.01" value="<?php echo htmlspecialchars($formData['ancho'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    <p class="help-text">Mínimo capturable: 0.20 m. Mínimo cobrable: 1.00 m.</p>
                </div>
                <div>
                    <label for="alto">Alto (m)</label>
                    <input id="alto" name="alto" type="number" min="0.20" step="0.01" value="<?php echo htmlspecialchars($formData['alto'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    <p class="help-text">Estas medidas no se incluyen en el DOCX final.</p>
                </div>
                <?php if ($formData['accionamiento'] === 'Motorizado'): ?>
                    <?php if (empty($motors)): ?>
                        <div class="full-span">
                            <div class="message message-warning no-margin">No hay motores compatibles para el tipo de persiana seleccionado en esta fase.</div>
                        </div>
                    <?php else: ?>
                        <div>
                            <label for="motor">Motor compatible</label>
                            <select id="motor" name="motor">
                                <option value="">Selecciona una opción</option>
                                <?php foreach ($motors as $motor): ?>
                                    <option value="<?php echo htmlspecialchars($motor['code'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['motor'] === $motor['code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($motor['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-text">Solo se muestran motores compatibles con el tipo seleccionado.</p>
                        </div>
                        <div>
                            <label for="control">Control remoto</label>
                            <select id="control" name="control">
                                <?php foreach ($controls as $control): ?>
                                    <option value="<?php echo htmlspecialchars($control['code'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $formData['control'] === $control['code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($control['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-text">Disponible solo para motores RTS. Puedes elegir "Sin control remoto".</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="full-span">
                        <div class="message message-info no-margin">Accionamiento manual seleccionado. No se agrega costo extra en esta fase.</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="actions actions-wrap">
            <button type="submit" class="button" onclick="document.getElementById('form_action').value='quote_single';">Cotizar persiana</button>
            <button type="submit" class="button button-secondary" onclick="document.getElementById('form_action').value='add_piece';">Agregar persiana</button>
            <button type="submit" class="button button-accent" onclick="document.getElementById('form_action').value='download_single';">Descargar DOCX individual</button>
            <button type="submit" class="button button-dark" onclick="document.getElementById('form_action').value='quote_all';" <?php echo $disableQuoteAll ? 'disabled' : ''; ?>>Cotizar PERSIANAS</button>
        </div>
    </form>
</section>

<?php if ($currentQuote): ?>
    <section class="panel panel-spacing">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Resultado</p>
                <h2>Cotización individual</h2>
            </div>
            <span class="summary-chip">Precio pieza: $<?php echo htmlspecialchars(format_money($currentQuote['total_price']), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="quote-breakdown">
            <div class="breakdown-item"><strong>Tipo:</strong> <?php echo htmlspecialchars($currentQuote['type'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Tela / modelo:</strong> <?php echo htmlspecialchars($currentQuote['model'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Color:</strong> <?php echo htmlspecialchars($currentQuote['color'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Accionamiento:</strong> <?php echo htmlspecialchars($currentQuote['operation'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Medidas capturadas:</strong> <?php echo htmlspecialchars(format_money($currentQuote['width']), ENT_QUOTES, 'UTF-8'); ?> × <?php echo htmlspecialchars(format_money($currentQuote['height']), ENT_QUOTES, 'UTF-8'); ?> m</div>
            <div class="breakdown-item"><strong>Medidas cobrables:</strong> <?php echo htmlspecialchars(format_money($currentQuote['billable_width']), ENT_QUOTES, 'UTF-8'); ?> × <?php echo htmlspecialchars(format_money($currentQuote['billable_height']), ENT_QUOTES, 'UTF-8'); ?> m</div>
            <div class="breakdown-item"><strong>Área cobrable:</strong> <?php echo htmlspecialchars(format_money($currentQuote['area']), ENT_QUOTES, 'UTF-8'); ?> m²</div>
            <div class="breakdown-item"><strong>Precio por m²:</strong> $<?php echo htmlspecialchars(format_money($currentQuote['price_per_m2']), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Subtotal persiana:</strong> $<?php echo htmlspecialchars(format_money($currentQuote['base_price']), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Motor:</strong> <?php echo htmlspecialchars($currentQuote['motor_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Control remoto:</strong> <?php echo htmlspecialchars($currentQuote['control_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="breakdown-item"><strong>Total:</strong> $<?php echo htmlspecialchars(format_money($currentQuote['total_price']), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </section>
<?php endif; ?>

<section class="panel panel-spacing">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Tabla temporal</p>
            <h2>Piezas acumuladas</h2>
        </div>
        <div class="summary-chip-group">
            <span class="summary-chip">Cantidad: <?php echo htmlspecialchars((string) $summary['count'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="summary-chip">Total acumulado: $<?php echo htmlspecialchars(format_money($summary['total']), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <p class="help-text">El botón <strong>Cotizar PERSIANAS</strong> genera y descarga el DOCX final con folio incremental cuando existan al menos tres piezas.</p>

    <?php if (empty($items)): ?>
        <p>Aún no hay persianas agregadas a la tabla temporal.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Tela / modelo</th>
                        <th>Color</th>
                        <th>Accionamiento</th>
                        <th>Precio pieza</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['model'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['operation'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>$<?php echo htmlspecialchars(format_money($item['total_price']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">Total acumulado</td>
                        <td>$<?php echo htmlspecialchars(format_money($summary['total']), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
<script>
(function () {
    const typeSelect = document.getElementById('tipo');
    const modelSelect = document.getElementById('modelo');
    const priceInput = document.getElementById('model_price_value');

    if (!typeSelect || !modelSelect || !priceInput) {
        return;
    }

    const syncPrice = () => {
        const selected = modelSelect.options[modelSelect.selectedIndex];
        priceInput.value = selected && selected.dataset.price ? selected.dataset.price : '';
    };

    const populateModels = (items, selectedModelId) => {
        modelSelect.innerHTML = '';

        if (!items.length) {
            modelSelect.disabled = true;
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No hay telas activas para este tipo';
            modelSelect.appendChild(option);
            syncPrice();
            return;
        }

        modelSelect.disabled = false;
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Selecciona una opción';
        modelSelect.appendChild(placeholder);

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.name;
            option.dataset.price = String(item.price_value);
            if (String(item.id) === String(selectedModelId)) {
                option.selected = true;
            }
            modelSelect.appendChild(option);
        });

        syncPrice();
    };

    const loadModels = async (typeId, selectedModelId = '') => {
        if (!typeId) {
            populateModels([], '');
            return;
        }

        try {
            const response = await fetch(`api/fabrics.php?product_type_id=${encodeURIComponent(typeId)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            populateModels(payload.ok && Array.isArray(payload.items) ? payload.items : [], selectedModelId);
        } catch (error) {
            populateModels([], '');
        }
    };

    typeSelect.addEventListener('change', () => {
        loadModels(typeSelect.value, '');
    });

    modelSelect.addEventListener('change', syncPrice);
    syncPrice();
})();
</script>
<?php render_footer(); ?>
