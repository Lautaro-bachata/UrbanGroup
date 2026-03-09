<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Nosotros';
$currentPage = 'about';

include __DIR__ . '/../templates/header.php';
?>

<!-- Hero Section with Carousel -->
<?php
// load images from uploads/nosotros if directory exists
$nosotrosImages = [];
$dir = __DIR__ . '/../uploads/nosotros/';
if (is_dir($dir)) {
    // include uppercase extensions just in case
    foreach (glob($dir . '*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}', GLOB_BRACE) as $file) {
        $nosotrosImages[] = basename($file);
    }
}
?>
<section class="relative h-96 flex items-center justify-center overflow-hidden">
    <?php if (!empty($nosotrosImages)): ?>
    <div id="nosotrosCarousel" class="absolute inset-0">
        <?php foreach ($nosotrosImages as $index => $fname): ?>
            <div class="carousel-slide absolute inset-0 bg-cover bg-center transition-opacity duration-1000 <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>" 
                 style="background-image: url('<?= BASE_URL ?>../uploads/nosotros/<?= htmlspecialchars($fname) ?>');">
                <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70"></div>
            </div>
        <?php endforeach; ?>
        <?php if (count($nosotrosImages) > 1): ?>
        <div class="absolute bottom-24 left-1/2 transform -translate-x-1/2 flex gap-2 z-20 hidden sm:flex">
            <?php foreach ($nosotrosImages as $index => $fname): ?>
                <button aria-label="Ir a slide <?= $index + 1 ?>" data-dot="<?= $index ?>" class="carousel-dot w-3 h-3 rounded-full transition <?= $index === 0 ? 'bg-white' : 'bg-white/50' ?>"></button>
            <?php endforeach; ?>
        </div>
        <button aria-label="Anterior" id="nosotrosPrev" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 text-white p-2 rounded-full z-20 hidden sm:inline-flex">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button aria-label="Siguiente" id="nosotrosNext" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 text-white p-2 rounded-full z-20 hidden sm:inline-flex">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1920&h=1080&fit=crop');">
        <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70"></div>
    </div>
    <?php endif; ?>
    <div class="relative z-10 text-center text-white px-4">
        <h1 class="text-4xl lg:text-5xl font-bold mb-4">Sobre Urban Group</h1>
        <p class="text-lg lg:text-xl text-white/90">Somos el puente entre tu sueño inmobiliario y la realidad</p>
    </div>
</section>

<!-- History Section -->
<section class="py-16 lg:py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
            <div>
                <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-6">Nuestra Historia</h2>
                <div class="space-y-4 text-gray-600 leading-relaxed">
                    <p>
                        Urban Group nació de la visión de un grupo de profesionales del sector inmobiliario que identificaron la necesidad de modernizar la forma en que las personas buscan y encuentran propiedades en Chile.
                    </p>
                    <p>
                        Desde nuestros inicios, nos hemos enfocado en crear una plataforma que combine tecnología de vanguardia con el conocimiento profundo del mercado inmobiliario chileno, facilitando el proceso tanto para compradores como para vendedores.
                    </p>
                    <p>
                        Hoy, somos parte de Urban Group, un conglomerado de empresas dedicadas al desarrollo urbano y la transformación del sector inmobiliario en Latinoamérica.
                    </p>
                </div>
            </div>
            <div>
                <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=400&fit=crop" alt="Oficinas Urban Group" class="rounded-xl shadow-lg">
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section class="py-16 lg:py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-4">Nuestros Valores</h2>
            <p class="text-gray-600 text-lg max-w-2xl mx-auto">Los principios que guían cada una de nuestras acciones</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg transition">
                <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Transparencia</h3>
                <p class="text-gray-600">Creemos en la honestidad total. Cada propiedad listada cuenta con información verificada y actualizada.</p>
            </div>

            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg transition">
                <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Colaboración</h3>
                <p class="text-gray-600">Trabajamos junto a corredores, inmobiliarias y clientes para crear relaciones de largo plazo.</p>
            </div>

            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg transition">
                <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Innovación</h3>
                <p class="text-gray-600">Constantemente mejoramos nuestra tecnología para ofrecer la mejor experiencia de búsqueda.</p>
            </div>

            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg transition">
                <div class="w-14 h-14 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Compromiso</h3>
                <p class="text-gray-600">Nos apasiona lo que hacemos y ponemos todo nuestro esfuerzo en cada cliente que ayudamos.</p>
            </div>

            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg transition">
                <div class="w-14 h-14 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Cobertura</h3>
                <p class="text-gray-600">Presencia en todas las regiones de Chile, desde Arica hasta Magallanes.</p>
            </div>

            <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg transition">
                <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Agilidad</h3>
                <p class="text-gray-600">Respuestas rápidas y procesos optimizados para que encuentres tu propiedad ideal.</p>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision Section -->
<section class="py-16 lg:py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
            <div>
                <img src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=600&h=400&fit=crop" alt="Propiedades Urban Group" class="rounded-xl shadow-lg">
            </div>
            <div>
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Nuestra Misión</h2>
                    <p class="text-gray-600 leading-relaxed">
                        Facilitar el acceso a propiedades de calidad para todos los chilenos, democratizando el mercado inmobiliario a través de tecnología e información transparente.
                    </p>
                </div>
                
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Nuestra Visión</h2>
                    <p class="text-gray-600 leading-relaxed">
                        Ser la plataforma inmobiliaria líder en Chile, reconocida por nuestra innovación, confiabilidad y compromiso con la satisfacción de nuestros usuarios.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->

<script>
function redirectToWhatsApp() {
    const message = "¡Hola! Me comunico por la página web. Me gustaría ser socio de Urban Group!";
    const whatsappNumber = '<?php defined("WHATSAPP_NUMBER") ? preg_replace("/[^0-9]/", "", WHATSAPP_NUMBER) : "56966785614" ?>';
    const encodedMessage = encodeURIComponent(message);
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodedMessage}`;
    window.open(whatsappUrl, '_blank');
}

<?php if (!empty($nosotrosImages) && count($nosotrosImages) > 1): ?>
(function() {
    const slides = document.querySelectorAll('#nosotrosCarousel .carousel-slide');
    const dots = document.querySelectorAll('#nosotrosCarousel .carousel-dot');
    let current = 0;
    function goTo(idx) {
        slides[current].classList.remove('opacity-100');
        slides[current].classList.add('opacity-0');
        slides[idx].classList.remove('opacity-0');
        slides[idx].classList.add('opacity-100');
        if (dots.length) {
            dots[current].classList.remove('bg-white');
            dots[current].classList.add('bg-white/50');
            dots[idx].classList.remove('bg-white/50');
            dots[idx].classList.add('bg-white');
        }
        current = idx;
    }
    document.getElementById('nosotrosPrev')?.addEventListener('click', () => goTo((current - 1 + slides.length) % slides.length));
    document.getElementById('nosotrosNext')?.addEventListener('click', () => goTo((current + 1) % slides.length));
    dots.forEach(dot => {
        dot.addEventListener('click', () => goTo(parseInt(dot.dataset.dot)));
    });
    setInterval(() => goTo((current + 1) % slides.length), 5000);
})();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
