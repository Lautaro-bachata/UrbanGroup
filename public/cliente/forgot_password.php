<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Por favor ingresá tu email.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                header("Location: reset_password.php");
                exit;
            } else {
                $error = 'No existe una cuenta con ese email.';
            }
        } catch (PDOException $e) {
            die("ERROR REAL: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Recuperar Contraseña';
include __DIR__ . '/../../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 py-12 flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Recuperar Contraseña</h1>
            <p class="text-gray-600 mt-2">Ingresa tu email para continuar</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required placeholder="tu@email.com"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition">
                Continuar
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="login.php" class="text-sm text-blue-600 hover:underline">Volver al inicio de sesión</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
