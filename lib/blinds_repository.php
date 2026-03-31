<?php

function get_blind_product_types(PDO $pdo, $productId = 1)
{
    $stmt = $pdo->prepare('SELECT id, name FROM product_types WHERE product_id = :product_id ORDER BY name ASC');
    $stmt->execute(['product_id' => (int) $productId]);

    return $stmt->fetchAll();
}

function get_active_fabrics_by_product_type(PDO $pdo, $productTypeId)
{
    $stmt = $pdo->prepare(
        'SELECT fm.id AS fabric_model_id, fm.name, fmpt.price_value
        FROM fabric_model_product_types fmpt
        INNER JOIN fabric_models fm ON fm.id = fmpt.fabric_model_id
        WHERE fmpt.product_type_id = :product_type_id
          AND fm.is_active = 1
        ORDER BY fm.name ASC'
    );

    $stmt->execute(['product_type_id' => (int) $productTypeId]);

    return $stmt->fetchAll();
}

function get_type_map(array $types)
{
    $map = [];

    foreach ($types as $type) {
        $map[(string) $type['id']] = $type;
    }

    return $map;
}

function get_fabric_map(array $fabrics)
{
    $map = [];

    foreach ($fabrics as $fabric) {
        $map[(string) $fabric['fabric_model_id']] = [
            'id' => (string) $fabric['fabric_model_id'],
            'name' => $fabric['name'],
            'price' => (float) $fabric['price_value'],
            'max_width' => null,
            'max_height' => null,
        ];
    }

    return $map;
}
