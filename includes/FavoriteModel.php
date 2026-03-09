<?php
// Ensure config is loaded before database initialization
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class FavoriteModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function addFavorite($clientId, $propertyId, $sectionType = 'general') {
        try {
            $stmt = $this->db->prepare("INSERT INTO client_favorites (client_id, property_id, section_type) VALUES (?, ?, ?)");
            $stmt->execute([$clientId, $propertyId, $sectionType]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $isDuplicate = false;
            if (strpos($msg, 'UNIQUE constraint failed') !== false) $isDuplicate = true;
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) $isDuplicate = true;
            if ($isDuplicate) {
                return false;
            }
            throw $e;
        }
    }

    public function removeFavorite($clientId, $propertyId) {
        $stmt = $this->db->prepare("DELETE FROM client_favorites WHERE client_id = ? AND property_id = ?");
        return $stmt->execute([$clientId, $propertyId]);
    }

    public function isFavorite($clientId, $propertyId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM client_favorites WHERE client_id = ? AND property_id = ?");
        $stmt->execute([$clientId, $propertyId]);
        return $stmt->fetchColumn() > 0;
    }

    private function getVisibilityCondition() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return "(p.status = 'Activo' OR p.status IS NULL OR (p.status = 'Vendido' AND p.sold_at >= datetime('now', '-30 days')))";
        }
        return "(p.status = 'Activo' OR p.status IS NULL OR (p.status = 'Vendido' AND p.sold_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)))";
    }
    
    private function getNormalizedSectionExpr() {
        return "CASE 
            WHEN COALESCE(f.section_type, p.section_type, 'general') = 'propiedades' THEN 'general'
            WHEN COALESCE(f.section_type, p.section_type, 'general') = '' THEN 'general'
            ELSE COALESCE(f.section_type, p.section_type, 'general')
        END";
    }
    
    public function getClientFavorites($clientId, $sectionType = null) {
        $dateCheck = $this->getVisibilityCondition();
        $sectionExpr = $this->getNormalizedSectionExpr();
        
        $sql = "
            SELECT p.*, r.name as region_name, c.name as comuna_name, 
                   {$sectionExpr} as favorite_section,
                   (SELECT url FROM property_photos WHERE property_id = p.id ORDER BY sort_order, id LIMIT 1) as main_photo
            FROM client_favorites f
            JOIN properties p ON f.property_id = p.id
            LEFT JOIN regions r ON p.region_id = r.id
            LEFT JOIN comunas c ON p.comuna_id = c.id
            WHERE f.client_id = ? AND {$dateCheck}";
        
        $params = [$clientId];
        
        if ($sectionType !== null) {
            $sql .= " AND {$sectionExpr} = ?";
            $params[] = $sectionType;
        }
        
        $sql .= " ORDER BY f.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getClientFavoritesBySection($clientId) {
        $sections = ['general' => [], 'terrenos' => [], 'activos' => [], 'usa' => []];
        
        $dateCheck = $this->getVisibilityCondition();
        $sectionExpr = $this->getNormalizedSectionExpr();
        
        $stmt = $this->db->prepare("
            SELECT p.*, r.name as region_name, c.name as comuna_name, 
                   {$sectionExpr} as favorite_section,
                   (SELECT url FROM property_photos WHERE property_id = p.id ORDER BY sort_order, id LIMIT 1) as main_photo
            FROM client_favorites f
            JOIN properties p ON f.property_id = p.id
            LEFT JOIN regions r ON p.region_id = r.id
            LEFT JOIN comunas c ON p.comuna_id = c.id
            WHERE f.client_id = ? AND {$dateCheck}
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$clientId]);
        $results = $stmt->fetchAll();
        
        foreach ($results as $fav) {
            $section = $fav['favorite_section'] ?? 'general';
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][] = $fav;
        }
        
        return $sections;
    }

    public function getFavoriteCount($propertyId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM client_favorites WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        return $stmt->fetchColumn();
    }

    public function getClientFavoriteIds($clientId) {
        $stmt = $this->db->prepare("SELECT property_id FROM client_favorites WHERE client_id = ?");
        $stmt->execute([$clientId]);
        return array_column($stmt->fetchAll(), 'property_id');
    }

    public function toggleFavorite($clientId, $propertyId, $sectionType = 'general') {
        if ($this->isFavorite($clientId, $propertyId)) {
            $this->removeFavorite($clientId, $propertyId);
            return ['success' => true, 'action' => 'removed', 'message' => 'Propiedad eliminada de favoritos'];
        } else {
            $this->addFavorite($clientId, $propertyId, $sectionType);
            return ['success' => true, 'action' => 'added', 'message' => 'Propiedad agregada a favoritos'];
        }
    }
}
