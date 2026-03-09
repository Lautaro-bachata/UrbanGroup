<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PortalClientModel.php';

$portalClientModel = new PortalClientModel();
$section = $_GET['section'] ?? 'terrenos';
$error = '';

$sectionTitles = [
    'terrenos' => 'Terrenos Inmobiliarios',
    'activos' => 'Activos Inmobiliarios',
    'usa' => 'Propiedades USA'
];

$sectionRedirects = [
    'terrenos' => 'terrenos.php',
    'activos' => 'activos.php',
    'usa' => 'usa.php'
];

$sectionTitle = $sectionTitles[$section] ?? 'Portal de Propiedades';

if (isset($_SESSION['portal_client_id'])) {
    header('Location: ' . ($sectionRedirects[$section] ?? 'terrenos.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $client = $portalClientModel->authenticate($email, $password);
        
        if ($client) {
            session_regenerate_id(true);
            $_SESSION['portal_client_id'] = $client['id'];
            $_SESSION['portal_client_name'] = $client['nombre_completo'];
            $_SESSION['portal_client_email'] = $client['email'];
            $_SESSION['portal_client'] = [
                'id' => $client['id'],
                'email' => $client['email'],
                'nombre_completo' => $client['nombre_completo'],
                'alias' => $client['alias'],
                'razon_social' => $client['razon_social']
            ];
            
            header('Location: ' . ($sectionRedirects[$section] ?? 'terrenos.php'));
            exit;
        } else {
            $lookup = $portalClientModel->getByEmail($email);
            if ($lookup && isset($lookup['status']) && $lookup['status'] === 'inactive') {
                $error = 'La cuenta está inactiva. Contacte al administrador.';
            } else {
                $error = 'Email o contraseña incorrectos';
            }
        }
    }
}

$pageTitle = 'Iniciar Sesión - ' . $sectionTitle;
$currentPage = 'portal-login';
include __DIR__ . '/../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-md mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Acceso Exclusivo</h1>
                <p class="text-gray-600"><?= htmlspecialchars($sectionTitle) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="su@email.com" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                    <input type="password" name="password" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="••••••••" required>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 transition">
                    Iniciar Sesión
                </button>

                <div class="text-center mt-3">
                    <a href="portal_forgot_password.php" class="text-sm text-blue-600 hover:underline">¿Olvidaste tu contraseña?</a>
                </div>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    ¿No tiene cuenta? 
                    <a href="portal_register.php?section=<?= $section ?>" class="text-blue-600 hover:underline font-medium">
                        Registrarse
                    </a>
                </p>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-200">
                <p style="font-size: 18px;" class="text-xs text-gray-500 text-center leading-relaxed">
                    Este portal es de acceso exclusivo para clientes registrados. 
                    La información contenida es confidencial y de carácter privado.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
