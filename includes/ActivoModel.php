<?php
require_once __DIR__ . '/../config/database.php';

class ActivoModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getDetailsByPropertyId($propertyId) {
        $stmt = $this->db->prepare("SELECT * FROM activo_details WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        return $stmt->fetch();
    }

    public function saveDetails($propertyId, $data) {
        $existing = $this->getDetailsByPropertyId($propertyId);
        
        if ($existing) {
            return $this->updateDetails($propertyId, $data);
        } else {
            return $this->insertDetails($propertyId, $data);
        }
    }

    private function insertDetails($propertyId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO activo_details (
                property_id, comuna_text, ciudad, superficie_terreno, 
                superficie_util, precio_uf, con_renta, rentabilidad_anual,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = $driver === 'sqlite' ? date('Y-m-d H:i:s') : null;
        
        if ($driver === 'sqlite') {
            $stmt = $this->db->prepare("
                INSERT INTO activo_details (
                    property_id, comuna_text, ciudad, superficie_terreno, 
                    superficie_util, precio_uf, con_renta, rentabilidad_anual,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ");
        }
        
        return $stmt->execute([
            $propertyId,
            $data['comuna_text'] ?? '',
            $data['ciudad'] ?? '',
            $data['superficie_terreno'] ?? null,
            $data['superficie_util'] ?? null,
            $data['precio_uf'] ?? null,
            !empty($data['con_renta']) ? 1 : 0,
            $data['rentabilidad_anual'] ?? null
        ]);
    }

    private function updateDetails($propertyId, $data) {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = $driver === 'sqlite' ? "datetime('now')" : "NOW()";
        
        $stmt = $this->db->prepare("
            UPDATE activo_details SET 
                comuna_text = ?,
                ciudad = ?,
                superficie_terreno = ?,
                superficie_util = ?,
                precio_uf = ?,
                con_renta = ?,
                rentabilidad_anual = ?,
                updated_at = {$now}
            WHERE property_id = ?
        ");
        
        return $stmt->execute([
            $data['comuna_text'] ?? '',
            $data['ciudad'] ?? '',
            $data['superficie_terreno'] ?? null,
            $data['superficie_util'] ?? null,
            $data['precio_uf'] ?? null,
            !empty($data['con_renta']) ? 1 : 0,
            $data['rentabilidad_anual'] ?? null,
            $propertyId
        ]);
    }

    public function deleteDetails($propertyId) {
        $stmt = $this->db->prepare("DELETE FROM activo_details WHERE property_id = ?");
        return $stmt->execute([$propertyId]);
    }

    public function getActivosWithFilters($filters = [], $limit = 20, $offset = 0) {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $dateCheck = $driver === 'sqlite' 
            ? "(p.status = 'Activo' OR p.status IS NULL OR (p.status = 'Vendido' AND p.sold_at >= datetime('now', '-30 days')))"
            : "(p.status = 'Activo' OR p.status IS NULL OR (p.status = 'Vendido' AND p.sold_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)))";
        
        $sql = "
            SELECT p.*, ad.*, r.name as region_name, c.name as comuna_name
            FROM properties p
            LEFT JOIN activo_details ad ON p.id = ad.property_id
            LEFT JOIN regions r ON p.region_id = r.id
            LEFT JOIN comunas c ON p.comuna_id = c.id
            WHERE p.section_type = 'activos' 
            AND p.is_active = 1 
            AND {$dateCheck}
        ";
        
        $params = [];
        
        if (!empty($filters['comuna_text'])) {
            $sql .= " AND ad.comuna_text LIKE ?";
            $params[] = '%' . $filters['comuna_text'] . '%';
        }
        
        if (!empty($filters['min_superficie_util'])) {
            $sql .= " AND ad.superficie_util >= ?";
            $params[] = $filters['min_superficie_util'];
        }
        
        if (!empty($filters['max_superficie_util'])) {
            $sql .= " AND ad.superficie_util <= ?";
            $params[] = $filters['max_superficie_util'];
        }
        
        if (!empty($filters['min_precio_uf'])) {
            $sql .= " AND ad.precio_uf >= ?";
            $params[] = $filters['min_precio_uf'];
        }
        
        if (!empty($filters['max_precio_uf'])) {
            $sql .= " AND ad.precio_uf <= ?";
            $params[] = $filters['max_precio_uf'];
        }
        
        if (!empty($filters['min_rentabilidad'])) {
            $sql .= " AND ad.rentabilidad_anual >= ?";
            $params[] = $filters['min_rentabilidad'];
        }
        
        if (!empty($filters['max_rentabilidad'])) {
            $sql .= " AND ad.rentabilidad_anual <= ?";
            $params[] = $filters['max_rentabilidad'];
        }
        
        if (!empty($filters['con_renta'])) {
            $sql .= " AND ad.con_renta = 1";
        }
        
        $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getAvailableComunas() {
        $stmt = $this->db->prepare("
            SELECT DISTINCT ad.comuna_text 
            FROM activo_details ad 
            JOIN properties p ON ad.property_id = p.id 
            WHERE p.section_type = 'activos' AND p.is_active = 1 AND ad.comuna_text IS NOT NULL AND ad.comuna_text != ''
            ORDER BY ad.comuna_text
        ");
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'comuna_text');
    }
}
