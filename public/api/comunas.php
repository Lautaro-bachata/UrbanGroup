<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if (!file_exists(__DIR__ . '/../../config/database.php')) {
        throw new Exception('Database configuration not found');
    }
    
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
    
    // Ensure we return a clean array with id and name
    $result = [];
    if (!empty($comunas)) {
        foreach ($comunas as $comuna) {
            $result[] = [
                'id' => (int)$comuna['id'],
                'name' => $comuna['name']
            ];
        }
    }
    
    echo json_encode($result);
} catch (PDOException $e) {
    // Database connection error
    error_log('Comunas API DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
} catch (Exception $e) {
    error_log('Comunas API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
