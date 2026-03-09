<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/PortalClientModel.php';
require_once __DIR__ . '/../../includes/EmailHelper.php';

$token = $_GET['token'] ?? '';
$message = '';
$error = '';

if (!$token) {
    $error = 'Token inválido.';
} else {
    $model = new PortalClientModel();
    $client = $model->getByVerificationToken($token);
    if (!$client) {
        $error = 'Token no válido o ya usado.';
    } else {
        // activate
        $model->activateById($client['id']);
        $message = 'Correo verificado correctamente. Ya puede iniciar sesión.';
        // send welcome and admin notifications
        $emailer = new EmailHelper();
        $emailer->sendWelcomeEmail($client['email'], $client['nombre_completo'], $client['registered_sections'] ?? '');
        $emailer->sendAdminNotification($client, $client['registered_sections'] ?? '');
    }
}

$pageTitle = 'Verificación de Correo';
include __DIR__ . '/../../templates/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12">
    <div class="max-w-md w-full px-4">
        <div class="bg-white p-8 rounded-lg shadow">
            <?php if ($error): ?>
                <div class="text-red-600 mb-4"><?= htmlspecialchars($error) ?></div>
                <a href="<?= BASE_URL ?>cliente/login.php" class="text-blue-600 hover:underline">Volver al inicio</a>
            <?php else: ?>
                <div class="text-green-600 mb-4"><?= htmlspecialchars($message) ?></div>
                <a href="<?= BASE_URL ?>cliente/login.php" class="text-blue-600 hover:underline">Ir a iniciar sesión</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php';
