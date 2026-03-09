<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/CarouselModel.php';
require_once __DIR__ . '/../../includes/base_url.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$carouselModel = new CarouselModel();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = $carouselModel->create([
                'title' => $_POST['title'] ?? '',
                'alt_text' => $_POST['alt_text'] ?? ''
            ], $_FILES['image'] ?? null);
            
            if (is_array($result) && isset($result['error'])) {
                $error = $result['error'];
            } else {
                $message = 'Imagen agregada correctamente';
            }
            break;
            
        case 'update':
            $id = (int)$_POST['id'];
            $result = $carouselModel->update($id, [
                'title' => $_POST['title'] ?? '',
                'alt_text' => $_POST['alt_text'] ?? '',
                'display_order' => (int)($_POST['display_order'] ?? 0)
            ], $_FILES['image'] ?? null);
            
            if (is_array($result) && isset($result['error'])) {
                $error = $result['error'];
            } else {
                $message = 'Imagen actualizada correctamente';
            }
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            $carouselModel->delete($id);
            $message = 'Imagen eliminada correctamente';
            break;
            
        case 'toggle':
            $id = (int)$_POST['id'];
            $carouselModel->toggleActive($id);
            $message = 'Estado de imagen actualizado';
            break;
            
        case 'reorder':
            $order = json_decode($_POST['order'] ?? '[]', true);
            if ($order) {
                $carouselModel->updateOrder($order);
                $message = 'Orden actualizado';
            }
            break;
    }
}

$images = $carouselModel->getAll();

$pageTitle = 'Gestión de Carousel';
$currentPage = 'carousel';
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
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
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

<div class="flex h-screen flex-col lg:flex-row">
    <aside class="hidden lg:flex flex-col w-64 bg-slate-900 text-white border-r border-slate-700 overflow-y-auto">
        <div class="p-6 border-b border-slate-700">
            <h3 class="text-xs font-semibold text-slate-400 mb-2">ADMINISTRACIÓN</h3>
            <p class="text-sm font-medium truncate"><?= htmlspecialchars($_SESSION['name']) ?></p>
        </div>
        
        <nav class="flex-1 p-4 space-y-1">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-2m-4 2l-4-2"/>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="index.php?action=properties" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>Propiedades</span>
            </a>
            <div class="px-4 py-2">
                <p class="text-xs font-semibold text-slate-400 mb-2">SECCIONES ESPECIALES</p>
                <div class="space-y-1">
                    <a href="index.php?action=special_list&type=terrenos" class="flex items-center gap-3 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                        <span>Terrenos Inmo</span>
                    </a>
                    <a href="index.php?action=special_list&type=activos" class="flex items-center gap-3 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                        <span>Activos Inmo</span>
                    </a>
                    <a href="index.php?action=special_list&type=usa" class="flex items-center gap-3 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                        <span>🇺🇸 Prop. USA</span>
                    </a>
                </div>
            </div>
            <a href="index.php?action=partners" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span>Socios</span>
            </a>
            <a href="carousel.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-blue-600 text-white transition text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Carousel Inicio</span>
            </a>
            <a href="portal_clients.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span>Clientes Portal</span>
            </a>
            <a href="index.php?action=locations" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Regiones / Comunas</span>
            </a>
        </nav>
    </aside>

    <div class="lg:hidden bg-white border-b border-gray-200 px-4 py-2 flex gap-2 overflow-x-auto scrollbar-hide">
        <a href="index.php" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Dashboard</a>
        <a href="index.php?action=properties" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Propiedades</a>
        <a href="index.php?action=partners" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Socios</a>
        <a href="carousel.php" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-blue-600 text-white">Carousel</a>
        <a href="portal_clients.php" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Clientes</a>
        <a href="index.php?action=locations" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Ubicaciones</a>
    </div>

    <main class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900"><?= $pageTitle ?></h1>
            <span class="text-sm text-gray-500"><?= count($images) ?> / 8 imágenes</span>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (count($images) < 8): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Agregar Nueva Imagen</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="create">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Imagen *</label>
                        <input type="file" name="image" accept="image/*" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">JPG, PNG, WebP. Máx 5MB</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                        <input type="text" name="title" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Título opcional">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Texto Alternativo</label>
                        <input type="text" name="alt_text" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Descripción de la imagen">
                    </div>
                </div>
                
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                    Agregar Imagen
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg mb-6">
            Has alcanzado el límite de 8 imágenes. Elimina una para agregar más.
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Imágenes del Carousel</h2>
            
            <?php if (empty($images)): ?>
                <p class="text-gray-500 text-center py-8">No hay imágenes en el carousel</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" id="imageGrid">
                    <?php foreach ($images as $image): ?>
                        <div class="border rounded-lg overflow-hidden <?= $image['is_active'] ? 'border-green-300' : 'border-gray-300 opacity-60' ?>">
                            <div class="relative h-40">
                                <img src="<?= BASE_URL . htmlspecialchars($image['file_path']) ?>" 
                                     alt="<?= htmlspecialchars($image['alt_text'] ?? '') ?>"
                                     class="w-full h-full object-cover">
                                <span class="absolute top-2 left-2 bg-black/50 text-white text-xs px-2 py-1 rounded">
                                    #<?= $image['display_order'] ?>
                                </span>
                                <?php if (!$image['is_active']): ?>
                                    <span class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded">
                                        Inactiva
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-3">
                                <p class="text-sm font-medium text-gray-900 truncate mb-2">
                                    <?= htmlspecialchars($image['title'] ?: 'Sin título') ?>
                                </p>
                                
                                <div class="flex gap-2">
                                    <button onclick="editImage(<?= htmlspecialchars(json_encode($image)) ?>)"
                                            class="flex-1 text-xs bg-gray-100 text-gray-700 py-1 px-2 rounded hover:bg-gray-200 transition">
                                        Editar
                                    </button>
                                    
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $image['id'] ?>">
                                        <button type="submit" class="w-full text-xs <?= $image['is_active'] ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?> py-1 px-2 rounded hover:opacity-80 transition">
                                            <?= $image['is_active'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="flex-1" onsubmit="return confirm('¿Eliminar esta imagen?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $image['id'] ?>">
                                        <button type="submit" class="w-full text-xs bg-red-100 text-red-700 py-1 px-2 rounded hover:bg-red-200 transition">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Imagen</h3>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nueva Imagen (opcional)</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                <input type="text" name="title" id="editTitle"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Texto Alternativo</label>
                <input type="text" name="alt_text" id="editAltText"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
                <input type="number" name="display_order" id="editOrder" min="1"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">
                    Guardar
                </button>
                <button type="button" onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 transition">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function editImage(image) {
        document.getElementById('editId').value = image.id;
        document.getElementById('editTitle').value = image.title || '';
        document.getElementById('editAltText').value = image.alt_text || '';
        document.getElementById('editOrder').value = image.display_order || 1;
        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').classList.add('flex');
    }
    
    function closeModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').classList.remove('flex');
    }
    
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
</script>
</body>
</html>
