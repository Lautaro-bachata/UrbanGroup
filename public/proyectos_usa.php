<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/contact_settings.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/USAModel.php';
require_once __DIR__ . '/../includes/PhotoModel.php';

if (!isset($_SESSION['portal_client'])) {
    header('Location: portal_login.php?section=usa');
    exit;
}

$usaModel = new USAModel();
$photoModel = new PhotoModel();

$projects = $usaModel->getProjects();

$pageTitle = 'Proyectos USA';
$currentPage = 'usa';
include __DIR__ . '/../templates/header.php';
?>

<div class="bg-gray-50 min-h-screen">
    <div class="bg-gradient-to-r from-amber-500 via-red-600 to-amber-500 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 text-center">
            <h1 class="text-4xl font-bold mb-4">Proyectos Inmobiliarios USA</h1>
            <p class="text-xl text-white/90 max-w-2xl mx-auto">
                Descubra las mejores oportunidades de inversion en proyectos inmobiliarios en Estados Unidos
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-12">
        <?php if (empty($projects)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No hay proyectos disponibles</h3>
                <p class="text-gray-500 mb-6">Proximamente tendremos proyectos inmobiliarios en Estados Unidos</p>
                <a href="usa.php" class="inline-block bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition">
                    Ver Propiedades USA
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php foreach ($projects as $project): ?>
                    <?php $photos = $photoModel->getByPropertyId($project['id']); ?>
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition group">
                        <div class="relative">
                            <?php if (!empty($photos) && count($photos) > 1): ?>
                            <div class="project-slideshow relative h-72" data-project-id="<?= $project['id'] ?>">
                                <?php foreach ($photos as $index => $photo): ?>
                                <div class="slide absolute inset-0 transition-opacity duration-500 <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>">
                                    <img src="<?= htmlspecialchars($photo['photo_url']) ?>" 
                                         alt="<?= htmlspecialchars($project['title']) ?>"
                                         class="w-full h-full object-cover">
                                </div>
                                <?php endforeach; ?>
                                <button onclick="prevSlide(<?= $project['id'] ?>)" class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white w-10 h-10 rounded-full flex items-center justify-center transition z-10">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </button>
                                <button onclick="nextSlide(<?= $project['id'] ?>)" class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white w-10 h-10 rounded-full flex items-center justify-center transition z-10">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                                    <?php foreach ($photos as $index => $photo): ?>
                                    <button onclick="goToSlide(<?= $project['id'] ?>, <?= $index ?>)" 
                                            class="slide-dot w-2 h-2 rounded-full bg-white/50 hover:bg-white transition <?= $index === 0 ? 'bg-white' : '' ?>" 
                                            data-index="<?= $index ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php elseif (!empty($photos)): ?>
                            <div class="h-72">
                                <img src="<?= htmlspecialchars($photos[0]['photo_url']) ?>" 
                                     alt="<?= htmlspecialchars($project['title']) ?>"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            </div>
                            <?php else: ?>
                            <div class="h-72 bg-gradient-to-br from-amber-400 to-red-600 flex items-center justify-center">
                                <svg class="w-24 h-24 text-white/50" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <?php endif; ?>
                            
                            <span class="absolute top-4 left-4 bg-amber-500 text-white text-sm font-bold px-4 py-2 rounded-full shadow-lg">
                                PROYECTO
                            </span>
                            
                            <?php if (!empty($project['project_units'])): ?>
                            <span class="absolute top-4 right-4 bg-black/70 text-white text-sm font-bold px-3 py-2 rounded-lg">
                                <?= $project['project_units'] ?> unidades
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-6">
                            <h3 class="text-2xl font-bold text-gray-900 mb-3"><?= htmlspecialchars($project['title']) ?></h3>
                            
                            <?php if (!empty($project['project_developer'])): ?>
                            <p class="text-gray-600 mb-4">
                                <span class="font-medium">Desarrollador:</span> <?= htmlspecialchars($project['project_developer']) ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($project['project_completion_date'])): ?>
                            <p class="text-gray-600 mb-4">
                                <span class="font-medium">Entrega estimada:</span> <?= date('F Y', strtotime($project['project_completion_date'])) ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($project['project_amenities'])): ?>
                            <div class="mb-4">
                                <p class="font-medium text-gray-700 mb-2">Amenidades:</p>
                                <p class="text-gray-600 text-sm"><?= htmlspecialchars($project['project_amenities']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex flex-wrap gap-3 mb-6">
                                <?php if (!empty($project['surface_sqft'])): ?>
                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                    <?= number_format($project['surface_sqft'], 0, '', ',') ?> sqft
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($project['bedrooms'])): ?>
                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                    <?= $project['bedrooms'] ?> Beds
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($project['bathrooms'])): ?>
                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                    <?= $project['bathrooms'] ?> Baths
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="border-t pt-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500">Desde</p>
                                        <p class="text-3xl font-bold text-red-600">
                                            <?= !empty($project['price_usd']) ? USAModel::formatUSD($project['price_usd']) : formatPrice($project['price']) ?>
                                        </p>
                                    </div>
                                    <div class="flex gap-3">
                                        <?php 
                                        $usaDetails = $usaModel->getUSADetailsByPropertyId($project['id']);
                                        $whatsapp = $usaDetails['whatsapp_number'] ?? WHATSAPP_NUMBER;
                                        $whatsappNumber = preg_replace('/[^0-9]/', '', $whatsapp);
                                        $whatsappMessage = urlencode("Hola, me interesa el proyecto: " . $project['title']);
                                        ?>
                                        <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= $whatsappMessage ?>" 
                                           target="_blank"
                                           class="bg-green-500 text-white px-4 py-3 rounded-lg hover:bg-green-600 transition flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                            </svg>
                                            WhatsApp
                                        </a>
                                        <a href="propiedad_usa.php?id=<?= $project['id'] ?>" 
                                           class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition">
                                            Ver Detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-12 text-center">
            <a href="usa.php" class="inline-flex items-center gap-2 text-red-600 hover:text-red-700 font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver a Propiedades USA
            </a>
        </div>
    </div>
</div>

<script>
const slideshows = {};

document.querySelectorAll('.project-slideshow').forEach(slideshow => {
    const projectId = slideshow.dataset.projectId;
    const slides = slideshow.querySelectorAll('.slide');
    const dots = slideshow.querySelectorAll('.slide-dot');
    
    slideshows[projectId] = {
        slides: slides,
        dots: dots,
        currentIndex: 0,
        total: slides.length
    };
    
    setInterval(() => {
        nextSlide(projectId);
    }, 5000);
});

function updateSlideshow(projectId) {
    const show = slideshows[projectId];
    if (!show) return;
    
    show.slides.forEach((slide, i) => {
        slide.classList.toggle('opacity-100', i === show.currentIndex);
        slide.classList.toggle('opacity-0', i !== show.currentIndex);
    });
    
    show.dots.forEach((dot, i) => {
        dot.classList.toggle('bg-white', i === show.currentIndex);
        dot.classList.toggle('bg-white/50', i !== show.currentIndex);
    });
}

function nextSlide(projectId) {
    const show = slideshows[projectId];
    if (!show) return;
    
    show.currentIndex = (show.currentIndex + 1) % show.total;
    updateSlideshow(projectId);
}

function prevSlide(projectId) {
    const show = slideshows[projectId];
    if (!show) return;
    
    show.currentIndex = (show.currentIndex - 1 + show.total) % show.total;
    updateSlideshow(projectId);
}

function goToSlide(projectId, index) {
    const show = slideshows[projectId];
    if (!show) return;
    
    show.currentIndex = index;
    updateSlideshow(projectId);
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
