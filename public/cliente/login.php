<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/PortalClientModel.php';
require_once __DIR__ . '/../../includes/UserModel.php';
require_once __DIR__ . '/../../includes/EmailHelper.php';

$clientModel = new PortalClientModel();
$error = '';
$success = '';

if (isset($_SESSION['portal_client_id'])) {
    header('Location: favoritos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    if ($action === 'login') {
        $identifier = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Por favor complete todos los campos';
        } else {
            // First, try to authenticate as a user (admin/partner)
            $userModel = new UserModel();
            $user = $userModel->authenticate($identifier, $password);

            if ($user) {
                // Admin/Partner login successful
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                if ($user['role'] === 'partner') {
                    $redirect_url = BASE_URL . 'partner/index.php';
                } else {
                    // Admin redirects to admin dashboard
                    $redirect_url = BASE_URL . 'admin/index.php';
                }
                header('Location: ' . $redirect_url);
                exit;
            }

            // If not a user, try as a portal client
            $client = $clientModel->authenticate($identifier, $password);
            if ($client) {
                // Client login successful
                session_regenerate_id(true);
                $_SESSION['portal_client_id'] = $client['id'];
                $_SESSION['portal_client_name'] = $client['nombre_completo'];
                $_SESSION['portal_client_email'] = $client['email'];

                $redirect = $_GET['redirect'] ?? 'favoritos.php';
                $redirect_url = BASE_URL . 'cliente/' . ltrim($redirect, '/');
                header('Location: ' . $redirect_url);
                exit;
            }

            // If both fail we want to give more specific message
            $clientLookup = $clientModel->getByEmail($identifier);
            if ($clientLookup && $clientLookup['status'] !== 'active') {
                $error = 'Por favor verifica tu correo antes de iniciar sesión (revisa tu bandeja de entrada).';
            } else {
                $error = 'Credenciales incorrectas o la cuenta está inactiva.';
            }
        }
    
    } elseif ($action === 'register') {
        $data = [
            'razon_social' => sanitizeInput($_POST['razon_social'] ?? ''),
            'rut' => sanitizeInput($_POST['rut'] ?? ''),
            'representante_legal' => sanitizeInput($_POST['representante_legal'] ?? ''),
            'nombre_completo' => sanitizeInput($_POST['nombre_completo'] ?? ''),
            'cedula_identidad' => sanitizeInput($_POST['cedula_identidad'] ?? ''),
            'celular' => sanitizeInput($_POST['celular'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'alias' => sanitizeInput($_POST['alias'] ?? '')
        ];
        
        if (empty($data['nombre_completo']) || empty($data['email']) || empty($data['password'])) {
            $error = 'Por favor complete los campos obligatorios';
        } elseif (strlen($data['password']) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            $result = $clientModel->create($data);
            if (is_array($result) && isset($result['error'])) {
                $error = $result['error'];
            } else {
                // model returns ['id'=>..., 'token'=>...] when successful
                if (is_array($result) && isset($result['token'])) {
                    // send verification email
                    $emailer = new EmailHelper();
                    $sent = $emailer->sendVerificationEmail($data['email'], $data['nombre_completo'], $result['token']);
                    if (!$sent) {
                        // log but continue
                        error_log("No se pudo enviar correo de verificación a {$data['email']}");
                    }
                }
                $success = 'Registro completado. Revisa tu correo y sigue el enlace para verificar tu cuenta antes de iniciar sesión.';
            }
        }
    }
}

$pageTitle = 'Acceso Clientes';
$currentPage = 'login';
include __DIR__ . '/../../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-md mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Acceso Propiedades</h1>
                <p class="text-gray-600 mt-2">Inicia sesión o crea una cuenta para guardar tus favoritos</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="mb-6">
                <div class="flex border-b border-gray-200">
                    <button onclick="showTab('login')" id="tabLogin" class="flex-1 py-3 text-center font-medium border-b-2 border-blue-600 text-blue-600">Iniciar Sesión</button>
                    <button onclick="showTab('register')" id="tabRegister" class="flex-1 py-3 text-center font-medium text-gray-500 hover:text-gray-700">Registrarse</button>
                </div>
            </div>

            <div id="formLogin">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email o Usuario</label>
                        <input type="text" name="email" required placeholder="Email o usuario" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                        <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition">
                        Iniciar Sesión
                    </button>

                    <div class="text-center mt-3">
                        <!-- Link to forgot password page -->
                        <a href="<?= htmlspecialchars(BASE_URL . 'cliente/forgot_password.php') ?>" class="text-sm text-blue-600 hover:underline">¿Olvidaste tu contraseña?</a>
                    </div>
                </form>
            </div>

            <div id="formRegister" class="hidden">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="register">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo *</label>
                        <input type="text" name="nombre_completo" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Celular</label>
                        <input type="tel" name="celular" placeholder="+56 xxx-xxx-xxx" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">RUT</label>
                        <input type="text" name="rut" placeholder="12.345.678-9" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña *</label>
                        <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Mínimo 6 caracteres</p>
                    </div>
                    
                    <input type="hidden" name="razon_social" value="">
                    <input type="hidden" name="representante_legal" value="">
                    <input type="hidden" name="cedula_identidad" value="">
                    <input type="hidden" name="alias" value="">
                    
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 rounded-lg transition">
                        Crear Cuenta
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tab) {
    const loginTab = document.getElementById('tabLogin');
    const registerTab = document.getElementById('tabRegister');
    const loginForm = document.getElementById('formLogin');
    const registerForm = document.getElementById('formRegister');
    
    if (tab === 'login') {
        loginTab.classList.add('border-b-2', 'border-blue-600', 'text-blue-600');
        loginTab.classList.remove('text-gray-500');
        registerTab.classList.remove('border-b-2', 'border-blue-600', 'text-blue-600');
        registerTab.classList.add('text-gray-500');
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
    } else {
        registerTab.classList.add('border-b-2', 'border-blue-600', 'text-blue-600');
        registerTab.classList.remove('text-gray-500');
        loginTab.classList.remove('border-b-2', 'border-blue-600', 'text-blue-600');
        loginTab.classList.add('text-gray-500');
        registerForm.classList.remove('hidden');
        loginForm.classList.add('hidden');
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>