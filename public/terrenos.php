<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PropertyModel.php';
require_once __DIR__ . '/../includes/PhotoModel.php';
require_once __DIR__ . '/../includes/LocationModel.php';
require_once __DIR__ . '/../includes/FavoriteModel.php';
require_once __DIR__ . '/../includes/TerrenoModel.php';

if (!isset($_SESSION['portal_client'])) {
    header('Location: portal_login.php?section=terrenos');
    exit;
}

$propertyModel = new PropertyModel();
$photoModel = new PhotoModel();
$locationModel = new LocationModel();
$favoriteModel = new FavoriteModel();
$terrenoModel = new TerrenoModel();

$clientLoggedIn = isset($_SESSION['portal_client_id']);
$favoriteIds = $clientLoggedIn ? $favoriteModel->getClientFavoriteIds($_SESSION['portal_client_id']) : [];

$filters = [
    'operation_type' => $_GET['operation_type'] ?? '',
    'property_type' => '',
    'region_id' => $_GET['region_id'] ?? '',
    'comuna_id' => $_GET['comuna_id'] ?? '',
    'min_price' => $_GET['min_price'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
    'min_area' => $_GET['min_area'] ?? '',
    'max_area' => $_GET['max_area'] ?? '',
    'search' => $_GET['search'] ?? '',
    'section_type' => 'terrenos'
];

$advancedFilters = [
    'zona_prc' => $_GET['zona_prc'] ?? '',
    'usos_suelo' => $_GET['usos_suelo'] ?? '',
    'anteproyecto' => $_GET['anteproyecto'] ?? '',
    'sistema_agrupamiento' => $_GET['sistema_agrupamiento'] ?? '',
    'estado_terreno' => $_GET['estado_terreno'] ?? '',
    'ciudad_terreno' => $_GET['ciudad_terreno'] ?? '',
    'min_densidad' => $_GET['min_densidad'] ?? '',
    'max_densidad' => $_GET['max_densidad'] ?? '',
    'min_superficie_util' => $_GET['min_superficie_util'] ?? '',
    'max_superficie_util' => $_GET['max_superficie_util'] ?? '',
    'min_precio_uf' => $_GET['min_precio_uf'] ?? '',
    'max_precio_uf' => $_GET['max_precio_uf'] ?? ''
];

// Combine basic and advanced filters
$filters = array_merge($filters, $advancedFilters);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$properties = $propertyModel->getAll($filters, $limit, $offset);
$totalProperties = $propertyModel->count($filters);
$totalPages = ceil($totalProperties / $limit);

$regions = $locationModel->getRegions();
$comunas = $locationModel->getComunas($filters['region_id'] ?: null);

$zonasPRC = $terrenoModel->getDistinctZonasPRC();
$usosSuelo = $terrenoModel->getDistinctUsosSuelo();

$pageTitle = 'Terrenos Inmobiliarios';
$currentPage = 'terrenos';
include __DIR__ . '/../templates/header.php';
?>

<div class="bg-gradient-to-r from-green-600 to-green-800 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Terrenos Inmobiliarios</h1>
                <p class="text-green-100">Bienvenido, <?= htmlspecialchars($_SESSION['portal_client']['nombre_completo']) ?> - <?= $totalProperties ?> terreno(s) disponible(s)</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($clientLoggedIn): ?>
                    <a href="cliente/favoritos.php" class="flex items-center gap-2 px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg transition">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        Mis Favoritos
                    </a>
                <?php endif; ?>
                <a href="portal_logout.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</div>

<section class="bg-white border-b border-gray-200 py-6">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <form action="" method="GET" id="filterForm">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
                <select name="operation_type" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" onchange="this.form.submit()">
                    <option value="">Tipo de Operación</option>
                    <option value="Venta" <?= $filters['operation_type']==='Venta' ? 'selected':'' ?>>Venta</option>
                    <option value="Arriendo" <?= $filters['operation_type']==='Arriendo' ? 'selected':'' ?>>Arriendo</option>
                </select>

                <select name="region_id" id="regionSelect" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" onchange="loadComunas(this.value); this.form.submit()">
                    <option value="">Región</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?= $region['id'] ?>" <?= $filters['region_id']==$region['id'] ? 'selected':'' ?>>
                            <?= htmlspecialchars($region['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="comuna_id" id="comunaSelect" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" onchange="this.form.submit()">
                    <option value="">Comuna</option>
                    <?php foreach ($comunas as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filters['comuna_id']==$c['id'] ? 'selected':'' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="search" placeholder="Buscar por título, dirección..." 
                    class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"
                    value="<?= htmlspecialchars($filters['search']) ?>">

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                        Buscar
                    </button>
                    <a href="terrenos.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Limpiar
                    </a>
                </div>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Filtros de Precio y Superficie</h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Precio Mín</label>
                        <input type="number" name="min_price" placeholder="$ Mínimo" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($filters['min_price']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Precio Máx</label>
                        <input type="number" name="max_price" placeholder="$ Máximo" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($filters['max_price']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Superficie Mín (m²)</label>
                        <input type="number" name="min_area" placeholder="m² mín" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($filters['min_area']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Superficie Máx (m²)</label>
                        <input type="number" name="max_area" placeholder="m² máx" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($filters['max_area']) ?>">
                    </div>
                </div>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-green-800 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    Filtros Avanzados de Terrenos
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Zona PRC</label>
                        <select name="zona_prc" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                            <option value="">Todas</option>
                            <?php foreach ($zonasPRC as $zona): ?>
                                <option value="<?= htmlspecialchars($zona) ?>" <?= $advancedFilters['zona_prc'] === $zona ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($zona) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Usos de Suelo</label>
                        <select name="usos_suelo" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                            <option value="">Todos</option>
                            <?php foreach ($usosSuelo as $uso): ?>
                                <option value="<?= htmlspecialchars($uso) ?>" <?= $advancedFilters['usos_suelo'] === $uso ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($uso) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Anteproyecto</label>
                        <select name="anteproyecto" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                            <option value="">Todos</option>
                            <option value="con" <?= $advancedFilters['anteproyecto'] === 'con' ? 'selected' : '' ?>>Con Anteproyecto</option>
                            <option value="sin" <?= $advancedFilters['anteproyecto'] === 'sin' ? 'selected' : '' ?>>Sin Anteproyecto</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Sistema Agrupamiento</label>
                        <select name="sistema_agrupamiento" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                            <option value="">Todos</option>
                            <option value="Aislado" <?= $advancedFilters['sistema_agrupamiento'] === 'Aislado' ? 'selected' : '' ?>>Aislado</option>
                            <option value="Pareado" <?= $advancedFilters['sistema_agrupamiento'] === 'Pareado' ? 'selected' : '' ?>>Pareado</option>
                            <option value="Continuo" <?= $advancedFilters['sistema_agrupamiento'] === 'Continuo' ? 'selected' : '' ?>>Continuo</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Estado</label>
                        <select name="estado_terreno" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                            <option value="">Todos</option>
                            <option value="Disponible" <?= $advancedFilters['estado_terreno'] === 'Disponible' ? 'selected' : '' ?>>Disponible</option>
                            <option value="Reservado" <?= $advancedFilters['estado_terreno'] === 'Reservado' ? 'selected' : '' ?>>Reservado</option>
                            <option value="Vendido" <?= $advancedFilters['estado_terreno'] === 'Vendido' ? 'selected' : '' ?>>Vendido</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Ciudad</label>
                        <input type="text" name="ciudad_terreno" placeholder="Cualquier ciudad"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($advancedFilters['ciudad_terreno']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Densidad Mín (hab/ha)</label>
                        <input type="number" name="min_densidad" placeholder="Mín" step="0.1"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($advancedFilters['min_densidad']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Densidad Máx (hab/ha)</label>
                        <input type="number" name="max_densidad" placeholder="Máx" step="0.1"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($advancedFilters['max_densidad']) ?>">
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Sup. Útil Mín (m²)</label>
                        <input type="number" name="min_superficie_util" placeholder="m² mín" step="0.01"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($advancedFilters['min_superficie_util']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Sup. Útil Máx (m²)</label>
                        <input type="number" name="max_superficie_util" placeholder="m² máx" step="0.01"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($advancedFilters['max_superficie_util']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Precio UF/m² Mín</label>
                        <input type="number" name="min_precio_uf" placeholder="UF mín" step="0.01"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($advancedFilters['min_precio_uf']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Precio UF/m² Máx</label>
                        <input type="number" name="max_precio_uf" placeholder="UF máx" step="0.01"
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                            value="<?= htmlspecialchars($advancedFilters['max_precio_uf']) ?>">
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="terrenos.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
                        Limpiar Filtros
                    </a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Aplicar Filtros
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">

        <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
            <div class="flex gap-3">
                <button onclick="toggleSelectAll()" id="selectAllBtn"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span>Seleccionar Todos</span>
                </button>
                <button onclick="openOrdenVisitaModal()" id="solicitarBtn"
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Solicitar Información (<span id="selectedCount">0</span>)
                </button>
            </div>
            <p class="text-sm text-gray-500">Selecciona los terrenos sobre los que deseas recibir más información</p>
        </div>

        <?php if (empty($properties)): ?>
            <div class="text-center py-12 bg-white rounded-xl shadow">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900">No se encontraron terrenos</h3>
                <p class="text-gray-500 mt-2">Intente ajustar los filtros de búsqueda</p>
                <a href="terrenos.php" class="mt-4 inline-block px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Limpiar filtros
                </a>
            </div>

        <?php else: ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-12">

                <?php foreach ($properties as $p): ?>
                    <?php
                        $photos = $photoModel->getByPropertyId($p['id']);
                        $photo  = !empty($photos)
                            ? str_replace('../','',$photos[0]['photo_url'])
                            : getFirstImage($p['images']);
                        $isFavorite = in_array($p['id'], $favoriteIds);
                        $propertyUrl = SITE_URL . '/propiedad.php?id=' . $p['id'] . '&section_type=terrenos';
                    ?>

                    <div class="group relative property-card" data-property-id="<?= $p['id'] ?>" data-property-title="<?= htmlspecialchars($p['title']) ?>" data-property-m2="<?= number_format($p['total_area'] ?? 0, 0, ',', '.') ?>" data-property-price="<?= formatPrice($p['price']) ?>" data-property-url="<?= $propertyUrl ?>">
                        <div class="absolute top-3 left-3 z-10">
                            <input type="checkbox" class="property-checkbox w-5 h-5 rounded border-2 border-white shadow-lg cursor-pointer" 
                                   value="<?= $p['id'] ?>" onchange="updateSelectedCount()">
                        </div>
                        <div class="absolute top-3 right-3 z-10 flex gap-2">
                            <button onclick="toggleFavorite(<?= $p['id'] ?>, this)" class="w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-lg hover:bg-red-50 transition">
                                <svg class="w-5 h-5 <?= $isFavorite ? 'text-red-500' : 'text-gray-400' ?>" fill="<?= $isFavorite ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </button>
                            <button onclick="shareProperty('<?= htmlspecialchars($p['title']) ?>', '<?= $propertyUrl ?>')" class="w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-lg hover:bg-blue-50 transition">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                            </button>
                        </div>
                        
                        <a href="propiedad.php?id=<?= $p['id'] ?>&section_type=terrenos">
                            <div class="bg-white border rounded-xl overflow-hidden hover:shadow-lg transition">

                                <div class="relative aspect-[4/3] overflow-hidden">
                                    <img src="<?= $photo ?>" class="w-full h-full object-cover group-hover:scale-105 duration-300">
                                    
                                    <?php if (($p['status'] ?? '') === 'Vendido'): ?>
                                        <div class="absolute top-3 left-3 z-20 bg-red-600 text-white px-3 py-1 rounded-lg font-bold text-sm shadow-lg transform -rotate-6">
                                            VENDIDO
                                        </div>
                                    <?php elseif (($p['status'] ?? '') === 'Arrendado'): ?>
                                        <div class="absolute top-3 left-3 z-20 bg-orange-500 text-white px-3 py-1 rounded-lg font-bold text-sm shadow-lg transform -rotate-6">
                                            ARRENDADO
                                        </div>
                                    <?php else: ?>
                                        <span class="absolute top-3 left-12 text-xs px-3 py-1 bg-green-600 text-white rounded-lg">
                                            <?= formatPrice($p['price']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="absolute bottom-3 left-3 text-xs px-3 py-1 text-white rounded-lg <?= ($p['operation_type'] ?? '') ==='Venta' ? 'bg-green-600' : 'bg-amber-600' ?>">
                                        <?= $p['operation_type'] ?? 'Venta' ?>
                                    </span>
                                </div>

                                <div class="p-4">
                                    <h3 class="font-semibold text-gray-900 group-hover:text-green-600">
                                        <?= htmlspecialchars(truncateText($p['title'], 40)) ?>
                                    </h3>
                                    <p class="text-xs text-gray-600"><?= $p['comuna_name'] ?? '' ?></p>

                                    <div class="flex gap-4 text-xs text-gray-600 mt-2">
                                        <?php if (!empty($p['total_area']) && $p['total_area'] > 0): ?>
                                            <span>📐 <?= number_format($p['total_area'], 0, ',', '.') ?>m²</span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['built_area']) && $p['built_area'] > 0): ?>
                                            <span>🏗 <?= number_format($p['built_area'], 0, ',', '.') ?>m²</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </a>
                    </div>

                <?php endforeach; ?>

            </div>

            <?php if ($totalPages > 1): ?>
                <div class="flex justify-center gap-2">

                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50 bg-white">
                            Anterior
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-4 py-2 bg-green-600 text-white rounded-lg"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50 bg-white">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50 bg-white">
                            Siguiente
                        </a>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>

<div id="ordenVisitaModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Solicitud de Información - Terrenos</h2>
                <button onclick="closeOrdenVisitaModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-gray-800 mb-2">Terrenos Seleccionados:</h4>
                <ul id="selectedPropertiesList" class="space-y-1 max-h-32 overflow-y-auto"></ul>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-700 leading-relaxed max-h-96 overflow-y-auto">
                <h3 class="font-bold text-center mb-4">CONTRATO DE PRESTACIÓN DE SERVICIOS Y ACUERDO DE CONFIDENCIALIDAD Y RESERVA</h3>
                
                <?php
                    // Datos del cliente para el contrato
                    $clientData = $_SESSION['portal_client'] ?? [];
                    $esClienteEspecial = !empty($clientData['razon_social']);
                    
                    $contratoRazonSocial = $esClienteEspecial ? htmlspecialchars($clientData['razon_social']) : htmlspecialchars($clientData['nombre_completo'] ?? '-');
                    $contratoRut = htmlspecialchars($clientData['rut'] ?? '-');
                    $contratoCargo = $esClienteEspecial ? htmlspecialchars($clientData['cargo'] ?? 'Representante') : 'Titular';
                    $contratoNombre = htmlspecialchars($clientData['nombre_completo'] ?? '-');
                    $contratoCedula = htmlspecialchars($clientData['cedula_identidad'] ?? $clientData['rut'] ?? '-');
                    $contratoDomicilio = htmlspecialchars($clientData['domicilio'] ?? 'Santiago, Chile');
                    $fechaHoy = date('d \d\e F \d\e Y');
                ?>
                <p class="mb-3 text-center">
                    <strong>INVERSIONES Y ASESORÍAS URBAN GROUP SPA</strong><br>
                    y<br>
                    <em><?= $contratoRazonSocial ?></em>
                </p>
                
                <p class="mb-3">
                    En Santiago, a <?= $fechaHoy ?>, comparecen por una parte; la empresa <strong><?= $contratoRazonSocial ?></strong> RUT <strong><?= $contratoRut ?></strong> representada debidamente por su <?= $contratoCargo ?>, <strong><?= $contratoNombre ?></strong> C.I.: <strong><?= $contratoCedula ?></strong>, ambos domiciliados en <?= $contratoDomicilio ?>, en adelante también como la "Parte Receptora" y por otra; <strong>Inversiones y Asesorías Urban Group SPA RUT: 76.192.802-3</strong> gestionando acorde a su giro de Corretaje de Propiedades y representada legalmente por Patricio John Videla Lizana, C.I.: 12.252.857-K ambos domiciliados en Av. Nueva Providencia N° 1945 Oficina 502 Providencia, Región Metropolitana, Chile, en adelante también como "Urban Group" o "Parte Reveladora", quienes acuerdan suscribir el presente Contrato de Prestación de Servicios de Corretaje de Propiedades y Acuerdo de Confidencialidad y Reserva.
                </p>
                
                <p class="mb-3">
                    <strong>PRIMERO: Gestión de Corretaje de Propiedades:</strong> Urban Group es una empresa dedicada a la Gestión de Negocios Inmobiliarios y cuenta con autorización vigente de los propietarios para gestionar la búsqueda de compradores de los Terrenos Inmobiliarios. La parte Receptora analizará estos Terrenos Inmobiliarios presentados por Urban Group y evaluará la posibilidad de efectuar la compra parcial o total.
                </p>
                
                <p class="mb-3">
                    Dado lo anterior, La parte receptora se compromete a efectuar toda transacción, respecto a la posible compra y entrega en arrendamiento, con el(los) propietario(s) de esta(s) propiedad(es), sólo por intermedio de Urban Group. Por ello encarga y autoriza a Urban Group para que realice todas las gestiones requeridas y orientadas a efectuar las compraventas, arrendamientos, asociación o cualquier otra intermediación entre La parte Receptora y el(los) propietario(s), actuando Urban Group como único y exclusivo intermediario y corredor de propiedades de todas las operaciones que versen sobre estas propiedades. <strong>QUEDA ABSOLUTAMENTE PROHIBIDO</strong> a la parte Receptora tomar contacto directo con él (los) propietario(s) de los Terrenos Inmobiliarios, sin la coordinación y participación de Urban Group o de quien lo represente.
                </p>
                
                <p class="mb-3">
                    <strong>Las Comisiones:</strong> En caso de efectuarse la compraventa de algunas de las propiedades, La parte Receptora pagará a Urban Group la comisión equivalente al <strong>2,0% más IVA</strong> calculado sobre cada precio de las compraventas. Por ello, los montos equivalentes en pesos de las comisiones, con el valor de la unidad de fomento al día de la firma de escritura, serán tomados en Vales Vista o Depósitos a Plazo a 30 días auto-renovables, a la orden de la promitente compradora y endosados en blanco, los que serán presentados a cobro cuando sea firmada la o las escrituras de compraventa. Una vez firmada las escrituras de compraventa, la parte Receptora autoriza a Urban Group, para hacer efectivos dichos documentos.
                </p>
                
                <p class="mb-3">
                    Este Contrato de Prestación de Servicios de Corretaje de Propiedades es Personal, Corporativo, Intransferible e Irrevocable. La obligación de la parte Receptora de pagar las Comisiones a Urban Group, no se extingue, vence o caduca con el término del plazo del Mandato de Venta firmada por él (los) propietario(s). Por consiguiente, la parte Receptora se compromete indefectiblemente a pagar todas las comisiones involucradas en caso de firmas de escrituras de compraventa y de contratos de arrendamiento, incluso más allá del término del Mandato de Venta.
                </p>
                
                <p class="mb-3">
                    <strong>Terrenos Inmobiliarios en Venta:</strong> Todos los Terrenos Inmobiliarios solicitados en las páginas anteriores de este documento, los que son de interés para evaluación por la parte Receptora. La información de los Terrenos Inmobiliarios solicitados serán enviados vía correo electrónico y/o mediante enlaces para descarga de la información por la parte Receptora. El listado de Terrenos Inmobiliarios seleccionados y toda la información enviada de ellos, en respuesta a esta solicitud, forma parte del presente contrato y acuerdo.
                </p>
                
                <p class="mb-3">
                    <strong>SEGUNDO: INFORMACIÓN CONFIDENCIAL.</strong> Con el objeto de que la Parte Receptora pueda evaluar la potencial Transacción, la Parte Reveladora hará entrega de información confidencial (la "Información Confidencial"). En tal sentido, las Partes vienen libre y voluntariamente en declarar y aceptar lo siguiente:
                </p>
                
                <p class="mb-3">
                    Toda la información que sea entregada o haya sido entregada por la Parte Reveladora a la Parte Receptora y sus sociedades relacionadas, y/o a sus directores, accionistas, gerentes, empleados, trabajadores, contratistas, subcontratistas, administradores, analistas, asesores, financistas, abogados, clientes, agentes y/o representantes (todos conjuntamente como los "Representantes"), ya sea por escrito, oralmente o de cualquier otra forma, como así también toda información es susceptible de ser clasificada como Información Confidencial en forma indefinida.
                </p>
                
                <p class="mb-3">
                    <strong>TERCERO: USO DE LA INFORMACIÓN CONFIDENCIAL.</strong> Por el presente acto, la Parte Receptora se obliga, tanto por si, como por sus Representantes a:
                </p>
                
                <ul class="mb-3 list-disc pl-6">
                    <li>Que mantendrán y conservarán en todo momento la Información Confidencial recibida de la Parte Reveladora como secreta y confidencial, y no la comunicarán ni revelarán directa ni indirectamente (tanto en forma oral, visual o escrita) a ninguna otra persona natural o jurídica, con la única excepción de aquellos miembros de su personal que participen activa y directamente en la Transacción.</li>
                    <li>Que se abstendrán de utilizar la Información Confidencial para cualquier otro propósito (incluyendo, pero sin limitarse a ellos, cualquier propósito competitivo o comercial o para su propio uso o el de terceros), distinto de aquellos relacionados directamente con el presente Acuerdo y específicamente con la Transacción de que el mismo da cuenta.</li>
                    <li>Que informarán a las personas que tengan acceso a la Información Confidencial de la existencia y los términos del presente Acuerdo limitando al mínimo imprescindible el número de personas que tendrán acceso a la Información Confidencial.</li>
                    <li>En caso alguno, la Parte Receptora podrá utilizar los valores o montos que la Parte Reveladora le haya entregado por motivo de la Transacción para negociar con otros eventuales interesados en realizar una propuesta u oferta relativa al Inmueble hasta no contar formalmente con mandato de venta.</li>
                </ul>
                
                <p class="mb-3">
                    <strong>CUARTO: DIVULGACIÓN AUTORIZADA.</strong> Con todo, se deja constancia que la siguiente información no estará sujeta a las obligaciones de confidencialidad referidas en las cláusulas precedentes:
                </p>
                
                <ul class="mb-3 list-disc pl-6">
                    <li>Información que sea pública o llegue a estar disponible para el público, sin que medie incumplimiento o infracción al presente Acuerdo por la Parte Receptora o por sus Representantes.</li>
                    <li>Información que la Parte Receptora estuviere obligada a entregar en un proceso judicial o administrativo, o aquella que deba ser entregada en virtud de un requerimiento legal.</li>
                    <li>Información que sea entregada o puesta a disposición de la Parte Receptora por cualquier tercero que tenga el legítimo derecho de revelarla o entregarla.</li>
                    <li>Cuando la información sea revelada a terceros con la expresa autorización de la parte Reveladora.</li>
                </ul>
                
                <p class="mb-3">
                    <strong>QUINTO: PROPIEDAD DE LA INFORMACIÓN CONFIDENCIAL.</strong> Toda la Información Confidencial es y continuará siendo propiedad de la Parte Reveladora. Nada de lo aquí previsto podrá ser interpretado como que otorga o confiere a la Parte Receptora ningún derecho, licencia, patente, o derecho de propiedad industrial, intelectual u otra, respecto a la Información Confidencial.
                </p>
                
                <p class="mb-3">
                    <strong>SEXTO: VIGENCIA.</strong> El presente instrumento de confidencialidad estará vigente mientras la Parte Receptora tenga acceso a la Información Confidencial de la Parte Reveladora, la obligación de mantener en forma confidencial la información subsistirá indefinidamente.
                </p>
                
                <p class="mb-3">
                    <strong>SÉPTIMO: PARTES INDEPENDIENTES.</strong> Asimismo, ninguna estipulación del presente Acuerdo se interpretará como la creación de una representación, consorcio, joint venture, sociedad u otra relación entre las Partes que no sea la de Partes independientes.
                </p>
                
                <p class="mb-3">
                    <strong>SÉPTIMO: RESPONSABILIDAD:</strong> El incumplimiento de las obligaciones contenidas en el presente acuerdo de confidencialidad y reserva facultará a la Parte Reveladora para reclamar la correspondiente indemnización de daños y perjuicios, no obstante, la ausencia del ejercicio de las acciones derivadas del incumplimiento de este acuerdo de confidencialidad, no podrá entenderse como una renuncia a las mismas, excepto si tal renuncia se notifica por escrito.
                </p>
                
                <p class="mb-3">
                    <strong>OCTAVO:</strong> La obligación de los pagos de comisiones regirán de manera indefinida, sobreviviendo al cumplimiento de plazos de otros contratos firmados con motivo del objeto de este contrato, inclusive en caso de desistimiento de avanzar en las evaluaciones y/o de la compra parcial o total; o en caso de desinterés la parte Receptora de concretar los acuerdos y negociaciones que verse sobre cualquiera de los Terrenos Inmobiliarios.
                </p>
                
                <p class="mb-3">
                    <strong>NOVENO:</strong> Todas las dificultades o divergencias relativas al cumplimiento, resolución, interpretación, validez o nulidad que se susciten entre las partes y que estén relacionadas directa o indirectamente con el presente contrato de prestación de servicios de corretaje de propiedades y con el acuerdo de confidencialidad, la información entregada, todos sus Anexos y sus documentos complementarios o accesorios, así como la determinación y monto de las indemnizaciones a que hubiere lugar, serán sometidas a los tribunales de justicia ordinaria de la ciudad de Santiago.
                </p>
                
                <p class="mb-3">
                    <strong>DÉCIMO: DOMICILIO:</strong> Para todos los efectos legales de este contrato las Partes fijan su domicilio en la ciudad de Santiago.
                </p>
                
                <p class="mb-3">
                    <strong>UNDÉCIMO:</strong> El presente contrato de prestación de servicios de corretaje de propiedades y acuerdo de confidencialidad se firma electrónicamente y se envía a través de los correos electrónicos corporativos de las partes. Todo mail enviado entre las partes queda sujeto a la ley Nº 19.799. En virtud de la Ley N° 19.799, se entiende por documento electrónico toda representación de un hecho, imagen o idea que sea creada, enviada, comunicada o recibida por medios electrónicos y almacenada de un modo idóneo para permitir su uso posterior. Además, la ley dispone expresamente que los actos, acuerdos y contratos suscritos por medio de firma electrónica serán válidos de la misma manera y producirán los mismos efectos que los celebrados por escrito y en soporte de papel.
                </p>
                
                <div class="mt-4 pt-4 border-t border-gray-300">
                    <p class="mb-2"><strong>PERSONERÍAS.-</strong></p>
                    <p class="mb-3 text-xs">
                        La personería de don Patricio John Videla Lizana para representar a Inversiones y Asesorías URBAN GROUP SPA, consta en la escritura pública de fecha 22 de Mayo del 2012 otorgada en la Notaría de Vitacura de don Luis Poza Maldonado.
                    </p>
                    <p class="mb-3 text-xs">
                        El <?= $contratoCargo ?> / <strong><?= $contratoNombre ?></strong> / C.I.: <?= $contratoCedula ?> declara que se encuentra debidamente facultado para representar a <strong><?= $contratoRazonSocial ?></strong> RUT: <?= $contratoRut ?>.
                    </p>
                    <p class="text-xs text-gray-500">
                        Declaran los comparecientes, que los poderes aquí mencionados se encuentran vigentes y que les autorizan a suscribir el presente contrato y acuerdo con plena vinculación para su mandante y poderdante, para todos los efectos legales.
                    </p>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <label class="flex items-start cursor-pointer">
                    <input type="checkbox" id="acceptTerms" class="mt-1 h-5 w-5 text-green-600 border-gray-300 rounded">
                    <span class="ml-3 text-sm text-gray-700">
                        <strong>ACEPTO</strong> los términos y condiciones de esta Orden de Visita y autorizo a 
                        Urban Group SpA a enviarme información detallada de los terrenos seleccionados.
                    </span>
                </label>
            </div>
            
            <div id="submitError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"></div>
            <div id="submitSuccess" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4"></div>
            
            <div class="flex justify-end gap-4">
                <button onclick="closeOrdenVisitaModal()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button onclick="submitOrdenVisita()" id="submitOrderBtn" 
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                    Enviar Solicitud
                </button>
            </div>
        </div>
    </div>
</div>

<div id="shareModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl p-6 max-w-md w-full">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Compartir Terreno</h3>
            <button onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <div class="grid grid-cols-2 gap-3 mb-4">
            <a id="shareWhatsApp" href="#" target="_blank" class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white font-medium py-3 rounded-lg transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                WhatsApp
            </a>
            <a id="shareFacebook" href="#" target="_blank" class="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Facebook
            </a>
            <a id="shareInstagram" href="#" target="_blank" class="flex items-center justify-center gap-2 bg-gradient-to-r from-purple-500 via-pink-500 to-orange-500 hover:from-purple-600 hover:via-pink-600 hover:to-orange-600 text-white font-medium py-3 rounded-lg transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                Instagram
            </a>
            <button onclick="copyShareLink()" class="flex items-center justify-center gap-2 bg-gray-700 hover:bg-gray-800 text-white font-medium py-3 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                Copiar
            </button>
        </div>
        <input type="hidden" id="shareUrl" value="">
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const clientLoggedIn = <?= $clientLoggedIn ? 'true' : 'false' ?>;

function loadComunas(regionId) {
    const comunaSelect = document.getElementById('comunaSelect');
    comunaSelect.innerHTML = '<option value="">Cargando...</option>';
    
    if (regionId) {
        fetch(BASE_URL + 'api/comunas.php?region_id=' + regionId)
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                comunaSelect.innerHTML = '<option value="">Comuna</option>';
                if (Array.isArray(data)) {
                    data.forEach(comuna => {
                        comunaSelect.innerHTML += `<option value="${comuna.id}">${comuna.name}</option>`;
                    });
                }
            })
            .catch(err => {
                console.error('Error loading comunas:', err);
                comunaSelect.innerHTML = '<option value="">Comuna</option>';
            });
    } else {
        comunaSelect.innerHTML = '<option value="">Comuna</option>';
    }
}

function toggleFavorite(propertyId, button) {
    if (!clientLoggedIn) {
        if (confirm('Debes iniciar sesión para guardar favoritos. ¿Deseas iniciar sesión ahora?')) {
            window.location.href = 'cliente/login.php?redirect=' + encodeURIComponent(window.location.href);
        }
        return;
    }
    
    fetch('api/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle&property_id=' + propertyId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const icon = button.querySelector('svg');
            if (data.is_favorite) {
                icon.classList.add('text-red-500');
                icon.classList.remove('text-gray-400');
                icon.setAttribute('fill', 'currentColor');
            } else {
                icon.classList.remove('text-red-500');
                icon.classList.add('text-gray-400');
                icon.setAttribute('fill', 'none');
            }
        } else if (data.error === 'not_authenticated') {
            window.location.href = 'cliente/login.php?redirect=' + encodeURIComponent(window.location.href);
        }
    });
}

function shareProperty(title, url) {
    document.getElementById('shareWhatsApp').href = 'https://wa.me/?text=' + encodeURIComponent('Mira este terreno: ' + title + ' - ' + url);
    document.getElementById('shareFacebook').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
    document.getElementById('shareInstagram').href = 'https://www.instagram.com/?url=' + encodeURIComponent(url);
    document.getElementById('shareUrl').value = url;
    
    document.getElementById('shareModal').classList.remove('hidden');
}

function closeShareModal() {
    document.getElementById('shareModal').classList.add('hidden');
}

function copyShareLink() {
    const url = document.getElementById('shareUrl').value;
    navigator.clipboard.writeText(url).then(() => {
        alert('Enlace copiado al portapapeles');
    });
}

document.getElementById('shareModal').addEventListener('click', function(e) {
    if (e.target === this) closeShareModal();
});

let allSelected = false;

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.property-checkbox');
    allSelected = !allSelected;
    checkboxes.forEach(cb => cb.checked = allSelected);
    updateSelectedCount();
    
    const btn = document.getElementById('selectAllBtn');
    btn.querySelector('span').textContent = allSelected ? 'Deseleccionar Todos' : 'Seleccionar Todos';
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.property-checkbox:checked');
    const count = checked.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('solicitarBtn').disabled = count === 0;
}

function openOrdenVisitaModal() {
    const checked = document.querySelectorAll('.property-checkbox:checked');
    if (checked.length === 0) return;
    
    const list = document.getElementById('selectedPropertiesList');
    list.innerHTML = '';
    
    checked.forEach(cb => {
        const card = cb.closest('.property-card');
        const title = card.dataset.propertyTitle;
        const m2 = card.dataset.propertyM2;
        const price = card.dataset.propertyPrice;
        const url = card.dataset.propertyUrl;
        const li = document.createElement('li');
        li.className = 'text-sm text-gray-700 border-b border-gray-100 pb-2 mb-2';
        li.innerHTML = '<strong>' + title + '</strong><br>' +
            '<span class="text-xs text-gray-500">Superficie: ' + m2 + ' m² | Precio: ' + price + '</span><br>' +
            '<a href="' + url + '" target="_blank" class="text-xs text-blue-600 hover:underline">' + url + '</a>';
        list.appendChild(li);
    });
    
    document.getElementById('ordenVisitaModal').classList.remove('hidden');
}

function closeOrdenVisitaModal() {
    document.getElementById('ordenVisitaModal').classList.add('hidden');
    document.getElementById('submitError').classList.add('hidden');
    document.getElementById('submitSuccess').classList.add('hidden');
}

function submitOrdenVisita() {
    const acceptTerms = document.getElementById('acceptTerms').checked;
    const errorDiv = document.getElementById('submitError');
    const successDiv = document.getElementById('submitSuccess');
    
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');
    
    if (!acceptTerms) {
        errorDiv.textContent = 'Debe aceptar los términos y condiciones para continuar.';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    const checked = document.querySelectorAll('.property-checkbox:checked');
    const propertyIds = Array.from(checked).map(cb => cb.value);
    
    fetch('api/orden_visita.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ property_ids: propertyIds })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            successDiv.textContent = 'Su solicitud ha sido enviada correctamente. Nos pondremos en contacto pronto.';
            successDiv.classList.remove('hidden');
            setTimeout(() => {
                closeOrdenVisitaModal();
                document.querySelectorAll('.property-checkbox').forEach(cb => cb.checked = false);
                updateSelectedCount();
            }, 3000);
        } else {
            errorDiv.textContent = data.error || 'Error al enviar la solicitud. Intente nuevamente.';
            errorDiv.classList.remove('hidden');
        }
    })
    .catch(() => {
        errorDiv.textContent = 'Error de conexión. Intente nuevamente.';
        errorDiv.classList.remove('hidden');
    });
}

document.getElementById('ordenVisitaModal').addEventListener('click', function(e) {
    if (e.target === this) closeOrdenVisitaModal();
});

</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
