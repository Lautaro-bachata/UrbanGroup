<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/contact_settings.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    private $mailer;
    private $useSmtp;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->useSmtp = defined('SMTP_HOST') && !empty(SMTP_HOST);
        
        if ($this->useSmtp) {
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USER;
            $this->mailer->Password = SMTP_PASS;
            $this->mailer->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        }
        
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(true);
    }
    
    public function sendWelcomeEmail($clientEmail, $clientName, $section) {
        $sectionNames = [
            'terrenos' => 'Terrenos Inmobiliarios',
            'activos' => 'Activos Inmobiliarios',
            'usa' => 'Propiedades USA'
        ];
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(NOREPLY_EMAIL, COMPANY_NAME);
            $this->mailer->addReplyTo(ADMIN_EMAIL, COMPANY_NAME);
            $this->mailer->addAddress($clientEmail, $clientName);
            
            $this->mailer->Subject = 'Bienvenido a ' . COMPANY_NAME . ' - Registro Exitoso';
            $this->mailer->Body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #1e40af;">Bienvenido a ' . COMPANY_NAME . '</h2>
        <p>Estimado/a <strong>' . htmlspecialchars($clientName) . '</strong>,</p>
        <p>Gracias por registrarte en el portal de ' . COMPANY_NAME . '.</p>
        <p>Tu cuenta ha sido creada exitosamente. Ya puedes acceder al portal con tu correo electrónico y contraseña.</p>
        <div style="background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Email:</strong> ' . htmlspecialchars($clientEmail) . '</p>
            <p style="margin: 0;"><strong>Sección:</strong> ' . htmlspecialchars($sectionNames[$section] ?? $section) . '</p>
        </div>
        <p>Si tiene alguna consulta, no dude en contactarnos:</p>
        <p><strong>Email:</strong> ' . ADMIN_EMAIL . '<br>
        <strong>Teléfono:</strong> ' . ADMIN_PHONE . '</p>
        <hr style="border: 1px solid #e5e7eb;">
        <p style="color: #6b7280; font-size: 12px;">Este es un mensaje automático del portal de ' . COMPANY_NAME . '.</p>
    </div>
</body>
</html>';
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email error (welcome): " . $e->getMessage());
            return false;
        }
    }
    
    public function sendAdminNotification($clientData, $section) {
        $sectionNames = [
            'terrenos' => 'Terrenos Inmobiliarios',
            'activos' => 'Activos Inmobiliarios',
            'usa' => 'Propiedades USA'
        ];
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(NOREPLY_EMAIL, COMPANY_NAME);
            $this->mailer->addReplyTo($clientData['email'], $clientData['nombre_completo']);
            $this->mailer->addAddress(ADMIN_EMAIL);
            
            $this->mailer->Subject = 'Nueva Registración Portal - ' . ucfirst($section);
            
            $tableRows = '';
            $fields = [
                'nombre_completo' => 'Nombre Completo',
                'email' => 'Email',
                'celular' => 'Celular',
                'cedula_identidad' => 'Cédula de Identidad',
                'alias' => '(nombres) Representante Legal',
                'razon_social' => 'Razón Social',
                'rut' => 'RUT',
                'representante_legal' => 'Representante Legal'
            ];
            
            foreach ($fields as $key => $label) {
                if (!empty($clientData[$key])) {
                    $tableRows .= '<tr><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>' . $label . ':</strong></td><td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">' . htmlspecialchars($clientData[$key]) . '</td></tr>';
                }
            }
            
            $this->mailer->Body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #1e40af;">Nuevo Registro en el Portal de ' . COMPANY_NAME . '</h2>
    <p><strong>Sección:</strong> ' . htmlspecialchars($sectionNames[$section] ?? $section) . '</p>
    <hr style="border: 1px solid #e5e7eb;">
    <h3>Datos del Cliente:</h3>
    <table style="border-collapse: collapse; width: 100%;">' . $tableRows . '</table>
    <hr style="border: 1px solid #e5e7eb;">
    <p style="color: #6b7280; font-size: 12px;">Este es un mensaje automático del portal de ' . COMPANY_NAME . '.</p>
</body>
</html>';
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email error (admin): " . $e->getMessage());
            return false;
        }
    }
    
    public function sendVerificationEmail($clientEmail, $clientName, $token) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(NOREPLY_EMAIL, COMPANY_NAME);
            $this->mailer->addReplyTo(ADMIN_EMAIL, COMPANY_NAME);
            $this->mailer->addAddress($clientEmail, $clientName);

            $verifyUrl = rtrim(BASE_URL, '/') . '/cliente/verify_email.php?token=' . urlencode($token);

            $this->mailer->Subject = 'Verifica tu correo en ' . COMPANY_NAME;
            $this->mailer->Body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #1e40af;">Verifica tu correo en ' . COMPANY_NAME . '</h2>
        <p>Estimado/a <strong>' . htmlspecialchars($clientName) . '</strong>,</p>
        <p>Gracias por registrarte. Para completar tu registro, por favor haz clic en el siguiente enlace:</p>
        <p><a href="' . htmlspecialchars($verifyUrl) . '" style="background: #1e40af; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Verificar correo</a></p>
        <p>Si no puedes hacer clic en el botón, copia y pega esta URL en tu navegador:</p>
        <p><small>' . htmlspecialchars($verifyUrl) . '</small></p>
        <hr style="border: 1px solid #e5e7eb;">
        <p style="color: #6b7280; font-size: 12px;">Este es un mensaje automático del portal de ' . COMPANY_NAME . '.</p>
    </div>
</body>
</html>';
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email error (verification): " . $e->getMessage());
            return false;
        }
    }

    public function testConnection() {
        if (!$this->useSmtp) {
            return ['success' => false, 'message' => 'SMTP no configurado. Configure las variables SMTP_HOST, SMTP_USER y SMTP_PASS en contact_settings.php'];
        }
        
        try {
            $this->mailer->smtpConnect();
            $this->mailer->smtpClose();
            return ['success' => true, 'message' => 'Conexión SMTP exitosa'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error SMTP: ' . $e->getMessage()];
        }
    }
    
    public function sendPartnerPropertyNotification($partnerData, $propertyData, $propertyId, $baseUrl) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(NOREPLY_EMAIL, COMPANY_NAME);
            $this->mailer->addReplyTo($partnerData['email'] ?? NOREPLY_EMAIL, $partnerData['name'] ?? 'Partner');
            $this->mailer->addAddress(ADMIN_EMAIL);
            
            $sectionType = $propertyData['section_type'] ?? 'propiedades';
            $sectionNames = [
                'propiedades' => 'Propiedades',
                'terrenos' => 'Terrenos',
                'activos' => 'Activos',
                'usa' => 'USA'
            ];
            
            $propertyUrl = rtrim($baseUrl, '/') . '/property.php?id=' . $propertyId;
            $adminUrl = rtrim($baseUrl, '/') . '/admin/?action=edit&id=' . $propertyId;
            
            $this->mailer->Subject = 'Nueva Propiedad Registrada por Partner - ' . ($propertyData['title'] ?? 'Sin título');
            
            $this->mailer->Body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #1e40af; color: white; padding: 15px; border-radius: 8px 8px 0 0;">
            <h2 style="margin: 0;">Nueva Propiedad Registrada</h2>
        </div>
        
        <div style="background: #f3f4f6; padding: 20px; border-radius: 0 0 8px 8px;">
            <h3 style="color: #1e40af; margin-top: 0;">Datos del Partner</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px 0;"><strong>Nombre:</strong></td><td>' . htmlspecialchars($partnerData['name'] ?? '-') . '</td></tr>
                <tr><td style="padding: 8px 0;"><strong>Email:</strong></td><td>' . htmlspecialchars($partnerData['email'] ?? '-') . '</td></tr>
                <tr><td style="padding: 8px 0;"><strong>Teléfono:</strong></td><td>' . htmlspecialchars($partnerData['phone'] ?? '-') . '</td></tr>
            </table>
            
            <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">
            
            <h3 style="color: #1e40af;">Datos de la Propiedad</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 8px 0;"><strong>Título:</strong></td><td>' . htmlspecialchars($propertyData['title'] ?? '-') . '</td></tr>
                <tr><td style="padding: 8px 0;"><strong>Sección:</strong></td><td><span style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px;">' . htmlspecialchars($sectionNames[$sectionType] ?? $sectionType) . '</span></td></tr>
                <tr><td style="padding: 8px 0;"><strong>Tipo:</strong></td><td>' . htmlspecialchars($propertyData['property_type'] ?? '-') . '</td></tr>
                <tr><td style="padding: 8px 0;"><strong>Operación:</strong></td><td>' . htmlspecialchars($propertyData['operation_type'] ?? '-') . '</td></tr>
                <tr><td style="padding: 8px 0;"><strong>Precio:</strong></td><td>$' . number_format($propertyData['price'] ?? 0, 0, ',', '.') . ' ' . ($propertyData['currency'] ?? 'UF') . '</td></tr>
                <tr><td style="padding: 8px 0;"><strong>Dirección:</strong></td><td>' . htmlspecialchars($propertyData['address'] ?? '-') . '</td></tr>
            </table>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="' . htmlspecialchars($adminUrl) . '" style="display: inline-block; background: #1e40af; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Ver en Panel Admin</a>
                <a href="' . htmlspecialchars($propertyUrl) . '" style="display: inline-block; background: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-left: 10px;">Ver Propiedad</a>
            </div>
        </div>
        
        <p style="color: #6b7280; font-size: 12px; text-align: center; margin-top: 20px;">
            Este es un mensaje automático del sistema ' . COMPANY_NAME . '
        </p>
    </div>
</body>
</html>';
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email error (partner property): " . $e->getMessage());
            return false;
        }
    }
}
