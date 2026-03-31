<?php

require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'No autorizado']);
    exit;
}

$productTypeId = isset($_GET['product_type_id']) ? (int) $_GET['product_type_id'] : 0;

if ($productTypeId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parámetro product_type_id inválido.']);
    exit;
}

try {
    $pdo = get_pdo_connection();
    $fabrics = get_active_fabrics_by_product_type($pdo, $productTypeId);

    echo json_encode([
        'ok' => true,
        'items' => array_map(static function ($fabric) {
            return [
                'id' => (int) $fabric['fabric_model_id'],
                'name' => $fabric['name'],
                'price_value' => (float) $fabric['price_value'],
            ];
        }, $fabrics),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudieron cargar las telas.']);
}
