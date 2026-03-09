<?php
function formatPrice($price, $currency = 'CLP') {
    if ($currency === 'CLP') {
        return '$' . number_format($price, 0, ',', '.');
    } elseif ($currency === 'UF') {
        return number_format($price, 2, ',', '.') . ' UF';
    }
    return '$' . number_format($price, 0, ',', '.');
}

function formatArea($area) {
    return number_format($area, 0, ',', '.') . ' m²';
}

function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function getFirstImage($images) {
    if (empty($images)) {
        return 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=800';
    }
    $imageArray = json_decode($images, true);
    return $imageArray[0] ?? 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=800';
}

function getImages($images) {
    if (empty($images)) {
        return [];
    }
    return json_decode($images, true) ?? [];
}

function getFeatures($features) {
    if (empty($features)) {
        return [];
    }
    return json_decode($features, true) ?? [];
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isPartner() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'partner' || $_SESSION['role'] === 'admin');
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /');
        exit;
    }
}

function requirePartner() {
    requireLogin();
    if (!isPartner()) {
        header('Location: /');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'name' => $_SESSION['name'],
        'role' => $_SESSION['role'],
        'company_name' => $_SESSION['company_name'] ?? ''
    ];
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getPropertyTypeBadgeColor($type) {
    $colors = [
        'Casa' => 'bg-blue-100 text-blue-800',
        'Departamento' => 'bg-purple-100 text-purple-800',
        'Oficina' => 'bg-green-100 text-green-800',
        'Local Comercial' => 'bg-orange-100 text-orange-800',
        'Bodega' => 'bg-gray-100 text-gray-800',
        'Terreno' => 'bg-yellow-100 text-yellow-800',
        'Galpón' => 'bg-red-100 text-red-800',
        'Estacionamiento' => 'bg-cyan-100 text-cyan-800'
    ];
    return $colors[$type] ?? 'bg-gray-100 text-gray-800';
}

function getOperationBadgeColor($operation) {
    return $operation === 'Venta' ? 'bg-emerald-500' : 'bg-blue-500';
}


function getPropertyPhotoUrl($photoUrl, $fromPublicRoot = false) {
    if (empty($photoUrl)) {
        return 'https://via.placeholder.com/400x300.png?text=No+Image';
    }
    if (filter_var($photoUrl, FILTER_VALIDATE_URL)) {
        return $photoUrl;
    }
    return BASE_URL . ltrim(str_replace(['../', '..\\'], '', $photoUrl), '/');
}


function getPartnerPhotoUrl($photoUrl) {
    if (empty($photoUrl)) {
        return 'https://via.placeholder.com/150.png?text=No+Photo';
    }
    if (filter_var($photoUrl, FILTER_VALIDATE_URL)) {
        return $photoUrl;
    }
    return BASE_URL . ltrim(str_replace(['../', '..\\'], '', $photoUrl), '/');
}
