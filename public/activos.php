<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PropertyModel.php';
require_once __DIR__ . '/../includes/PhotoModel.php';
require_once __DIR__ . '/../includes/LocationModel.php';
require_once __DIR__ . '/../includes/FavoriteModel.php';
require_once __DIR__ . '/../includes/ActivoModel.php';

if (!isset($_SESSION['portal_client'])) {
    header('Location: portal_login.php?section=activos');
    exit;
}

$propertyModel = new PropertyModel();
$photoModel = new PhotoModel();
$locationModel = new LocationModel();
$favoriteModel = new FavoriteModel();
$activoModel = new ActivoModel();

$clientLoggedIn = isset($_SESSION['portal_client_id']);
$favoriteIds = $clientLoggedIn ? $favoriteModel->getClientFavoriteIds($_SESSION['portal_client_id']) : [];

$filters = [
    'operation_type' => $_GET['operation_type'] ?? '',
    'region_id' => $_GET['region_id'] ?? '',
    'comuna_id' => $_GET['comuna_id'] ?? '',
    'comuna_text' => $_GET['comuna_text'] ?? '',
    'ciudad_activo' => $_GET['ciudad_activo'] ?? '',
    'min_superficie_util' => $_GET['min_superficie_util'] ?? '',
    'max_superficie_util' => $_GET['max_superficie_util'] ?? '',
    'min_precio_uf' => $_GET['min_precio_uf'] ?? '',
    'max_precio_uf' => $_GET['max_precio_uf'] ?? '',
    'min_rentabilidad' => $_GET['min_rentabilidad'] ?? '',
    'max_rentabilidad' => $_GET['max_rentabilidad'] ?? '',
    'con_renta' => $_GET['con_renta'] ?? '',
    'search' => $_GET['search'] ?? '',
    'section_type' => 'activos'
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$properties = $propertyModel->getAll($filters, $limit, $offset);
$totalProperties = $propertyModel->count($filters);
$totalPages = ceil($totalProperties / $limit);

$availableComunas = $activoModel->getAvailableComunas();

$pageTitle = 'Activos Inmobiliarios';
$currentPage = 'activos';
include __DIR__ . '/../templates/header.php';
?>

<div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Activos Inmobiliarios</h1>
                <p class="text-purple-100">Bienvenido, <?= htmlspecialchars($_SESSION['portal_client']['nombre_completo']) ?> - <?= $totalProperties ?> activo(s) disponible(s)</p>
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
                    Cerrar Sesion
                </a>
            </div>
        </div>
    </div>
</div>

<section class="bg-white border-b border-gray-200 py-6">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <form action="" method="GET" id="filterForm">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Comuna</label>
                    <select name="comuna_text" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Todas las Comunas</option>
                        <?php foreach ($availableComunas as $comuna): ?>
                            <option value="<?= htmlspecialchars($comuna) ?>" <?= $filters['comuna_text'] === $comuna ? 'selected' : '' ?>>
                                <?= htmlspecialchars($comuna) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ciudad</label>
                    <input type="text" name="ciudad_activo" placeholder="Cualquier ciudad"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                        value="<?= htmlspecialchars($filters['ciudad_activo']) ?>">
                </div>
                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Superficie Util (m2)</label>
                    <div class="flex gap-2">
                        <input type="number" name="min_superficie_util" placeholder="Min" step="0.01"
                            class="w-1/2 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500"
                            value="<?= htmlspecialchars($filters['min_superficie_util']) ?>">
                        <input type="number" name="max_superficie_util" placeholder="Max" step="0.01"
                            class="w-1/2 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500"
                            value="<?= htmlspecialchars($filters['max_superficie_util']) ?>">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Precio (UF)</label>
                    <div class="flex gap-2">
                        <input type="number" name="min_precio_uf" placeholder="Min" step="0.01"
                            class="w-1/2 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500"
                            value="<?= htmlspecialchars($filters['min_precio_uf']) ?>">
                        <input type="number" name="max_precio_uf" placeholder="Max" step="0.01"
                            class="w-1/2 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500"
                            value="<?= htmlspecialchars($filters['max_precio_uf']) ?>">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Rentabilidad (%)</label>
                    <div class="flex gap-2">
                        <input type="number" name="min_rentabilidad" placeholder="Min" step="0.01"
                            class="w-1/2 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500"
                            value="<?= htmlspecialchars($filters['min_rentabilidad']) ?>">
                        <input type="number" name="max_rentabilidad" placeholder="Max" step="0.01"
                            class="w-1/2 px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500"
                            value="<?= htmlspecialchars($filters['max_rentabilidad']) ?>">
                    </div>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-4 mb-4">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="con_renta" value="1" <?= !empty($filters['con_renta']) ? 'checked' : '' ?> class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-sm font-medium">Solo con Renta</span>
                </label>
                
                <input type="text" name="search" placeholder="Buscar por nombre..." 
                    class="flex-1 min-w-[200px] px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500"
                    value="<?= htmlspecialchars($filters['search']) ?>">
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                    Aplicar Filtros
                </button>
                <a href="activos.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Limpiar Todo
                </a>
            </div>
        </form>
    </div>
</section>

<section class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">

        <?php if (empty($properties)): ?>
            <div class="text-center py-12 bg-white rounded-xl shadow">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900">No se encontraron activos</h3>
                <p class="text-gray-500 mt-2">Intente ajustar los filtros de busqueda</p>
                <a href="activos.php" class="mt-4 inline-block px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
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
                        $propertyUrl = SITE_URL . '/propiedad.php?id=' . $p['id'] . '&section_type=activos';
                        
                        $activoDetails = $activoModel->getDetailsByPropertyId($p['id']);
                        $precioUf = $activoDetails['precio_uf'] ?? $p['price'];
                        $superficieUtil = $activoDetails['superficie_util'] ?? 0;
                        $conRenta = !empty($activoDetails['con_renta']);
                        $rentabilidad = $activoDetails['rentabilidad_anual'] ?? null;
                        $isSold = ($p['status'] ?? '') === 'Vendido';
                        $isRented = ($p['status'] ?? '') === 'Arrendado';
                    ?>

                    <div class="group relative">
                        <?php if ($isSold): ?>
                        <div class="absolute top-0 left-0 right-0 z-20 bg-red-600 text-white text-center py-1 text-xs font-bold rounded-t-xl">
                            VENDIDO
                        </div>
                        <?php elseif ($isRented): ?>
                        <div class="absolute top-0 left-0 right-0 z-20 bg-orange-500 text-white text-center py-1 text-xs font-bold rounded-t-xl">
                            ARRENDADO
                        </div>
                        <?php endif; ?>
                        
                        <div class="absolute top-3 right-3 z-10 flex gap-2 <?= ($isSold || $isRented) ? 'mt-6' : '' ?>">
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
                        
                        <a href="propiedad.php?id=<?= $p['id'] ?>&section_type=activos">
                            <div class="bg-white border rounded-xl overflow-hidden hover:shadow-lg transition <?= ($isSold || $isRented) ? 'opacity-75' : '' ?>">

                                <div class="relative aspect-[4/3] overflow-hidden">
                                    <img src="<?= $photo ?>" class="w-full h-full object-cover group-hover:scale-105 duration-300">

                                    <span class="absolute top-3 left-3 text-xs px-3 py-1 bg-purple-600 text-white rounded-lg font-bold">
                                        <?= number_format($precioUf, 0, ',', '.') ?> UF
                                    </span>
                                    
                                    <?php if ($conRenta && $rentabilidad): ?>
                                    <span class="absolute bottom-3 left-3 text-xs px-3 py-1 bg-green-600 text-white rounded-lg font-bold">
                                        <?= number_format($rentabilidad, 1, ',', '.') ?>% Rent. Anual
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <div class="p-4">
                                    <h3 class="font-semibold text-gray-900 group-hover:text-purple-600">
                                        <?= htmlspecialchars(truncateText($p['title'], 40)) ?>
                                    </h3>
                                    <p class="text-xs text-gray-600"><?= htmlspecialchars($activoDetails['comuna_text'] ?? '') ?>, <?= htmlspecialchars($activoDetails['ciudad'] ?? '') ?></p>

                                    <div class="flex flex-wrap gap-3 text-xs text-gray-600 mt-2">
                                        <?php if ($superficieUtil > 0): ?>
                                        <span>📐 <?= number_format($superficieUtil, 2, ',', '.') ?> m2 util</span>
                                        <?php endif; ?>
                                        <?php if (!empty($activoDetails['superficie_terreno'])): ?>
                                        <span>🏞 <?= number_format($activoDetails['superficie_terreno'], 0, ',', '.') ?> m2</span>
                                        <?php endif; ?>
                                        <?php if ($conRenta): ?>
                                        <span class="text-green-600 font-semibold">CON RENTA</span>
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
                            <span class="px-4 py-2 bg-purple-600 text-white rounded-lg"><?= $i ?></span>
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

<div id="shareModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl p-6 max-w-md w-full">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900">Compartir Propiedad</h3>
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
const clientLoggedIn = <?= $clientLoggedIn ? 'true' : 'false' ?>;

function toggleFavorite(propertyId, button) {
    if (!clientLoggedIn) {
        if (confirm('Debes iniciar sesion para guardar favoritos. Deseas iniciar sesion ahora?')) {
            window.location.href = 'cliente/login.php?redirect=' + encodeURIComponent(window.location.href);
        }
        return;
    }
    
    fetch('api/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle&property_id=' + propertyId + '&section_type=activos'
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
        }
    });
}

function shareProperty(title, url) {
    document.getElementById('shareUrl').value = url;
    document.getElementById('shareWhatsApp').href = 'https://wa.me/?text=' + encodeURIComponent(title + ': ' + url);
    document.getElementById('shareFacebook').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
    document.getElementById('shareInstagram').href = 'https://www.instagram.com/';
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
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
