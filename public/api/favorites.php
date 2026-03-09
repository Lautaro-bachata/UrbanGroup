<?php
session_start();
header('Content-Type: application/json');

// Ensure config is loaded before initializing database (some includes expect DB constants)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/FavoriteModel.php';

$favoriteModel = new FavoriteModel();

$clientId = $_SESSION['portal_client_id'] ?? null;

if (!$clientId) {
    echo json_encode(['success' => false, 'error' => 'not_authenticated', 'message' => 'Debes iniciar sesión para usar favoritos']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
$action = $data['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
$propertyId = (int)($data['property_id'] ?? $_POST['property_id'] ?? $_GET['property_id'] ?? 0);
$sectionType = $data['section_type'] ?? $_POST['section_type'] ?? $_GET['section_type'] ?? 'general';

if (empty($action) && $propertyId > 0) {
    $action = 'toggle';
}

switch ($action) {
    case 'add':
        if ($propertyId > 0) {
            $result = $favoriteModel->addFavorite($clientId, $propertyId, $sectionType);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Agregado a favoritos']);
            } else {
                echo json_encode(['success' => true, 'message' => 'Ya está en favoritos']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de propiedad inválido']);
        }
        break;

    case 'remove':
        if ($propertyId > 0) {
            $favoriteModel->removeFavorite($clientId, $propertyId);
            echo json_encode(['success' => true, 'message' => 'Eliminado de favoritos']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de propiedad inválido']);
        }
        break;

    case 'toggle':
        if ($propertyId > 0) {
            $isFavorite = $favoriteModel->isFavorite($clientId, $propertyId);
            if ($isFavorite) {
                $favoriteModel->removeFavorite($clientId, $propertyId);
                echo json_encode(['success' => true, 'action' => 'removed', 'is_favorite' => false, 'message' => 'Eliminado de favoritos']);
            } else {
                $favoriteModel->addFavorite($clientId, $propertyId, $sectionType);
                echo json_encode(['success' => true, 'action' => 'added', 'is_favorite' => true, 'message' => 'Agregado a favoritos']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de propiedad inválido']);
        }
        break;

    case 'check':
        if ($propertyId > 0) {
            $isFavorite = $favoriteModel->isFavorite($clientId, $propertyId);
            echo json_encode(['success' => true, 'is_favorite' => $isFavorite]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de propiedad inválido']);
        }
        break;

    case 'list':
        $favorites = $favoriteModel->getClientFavorites($clientId);
        echo json_encode(['success' => true, 'favorites' => $favorites]);
        break;

    case 'ids':
        $ids = $favoriteModel->getClientFavoriteIds($clientId);
        echo json_encode(['success' => true, 'favorite_ids' => $ids]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
