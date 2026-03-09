<?php
require_once __DIR__ . '/../config/database.php';

class USAModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureUSAColumns();
    }

    private function ensureUSAColumns() {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'mysql') {
                $dbName = defined('DB_NAME') ? DB_NAME : null;
                if ($dbName) {
                    $stmt = $this->db->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = 'property_types' AND column_name = 'is_usa'");
                    $stmt->execute([$dbName]);
                    $columnExists = $stmt->fetch();
                    
                    if (!$columnExists) {
                        try {
                            $this->db->exec("ALTER TABLE property_types ADD COLUMN is_usa TINYINT(1) DEFAULT 0");
                        } catch (Exception $e) {
                        }
                    }
                    
                    $checkStmt = $this->db->query("SELECT COUNT(*) as cnt FROM property_types WHERE is_usa = 1");
                    $usaCount = $checkStmt->fetch();
                    
                    if (!$usaCount || $usaCount['cnt'] == 0) {
                        $usaTypes = [
                            ['Single Family Home', 'home'],
                            ['Condo', 'building'],
                            ['Townhouse', 'home'],
                            ['Multi-Family', 'building'],
                            ['Land', 'map'],
                            ['Commercial', 'briefcase'],
                            ['Vacation Home', 'umbrella'],
                            ['Investment Property', 'chart'],
                            ['New Construction', 'construction'],
                            ['Luxury Home', 'star']
                        ];
                        
                        $insertStmt = $this->db->prepare("INSERT IGNORE INTO property_types (name, icon, is_usa) VALUES (?, ?, 1)");
                        foreach ($usaTypes as $type) {
                            try {
                                $insertStmt->execute([$type[0], $type[1]]);
                            } catch (Exception $e) {
                            }
                        }
                    }
                }
            } elseif ($driver === 'sqlite') {
                $stmt = $this->db->query("PRAGMA table_info('property_types')");
                $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $existing = array_map(function($c){ return strtolower($c['name']); }, $cols);
                
                if (!in_array('is_usa', $existing)) {
                    try {
                        $this->db->exec("ALTER TABLE property_types ADD COLUMN is_usa INTEGER DEFAULT 0");
                    } catch (Exception $e) {
                    }
                }
            }
        } catch (Exception $e) {
        }
    }

    public function getUSAPropertyTypes() {
        try {
            $stmt = $this->db->query("SELECT * FROM property_types WHERE is_usa = 1 ORDER BY name");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getUSAProperties($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT p.*, 
                       ud.surface_sqft, ud.lot_size_sqft, ud.price_usd, ud.is_project as usa_is_project,
                       ud.year_built as usa_year_built, ud.stories, ud.garage_spaces, ud.pool,
                       ud.waterfront, ud.view_type, ud.cooling, ud.project_units, ud.project_developer,
                       ud.project_amenities, ud.state, ud.city, ud.whatsapp_number
                FROM properties p 
                LEFT JOIN property_usa_details ud ON p.id = ud.property_id
                WHERE p.is_active = 1 AND p.section_type = 'usa'";

        $params = [];

        if (!empty($filters['operation_type'])) {
            $sql .= " AND p.operation_type = ?";
            $params[] = $filters['operation_type'];
        }

        if (!empty($filters['property_type'])) {
            $sql .= " AND p.property_type = ?";
            $params[] = $filters['property_type'];
        }

        if (!empty($filters['is_project'])) {
            $sql .= " AND p.is_project = 1";
        }

        if (!empty($filters['min_price'])) {
            $sql .= " AND (ud.price_usd >= ? OR p.price >= ?)";
            $params[] = (float)$filters['min_price'];
            $params[] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= " AND (ud.price_usd <= ? OR p.price <= ?)";
            $params[] = (float)$filters['max_price'];
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['bedrooms'])) {
            $sql .= " AND p.bedrooms >= ?";
            $params[] = (int)$filters['bedrooms'];
        }

        if (!empty($filters['bathrooms'])) {
            $sql .= " AND p.bathrooms >= ?";
            $params[] = (int)$filters['bathrooms'];
        }

        if (!empty($filters['state'])) {
            $sql .= " AND ud.state = ?";
            $params[] = $filters['state'];
        }

        if (!empty($filters['pool'])) {
            $sql .= " AND ud.pool = 1";
        }

        if (!empty($filters['waterfront'])) {
            $sql .= " AND ud.waterfront = 1";
        }

        $sql .= " ORDER BY p.is_project DESC, p.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getProjects($limit = null) {
        $sql = "SELECT p.*, 
                       ud.surface_sqft, ud.price_usd, ud.project_units, ud.project_developer,
                       ud.project_amenities, ud.project_completion_date
                FROM properties p 
                LEFT JOIN property_usa_details ud ON p.id = ud.property_id
                WHERE p.is_active = 1 AND p.section_type = 'usa' AND p.is_project = 1
                ORDER BY p.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ?";
        }

        $stmt = $this->db->prepare($sql);
        if ($limit !== null) {
            $stmt->execute([(int)$limit]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function getUSADetailsByPropertyId($propertyId) {
        $stmt = $this->db->prepare("SELECT * FROM property_usa_details WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        return $stmt->fetch();
    }

    public function createOrUpdateUSADetails($propertyId, $data) {
        $existing = $this->getUSADetailsByPropertyId($propertyId);
        
        if ($existing) {
            return $this->updateUSADetails($propertyId, $data);
        } else {
            return $this->createUSADetails($propertyId, $data);
        }
    }

    public function createUSADetails($propertyId, $data) {
        $sql = "INSERT INTO property_usa_details (
            property_id, is_project, surface_sqft, lot_size_sqft, price_usd,
            hoa_fee, property_tax, year_built, stories, garage_spaces,
            pool, waterfront, view_type, heating, cooling, flooring,
            appliances, exterior_features, interior_features, community_features,
            project_units, project_developer, project_completion_date, project_amenities,
            whatsapp_number, mls_id, state, city, zip_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $propertyId,
            $data['is_project'] ?? 0,
            $data['surface_sqft'] ?? null,
            $data['lot_size_sqft'] ?? null,
            $data['price_usd'] ?? null,
            $data['hoa_fee'] ?? null,
            $data['property_tax'] ?? null,
            $data['year_built'] ?? null,
            $data['stories'] ?? null,
            $data['garage_spaces'] ?? null,
            $data['pool'] ?? 0,
            $data['waterfront'] ?? 0,
            $data['view_type'] ?? null,
            $data['heating'] ?? null,
            $data['cooling'] ?? null,
            $data['flooring'] ?? null,
            $data['appliances'] ?? null,
            $data['exterior_features'] ?? null,
            $data['interior_features'] ?? null,
            $data['community_features'] ?? null,
            $data['project_units'] ?? null,
            $data['project_developer'] ?? null,
            $data['project_completion_date'] ?? null,
            $data['project_amenities'] ?? null,
            $data['whatsapp_number'] ?? null,
            $data['mls_id'] ?? null,
            $data['state'] ?? null,
            $data['city'] ?? null,
            $data['zip_code'] ?? null
        ]);
    }

    public function updateUSADetails($propertyId, $data) {
        $fields = [];
        $params = [];

        $allowedFields = [
            'is_project', 'surface_sqft', 'lot_size_sqft', 'price_usd',
            'hoa_fee', 'property_tax', 'year_built', 'stories', 'garage_spaces',
            'pool', 'waterfront', 'view_type', 'heating', 'cooling', 'flooring',
            'appliances', 'exterior_features', 'interior_features', 'community_features',
            'project_units', 'project_developer', 'project_completion_date', 'project_amenities',
            'whatsapp_number', 'mls_id', 'state', 'city', 'zip_code'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) return true;

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $propertyId;

        $sql = "UPDATE property_usa_details SET " . implode(', ', $fields) . " WHERE property_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function formatSqft($sqft) {
        if (!$sqft) return 'N/A';
        return number_format($sqft, 0, ',', ',') . ' sqft';
    }

    public static function formatUSD($price) {
        if (!$price) return 'N/A';
        return '$' . number_format($price, 0, '.', ',') . ' USD';
    }
}
