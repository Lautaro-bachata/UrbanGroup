<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/contact_settings.php';
require_once __DIR__ . '/../../../includes/FavoriteModel.php';
require_once __DIR__ . '/../../../includes/PortalClientModel.php';
require_once __DIR__ . '/../../../includes/PropertyModel.php';

function generateOrdenVisitaHTML($client, $favorites, $fecha) {
    $razonSocial = htmlspecialchars($client['razon_social'] ?? $client['nombre_completo'] ?? '-');
    $rut = htmlspecialchars($client['rut'] ?? '-');
    $representante = htmlspecialchars($client['representante_legal'] ?? '-');
    $nombreCompleto = htmlspecialchars($client['nombre_completo'] ?? '-');
    $email = htmlspecialchars($client['email'] ?? '-');
    $celular = htmlspecialchars($client['celular'] ?? '-');
    $cargo = htmlspecialchars($client['cargo'] ?? '-');
    $domicilio = htmlspecialchars($client['domicilio'] ?? '-');
    
    $propiedadesHtml = '';
    $i = 1;
    foreach ($favorites as $prop) {
        $propiedadesHtml .= '<tr>';
        $propiedadesHtml .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $i . '</td>';
        $propiedadesHtml .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($prop['title'] ?? '-') . '</td>';
        $propiedadesHtml .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($prop['comuna_name'] ?? '-') . '</td>';
        $propiedadesHtml .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">$' . number_format($prop['price'] ?? 0, 0, ',', '.') . '</td>';
        $propiedadesHtml .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . number_format($prop['total_area'] ?? 0, 0, ',', '.') . ' m2</td>';
        $propiedadesHtml .= '</tr>';
        $i++;
    }
    
    $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Visita - Urban Group</title>
    <style>
        @page { margin: 2cm; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { color: #1e40af; margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        .section { margin-bottom: 20px; }
        .section-title { background: #1e40af; color: white; padding: 8px 15px; font-size: 14px; font-weight: bold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 6px 10px; border-bottom: 1px solid #eee; }
        .info-table td:first-child { font-weight: bold; width: 35%; background: #f9f9f9; }
        .props-table th { background: #1e40af; color: white; padding: 10px; text-align: left; }
        .props-table td { padding: 8px 10px; border: 1px solid #ddd; }
        .terms { background: #fffbeb; border: 1px solid #f59e0b; padding: 15px; margin: 20px 0; font-size: 11px; }
        .signature-section { margin-top: 40px; page-break-inside: avoid; }
        .signature-box { border-top: 1px solid #333; width: 250px; margin-top: 60px; text-align: center; padding-top: 5px; }
        .footer { text-align: center; font-size: 10px; color: #666; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>URBAN GROUP SpA</h1>
        <p>RUT: 76.192.802-3</p>
        <p>Portal de Terrenos Inmobiliarios</p>
    </div>
    
    <h2 style="text-align: center; color: #1e40af;">ORDEN DE VISITA INMOBILIARIA</h2>
    <p style="text-align: right;"><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($fecha)) . '</p>
    
    <div class="section">
        <div class="section-title">DATOS DEL CLIENTE</div>
        <table class="info-table">
            <tr><td>Razon Social:</td><td>' . $razonSocial . '</td></tr>
            <tr><td>RUT:</td><td>' . $rut . '</td></tr>
            <tr><td>Representante Legal:</td><td>' . $representante . '</td></tr>
            <tr><td>Nombre Completo:</td><td>' . $nombreCompleto . '</td></tr>
            <tr><td>Cargo:</td><td>' . $cargo . '</td></tr>
            <tr><td>Email:</td><td>' . $email . '</td></tr>
            <tr><td>Celular:</td><td>' . $celular . '</td></tr>
            <tr><td>Domicilio:</td><td>' . $domicilio . '</td></tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">PROPIEDADES SELECCIONADAS (' . count($favorites) . ')</div>
        <table class="props-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Propiedad</th>
                    <th>Comuna</th>
                    <th style="width: 120px;">Precio</th>
                    <th style="width: 100px;">Superficie</th>
                </tr>
            </thead>
            <tbody>
                ' . $propiedadesHtml . '
            </tbody>
        </table>
    </div>
    
    <div style="background: #dcfce7; border: 2px solid #16a34a; padding: 15px; margin: 20px 0; border-radius: 8px; text-align: center;">
        <div style="display: inline-block; background: #16a34a; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; margin-bottom: 10px;">
            ✓ TÉRMINOS ACEPTADOS ELECTRÓNICAMENTE
        </div>
        <p style="margin: 5px 0; font-size: 12px; color: #166534;">El cliente aceptó los términos y condiciones del contrato de forma electrónica el ' . date('d/m/Y \a \l\a\s H:i') . ' hrs.</p>
    </div>
    
    <div class="terms">
        <h3 style="margin-top: 0; color: #92400e;">TERMINOS Y CONDICIONES</h3>
        <p>El suscrito, en adelante "El Cliente", mediante la presente Orden de Visita, autoriza a <strong>URBAN GROUP SpA, RUT 76.192.802-3</strong>, en adelante "El Corredor", para que realice las gestiones de intermediacion conducentes a la compraventa de los inmuebles arriba indicados.</p>
        
        <p><strong>PRIMERO:</strong> El Cliente declara que ha tomado conocimiento de las propiedades listadas en este documento a traves del Portal de Terrenos Inmobiliarios de Urban Group.</p>
        
        <p><strong>SEGUNDO:</strong> El Cliente se compromete a pagar a El Corredor una comision equivalente al <strong>2,0% + IVA</strong> sobre el precio de venta final de cada propiedad que efectivamente adquiera, la cual se pagara al momento de la firma de la escritura publica de compraventa.</p>
        
        <p><strong>TERCERO:</strong> El Cliente declara que la informacion contenida en este portal es confidencial y de caracter privado, prohibiendose la divulgacion parcial o total de su contenido y/o de las imagenes.</p>
        
        <p><strong>CUARTO:</strong> Esta orden tendra una vigencia de 12 meses desde la fecha de aceptacion.</p>
    </div>
    
    <div class="signature-section">
        <p><strong>ACEPTACION:</strong> El Cliente declara haber leido, comprendido y aceptado todos los terminos y condiciones de esta Orden de Visita mediante aceptacion electronica en el Portal de Urban Group.</p>
        
        <table style="width: 100%; margin-top: 30px;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="signature-box">
                        <p style="margin: 0;">' . $nombreCompleto . '</p>
                        <p style="margin: 0; font-size: 10px;">El Cliente</p>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="signature-box">
                        <p style="margin: 0;">' . COMPANY_NAME . '</p>
                        <p style="margin: 0; font-size: 10px;">El Corredor</p>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="footer">
        <p>Documento generado automaticamente el ' . date('d/m/Y H:i') . ' - ' . COMPANY_NAME . '</p>
    </div>
</body>
</html>';
    
    return $html;
}

if (!isset($_SESSION['portal_client'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_POST['action'] ?? $input['action'] ?? '';

if ($action === 'send_orden_visita' || (!empty($input['property_ids']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    try {
        $clientId = $_SESSION['portal_client']['id'];
        
        $propertyIds = [];
        if (isset($_POST['property_ids'])) {
            $propertyIds = explode(',', $_POST['property_ids']);
        } elseif (isset($input['property_ids']) && is_array($input['property_ids'])) {
            $propertyIds = $input['property_ids'];
        }
        $propertyIds = array_filter(array_map('intval', $propertyIds));
        
        $favoriteModel = new FavoriteModel();
        $portalClientModel = new PortalClientModel();
        $propertyModel = new PropertyModel();
        
        $client = $portalClientModel->getById($clientId);
        
        $properties = [];
        if (!empty($propertyIds)) {
            foreach ($propertyIds as $propId) {
                $prop = $propertyModel->getById($propId);
                if ($prop) {
                    $properties[] = $prop;
                }
            }
        } else {
            $allFavorites = $favoriteModel->getClientFavorites($clientId);
            $properties = $allFavorites;
        }
        
        if (empty($properties)) {
            echo json_encode(['success' => false, 'error' => 'No hay propiedades seleccionadas']);
            exit;
        }
        
        if (!$client) {
            echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
            exit;
        }
        
        $razonSocial = $client['razon_social'] ?? $client['nombre_completo'];
        $fecha = date('Y-m-d');
        $nombreArchivo = "T_Inmo_Seleccion_" . preg_replace('/[^a-zA-Z0-9]/', '_', $razonSocial) . "_" . $fecha . ".html";
        
        $ordenHtml = generateOrdenVisitaHTML($client, $properties, $fecha);
        
        $uploadDir = __DIR__ . '/../../../uploads/ordenes_visita/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filePath = $uploadDir . $nombreArchivo;
        file_put_contents($filePath, $ordenHtml);
        
        $to = ADMIN_EMAIL;
        $subject = 'Nueva Orden de Visita - ' . $razonSocial;
        
        $boundary = md5(time());
        
        $headers = "From: " . NOREPLY_EMAIL . "\r\n";
        $headers .= "Reply-To: " . ($client['email'] ?? NOREPLY_EMAIL) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";
        
        $propiedadesResumen = '';
        foreach ($properties as $prop) {
            $propiedadesResumen .= '- ' . ($prop['title'] ?? 'Sin titulo') . ' (' . ($prop['comuna_name'] ?? '-') . ")\n";
        }
        
        $mensaje = "Nueva Orden de Visita recibida:\n\n";
        $mensaje .= "Cliente: " . ($client['razon_social'] ?? $client['nombre_completo']) . "\n";
        $mensaje .= "RUT: " . ($client['rut'] ?? '-') . "\n";
        $mensaje .= "Email: " . ($client['email'] ?? '-') . "\n";
        $mensaje .= "Celular: " . ($client['celular'] ?? '-') . "\n\n";
        $mensaje .= "Propiedades seleccionadas (" . count($properties) . "):\n";
        $mensaje .= $propiedadesResumen . "\n";
        $mensaje .= "Ver documento adjunto para detalles completos.\n";
        
        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $mensaje . "\r\n";
        
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8; name=\"" . $nombreArchivo . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . $nombreArchivo . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode($ordenHtml)) . "\r\n";
        
        $body .= "--" . $boundary . "--";
        
        $mailSent = @mail($to, $subject, $body, $headers);
        $mailError = error_get_last();
        
        $logDir = __DIR__ . '/../../../uploads/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'orden_visita_' . date('Y-m') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " | Client: " . $clientId . " | File: " . $nombreArchivo;
        
        if (!$mailSent) {
            $logEntry .= " | Status: EMAIL_FAILED | Error: " . ($mailError['message'] ?? 'Unknown');
            file_put_contents($logFile, $logEntry . "\n", FILE_APPEND);
            
            echo json_encode([
                'success' => true,
                'message' => 'Orden de visita guardada. El documento ha sido generado.',
                'properties_count' => count($properties),
                'file' => $nombreArchivo,
                'mail_warning' => 'El servidor de correo no esta disponible, pero el documento fue guardado.'
            ]);
        } else {
            $logEntry .= " | Status: SUCCESS";
            file_put_contents($logFile, $logEntry . "\n", FILE_APPEND);
            
            echo json_encode([
                'success' => true,
                'message' => 'Orden de visita enviada correctamente',
                'properties_count' => count($properties),
                'file' => $nombreArchivo
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error al procesar la solicitud: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Accion no valida']);
