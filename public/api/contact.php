<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$rawInput = file_get_contents('php://input');
$data = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

$name = trim($data['name'] ?? $_POST['name'] ?? '');
$email = trim($data['email'] ?? $_POST['email'] ?? '');
$phone = trim($data['phone'] ?? $_POST['phone'] ?? '');
$message = trim($data['message'] ?? $_POST['message'] ?? '');
$propertyId = (int)($data['property_id'] ?? $_POST['property_id'] ?? 0);
$propertyTitle = trim($data['property_title'] ?? $_POST['property_title'] ?? '');

if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Por favor complete todos los campos requeridos (nombre, email y mensaje)'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Por favor ingrese un email válido'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    $tableExists = false;
    try {
        if ($driver === 'sqlite') {
            $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='contact_inquiries'");
            $tableExists = $check->fetch() !== false;
        } elseif ($driver === 'pgsql') {
            $check = $db->query("SELECT to_regclass('public.contact_inquiries')");
            $result = $check->fetchColumn();
            $tableExists = !empty($result);
        } else {
            $check = $db->query("SHOW TABLES LIKE 'contact_inquiries'");
            $tableExists = $check->rowCount() > 0;
        }
    } catch (Exception $e) {
        $tableExists = false;
    }
    
    if (!$tableExists) {
        if ($driver === 'sqlite') {
            $db->exec("
                CREATE TABLE IF NOT EXISTS contact_inquiries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT,
                    message TEXT NOT NULL,
                    property_id INTEGER,
                    property_title TEXT,
                    status TEXT DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } elseif ($driver === 'pgsql') {
            $db->exec("
                CREATE TABLE IF NOT EXISTS contact_inquiries (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone VARCHAR(50),
                    message TEXT NOT NULL,
                    property_id INTEGER,
                    property_title VARCHAR(500),
                    status VARCHAR(50) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } else {
            $db->exec("
                CREATE TABLE IF NOT EXISTS contact_inquiries (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone VARCHAR(50),
                    message TEXT NOT NULL,
                    property_id INT,
                    property_title VARCHAR(500),
                    status VARCHAR(50) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO contact_inquiries (
            name, email, phone, message, property_id, property_title, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $result = $stmt->execute([
        $name,
        $email,
        $phone,
        $message,
        $propertyId > 0 ? $propertyId : null,
        $propertyTitle
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Gracias por contactarnos. Le responderemos pronto.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error al guardar el mensaje. Por favor intente nuevamente.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Contact API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor. Por favor intente nuevamente.'
    ]);
}
