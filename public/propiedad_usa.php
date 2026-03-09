<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PropertyModel.php';
require_once __DIR__ . '/../includes/PhotoModel.php';
require_once __DIR__ . '/../includes/USAModel.php';
require_once __DIR__ . '/../includes/FavoriteModel.php';

if (!isset($_SESSION['portal_client'])) {
    header('Location: portal_login.php?section=usa');
    exit;
}

$propertyModel = new PropertyModel();
$photoModel = new PhotoModel();
$usaModel = new USAModel();
$favoriteModel = new FavoriteModel();

$id = (int)($_GET['id'] ?? 0);
$property = $propertyModel->getById($id);

if (!$property || $property['section_type'] !== 'usa') {
    header('Location: usa.php');
    exit;
}

$usaDetails = $usaModel->getUSADetailsByPropertyId($id);
$propertyPhotos = $photoModel->getByPropertyId($id);
$images = !empty($propertyPhotos) ? array_map(function($p) { 
    return getPropertyPhotoUrl($p['photo_url'], true);
}, $propertyPhotos) : [];

if (empty($images)) {
    $images = ['https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=800'];
}

$features = getFeatures($property['features'] ?? '[]');

$clientLoggedIn = isset($_SESSION['portal_client_id']);
$isFavorite = $clientLoggedIn ? $favoriteModel->isFavorite($_SESSION['portal_client_id'], $id) : false;

$usaPrice = $usaDetails['price_usd'] ?? $property['price'] ?? 0;
$similarProperties = $propertyModel->getSimilar($id, 'usa', $property['property_type'], $usaPrice, 4);

$propertyUrl = SITE_URL . '/propiedad_usa.php?id=' . $id;
$whatsappNumber = defined('WHATSAPP_NUMBER') ? preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER) : '56966785614';

$pageTitle = $property['title'] . ' | USA';
$currentPage = 'usa';

include __DIR__ . '/../templates/header.php';
?>

<div class="bg-gray-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm">
                <a href="/" class="text-blue-600 hover:text-blue-700">Inicio</a>
                <span class="text-gray-400">/</span>
                <a href="/usa.php" class="text-blue-600 hover:text-blue-700">Propiedades USA</a>
                <span class="text-gray-400">/</span>
                <span class="text-gray-600"><?= htmlspecialchars(truncateText($property['title'], 50)) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="toggleFavorite(<?= $id ?>)" id="favBtn" class="p-2 rounded-full border <?= $isFavorite ? 'bg-red-50 border-red-200' : 'bg-white border-gray-200' ?> hover:bg-red-50 transition">
                    <svg class="w-5 h-5 <?= $isFavorite ? 'text-red-500' : 'text-gray-400' ?>" id="favIcon" fill="<?= $isFavorite ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<section class="py-8 lg:py-12 bg-white">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="mb-8">
                    <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-gray-100 mb-4">
                        <img src="<?= $images[0] ?>" alt="<?= htmlspecialchars($property['title']) ?>" id="mainImage" class="w-full h-full object-cover">
                        <div class="absolute top-4 left-4 flex gap-2">
                            <span class="bg-red-600 text-white text-sm font-bold px-3 py-1 rounded-lg flex items-center gap-1">
                                🇺🇸 USA
                            </span>
                        </div>
                    </div>
                    
                    <?php if (count($images) > 1): ?>
                        <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                            <?php foreach ($images as $index => $image): ?>
                                <div class="aspect-square rounded-lg overflow-hidden cursor-pointer border-2 <?= $index === 0 ? 'border-red-600' : 'border-gray-200' ?> hover:border-red-600 transition" onclick="changeImage('<?= htmlspecialchars($image) ?>', this)">
                                    <img src="<?= $image ?>" alt="Imagen <?= $index + 1 ?>" class="w-full h-full object-cover">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Descripción</h2>
                    <p class="text-gray-600 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($property['description']) ?></p>
                </div>

                <div class="mb-8 bg-blue-50 rounded-xl p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Características (en sqft)</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php 
                        $surfaceSqft = $usaDetails['surface_sqft'] ?? ($property['built_area'] ? $property['built_area'] * 10.764 : 0);
                        $lotSqft = $usaDetails['lot_size_sqft'] ?? ($property['total_area'] ? $property['total_area'] * 10.764 : 0);
                        ?>
                        
                        <?php if ($surfaceSqft > 0): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">📐</div>
                                <p class="text-xl font-bold text-gray-900"><?= number_format($surfaceSqft, 0) ?></p>
                                <p class="text-xs text-gray-600">sqft Interior</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($lotSqft > 0): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">🏞️</div>
                                <p class="text-xl font-bold text-gray-900"><?= number_format($lotSqft, 0) ?></p>
                                <p class="text-xs text-gray-600">sqft Lote</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($property['bedrooms']) && $property['bedrooms'] > 0): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">🛏️</div>
                                <p class="text-xl font-bold text-gray-900"><?= $property['bedrooms'] ?></p>
                                <p class="text-xs text-gray-600">Dormitorios</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($property['bathrooms']) && $property['bathrooms'] > 0): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">🚿</div>
                                <p class="text-xl font-bold text-gray-900"><?= $property['bathrooms'] ?></p>
                                <p class="text-xs text-gray-600">Baños</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($usaDetails['garage_spaces']) && $usaDetails['garage_spaces'] > 0): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">🚗</div>
                                <p class="text-xl font-bold text-gray-900"><?= $usaDetails['garage_spaces'] ?></p>
                                <p class="text-xs text-gray-600">Garage</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($usaDetails['year_built'])): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">📅</div>
                                <p class="text-xl font-bold text-gray-900"><?= $usaDetails['year_built'] ?></p>
                                <p class="text-xs text-gray-600">Año Construcción</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($usaDetails['stories'])): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">🏢</div>
                                <p class="text-xl font-bold text-gray-900"><?= $usaDetails['stories'] ?></p>
                                <p class="text-xs text-gray-600">Pisos</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($usaDetails['pool'])): ?>
                            <div class="bg-white rounded-lg p-4 text-center shadow-sm">
                                <div class="text-2xl mb-1">🏊</div>
                                <p class="text-xl font-bold text-gray-900">Sí</p>
                                <p class="text-xs text-gray-600">Piscina</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($features)): ?>
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Características Adicionales</h2>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($features as $feature): ?>
                            <li class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="text-gray-700"><?= htmlspecialchars($feature) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($usaDetails)): ?>
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Detalles de la Propiedad</h2>
                    <div class="bg-gray-50 rounded-xl p-6">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <?php if (!empty($usaDetails['view_type'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">Vista</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($usaDetails['view_type']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($usaDetails['cooling'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">Climatización</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($usaDetails['cooling']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($usaDetails['heating'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">Calefacción</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($usaDetails['heating']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($usaDetails['flooring'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">Pisos</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($usaDetails['flooring']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($usaDetails['hoa_fee'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">HOA Fee (mensual)</p>
                                <p class="font-medium text-gray-900">$<?= number_format($usaDetails['hoa_fee'], 0) ?> USD</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($usaDetails['property_tax'])): ?>
                            <div>
                                <p class="text-sm text-gray-500">Impuesto Propiedad (anual)</p>
                                <p class="font-medium text-gray-900">$<?= number_format($usaDetails['property_tax'], 0) ?> USD</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 sticky top-24">
                    <div class="flex gap-2 mb-4">
                        <span class="inline-block px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-lg">
                            🇺🇸 USA
                        </span>
                        <span class="inline-block px-3 py-1 <?= $property['operation_type'] === 'Venta' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?> text-xs font-semibold rounded-lg">
                            <?= $property['operation_type'] ?>
                        </span>
                    </div>

                    <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($property['title']) ?></h1>
                    
                    <div class="text-sm text-gray-600 mb-4">
                        <?= htmlspecialchars($property['property_type']) ?>
                    </div>
                    
                    <?php if (!empty($property['address'])): ?>
                    <div class="flex items-start gap-2 text-gray-600 mb-6 pb-6 border-b border-gray-200">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        </svg>
                        <div>
                            <p><?= htmlspecialchars($property['address']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mb-6 pb-6 border-b border-gray-200">
                        <p class="text-gray-600 text-sm mb-1">Precio</p>
                        <p class="text-3xl font-bold text-green-600">
                            <?= USAModel::formatUSD($property['price']) ?>
                            <?php if ($property['operation_type'] === 'Arriendo'): ?>
                                <span class="text-lg text-gray-600">/mes</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="mb-6 pb-6 border-b border-gray-200 space-y-3">
                        <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= urlencode('Hola, me interesa la propiedad "' . $property['title'] . '" (' . USAModel::formatUSD($property['price']) . ') que vi en UrbanPropiedades USA: ' . $propertyUrl) ?>" target="_blank" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 rounded-lg transition flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            Contactar por WhatsApp
                        </a>
                        <a href="api/download-property-pdf.php?id=<?= $property['id'] ?>" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Descargar PDF
                        </a>
                    </div>

                    <div class="bg-red-50 rounded-xl p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">¿Interesado en esta propiedad?</h3>
                        <form method="POST" action="api/contact.php" class="space-y-3" onsubmit="return sendWhatsApp(event)">
                            <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                            <input type="hidden" name="property_title" value="<?= htmlspecialchars($property['title']) ?>">
                            
                            <input type="text" name="name" placeholder="Tu nombre" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm">
                            
                            <input type="email" name="email" placeholder="Tu email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm">
                            
                            <input type="tel" name="phone" placeholder="Tu teléfono" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 text-sm">
                            
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 rounded-lg transition">
                                Enviar Consulta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function changeImage(src, element) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.aspect-square').forEach(el => {
        el.classList.remove('border-red-600');
        el.classList.add('border-gray-200');
    });
    element.classList.remove('border-gray-200');
    element.classList.add('border-red-600');
}

function toggleFavorite(propertyId) {
    fetch('api/favorites.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle&property_id=' + propertyId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('favBtn');
            const icon = document.getElementById('favIcon');
            if (data.is_favorite) {
                btn.classList.add('bg-red-50', 'border-red-200');
                btn.classList.remove('bg-white', 'border-gray-200');
                icon.classList.add('text-red-500');
                icon.classList.remove('text-gray-400');
                icon.setAttribute('fill', 'currentColor');
            } else {
                btn.classList.remove('bg-red-50', 'border-red-200');
                btn.classList.add('bg-white', 'border-gray-200');
                icon.classList.remove('text-red-500');
                icon.classList.add('text-gray-400');
                icon.setAttribute('fill', 'none');
            }
        }
    });
}

function sendWhatsApp(event) {
    event.preventDefault();
    const form = event.target;
    const name = form.querySelector('[name="name"]').value;
    const email = form.querySelector('[name="email"]').value;
    const phone = form.querySelector('[name="phone"]').value;
    const title = form.querySelector('[name="property_title"]').value;
    
    const message = `Hola, soy ${name}.\n\nMe interesa la propiedad "${title}" que vi en UrbanPropiedades USA.\n\nEmail: ${email}${phone ? '\nTeléfono: ' + phone : ''}\n\n¿Podrían darme más información?`;
    
    window.open(`https://wa.me/<?= $whatsappNumber ?>?text=${encodeURIComponent(message)}`, '_blank');
    return false;
}
</script>

<?php if (!empty($similarProperties)): ?>
<section class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Propiedades USA Similares</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($similarProperties as $similar): ?>
                <?php $similarPhotos = $photoModel->getByPropertyId($similar['id']); ?>
                <a href="propiedad_usa.php?id=<?= $similar['id'] ?>" class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition group">
                    <div class="relative h-48">
                        <?php if (!empty($similarPhotos)): ?>
                            <img src="<?= htmlspecialchars(getPropertyPhotoUrl($similarPhotos[0]['photo_url'], true)) ?>" 
                                 alt="<?= htmlspecialchars($similar['title']) ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center">
                                <svg class="w-12 h-12 text-white/50" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <span class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded flex items-center gap-1">
                            🇺🇸 USA
                        </span>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-gray-900 mb-1 line-clamp-1"><?= htmlspecialchars($similar['title']) ?></h3>
                        <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($similar['property_type']) ?></p>
                        <div class="flex items-center gap-3 text-xs text-gray-600 mb-3">
                            <?php if (!empty($similar['bedrooms'])): ?>
                                <span><?= $similar['bedrooms'] ?> bed</span>
                            <?php endif; ?>
                            <?php if (!empty($similar['bathrooms'])): ?>
                                <span><?= $similar['bathrooms'] ?> bath</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-lg font-bold text-green-600"><?= USAModel::formatUSD($similar['price']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
