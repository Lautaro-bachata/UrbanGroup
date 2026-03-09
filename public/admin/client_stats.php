<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/PortalClientModel.php';
require_once __DIR__ . '/../../includes/FavoriteModel.php';
require_once __DIR__ . '/../../includes/ClientActivityModel.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$clientId) {
    header('Location: portal_clients.php');
    exit;
}

$portalClientModel = new PortalClientModel();
$favoriteModel = new FavoriteModel();
$activityModel = new ClientActivityModel();

$client = $portalClientModel->getById($clientId);

if (!$client) {
    header('Location: portal_clients.php');
    exit;
}

$stats = $activityModel->getClientStats($clientId);
$favorites = $favoriteModel->getClientFavorites($clientId);
$mostViewed = $activityModel->getMostViewedByClient($clientId, 10);
$recentViews = $activityModel->getClientViews($clientId, 20);
$viewsByType = $activityModel->getViewsByPropertyType($clientId);
$viewsBySection = $activityModel->getViewsBySectionType($clientId);
$viewsByOperation = $activityModel->getViewsByOperationType($clientId);
$timeline = $activityModel->getActivityTimeline($clientId, 30);

$pageTitle = 'Estadísticas de Cliente';
$currentPage = 'portal_clients';

$sectionLabels = [
    'general' => 'Propiedades',
    'propiedades' => 'Propiedades',
    'terrenos' => 'Terrenos',
    'activos' => 'Activos',
    'usa' => 'USA'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Urban Group Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">

<header class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4 flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-3">
            <img src="../uploads/logo.png" alt="Urban Group" class="site-logo--small">
            <span class="sr-only">Urban Group</span>
        </a>
       
        <a href="../logout.php" class="px-3 lg:px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Cerrar</a>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
    <div class="mb-6">
        <a href="portal_clients.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a Clientes
        </a>
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Estadísticas de Cliente</h1>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center gap-6">
            <div class="w-20 h-20 rounded-full bg-blue-100 flex items-center justify-center">
                <span class="text-3xl font-bold text-blue-600"><?= strtoupper(substr($client['nombre_completo'], 0, 1)) ?></span>
            </div>
            <div class="flex-1">
                <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($client['nombre_completo']) ?></h2>
                <p class="text-gray-500"><?= htmlspecialchars($client['alias']) ?></p>
                <div class="flex flex-wrap gap-4 mt-3 text-sm text-gray-600">
                    <span><strong>Email:</strong> <?= htmlspecialchars($client['email']) ?></span>
                    <span><strong>Celular:</strong> <?= htmlspecialchars($client['celular']) ?></span>
                    <span><strong>RUT:</strong> <?= htmlspecialchars($client['rut']) ?></span>
                </div>
                <?php if (!empty($client['razon_social'])): ?>
                <div class="mt-2 text-sm text-gray-600">
                    <span><strong>Empresa:</strong> <?= htmlspecialchars($client['razon_social']) ?></span>
                    <?php if (!empty($client['representante_legal'])): ?>
                    <span class="ml-4"><strong>Rep. Legal:</strong> <?= htmlspecialchars($client['representante_legal']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $client['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= $client['status'] === 'active' ? 'Activo' : 'Inactivo' ?>
                </span>
                <p class="text-sm text-gray-500 mt-2">Registrado: <?= date('d/m/Y', strtotime($client['created_at'])) ?></p>
                <?php if ($client['last_login_at']): ?>
                <p class="text-sm text-gray-500">Último acceso: <?= date('d/m/Y H:i', strtotime($client['last_login_at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-lg bg-blue-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?= $stats['total_properties_viewed'] ?? 0 ?></p>
                    <p class="text-sm text-gray-500">Propiedades Vistas</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-lg bg-purple-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?= $stats['total_views'] ?? 0 ?></p>
                    <p class="text-sm text-gray-500">Visitas Totales</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-lg bg-red-100 flex items-center justify-center">
                    <svg class="w-7 h-7 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?= $stats['total_favorites'] ?? 0 ?></p>
                    <p class="text-sm text-gray-500">Favoritos Guardados</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Interés por Tipo de Propiedad</h3>
            <?php if (empty($viewsByType)): ?>
                <p class="text-gray-500 text-center py-8">Sin datos de actividad</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php 
                    $maxViews = max(array_column($viewsByType, 'total_views'));
                    foreach ($viewsByType as $type): 
                        $percentage = $maxViews > 0 ? ($type['total_views'] / $maxViews) * 100 : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($type['property_type']) ?></span>
                            <span class="text-gray-500"><?= $type['total_views'] ?> visitas (<?= $type['properties_count'] ?> prop.)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Interés por Sección</h3>
            <?php if (empty($viewsBySection)): ?>
                <p class="text-gray-500 text-center py-8">Sin datos de actividad</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php 
                    $maxViews = max(array_column($viewsBySection, 'total_views'));
                    $colors = ['general' => 'bg-gray-600', 'propiedades' => 'bg-gray-600', 'terrenos' => 'bg-green-600', 'activos' => 'bg-purple-600', 'usa' => 'bg-blue-600'];
                    foreach ($viewsBySection as $section): 
                        $percentage = $maxViews > 0 ? ($section['total_views'] / $maxViews) * 100 : 0;
                        $color = $colors[$section['section_type']] ?? 'bg-gray-600';
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700"><?= $sectionLabels[$section['section_type']] ?? $section['section_type'] ?></span>
                            <span class="text-gray-500"><?= $section['total_views'] ?> visitas (<?= $section['properties_count'] ?> prop.)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="<?= $color ?> h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Preferencia: Venta vs Arriendo</h3>
        <?php if (empty($viewsByOperation)): ?>
            <p class="text-gray-500 text-center py-8">Sin datos de actividad</p>
        <?php else: ?>
            <div class="flex gap-8 justify-center">
                <?php foreach ($viewsByOperation as $op): ?>
                <div class="text-center">
                    <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-2 <?= $op['operation_type'] === 'Venta' ? 'bg-green-100' : 'bg-amber-100' ?>">
                        <span class="text-2xl font-bold <?= $op['operation_type'] === 'Venta' ? 'text-green-600' : 'text-amber-600' ?>"><?= $op['total_views'] ?></span>
                    </div>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($op['operation_type']) ?></p>
                    <p class="text-sm text-gray-500"><?= $op['properties_count'] ?> propiedades</p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Propiedades Más Vistas</h3>
            <?php if (empty($mostViewed)): ?>
                <p class="text-gray-500 text-center py-8">Sin propiedades vistas</p>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($mostViewed as $prop): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($prop['property_title']) ?></p>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($prop['comuna_name']) ?> - <?= formatPrice($prop['price']) ?></p>
                        </div>
                        <div class="text-right ml-4">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                <?= $prop['view_count'] ?> visitas
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Propiedades Favoritas</h3>
            <?php if (empty($favorites)): ?>
                <p class="text-gray-500 text-center py-8">Sin favoritos guardados</p>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($favorites as $fav): ?>
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate"><?= htmlspecialchars($fav['title']) ?></p>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($fav['comuna_name']) ?> - <?= formatPrice($fav['price']) ?></p>
                        </div>
                        <div class="ml-4">
                            <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Actividad Reciente</h3>
        <?php if (empty($timeline)): ?>
            <p class="text-gray-500 text-center py-8">Sin actividad registrada</p>
        <?php else: ?>
            <div class="relative">
                <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                <div class="space-y-4">
                    <?php foreach ($timeline as $activity): ?>
                    <div class="relative pl-10">
                        <div class="absolute left-2 w-5 h-5 rounded-full flex items-center justify-center <?= $activity['activity_type'] === 'favorite' ? 'bg-red-100' : 'bg-blue-100' ?>">
                            <?php if ($activity['activity_type'] === 'favorite'): ?>
                                <svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            <?php else: ?>
                                <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= $activity['activity_type'] === 'favorite' ? 'Guardó en favoritos' : 'Vió propiedad' ?>
                                    </span>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($activity['property_title']) ?></p>
                                </div>
                                <span class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($activity['activity_date'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
