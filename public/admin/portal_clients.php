<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/PortalClientModel.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$portalClientModel = new PortalClientModel();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'toggle':
            $id = (int)$_POST['id'];
            $portalClientModel->toggleStatus($id);
            $message = 'Estado del cliente actualizado';
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            $portalClientModel->delete($id);
            $message = 'Cliente eliminado correctamente';
            break;
    }
}

$clients = $portalClientModel->getAll();

$clientsBySection = [
    'all' => $clients,
    'normal' => [],
    'terrenos' => [],
    'activos' => [],
    'usa' => []
];

foreach ($clients as $c) {
    $regs = isset($c['registered_sections']) ? trim($c['registered_sections']) : '';
    $hasCompanyData = !empty($c['razon_social']);
    
    if ($hasCompanyData) {
        $clientsBySection['terrenos'][] = $c;
        $clientsBySection['activos'][] = $c;
        $clientsBySection['usa'][] = $c;
    } else {
        if (empty($regs)) {
            $clientsBySection['normal'][] = $c;
        } else {
            $parts = array_map('trim', explode(',', $regs));
            $validSections = ['terrenos', 'activos', 'usa'];
            $foundSection = false;
            
            foreach ($parts as $section) {
                if (!empty($section) && in_array($section, $validSections)) {
                    $clientsBySection[$section][] = $c;
                    $foundSection = true;
                }
            }
            
            if (!$foundSection) {
                $clientsBySection['normal'][] = $c;
            }
        }
    }
}

$tab = $_GET['tab'] ?? 'all';
if (!array_key_exists($tab, $clientsBySection)) $tab = 'all';
$displayClients = $clientsBySection[$tab];

$pageTitle = 'Clientes del Portal';
$currentPage = 'portal_clients';
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
            <a href="carousel.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Carousel Inicio</span>
            </a>
            <a href="portal_clients.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-blue-600 text-white transition text-sm">
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
        <a href="carousel.php" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Carousel</a>
        <a href="portal_clients.php" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-blue-600 text-white">Clientes</a>
        <a href="index.php?action=locations" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Ubicaciones</a>
    </div>

    <main class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900"><?= $pageTitle ?></h1>
            <span class="text-sm text-gray-500"><?= count($clients) ?> cliente(s) registrado(s)</span>
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

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-4 border-b">
                <nav class="flex gap-2 flex-wrap">
                    <a href="?tab=all" class="px-3 py-1 rounded-lg text-sm <?= $tab==='all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">Todos (<?= count($clientsBySection['all']) ?>)</a>
                    <a href="?tab=normal" class="px-3 py-1 rounded-lg text-sm <?= $tab==='normal' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">Normales (<?= count($clientsBySection['normal']) ?>)</a>
                    <a href="?tab=terrenos" class="px-3 py-1 rounded-lg text-sm <?= $tab==='terrenos' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">Terrenos (<?= count($clientsBySection['terrenos']) ?>)</a>
                    <a href="?tab=activos" class="px-3 py-1 rounded-lg text-sm <?= $tab==='activos' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">Activos (<?= count($clientsBySection['activos']) ?>)</a>
                    <a href="?tab=usa" class="px-3 py-1 rounded-lg text-sm <?= $tab==='usa' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">USA (<?= count($clientsBySection['usa']) ?>)</a>
                </nav>
            </div>
            <?php if (empty($clients)): ?>
                <div class="p-12 text-center">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay clientes registrados</h3>
                    <p class="text-gray-500">Los clientes del portal aparecerán aquí cuando se registren</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Cliente</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">RUT</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Empresa</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Contacto</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Secciones</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Registro</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($displayClients as $client): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4">
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($client['nombre_completo']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($client['alias']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        <?= htmlspecialchars($client['rut']) ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($client['razon_social']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($client['representante_legal']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm">
                                            <p class="text-gray-900"><?= htmlspecialchars($client['email']) ?></p>
                                            <p class="text-gray-500"><?= htmlspecialchars($client['celular']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php 
                                            $sections = isset($client['registered_sections']) ? trim($client['registered_sections']) : '';
                                            if (empty($sections)) {
                                                echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Normal</span>';
                                            } else {
                                                $sectionMap = ['terrenos' => 'Terrenos', 'activos' => 'Activos', 'usa' => 'USA'];
                                                $parts = array_map('trim', explode(',', $sections));
                                                foreach ($parts as $section) {
                                                    if (!empty($section) && isset($sectionMap[$section])) {
                                                        $colorMap = [
                                                            'terrenos' => 'bg-blue-100 text-blue-700',
                                                            'activos' => 'bg-purple-100 text-purple-700',
                                                            'usa' => 'bg-green-100 text-green-700'
                                                        ];
                                                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . $colorMap[$section] . ' mr-1">' . $sectionMap[$section] . '</span>';
                                                    }
                                                }
                                            }
                                        ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $client['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                            <?= $client['status'] === 'active' ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($client['created_at'])) ?>
                                        <?php if ($client['last_login_at']): ?>
                                            <p class="text-xs text-gray-400">Último: <?= date('d/m/Y H:i', strtotime($client['last_login_at'])) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex gap-2">
                                            <a href="client_stats.php?id=<?= $client['id'] ?>" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 transition">
                                                Ver Stats
                                            </a>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $client['id'] ?>">
                                                <button type="submit" class="text-xs <?= $client['status'] === 'active' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?> px-2 py-1 rounded hover:opacity-80 transition">
                                                    <?= $client['status'] === 'active' ? 'Desactivar' : 'Activar' ?>
                                                </button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('¿Eliminar este cliente?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $client['id'] ?>">
                                                <button type="submit" class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200 transition">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-medium text-blue-900 mb-2">Información sobre Clientes del Portal</h3>
            <p class="text-sm text-blue-700">
                Los clientes del portal son usuarios que se registran para acceder a las secciones exclusivas: 
                <strong>Terrenos Inmobiliarios</strong>, <strong>Activos Inmobiliarios</strong> y <strong>Propiedades USA</strong>.
                Al registrarse, aceptan la comisión del 2% + IVA en caso de compraventa.
            </p>
        </div>
    </main>
</div>
</body>
</html>
