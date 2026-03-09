<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PortalClientModel.php';

$error = '';
$success = '';
$email = '';
$client_id = $_SESSION['reset_portal_client_id'] ?? null;

$clientModel = new PortalClientModel();

if ($client_id) {
    $client = $clientModel->getById($client_id);
    if ($client) {
        $email = $client['email'];
    } else {
        $error = 'Cuenta no encontrada.';
    }
} else {
    $error = 'Acceso inválido. Primero solicitá el cambio de contraseña.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        // keep same minimum as registration
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        // use model update to handle hashing and timestamps
        $clientModel->update($client_id, ['password' => $password]);
        unset($_SESSION['reset_portal_client_id']);
        $success = 'Tu contraseña fue actualizada correctamente.';
    }
}

$pageTitle = 'Restablecer Contraseña - Portal';
include __DIR__ . '/../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 py-12 flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8">

        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Nueva Contraseña</h1>
            <?php if ($email): ?>
                <p class="text-green-600 font-medium mt-2">Bienvenido <?= htmlspecialchars($email) ?> 👋</p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-center">
                <?= htmlspecialchars($success) ?>
                <div class="mt-6">
                    <a href="portal_login.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-3 rounded-lg transition">
                        Volver al Login
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$success && !$error): ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                <p class="text-xs text-gray-500 mt-1">Mínimo 6 caracteres</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Nueva Contraseña</label>
                <input type="password" name="confirm_password" required minlength="6"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <button type="submit"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 rounded-lg transition">
                Actualizar Contraseña
            </button>
        </form>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
