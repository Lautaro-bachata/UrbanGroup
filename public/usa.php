<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PropertyModel.php';
require_once __DIR__ . '/../includes/PhotoModel.php';
require_once __DIR__ . '/../includes/FavoriteModel.php';

if (!isset($_SESSION['portal_client'])) {
    header('Location: portal_login.php?section=usa');
    exit;
}

$propertyModel = new PropertyModel();
$photoModel = new PhotoModel();
$favoriteModel = new FavoriteModel();

$clientLoggedIn = isset($_SESSION['portal_client_id']);
$favoriteIds = $clientLoggedIn ? $favoriteModel->getClientFavoriteIds($_SESSION['portal_client_id']) : [];

$usStates = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
    'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
    'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
    'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
    'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
    'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
    'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
    'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
    'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia'
];

$filters = [
    'operation_type' => $_GET['operation_type'] ?? '',
    'property_type' => $_GET['property_type'] ?? '',
    'min_price' => $_GET['min_price'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
    'bedrooms' => $_GET['bedrooms'] ?? '',
    'bathrooms' => $_GET['bathrooms'] ?? '',
    'search' => $_GET['search'] ?? '',
    'section_type' => 'usa'
];

$advancedFilters = [
    'state' => $_GET['state'] ?? '',
    'city' => $_GET['city'] ?? '',
    'pool' => $_GET['pool'] ?? '',
    'waterfront' => $_GET['waterfront'] ?? '',
    'gated_community' => $_GET['gated_community'] ?? '',
    'is_project' => $_GET['is_project'] ?? '',
    'min_year_built' => $_GET['min_year_built'] ?? '',
    'max_year_built' => $_GET['max_year_built'] ?? '',
    'min_sqft' => $_GET['min_sqft'] ?? '',
    'max_sqft' => $_GET['max_sqft'] ?? '',
    'min_lot' => $_GET['min_lot'] ?? '',
    'max_lot' => $_GET['max_lot'] ?? ''
];

// Combine basic and advanced filters
$filters = array_merge($filters, $advancedFilters);


$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$properties = $propertyModel->getAll($filters, $limit, $offset);
$totalProperties = $propertyModel->count($filters);
$totalPages = ceil($totalProperties / $limit);

$pageTitle = 'Propiedades USA';
$currentPage = 'usa';
include __DIR__ . '/../templates/header.php';

function formatUSD($amount) {
    return '$' . number_format($amount, 0, '.', ',') . ' USD';
}
?>

<div class="bg-gradient-to-r from-red-600 to-red-800 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Propiedades USA</h1>
                <p class="text-red-100">Bienvenido, <?= htmlspecialchars($_SESSION['portal_client']['nombre_completo']) ?> - <?= $totalProperties ?> propiedad(es) en USA</p>
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
                <select name="operation_type" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500" onchange="this.form.submit()">
                    <option value="">Tipo de Operación</option>
                    <option value="Venta" <?= $filters['operation_type']==='Venta' ? 'selected':'' ?>>Venta (Sale)</option>
                    <option value="Arriendo" <?= $filters['operation_type']==='Arriendo' ? 'selected':'' ?>>Rent</option>
                </select>

                <select name="property_type" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500" onchange="this.form.submit()">
                    <option value="">Tipo de Propiedad</option>
                    <option value="Single Family" <?= $filters['property_type'] === 'Single Family' ? 'selected' : '' ?>>Single Family</option>
                    <option value="Condo" <?= $filters['property_type'] === 'Condo' ? 'selected' : '' ?>>Condo</option>
                    <option value="Townhouse" <?= $filters['property_type'] === 'Townhouse' ? 'selected' : '' ?>>Townhouse</option>
                    <option value="Multi-Family" <?= $filters['property_type'] === 'Multi-Family' ? 'selected' : '' ?>>Multi-Family</option>
                    <option value="Land" <?= $filters['property_type'] === 'Land' ? 'selected' : '' ?>>Land</option>
                    <option value="Commercial" <?= $filters['property_type'] === 'Commercial' ? 'selected' : '' ?>>Commercial</option>
                </select>

                <select name="bedrooms" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500" onchange="this.form.submit()">
                    <option value="">Bedrooms</option>
                    <option value="1" <?= $filters['bedrooms']==='1' ? 'selected':'' ?>>1+</option>
                    <option value="2" <?= $filters['bedrooms']==='2' ? 'selected':'' ?>>2+</option>
                    <option value="3" <?= $filters['bedrooms']==='3' ? 'selected':'' ?>>3+</option>
                    <option value="4" <?= $filters['bedrooms']==='4' ? 'selected':'' ?>>4+</option>
                    <option value="5" <?= $filters['bedrooms']==='5' ? 'selected':'' ?>>5+</option>
                </select>

                <select name="bathrooms" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500" onchange="this.form.submit()">
                    <option value="">Bathrooms</option>
                    <option value="1" <?= $filters['bathrooms']==='1' ? 'selected':'' ?>>1+</option>
                    <option value="2" <?= $filters['bathrooms']==='2' ? 'selected':'' ?>>2+</option>
                    <option value="3" <?= $filters['bathrooms']==='3' ? 'selected':'' ?>>3+</option>
                    <option value="4" <?= $filters['bathrooms']==='4' ? 'selected':'' ?>>4+</option>
                </select>

                <input type="text" name="search" placeholder="Buscar..." 
                    class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500"
                    value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-gray-700">Filtros de Precio (USD)</h4>
                    <?php 
                    $hasActiveFilters = !empty($filters['min_price']) || !empty($filters['max_price']) || !empty($advancedFilters['state']) || !empty($advancedFilters['pool']) || !empty($advancedFilters['waterfront']) || !empty($advancedFilters['is_project']);
                    ?>
                    <?php if ($hasActiveFilters): ?>
                        <a href="usa.php" class="text-xs text-red-600 hover:underline">Clear filters</a>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Min Price (USD)</label>
                        <input type="number" name="min_price" placeholder="$ Min" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($filters['min_price']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Max Price (USD)</label>
                        <input type="number" name="max_price" placeholder="$ Max" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($filters['max_price']) ?>">
                    </div>
                </div>
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-semibold text-red-800 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                    </svg>
                    Advanced USA Filters
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 mb-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">State</label>
                        <select name="state" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500">
                            <option value="">All States</option>
                            <?php foreach ($usStates as $code => $name): ?>
                                <option value="<?= $code ?>" <?= $advancedFilters['state'] === $code ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">City</label>
                        <input type="text" name="city" placeholder="Any city" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($advancedFilters['city']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Pool</label>
                        <select name="pool" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500">
                            <option value="">Any</option>
                            <option value="1" <?= $advancedFilters['pool'] === '1' ? 'selected' : '' ?>>With Pool</option>
                            <option value="0" <?= $advancedFilters['pool'] === '0' ? 'selected' : '' ?>>Without Pool</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Waterfront</label>
                        <select name="waterfront" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500">
                            <option value="">Any</option>
                            <option value="1" <?= $advancedFilters['waterfront'] === '1' ? 'selected' : '' ?>>Waterfront</option>
                            <option value="0" <?= $advancedFilters['waterfront'] === '0' ? 'selected' : '' ?>>Not Waterfront</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Gated Community</label>
                        <select name="gated_community" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500">
                            <option value="">Any</option>
                            <option value="1" <?= $advancedFilters['gated_community'] === '1' ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= $advancedFilters['gated_community'] === '0' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Property Status</label>
                        <select name="is_project" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500">
                            <option value="">All</option>
                            <option value="1" <?= $advancedFilters['is_project'] === '1' ? 'selected' : '' ?>>New Projects</option>
                            <option value="0" <?= $advancedFilters['is_project'] === '0' ? 'selected' : '' ?>>Existing Properties</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Year Built (Min)</label>
                        <input type="number" name="min_year_built" placeholder="From" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($advancedFilters['min_year_built']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Year Built (Max)</label>
                        <input type="number" name="max_year_built" placeholder="To" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($advancedFilters['max_year_built']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Sqft (Min)</label>
                        <input type="number" name="min_sqft" placeholder="Min sqft" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($advancedFilters['min_sqft']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Sqft (Max)</label>
                        <input type="number" name="max_sqft" placeholder="Max sqft" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($advancedFilters['max_sqft']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Lot Size (Min sqft)</label>
                        <input type="number" name="min_lot" placeholder="Min lot" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($advancedFilters['min_lot']) ?>">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Lot Size (Max sqft)</label>
                        <input type="number" name="max_lot" placeholder="Max lot" 
                            class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                            value="<?= htmlspecialchars($advancedFilters['max_lot']) ?>">
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                    Apply Filters
                </button>
                <a href="usa.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Clear All
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900">No properties found in USA</h3>
                <p class="text-gray-500 mt-2">Try adjusting your search filters</p>
                <a href="usa.php" class="mt-4 inline-block px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Clear filters
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
                        $propertyUrl = SITE_URL . '/propiedad.php?id=' . $p['id'] . '&section_type=usa';
                        $displayPrice = formatUSD($p['price'] ?? 0);
                    ?>

                    <div class="group relative">
                        <div class="absolute top-3 right-3 z-10 flex gap-2">
                            <button onclick="toggleFavorite(<?= $p['id'] ?>, this)" class="w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-lg hover:bg-red-50 transition">
                                <svg class="w-5 h-5 <?= $isFavorite ? 'text-red-500' : 'text-gray-400' ?>" fill="<?= $isFavorite ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </button>
                            <button onclick="shareProperty('<?= htmlspecialchars($p['title'] ?? '') ?>', '<?= $propertyUrl ?>')" class="w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-lg hover:bg-blue-50 transition">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                            </button>
                        </div>
                        
                        <a href="propiedad.php?id=<?= $p['id'] ?>&section_type=usa">
                            <div class="bg-white border rounded-xl overflow-hidden hover:shadow-lg transition">

                                <div class="relative aspect-[4/3] overflow-hidden">
                                    <img src="<?= $photo ?>" class="w-full h-full object-cover group-hover:scale-105 duration-300">
                                    
                                    <?php if (($p['status'] ?? '') === 'Vendido'): ?>
                                        <div class="absolute top-3 left-3 z-20 bg-red-600 text-white px-3 py-1 rounded-lg font-bold text-sm shadow-lg transform -rotate-6">
                                            SOLD
                                        </div>
                                    <?php elseif (($p['status'] ?? '') === 'Arrendado'): ?>
                                        <div class="absolute top-3 left-3 z-20 bg-orange-500 text-white px-3 py-1 rounded-lg font-bold text-sm shadow-lg transform -rotate-6">
                                            RENTED
                                        </div>
                                    <?php else: ?>
                                        <span class="absolute top-3 left-3 text-xs px-3 py-1 bg-red-600 text-white rounded-lg">
                                            <?= $displayPrice ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="absolute bottom-3 left-3 text-xs px-3 py-1 bg-blue-900 text-white rounded-lg">
                                        USA
                                    </span>
                                </div>

                                <div class="p-4">
                                    <h3 class="font-semibold text-gray-900 group-hover:text-red-600">
                                        <?= htmlspecialchars(truncateText($p['title'] ?? '', 40)) ?>
                                    </h3>
                                    <p class="text-xs text-gray-600"><?= htmlspecialchars($p['property_type'] ?? '') ?></p>

                                    <div class="flex flex-wrap gap-3 text-xs text-gray-600 mt-2">
                                        <?php if (($p['bedrooms'] ?? 0) > 0): ?><span>🛏 <?= $p['bedrooms'] ?> Beds</span><?php endif; ?>
                                        <?php if (($p['bathrooms'] ?? 0) > 0): ?><span>🚿 <?= $p['bathrooms'] ?> Baths</span><?php endif; ?>
                                        <?php if (($p['built_area'] ?? 0) > 0): ?><span>📐 <?= number_format($p['built_area'], 0, '', ',') ?> sqft</span><?php endif; ?>
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
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="px-4 py-2 bg-red-600 text-white rounded-lg"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50 bg-white">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="px-4 py-2 border rounded-lg hover:bg-gray-50 bg-white">
                            Next
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
            <h3 class="text-lg font-bold text-gray-900">Share Property</h3>
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
                Copy
            </button>
        </div>
        <input type="hidden" id="shareUrl" value="">
    </div>
</div>

<script>
const clientLoggedIn = <?= $clientLoggedIn ? 'true' : 'false' ?>;

function toggleFavorite(propertyId, button) {
    if (!clientLoggedIn) {
        if (confirm('You need to log in to save favorites. Would you like to log in now?')) {
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
    document.getElementById('shareWhatsApp').href = 'https://wa.me/?text=' + encodeURIComponent('Check out this property: ' + title + ' - ' + url);
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
        alert('Link copied to clipboard');
    });
}

document.getElementById('shareModal').addEventListener('click', function(e) {
    if (e.target === this) closeShareModal();
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
