<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/TerrenoModel.php';

if (!isset($_SESSION['portal_client'])) {
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

$comunaId = $_GET['comuna_id'] ?? '';
$type = $_GET['type'] ?? 'zonas';

$terrenoModel = new TerrenoModel();

try {
    if ($type === 'zonas') {
        $data = !empty($comunaId) 
            ? $terrenoModel->getZonasPRCByComuna($comunaId) 
            : $terrenoModel->getDistinctZonasPRC();
    } elseif ($type === 'usos') {
        $data = !empty($comunaId) 
            ? $terrenoModel->getUsosSueloByComuna($comunaId) 
            : $terrenoModel->getDistinctUsosSuelo();
    } else {
        $data = [];
    }
    
    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode([]);
}
