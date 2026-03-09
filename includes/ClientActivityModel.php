<?php
require_once __DIR__ . '/../config/database.php';

class ClientActivityModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTable();
    }

    private function ensureTable() {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'sqlite') {
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS client_property_views (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        client_id INTEGER NOT NULL,
                        property_id INTEGER NOT NULL,
                        view_count INTEGER DEFAULT 1,
                        first_viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(client_id, property_id)
                    )
                ");
            } else {
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS client_property_views (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        client_id INT NOT NULL,
                        property_id INT NOT NULL,
                        view_count INT DEFAULT 1,
                        first_viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_client_property (client_id, property_id),
                        INDEX idx_client (client_id),
                        INDEX idx_property (property_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }
        } catch (Exception $e) {
        }
    }

    public function trackView($clientId, $propertyId) {
        if (!$clientId || !$propertyId) return false;
        
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'sqlite') {
                $stmt = $this->db->prepare("
                    INSERT INTO client_property_views (client_id, property_id, view_count, first_viewed_at, last_viewed_at)
                    VALUES (?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ON CONFLICT(client_id, property_id) DO UPDATE SET
                        view_count = view_count + 1,
                        last_viewed_at = CURRENT_TIMESTAMP
                ");
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO client_property_views (client_id, property_id, view_count, first_viewed_at, last_viewed_at)
                    VALUES (?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        view_count = view_count + 1,
                        last_viewed_at = NOW()
                ");
            }
            
            return $stmt->execute([$clientId, $propertyId]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function getClientViews($clientId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT 
                v.*,
                p.title as property_title,
                p.price,
                p.operation_type,
                p.property_type,
                p.section_type,
                c.name as comuna_name,
                r.name as region_name
            FROM client_property_views v
            JOIN properties p ON v.property_id = p.id
            LEFT JOIN comunas c ON p.comuna_id = c.id
            LEFT JOIN regions r ON p.region_id = r.id
            WHERE v.client_id = ?
            ORDER BY v.last_viewed_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function getMostViewedByClient($clientId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT 
                v.*,
                p.title as property_title,
                p.price,
                p.operation_type,
                p.property_type,
                p.section_type,
                c.name as comuna_name,
                r.name as region_name
            FROM client_property_views v
            JOIN properties p ON v.property_id = p.id
            LEFT JOIN comunas c ON p.comuna_id = c.id
            LEFT JOIN regions r ON p.region_id = r.id
            WHERE v.client_id = ?
            ORDER BY v.view_count DESC, v.last_viewed_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function getClientStats($clientId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT v.property_id) as total_properties_viewed,
                COALESCE(SUM(v.view_count), 0) as total_views,
                (SELECT COUNT(*) FROM client_favorites WHERE client_id = ?) as total_favorites
            FROM client_property_views v
            WHERE v.client_id = ?
        ");
        $stmt->execute([$clientId, $clientId]);
        return $stmt->fetch();
    }

    public function getViewsByPropertyType($clientId) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(p.property_type, 'Sin tipo') as property_type,
                COUNT(DISTINCT v.property_id) as properties_count,
                SUM(v.view_count) as total_views
            FROM client_property_views v
            JOIN properties p ON v.property_id = p.id
            WHERE v.client_id = ?
            GROUP BY p.property_type
            ORDER BY total_views DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function getViewsBySectionType($clientId) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(p.section_type, 'general') as section_type,
                COUNT(DISTINCT v.property_id) as properties_count,
                SUM(v.view_count) as total_views
            FROM client_property_views v
            JOIN properties p ON v.property_id = p.id
            WHERE v.client_id = ?
            GROUP BY p.section_type
            ORDER BY total_views DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function getViewsByOperationType($clientId) {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(p.operation_type, 'Sin tipo') as operation_type,
                COUNT(DISTINCT v.property_id) as properties_count,
                SUM(v.view_count) as total_views
            FROM client_property_views v
            JOIN properties p ON v.property_id = p.id
            WHERE v.client_id = ?
            GROUP BY p.operation_type
            ORDER BY total_views DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public function getActivityTimeline($clientId, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT 
                'view' as activity_type,
                v.property_id,
                p.title as property_title,
                v.last_viewed_at as activity_date,
                v.view_count
            FROM client_property_views v
            JOIN properties p ON v.property_id = p.id
            WHERE v.client_id = ?
            
            UNION ALL
            
            SELECT 
                'favorite' as activity_type,
                f.property_id,
                p.title as property_title,
                f.created_at as activity_date,
                1 as view_count
            FROM client_favorites f
            JOIN properties p ON f.property_id = p.id
            WHERE f.client_id = ?
            
            ORDER BY activity_date DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$clientId, $clientId]);
        return $stmt->fetchAll();
    }
}
