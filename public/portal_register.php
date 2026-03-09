<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/contact_settings.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/PortalClientModel.php';
require_once __DIR__ . '/../includes/EmailHelper.php';

$portalClientModel = new PortalClientModel();
$section = $_GET['section'] ?? 'terrenos';
$error = '';
$warning = '';
$success = '';

$sectionTitles = [
    'terrenos' => 'Terrenos Inmobiliarios',
    'activos' => 'Activos Inmobiliarios',
    'usa' => 'Propiedades USA'
];

$sectionTitle = $sectionTitles[$section] ?? 'Portal de Propiedades';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($section === 'usa') {
            $required = ['nombre_completo', 'cedula_identidad', 'celular', 'email', 'password', 'consent'];
        } else {
            $required = ['razon_social', 'rut', 'nombre_completo', 
                     'cedula_identidad', 'celular', 'email', 'password', 'consent'];
        }
    $missing = [];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }
    
    // Anti-bot honeypot check
    if (!empty($_POST['website'])) {
        exit; // silently fail bot
    }
    
    if (!empty($missing)) {
        $error = 'Todos los campos obligatorios deben ser completados';
    } else {
        $rutRaw = trim($_POST['rut'] ?? '');
        // RUT validation is CRITICAL - must be error, not warning
        if ($section !== 'usa' && !empty($rutRaw) && !PortalClientModel::validateRut($rutRaw)) {
            $error = 'El RUT ingresado no es válido. Por favor verifica el dígito verificador.';
        }
    }
    if (empty($error) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'El email ingresado no es válido';
    } elseif (strlen($_POST['password']) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($_POST['password'] !== $_POST['password_confirm']) {
        $error = 'Las contraseñas no coinciden';
    } elseif (empty($error) && $portalClientModel->getByEmail(trim($_POST['email']))) {
        $error = 'Este email ya está registrado. Por favor usa otro email o inicia sesión.';
    } else {
        try {
            // Sanitize and clean RUT (remove all non-alphanumeric except dash)
            $rutClean = preg_replace('/[^0-9kK-]/', '', $rutRaw);
            
            $result = $portalClientModel->create([
            'razon_social' => trim($_POST['razon_social'] ?? ''),
            'rut' => $rutClean,
            'representante_legal' => '',
            'alias' => '',
                'registered_sections' => $section,
            'nombre_completo' => trim($_POST['nombre_completo']),
            'cedula_identidad' => trim($_POST['cedula_identidad']),
            'celular' => trim($_POST['celular']),
            'email' => trim($_POST['email']),
            'password' => $_POST['password']
        ]);
        } catch (Exception $e) {
            $result = ['error' => 'Error al crear el registro: ' . $e->getMessage()];
        }

        if (is_array($result) && isset($result['error'])) {
            $error = $result['error'];
        } else {
            // $result may contain ['id'=>..., 'token'=>...]
            $success = 'Registro exitoso. Revise su correo electrónico y haga clic en el enlace de verificación.';
            if (is_array($result) && isset($result['token'])) {
                $emailer = new EmailHelper();
                $emailer->sendVerificationEmail(trim($_POST['email']), trim($_POST['nombre_completo']), $result['token']);
            }
            // admin notification and welcome will be handled after verification by verify_email.php
        }
    }
}

$pageTitle = 'Registro - ' . $sectionTitle;
$currentPage = 'portal-register';
include __DIR__ . '/../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-2xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Registro de Cliente</h1>
                <p class="text-gray-600"><?= htmlspecialchars($sectionTitle) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($warning)): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($warning) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <?= htmlspecialchars($success) ?>
                    <a href="portal_login.php?section=<?= $section ?>" class="underline font-medium">Iniciar Sesión</a>
                </div>
            <?php else: ?>

            <form method="POST" class="space-y-6">
                <!-- Honeypot anti-bot field (hidden from users) -->
                <input type="text" name="website" style="display:none; position:absolute; left:-9999px;" tabindex="-1" autocomplete="off">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if ($section !== 'usa'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Razón Social *</label>
                        <input type="text" name="razon_social" value="<?= htmlspecialchars($_POST['razon_social'] ?? '') ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">RUT * <span id="rutValidStatus" class="text-xs"></span></label>
                        <input type="text" id="rutInput" name="rut" value="<?= htmlspecialchars($_POST['rut'] ?? '') ?>" 
                               placeholder="77.471.725-1"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <p id="rutError" class="text-xs text-red-600 mt-1 hidden">RUT inválido</p>
                        <p id="rutSuccess" class="text-xs text-green-600 mt-1 hidden">RUT válido ✓</p>
                    </div>
                    <?php else: ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Razón Social <span class="text-gray-400">(opcional)</span></label>
                        <input type="text" name="razon_social" value="<?= htmlspecialchars($_POST['razon_social'] ?? '') ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">RUT <span class="text-gray-400">(opcional)</span></label>
                        <input type="text" name="rut" value="<?= htmlspecialchars($_POST['rut'] ?? '') ?>" 
                               placeholder="12.345.678-9"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo *</label>
                        <input type="text" name="nombre_completo" value="<?= htmlspecialchars($_POST['nombre_completo'] ?? '') ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cédula de Identidad * <span id="cedulaValidStatus" class="text-xs"></span></label>
                        <input type="text" id="cedulaInput" name="cedula_identidad" value="<?= htmlspecialchars($_POST['cedula_identidad'] ?? '') ?>" 
                               placeholder="12345678"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <p id="cedulaError" class="text-xs text-red-600 mt-1 hidden">Cédula inválida (mín. 7 dígitos)</p>
                        <p id="cedulaSuccess" class="text-xs text-green-600 mt-1 hidden">Cédula válida ✓</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Celular *</label>
                        <input type="tel" name="celular" value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>" 
                               placeholder="Ej: <?= ADMIN_PHONE ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña *</label>
                        <input type="password" name="password" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <p class="text-xs text-gray-500 mt-1">Mínimo 6 caracteres</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contraseña *</label>
                        <input type="password" name="password_confirm" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                    <div class="flex items-start">
                        <input type="checkbox" name="consent" id="consent" value="1" 
                               class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" required>
                        <label for="consent" class="ml-3 text-sm text-gray-700">
                            <span class="font-semibold">ACEPTO LOS TÉRMINOS Y CONDICIONES:</span>
                            <p class="mt-2 text-justify leading-relaxed">
                                AL REGISTRARME EN ESTE PORTAL DE PROPIEDADES, ACEPTO QUE EN CASO DE EFECTUAR UNA O MÁS COMPRAVENTAS, PAGARÉ A URBAN GROUP SPA LA COMISIÓN ESTABLECIDA DEL <strong>2,0% MÁS IVA</strong> SOBRE EL VALOR FINAL DE CADA COMPRAVENTA. POR ELLO, SE INCLUIRÁ EN CADA PROMESA DE COMPRAVENTA, UNA CLÁUSULA DE COMISIONES QUE REGULARÁ LA FORMA DE PAGO DE LAS COMISIONES.
                            </p>
                            <p class="mt-3 text-justify leading-relaxed text-gray-600">
                                La información contenida en este portal de propiedades es confidencial, de carácter privado y es proporcionada únicamente al cliente registrado, por lo que se prohíbe absolutamente el uso no autorizado, la divulgación parcial o total de su contenido y/o de las imágenes.
                            </p>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <button type="submit" id="submitBtn"
                            class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Registrarme
                    </button>
                    <a href="portal_login.php?section=<?= $section ?>" 
                       class="flex-1 text-center border border-gray-300 text-gray-700 py-3 px-6 rounded-lg font-medium hover:bg-gray-50 transition">
                        Ya tengo cuenta
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- rut.js library for RUT formatting -->
<script src="https://cdn.jsdelivr.net/npm/rut.js/dist/rut.min.js"></script>
<script>
// Manual RUT validation function (same logic as PHP backend)
function validateRutManual(rut) {
    const clean = rut.replace(/[^0-9kK]/gi, '').toUpperCase();
    if (clean.length < 2) return false;
    
    const body = clean.substring(0, clean.length - 1);
    const dv = clean.substring(clean.length - 1).toUpperCase();
    
    let sum = 0;
    let multiplier = 2;
    
    for (let i = body.length - 1; i >= 0; i--) {
        sum += parseInt(body[i], 10) * multiplier;
        multiplier = multiplier === 7 ? 2 : multiplier + 1;
    }
    
    let expectedDv = 11 - (sum % 11);
    if (expectedDv === 11) {
        expectedDv = '0';
    } else if (expectedDv === 10) {
        expectedDv = 'K';
    } else {
        expectedDv = expectedDv.toString();
    }
    
    return dv === expectedDv;
}

// Manual Cédula validation function (numeric only, min 7 digits)
function validateCedulaManual(cedula) {
    const clean = cedula.replace(/[^0-9]/g, '');
    return clean.length >= 7 && clean.length <= 12;
}

(function() {
    const rutInput = document.getElementById('rutInput');
    const rutError = document.getElementById('rutError');
    const rutSuccess = document.getElementById('rutSuccess');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.querySelector('form');
    const section = '<?= $section ?>';

    // Check if we need RUT validation (not USA section)
    const needsRutValidation = section !== 'usa' && rutInput;

    // Handle Cédula validation
    const cedulaInput = document.getElementById('cedulaInput');
    const cedulaError = document.getElementById('cedulaError');
    const cedulaSuccess = document.getElementById('cedulaSuccess');
    const needsCedulaValidation = cedulaInput !== null;

    if (needsCedulaValidation) {
        cedulaInput.addEventListener('input', function() {
            const value = this.value.trim();
            cedulaError.classList.add('hidden');
            cedulaSuccess.classList.add('hidden');

            if (!value) return;

            const isValid = validateCedulaManual(value);
            if (isValid) {
                cedulaSuccess.classList.remove('hidden');
                this.classList.remove('border-red-500');
                this.classList.add('border-green-500');
            } else {
                cedulaError.classList.remove('hidden');
                this.classList.remove('border-green-500');
                this.classList.add('border-red-500');
            }
        });
    }

    if (needsRutValidation) {
        // Initially disable submit button if field is empty
        if (!rutInput.value.trim()) {
            submitBtn.disabled = true;
        }

        // Real-time validation
        rutInput.addEventListener('input', function() {
            const value = this.value.trim();

            rutError.classList.add('hidden');
            rutSuccess.classList.add('hidden');

            if (!value) {
                submitBtn.disabled = true;
                return;
            }

            // Use manual validation function
            const isValid = validateRutManual(value);
            if (isValid) {
                rutSuccess.classList.remove('hidden');
                submitBtn.disabled = false;
                this.classList.remove('border-red-500');
                this.classList.add('border-green-500');
            } else {
                rutError.classList.remove('hidden');
                submitBtn.disabled = true;
                this.classList.remove('border-green-500');
                this.classList.add('border-red-500');
            }
        });

        // Also validate on form submit
        form.addEventListener('submit', function(e) {
            const value = rutInput.value.trim();
            if (!validateRutManual(value)) {
                e.preventDefault();
                rutError.classList.remove('hidden');
                rutInput.focus();
            }
        });

        // Format RUT as user types using rut.js if available
        rutInput.addEventListener('change', function() {
            const value = this.value.trim();
            try {
                if (typeof rut !== 'undefined' && rut.format) {
                    const formatted = rut.format(value);
                    this.value = formatted;
                }
            } catch (e) {
                // If format fails, keep original value
            }
        });
    }
})();
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>