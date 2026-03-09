<?php
header('Content-Type: application/json');

if (!file_exists(__DIR__ . '/../../config/database.php')) {
    echo json_encode([]);
    exit;
}

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/LocationModel.php';

    $region_id = (int)($_GET['region_id'] ?? 0);
    if (!$region_id) {
        echo json_encode([]);
        exit;
    }

    $locationModel = new LocationModel();
    $comunas = $locationModel->getComunas($region_id);
    echo json_encode($comunas ?? []);
} catch (Exception $e) {
    echo json_encode([]);
}
