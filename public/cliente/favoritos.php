<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/FavoriteModel.php';
require_once __DIR__ . '/../../includes/PhotoModel.php';

if (!isset($_SESSION['portal_client_id'])) {
    header('Location: login.php?redirect=cliente/favoritos.php');
    exit;
}

$clientData = [];
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("SELECT razon_social, rut, cargo, nombre_completo, cedula_identidad, domicilio FROM portal_clients WHERE id = ?");
    $stmt->execute([$_SESSION['portal_client_id']]);
    $clientData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $clientData = [];
}

$favoriteModel = new FavoriteModel();
$photoModel = new PhotoModel();

$favoritesBySection = $favoriteModel->getClientFavoritesBySection($_SESSION['portal_client_id']);
$favorites = array_merge(
    $favoritesBySection['general'] ?? [],
    $favoritesBySection['terrenos'] ?? [],
    $favoritesBySection['activos'] ?? [],
    $favoritesBySection['usa'] ?? []
);

$sectionLabels = [
    'general' => 'Propiedades',
    'terrenos' => 'Terrenos',
    'activos' => 'Activos',
    'usa' => 'USA'
];

$sectionIcons = [
    'general' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
    'terrenos' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>',
    'activos' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
    'usa' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>'
];

$pageTitle = 'Mis Favoritos';
$currentPage = 'favoritos';
include __DIR__ . '/../../templates/header.php';
?>

<div class="bg-gradient-to-b from-gray-50 to-white py-8 md:py-12 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Mis Favoritos</h1>
                <p class="text-gray-600">Hola, <?= htmlspecialchars($_SESSION['portal_client_name']) ?></p>
            </div>
            <a href="logout.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm">Cerrar Sesión</a>
        </div>
    </div>
</div>

<section class="py-12 bg-white">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <?php if (empty($favorites)): ?>
            <div class="text-center py-12 bg-gray-50 rounded-xl">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No tienes favoritos aún</h3>
                <p class="text-gray-600 mb-4">Explora nuestras propiedades y guarda tus favoritas</p>
                <a href="../propiedades.php" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Ver Propiedades
                </a>
            </div>
        <?php else: ?>
            <!-- Section Tabs -->
            <div class="mb-8">
                <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-4">
                    <?php $firstSection = true; ?>
                    <?php foreach ($sectionLabels as $sectionKey => $sectionLabel): ?>
                        <?php $sectionCount = count($favoritesBySection[$sectionKey] ?? []); ?>
                        <?php if ($sectionCount > 0): ?>
                        <button onclick="showSection('<?= $sectionKey ?>')" 
                                id="tab-<?= $sectionKey ?>"
                                class="section-tab px-4 py-2 rounded-lg font-medium flex items-center gap-2 transition <?= $firstSection ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $sectionIcons[$sectionKey] ?></svg>
                            <?= $sectionLabel ?> <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full"><?= $sectionCount ?></span>
                        </button>
                        <?php $firstSection = false; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="flex justify-between items-center mb-4">
                    <p class="text-gray-600"><?= count($favorites) ?> propiedad(es) en favoritos</p>
                    <div class="flex gap-3">
                        <button onclick="toggleSelectAll()" id="selectAllBtn"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <span>Seleccionar Todos</span>
                        </button>
                        <button onclick="openOrdenVisitaModal()" id="solicitarBtn"
                                class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Solicitar Más Información (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                </div>
                <p class="text-sm text-gray-500">Selecciona las propiedades sobre las que deseas recibir más información</p>
            </div>
            
            <!-- Section Contents -->
            <?php $firstSection = true; ?>
            <?php foreach ($sectionLabels as $sectionKey => $sectionLabel): ?>
            <?php $sectionFavorites = $favoritesBySection[$sectionKey] ?? []; ?>
            <?php if (count($sectionFavorites) > 0): ?>
            <div id="section-<?= $sectionKey ?>" class="section-content <?= $firstSection ? '' : 'hidden' ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($sectionFavorites as $p): ?>
                    <?php
                    $photos = $photoModel->getByPropertyId($p['id']);
                    $photo = null;
                    if (!empty($photos) && !empty($photos[0]['photo_url'])) {
                        $photo = getPropertyPhotoUrl($photos[0]['photo_url'], true);
                    } else {
                        $photo = getFirstImage($p['images'] ?? '[]');
                    }
                    ?>
                    <?php $propertyUrl = '../propiedad.php?id=' . $p['id']; ?>
                    <div class="bg-white border rounded-xl overflow-hidden hover:shadow-lg transition group relative property-card" data-property-id="<?= $p['id'] ?>" data-property-title="<?= htmlspecialchars($p['title']) ?>" data-property-m2="<?= number_format($p['total_area'] ?? 0, 0, ',', '.') ?>" data-property-price="<?= formatPrice($p['price']) ?>" data-property-url="<?= $propertyUrl ?>">
                        <div class="absolute top-3 left-3 z-10">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" class="property-checkbox w-5 h-5 text-blue-600 border-2 border-white rounded shadow-lg" 
                                       value="<?= $p['id'] ?>" onchange="updateSelectedCount()">
                            </label>
                        </div>
                        <button onclick="removeFavorite(<?= $p['id'] ?>, this)" class="absolute top-3 right-3 z-10 w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-lg hover:bg-red-50 transition">
                            <svg class="w-6 h-6 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                        
                        <a href="../propiedad.php?id=<?= $p['id'] ?>">
                            <div class="relative aspect-[4/3] overflow-hidden">
                                <img src="<?= $photo ?>" class="w-full h-full object-cover group-hover:scale-105 duration-300">
                                <span class="absolute top-3 left-3 text-xs px-3 py-1 bg-blue-600 text-white rounded-lg">
                                    <?= formatPrice($p['price']) ?>
                                </span>
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 group-hover:text-blue-600">
                                    <?= htmlspecialchars(truncateText($p['title'], 40)) ?>
                                </h3>
                                <p class="text-xs text-gray-600"><?= $p['comuna_name'] ?? '' ?></p>
                                <div class="flex gap-4 text-xs text-gray-600 mt-2">
                                    <?php if (($p['bedrooms'] ?? 0) > 0): ?><span>🛏 <?= $p['bedrooms'] ?></span><?php endif; ?>
                                    <?php if (($p['bathrooms'] ?? 0) > 0): ?><span>🚿 <?= $p['bathrooms'] ?></span><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php $firstSection = false; ?>
            <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<script>
function showSection(sectionKey) {
    document.querySelectorAll('.section-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.section-tab').forEach(el => {
        el.classList.remove('bg-blue-600', 'text-white');
        el.classList.add('bg-gray-100', 'text-gray-600', 'hover:bg-gray-200');
    });
    
    const section = document.getElementById('section-' + sectionKey);
    const tab = document.getElementById('tab-' + sectionKey);
    
    if (section) section.classList.remove('hidden');
    if (tab) {
        tab.classList.remove('bg-gray-100', 'text-gray-600', 'hover:bg-gray-200');
        tab.classList.add('bg-blue-600', 'text-white');
    }
}
</script>

<div id="ordenVisitaModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Orden de Visita</h2>
                <button onclick="closeOrdenVisitaModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-gray-800 mb-2">Propiedades Seleccionadas:</h4>
                <ul id="selectedPropertiesList" class="space-y-1 max-h-32 overflow-y-auto"></ul>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-700 leading-relaxed max-h-96 overflow-y-auto">
                <h3 class="font-bold text-center mb-4">CONTRATO DE PRESTACIÓN DE SERVICIOS Y ACUERDO DE CONFIDENCIALIDAD Y RESERVA</h3>
                
                <p class="mb-3 text-center">
                    <strong>INVERSIONES Y ASESORÍAS URBAN GROUP SPA</strong><br>
                    y<br>
                    <?php if (!empty($clientData['razon_social'])): ?>
                        <strong class="text-blue-700"><?= htmlspecialchars($clientData['razon_social']) ?></strong>
                    <?php else: ?>
                        <strong class="text-blue-700"><?= htmlspecialchars($clientData['nombre_completo'] ?? $_SESSION['portal_client_name'] ?? '') ?></strong>
                    <?php endif; ?>
                </p>
                
                <p class="mb-3">
                    En Santiago, a <strong class="text-blue-700"><?= date('d \d\e F \d\e Y') ?></strong>, comparecen por una parte; <?php if (!empty($clientData['razon_social'])): ?>la empresa <strong class="text-blue-700"><?= htmlspecialchars($clientData['razon_social']) ?></strong> <strong class="text-blue-700"><?= htmlspecialchars($clientData['rut'] ?? '') ?></strong> representada debidamente por su <strong class="text-blue-700"><?= htmlspecialchars($clientData['cargo'] ?? 'Representante') ?></strong>, <strong class="text-blue-700"><?= htmlspecialchars($clientData['nombre_completo'] ?? '') ?></strong> <strong class="text-blue-700"><?= htmlspecialchars($clientData['cedula_identidad'] ?? '') ?></strong>, ambos domiciliados en <strong class="text-blue-700"><?= htmlspecialchars($clientData['domicilio'] ?? '') ?></strong><?php else: ?><strong class="text-blue-700"><?= htmlspecialchars($clientData['nombre_completo'] ?? $_SESSION['portal_client_name'] ?? '') ?></strong> <strong class="text-blue-700"><?= htmlspecialchars($clientData['cedula_identidad'] ?? $clientData['rut'] ?? '') ?></strong><?php endif; ?>, en adelante también como la "Parte Receptora" y por otra; <strong>Inversiones y Asesorías Urban Group SPA RUT: 76.192.802-3</strong> gestionando acorde a su giro de Corretaje de Propiedades y representada legalmente por Patricio John Videla Lizana, C.I.: 12.252.857-K ambos domiciliados en Av. Nueva Providencia N° 1945 Oficina 502 Providencia, Región Metropolitana, Chile, en adelante también como "Urban Group" o "Parte Reveladora", quienes acuerdan suscribir el presente Contrato de Prestación de Servicios de Corretaje de Propiedades y Acuerdo de Confidencialidad y Reserva.
                </p>
                
                <p class="mb-3">
                    <strong>PRIMERO: Gestión de Corretaje de Propiedades:</strong> Urban Group es una empresa dedicada a la Gestión de Negocios Inmobiliarios y cuenta con autorización vigente de los propietarios para gestionar la búsqueda de compradores de los Terrenos Inmobiliarios. La parte Receptora analizará estos Terrenos Inmobiliarios presentados por Urban Group y evaluará la posibilidad de efectuar la compra parcial o total.
                </p>
                
                <p class="mb-3">
                    Dado lo anterior, La parte receptora se compromete a efectuar toda transacción, respecto a la posible compra y entrega en arrendamiento, con el(los) propietario(s) de esta(s) propiedad(es), sólo por intermedio de Urban Group. Por ello encarga y autoriza a Urban Group para que realice todas las gestiones requeridas y orientadas a efectuar las compraventas, arrendamientos, asociación o cualquier otra intermediación entre La parte Receptora y el(los) propietario(s), actuando Urban Group como único y exclusivo intermediario y corredor de propiedades de todas las operaciones que versen sobre estas propiedades. <strong>QUEDA ABSOLUTAMENTE PROHIBIDO</strong> a la parte Receptora tomar contacto directo con él (los) propietario(s) de los Terrenos Inmobiliarios, sin la coordinación y participación de Urban Group o de quien lo represente.
                </p>
                
                <p class="mb-3">
                    <strong>Las Comisiones:</strong> En caso de efectuarse la compraventa de algunas de las propiedades, La parte Receptora pagará a Urban Group la comisión equivalente al <strong>2,0% más IVA</strong> calculado sobre cada precio de las compraventas. Por ello, los montos equivalentes en pesos de las comisiones, con el valor de la unidad de fomento al día de la firma de escritura, serán tomados en Vales Vista o Depósitos a Plazo a 30 días auto-renovables, a la orden de la promitente compradora y endosados en blanco, los que serán presentados a cobro cuando sea firmada la o las escrituras de compraventa.
                </p>
                
                <p class="mb-3">
                    Este Contrato de Prestación de Servicios de Corretaje de Propiedades es Personal, Corporativo, Intransferible e Irrevocable. La obligación de la parte Receptora de pagar las Comisiones a Urban Group, no se extingue, vence o caduca con el término del plazo del Mandato de Venta firmada por él (los) propietario(s).
                </p>
                
                <p class="mb-3">
                    <strong>SEGUNDO: INFORMACIÓN CONFIDENCIAL.</strong> Toda la información que sea entregada por la Parte Reveladora a la Parte Receptora será considerada como "Información Confidencial" y queda sujeta a las regulaciones de este documento.
                </p>
                
                <p class="mb-3">
                    <strong>TERCERO: USO DE LA INFORMACIÓN CONFIDENCIAL.</strong> La Parte Receptora se obliga a mantener y conservar en todo momento la Información Confidencial como secreta y confidencial, y no la comunicará ni revelará directa ni indirectamente a ninguna otra persona natural o jurídica.
                </p>
                
                <p class="mb-3">
                    <strong>SEXTO: VIGENCIA.</strong> El presente instrumento de confidencialidad estará vigente mientras la Parte Receptora tenga acceso a la Información Confidencial, la obligación de mantener en forma confidencial la información subsistirá indefinidamente.
                </p>
                
                <p class="mb-3">
                    <strong>OCTAVO:</strong> La obligación de los pagos de comisiones regirán de manera indefinida, sobreviviendo al cumplimiento de plazos de otros contratos firmados con motivo del objeto de este contrato, inclusive en caso de desistimiento.
                </p>
                
                <p class="mb-3">
                    <strong>NOVENO:</strong> Todas las dificultades o divergencias serán sometidas a los tribunales de justicia ordinaria de la ciudad de Santiago.
                </p>
                
                <p class="mb-3">
                    <strong>DÉCIMO: DOMICILIO:</strong> Para todos los efectos legales de este contrato las Partes fijan su domicilio en la ciudad de Santiago.
                </p>
                
                <p class="mb-3 text-xs text-gray-500">
                    El presente contrato se firma electrónicamente. En virtud de la Ley N° 19.799, los actos, acuerdos y contratos suscritos por medio de firma electrónica serán válidos y producirán los mismos efectos que los celebrados por escrito y en soporte de papel.
                </p>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <label class="flex items-start cursor-pointer">
                    <input type="checkbox" id="acceptTerms" class="mt-1 h-5 w-5 text-blue-600 border-gray-300 rounded">
                    <span class="ml-3 text-sm text-gray-700">
                        <strong>ACEPTO</strong> los términos y condiciones de esta Orden de Visita y autorizo a 
                        Urban Group SpA a enviarme información detallada de las propiedades seleccionadas.
                    </span>
                </label>
            </div>
            
            <div id="submitError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"></div>
            <div id="submitSuccess" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4"></div>
            
            <div class="flex gap-4">
                <button onclick="closeOrdenVisitaModal()" class="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    Cancelar
                </button>
                <button onclick="submitOrdenVisita()" id="submitBtn" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Enviar Solicitud
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let allSelected = false;

function removeFavorite(propertyId, button) {
    if (!confirm('¿Eliminar de favoritos?')) return;
    
    fetch('api/favorites.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=remove&property_id=' + propertyId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            button.closest('.property-card').remove();
            updateSelectedCount();
            if (document.querySelectorAll('.property-card').length === 0) {
                location.reload();
            }
        }
    });
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.property-checkbox:checked');
    document.getElementById('selectedCount').textContent = checkboxes.length;
    
    const allCheckboxes = document.querySelectorAll('.property-checkbox');
    allSelected = checkboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
    document.getElementById('selectAllBtn').querySelector('span').textContent = allSelected ? 'Deseleccionar Todos' : 'Seleccionar Todos';
}

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.property-checkbox');
    allSelected = !allSelected;
    checkboxes.forEach(cb => cb.checked = allSelected);
    updateSelectedCount();
}

function getSelectedPropertyIds() {
    const checkboxes = document.querySelectorAll('.property-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function openOrdenVisitaModal() {
    const selected = getSelectedPropertyIds();
    if (selected.length === 0) {
        alert('Por favor, selecciona al menos una propiedad para solicitar información.');
        return;
    }
    
    const listContainer = document.getElementById('selectedPropertiesList');
    if (listContainer) {
        listContainer.innerHTML = '';
        document.querySelectorAll('.property-checkbox:checked').forEach(cb => {
            const card = cb.closest('.property-card');
            const title = card.dataset.propertyTitle;
            const m2 = card.dataset.propertyM2 || '0';
            const price = card.dataset.propertyPrice || 'Consultar';
            const url = card.dataset.propertyUrl || '#';
            listContainer.innerHTML += `<li class="text-sm text-gray-700 border-b border-gray-100 pb-2 mb-2">
                <strong>${title}</strong><br>
                <span class="text-xs text-gray-500">Superficie: ${m2} m² | Precio: ${price}</span><br>
                <a href="${url}" target="_blank" class="text-xs text-blue-600 hover:underline">${url}</a>
            </li>`;
        });
    }
    
    document.getElementById('ordenVisitaModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeOrdenVisitaModal() {
    document.getElementById('ordenVisitaModal').classList.add('hidden');
    document.body.style.overflow = '';
    document.getElementById('acceptTerms').checked = false;
    document.getElementById('submitError').classList.add('hidden');
    document.getElementById('submitSuccess').classList.add('hidden');
}

function submitOrdenVisita() {
    const acceptTerms = document.getElementById('acceptTerms').checked;
    const submitBtn = document.getElementById('submitBtn');
    const errorDiv = document.getElementById('submitError');
    const successDiv = document.getElementById('submitSuccess');
    const selectedIds = getSelectedPropertyIds();
    
    errorDiv.classList.add('hidden');
    successDiv.classList.add('hidden');
    
    if (!acceptTerms) {
        errorDiv.textContent = 'Debe aceptar los términos y condiciones para continuar.';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    if (selectedIds.length === 0) {
        errorDiv.textContent = 'Debe seleccionar al menos una propiedad.';
        errorDiv.classList.remove('hidden');
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';
    
    fetch('api/orden_visita.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=send_orden_visita&property_ids=' + selectedIds.join(',')
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            successDiv.textContent = 'Su solicitud ha sido enviada exitosamente. Recibirá un correo con la información.';
            successDiv.classList.remove('hidden');
            setTimeout(() => {
                closeOrdenVisitaModal();
            }, 3000);
        } else {
            errorDiv.textContent = data.error || 'Error al enviar la solicitud. Intente nuevamente.';
            errorDiv.classList.remove('hidden');
        }
    })
    .catch(err => {
        errorDiv.textContent = 'Error de conexión. Intente nuevamente.';
        errorDiv.classList.remove('hidden');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Enviar Solicitud';
    });
}

document.addEventListener('DOMContentLoaded', updateSelectedCount);
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
