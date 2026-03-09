<?php
require_once __DIR__ . '/../config/database.php';

class PropertyModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        // Ensure properties table has required columns (prevent fatal errors on older schemas)
        $this->ensurePropertiesColumns();
    }
    private function ensurePropertiesColumns() {
        try {
            $required = [
                'is_active' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN is_active INTEGER DEFAULT 1",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN is_active TINYINT(1) DEFAULT 1"
                ],
                'is_featured' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN is_featured INTEGER DEFAULT 0",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN is_featured TINYINT(1) DEFAULT 0"
                ],
                'is_project' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN is_project INTEGER DEFAULT 0",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN is_project TINYINT(1) DEFAULT 0"
                ],
                'section_type' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN section_type VARCHAR(50) DEFAULT 'propiedades'",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN section_type VARCHAR(50) DEFAULT 'propiedades'"
                ],
                'property_type' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN property_type VARCHAR(100)",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN property_type VARCHAR(100)"
                ],
                'property_type_id' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN property_type_id INTEGER",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN property_type_id INT"
                ],
                'property_category' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN property_category VARCHAR(100) DEFAULT ''",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN property_category VARCHAR(100) DEFAULT ''"
                ],
                'partner_id' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN partner_id INTEGER",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN partner_id INT"
                ],
                'total_area' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN total_area REAL",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN total_area DECIMAL(10,2)"
                ],
                'parking_spots' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN parking_spots INTEGER DEFAULT 0",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN parking_spots INT DEFAULT 0"
                ],
                'images' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN images TEXT",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN images TEXT"
                ],
                'features' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN features TEXT",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN features TEXT"
                ],
                'status' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN status VARCHAR(20) DEFAULT 'Activo'",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN status VARCHAR(20) DEFAULT 'Activo'"
                ],
                'sold_at' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN sold_at DATETIME",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN sold_at DATETIME"
                ],
                'youtube_url' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN youtube_url VARCHAR(500)",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN youtube_url VARCHAR(500)"
                ],
                'created_at' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN created_at DATETIME",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
                ],
                'updated_at' => [
                    'sqlite' => "ALTER TABLE properties ADD COLUMN updated_at DATETIME",
                    'mysql'  => "ALTER TABLE properties ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
                ]
            ];

            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $this->db->query("PRAGMA table_info('properties')");
                $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $existing = array_map(function($c){ return strtolower($c['name']); }, $cols);

                foreach ($required as $col => $sqls) {
                    if (!in_array(strtolower($col), $existing)) {
                        try { $this->db->exec($sqls['sqlite']); } catch (Exception $e) { /* ignore */ }
                    }
                }
            } elseif ($driver === 'pgsql') {
                $stmt = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'properties'");
                $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

                foreach ($required as $col => $sqls) {
                    if (!in_array(strtolower($col), $existing)) {
                        $pgsqlSql = str_replace(['TINYINT(1)', 'INT ', 'DATETIME'], ['SMALLINT', 'INTEGER ', 'TIMESTAMP'], $sqls['mysql']);
                        try { $this->db->exec($pgsqlSql); } catch (Exception $e) { /* ignore */ }
                    }
                }
            } else {
                $dbName = defined('DB_NAME') ? DB_NAME : null;
                $existing = [];
                if ($dbName) {
                    $stmt = $this->db->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = 'properties'");
                    $stmt->execute([$dbName]);
                    $existing = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
                }

                foreach ($required as $col => $sqls) {
                    if (!in_array(strtolower($col), $existing)) {
                        try { $this->db->exec($sqls['mysql']); } catch (Exception $e) { /* ignore */ }
                    }
                }
            }
        } catch (Exception $e) {
            // non-fatal — don't block page load
        }
    }
    
    private function getVisibilityCondition($tableAlias = 'p') {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return "({$tableAlias}.status = 'Activo' OR {$tableAlias}.status IS NULL OR ({$tableAlias}.status = 'Vendido' AND {$tableAlias}.sold_at >= datetime('now', '-30 days')))";
        }
        if ($driver === 'pgsql') {
            return "({$tableAlias}.status = 'Activo' OR {$tableAlias}.status IS NULL OR ({$tableAlias}.status = 'Vendido' AND {$tableAlias}.sold_at >= NOW() - INTERVAL '30 days'))";
        }
        return "({$tableAlias}.status = 'Activo' OR {$tableAlias}.status IS NULL OR ({$tableAlias}.status = 'Vendido' AND {$tableAlias}.sold_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)))";
    }

    public function getAll($filters = [], $limit = null, $offset = 0) {
        $isUsaSearch = !empty($filters['section_type']) && $filters['section_type'] === 'usa';
        $isTerrenoSearch = !empty($filters['section_type']) && $filters['section_type'] === 'terrenos';
        $isActivoSearch = !empty($filters['section_type']) && $filters['section_type'] === 'activos';

        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name" .
                       ($isUsaSearch ? ", ud.state, ud.city as usa_city, ud.pool, ud.waterfront, ud.year_built, ud.surface_sqft, ud.lot_size_sqft, ud.price_usd" : "") .
                       ($isTerrenoSearch ? ", td.zona_prc_edificacion, td.usos_suelo, td.has_anteproyecto, td.densidad_neta_max_hab_ha, td.superficie_util, td.precio_uf_m2, td.sistema_agrupamiento, td.estado as terreno_estado, td.ciudad as terreno_ciudad" : "") .
                       ($isActivoSearch ? ", ad.comuna_text, ad.ciudad as activo_ciudad, ad.superficie_util as activo_superficie_util, ad.precio_uf, ad.con_renta, ad.rentabilidad_anual" : "") .
                " FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id " .
                ($isUsaSearch ? "LEFT JOIN property_usa_details ud ON p.id = ud.property_id " : "") .
                ($isTerrenoSearch ? "LEFT JOIN property_terreno_details td ON p.id = td.property_id " : "") .
                ($isActivoSearch ? "LEFT JOIN activo_details ad ON p.id = ad.property_id " : "") .
                "WHERE " . $this->getVisibilityCondition();
        
        $params = [];

        // FILTROS BÁSICOS
        if (!empty($filters['operation_type'])) {
            $sql .= " AND p.operation_type = ?";
            $params[] = $filters['operation_type'];
        }
        if (!empty($filters['property_category'])) {
            $sql .= " AND p.property_category = ?";
            $params[] = $filters['property_category'];
        }
        if (!empty($filters['region_id'])) {
            $sql .= " AND p.region_id = ?";
            $params[] = $filters['region_id'];
        }
        if (!empty($filters['comuna_id'])) {
            $sql .= " AND p.comuna_id = ?";
            $params[] = $filters['comuna_id'];
        }
        if (!empty($filters['bedrooms'])) {
            $sql .= " AND p.bedrooms >= ?";
            $params[] = (int)$filters['bedrooms'];
        }
        if (!empty($filters['bathrooms'])) {
            $sql .= " AND p.bathrooms >= ?";
            $params[] = (int)$filters['bathrooms'];
        }

        // PRECIOS
        if ($isUsaSearch) {
            if (!empty($filters['min_price'])) { $sql .= " AND ud.price_usd >= ?"; $params[] = (float)$filters['min_price']; }
            if (!empty($filters['max_price'])) { $sql .= " AND ud.price_usd <= ?"; $params[] = (float)$filters['max_price']; }
        } else {
            if (!empty($filters['min_price'])) { $sql .= " AND p.price >= ?"; $params[] = (float)$filters['min_price']; }
            if (!empty($filters['max_price'])) { $sql .= " AND p.price <= ?"; $params[] = (float)$filters['max_price']; }
        }

        // SUPERFICIE
        if (!empty($filters['min_area'])) { $sql .= " AND p.total_area >= ?"; $params[] = (float)$filters['min_area']; }
        if (!empty($filters['max_area'])) { $sql .= " AND p.total_area <= ?"; $params[] = (float)$filters['max_area']; }

        // BÚSQUEDA GENERAL
        if (!empty($filters['search'])) {
            $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.address LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search; $params[] = $search; $params[] = $search;
        }

        // SECCIÓN
        if (!empty($filters['section_type'])) {
            $sql .= " AND p.section_type = ?";
            $params[] = $filters['section_type'];
        }

        // FILTROS AVANZADOS USA
        if ($isUsaSearch) {
            if (!empty($filters['state'])) { $sql .= " AND ud.state = ?"; $params[] = $filters['state']; }
            if (!empty($filters['city'])) { $sql .= " AND ud.city LIKE ?"; $params[] = '%' . $filters['city'] . '%'; }
            if (isset($filters['pool']) && $filters['pool'] !== '') { $sql .= " AND ud.pool = ?"; $params[] = (int)$filters['pool']; }
            if (isset($filters['waterfront']) && $filters['waterfront'] !== '') { $sql .= " AND ud.waterfront = ?"; $params[] = (int)$filters['waterfront']; }
                        if (isset($filters['is_project']) && $filters['is_project'] !== '') { $sql .= " AND ud.is_project = ?"; $params[] = (int)$filters['is_project']; }
            if (!empty($filters['min_year_built'])) { $sql .= " AND ud.year_built >= ?"; $params[] = (int)$filters['min_year_built']; }
            if (!empty($filters['max_year_built'])) { $sql .= " AND ud.year_built <= ?"; $params[] = (int)$filters['max_year_built']; }
            if (!empty($filters['min_sqft'])) { $sql .= " AND ud.surface_sqft >= ?"; $params[] = (float)$filters['min_sqft']; }
            if (!empty($filters['max_sqft'])) { $sql .= " AND ud.surface_sqft <= ?"; $params[] = (float)$filters['max_sqft']; }
            if (!empty($filters['min_lot'])) { $sql .= " AND ud.lot_size_sqft >= ?"; $params[] = (float)$filters['min_lot']; }
            if (!empty($filters['max_lot'])) { $sql .= " AND ud.lot_size_sqft <= ?"; $params[] = (float)$filters['max_lot']; }
        }
        
        // FILTROS AVANZADOS TERRENOS
        if ($isTerrenoSearch) {
            if (!empty($filters['zona_prc'])) { $sql .= " AND td.zona_prc_edificacion = ?"; $params[] = $filters['zona_prc']; }
            if (!empty($filters['usos_suelo'])) { $sql .= " AND td.usos_suelo LIKE ?"; $params[] = '%' . $filters['usos_suelo'] . '%'; }
            if (isset($filters['anteproyecto']) && $filters['anteproyecto'] === 'con') { $sql .= " AND td.has_anteproyecto = 1"; }
            if (isset($filters['anteproyecto']) && $filters['anteproyecto'] === 'sin') { $sql .= " AND (td.has_anteproyecto = 0 OR td.has_anteproyecto IS NULL)"; }
            if (!empty($filters['sistema_agrupamiento'])) { $sql .= " AND td.sistema_agrupamiento = ?"; $params[] = $filters['sistema_agrupamiento']; }
            if (!empty($filters['estado_terreno'])) { $sql .= " AND td.estado = ?"; $params[] = $filters['estado_terreno']; }
            if (!empty($filters['ciudad_terreno'])) { $sql .= " AND td.ciudad LIKE ?"; $params[] = '%' . $filters['ciudad_terreno'] . '%'; }
            if (!empty($filters['min_densidad'])) { $sql .= " AND td.densidad_neta_max_hab_ha >= ?"; $params[] = (float)$filters['min_densidad']; }
            if (!empty($filters['max_densidad'])) { $sql .= " AND td.densidad_neta_max_hab_ha <= ?"; $params[] = (float)$filters['max_densidad']; }
            if (!empty($filters['min_superficie_util'])) { $sql .= " AND td.superficie_util >= ?"; $params[] = (float)$filters['min_superficie_util']; }
            if (!empty($filters['max_superficie_util'])) { $sql .= " AND td.superficie_util <= ?"; $params[] = (float)$filters['max_superficie_util']; }
            if (!empty($filters['min_precio_uf'])) { $sql .= " AND td.precio_uf_m2 >= ?"; $params[] = (float)$filters['min_precio_uf']; }
            if (!empty($filters['max_precio_uf'])) { $sql .= " AND td.precio_uf_m2 <= ?"; $params[] = (float)$filters['max_precio_uf']; }
        }
        
        // FILTROS AVANZADOS ACTIVOS
        if ($isActivoSearch) {
            if (!empty($filters['comuna_text'])) { $sql .= " AND ad.comuna_text LIKE ?"; $params[] = '%' . $filters['comuna_text'] . '%'; }
            if (!empty($filters['ciudad_activo'])) { $sql .= " AND ad.ciudad LIKE ?"; $params[] = '%' . $filters['ciudad_activo'] . '%'; }
            if (!empty($filters['min_superficie_util'])) { $sql .= " AND ad.superficie_util >= ?"; $params[] = (float)$filters['min_superficie_util']; }
            if (!empty($filters['max_superficie_util'])) { $sql .= " AND ad.superficie_util <= ?"; $params[] = (float)$filters['max_superficie_util']; }
            if (!empty($filters['min_precio_uf'])) { $sql .= " AND ad.precio_uf >= ?"; $params[] = (float)$filters['min_precio_uf']; }
            if (!empty($filters['max_precio_uf'])) { $sql .= " AND ad.precio_uf <= ?"; $params[] = (float)$filters['max_precio_uf']; }
            if (isset($filters['con_renta']) && $filters['con_renta'] !== '') { $sql .= " AND ad.con_renta = ?"; $params[] = (int)$filters['con_renta']; }
            if (!empty($filters['min_rentabilidad'])) { $sql .= " AND ad.rentabilidad_anual >= ?"; $params[] = (float)$filters['min_rentabilidad']; }
            if (!empty($filters['max_rentabilidad'])) { $sql .= " AND ad.rentabilidad_anual <= ?"; $params[] = (float)$filters['max_rentabilidad']; }
        }

        // EXCLUIR SECCIONES
        if (!empty($filters['exclude_sections']) && is_array($filters['exclude_sections'])) {
            $placeholders = implode(',', array_fill(0, count($filters['exclude_sections']), '?'));
            $sql .= " AND (p.section_type IS NULL OR p.section_type NOT IN ($placeholders))";
            foreach ($filters['exclude_sections'] as $section) { $params[] = $section; }
        }

        $sql .= " ORDER BY p.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count properties matching a given section_type. Optionally exclude certain categories.
     *
     * @param string $sectionType
     * @param array $excludeCategories
     * @return int
     */
    public function countBySection($sectionType, $excludeCategories = []) {
        $sql = "SELECT COUNT(*) FROM properties WHERE section_type = ?";
        $params = [$sectionType];
        if (!empty($excludeCategories)) {
            $placeholders = implode(',', array_fill(0, count($excludeCategories), '?'));
            $sql .= " AND (property_category IS NULL OR property_category NOT IN ($placeholders))";
            $params = array_merge($params, $excludeCategories);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getFeatured($limit = 6) {
        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
                WHERE p.status = 'Activo' AND p.is_featured = 1 
                ORDER BY p.created_at DESC 
                LIMIT " . (int)$limit;

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name,
                       u.name as partner_name,
                       u.company_name,
                       u.phone as partner_phone,
                       u.email as partner_email,
                       u.photo_url as partner_photo
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
                LEFT JOIN users u ON p.partner_id = u.id
                WHERE p.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByPartnerId($partnerId) {
        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
                WHERE p.partner_id = ? 
                ORDER BY p.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll();
    }

    public function create($data) {
        $sql = "INSERT INTO properties (
                    title, description, property_type, property_type_id, operation_type, price, currency, 
                    bedrooms, bathrooms, built_area, total_area, parking_spots, 
                    address, comuna_id, region_id, images, features, 
                    is_featured, is_active, partner_id, section_type, property_category,
                    status, youtube_url, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['property_type'] ?? '',
            !empty($data['property_type_id']) ? (int)$data['property_type_id'] : null,
            $data['operation_type'],
            $data['price'],
            $data['currency'] ?? 'CLP',
            $data['bedrooms'] ?? 0,
            $data['bathrooms'] ?? 0,
            $data['built_area'] ?? 0,
            $data['total_area'] ?? 0,
            $data['parking_spots'] ?? 0,
            $data['address'] ?? '',
            !empty($data['comuna_id']) ? (int)$data['comuna_id'] : null,
            !empty($data['region_id']) ? (int)$data['region_id'] : null,
            $data['images'] ?? '[]',
            $data['features'] ?? '[]',
            $data['is_featured'] ?? 0,
            $data['is_active'] ?? 1,
            $data['partner_id'],
            $data['section_type'] ?? 'propiedades',
            $data['property_category'] ?? '',
            $data['status'] ?? 'Activo',
            $data['youtube_url'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'title', 'description', 'property_type', 'property_type_id', 'operation_type', 'price', 'currency',
            'bedrooms', 'bathrooms', 'built_area', 'total_area', 'parking_spots',
            'address', 'comuna_id', 'region_id', 'images', 'features',
            'is_featured', 'is_active', 'section_type', 'property_category',
            'status', 'sold_at', 'youtube_url'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                if (in_array($field, ['comuna_id', 'region_id', 'property_type_id'])) {
                    $params[] = !empty($data[$field]) ? (int)$data[$field] : null;
                } else {
                    $params[] = $data[$field];
                }
            }
        }
        
        if (empty($fields)) return false;

        $fields[] = "updated_at = NOW()";
        $params[] = $id;

        $sql = "UPDATE properties SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM properties WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function toggleFeatured($id) {
        $stmt = $this->db->prepare("UPDATE properties SET is_featured = NOT is_featured WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function toggleActive($id) {
        $stmt = $this->db->prepare("UPDATE properties SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getSimilar($propertyId, $sectionType = 'propiedades', $propertyType = '', $price = 0, $limit = 4) {
        $visCondition = $this->getVisibilityCondition();
        
        if ($sectionType === 'usa') {
            $sql = "SELECT p.*, 
                           ud.price_usd as usa_price,
                           c.name as comuna_name, 
                           r.name as region_name
                    FROM properties p 
                    LEFT JOIN property_usa_details ud ON p.id = ud.property_id
                    LEFT JOIN comunas c ON p.comuna_id = c.id 
                    LEFT JOIN regions r ON p.region_id = r.id
                    WHERE {$visCondition}
                    AND p.id != ? 
                    AND p.section_type = 'usa'";
            
            $params = [$propertyId];
            
            if (!empty($propertyType)) {
                $sql .= " AND (p.property_category = ? OR p.property_type = ?)";
                $params[] = $propertyType;
                $params[] = $propertyType;
            }
            
            $sql .= " ORDER BY ABS(COALESCE(ud.price_usd, p.price, 0) - ?) ASC, p.created_at DESC LIMIT " . (int)$limit;
            $params[] = (float)$price;
        } else {
            $sql = "SELECT p.*, 
                           c.name as comuna_name, 
                           r.name as region_name
                    FROM properties p 
                    LEFT JOIN comunas c ON p.comuna_id = c.id 
                    LEFT JOIN regions r ON p.region_id = r.id
                    WHERE {$visCondition}
                    AND p.id != ? 
                    AND p.section_type = ?";
            
            $params = [$propertyId, $sectionType];
            
            if (!empty($propertyType)) {
                $sql .= " AND (p.property_category = ? OR p.property_type = ?)";
                $params[] = $propertyType;
                $params[] = $propertyType;
            }
            
            $sql .= " ORDER BY ABS(COALESCE(p.price, 0) - ?) ASC, p.created_at DESC LIMIT " . (int)$limit;
            $params[] = (float)$price;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count($filters = []) {
        $isUsaSearch = !empty($filters['section_type']) && $filters['section_type'] === 'usa';
        $isTerrenoSearch = !empty($filters['section_type']) && $filters['section_type'] === 'terrenos';

        $sql = "SELECT COUNT(p.id) as total 
                FROM properties p ";

        if ($isUsaSearch) {
            $sql .= "LEFT JOIN property_usa_details ud ON p.id = ud.property_id ";
        }
        if ($isTerrenoSearch) {
            $sql .= "LEFT JOIN property_terreno_details td ON p.id = td.property_id ";
        }
        
        $sql .= "WHERE " . $this->getVisibilityCondition();
        
        $params = [];

        // Basic Filters
        if (!empty($filters['operation_type'])) { $sql .= " AND p.operation_type = ?"; $params[] = $filters['operation_type']; }
        if (!empty($filters['property_category'])) { $sql .= " AND p.property_category = ?"; $params[] = $filters['property_category']; }
        if (!empty($filters['region_id'])) { $sql .= " AND p.region_id = ?"; $params[] = $filters['region_id']; }
        if (!empty($filters['comuna_id'])) { $sql .= " AND p.comuna_id = ?"; $params[] = $filters['comuna_id']; }
        if (!empty($filters['bedrooms'])) { $sql .= " AND p.bedrooms >= ?"; $params[] = (int)$filters['bedrooms']; }
        if (!empty($filters['bathrooms'])) { $sql .= " AND p.bathrooms >= ?"; $params[] = (int)$filters['bathrooms']; }

        // Price filters
        if ($isUsaSearch) {
            if (!empty($filters['min_price'])) { $sql .= " AND ud.price_usd >= ?"; $params[] = (float)$filters['min_price']; }
            if (!empty($filters['max_price'])) { $sql .= " AND ud.price_usd <= ?"; $params[] = (float)$filters['max_price']; }
        } else {
            if (!empty($filters['min_price'])) { $sql .= " AND p.price >= ?"; $params[] = (float)$filters['min_price']; }
            if (!empty($filters['max_price'])) { $sql .= " AND p.price <= ?"; $params[] = (float)$filters['max_price']; }
        }

        // Area filters
        if (!empty($filters['min_area'])) { $sql .= " AND p.total_area >= ?"; $params[] = (float)$filters['min_area']; }
        if (!empty($filters['max_area'])) { $sql .= " AND p.total_area <= ?"; $params[] = (float)$filters['max_area']; }
        
        // Search
        if (!empty($filters['search'])) {
            $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.address LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search; $params[] = $search; $params[] = $search;
        }

        // Section
        if (!empty($filters['section_type'])) {
            $sql .= " AND p.section_type = ?";
            $params[] = $filters['section_type'];
        }

        // USA Advanced Filters
        if ($isUsaSearch) {
            if (!empty($filters['state'])) { $sql .= " AND ud.state = ?"; $params[] = $filters['state']; }
            if (isset($filters['pool']) && $filters['pool'] !== '') { $sql .= " AND ud.pool = ?"; $params[] = (int)$filters['pool']; }
            if (isset($filters['waterfront']) && $filters['waterfront'] !== '') { $sql .= " AND ud.waterfront = ?"; $params[] = (int)$filters['waterfront']; }
            if (isset($filters['is_project']) && $filters['is_project'] !== '') { $sql .= " AND p.is_project = ?"; $params[] = (int)$filters['is_project']; }
        }

        // Terrenos Advanced Filters
        if ($isTerrenoSearch) {
            if (!empty($filters['zona_prc'])) { $sql .= " AND td.zona_prc_edificacion = ?"; $params[] = $filters['zona_prc']; }
            if (!empty($filters['usos_suelo'])) { $sql .= " AND td.usos_suelo LIKE ?"; $params[] = '%' . $filters['usos_suelo'] . '%'; }
            if ($filters['anteproyecto'] === 'con') { $sql .= " AND td.has_anteproyecto = 1"; }
            if ($filters['anteproyecto'] === 'sin') { $sql .= " AND (td.has_anteproyecto = 0 OR td.has_anteproyecto IS NULL)"; }
            if (!empty($filters['min_densidad'])) { $sql .= " AND td.densidad_neta_max_hab_ha >= ?"; $params[] = (float)$filters['min_densidad']; }
            if (!empty($filters['max_densidad'])) { $sql .= " AND td.densidad_neta_max_hab_ha <= ?"; $params[] = (float)$filters['max_densidad']; }
            if (!empty($filters['min_superficie_util'])) { $sql .= " AND td.superficie_util >= ?"; $params[] = (float)$filters['min_superficie_util']; }
            if (!empty($filters['max_superficie_util'])) { $sql .= " AND td.superficie_util <= ?"; $params[] = (float)$filters['max_superficie_util']; }
            if (!empty($filters['min_precio_uf'])) { $sql .= " AND td.precio_uf_m2 >= ?"; $params[] = (float)$filters['min_precio_uf']; }
            if (!empty($filters['max_precio_uf'])) { $sql .= " AND td.precio_uf_m2 <= ?"; $params[] = (float)$filters['max_precio_uf']; }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ? (int)$result['total'] : 0;
    }

    public function getStatusCounts($sectionType = null, $partnerId = null) {
        $statuses = ['Activo', 'Actualizar', 'Oferta', 'Armar', 'Stand By', 'Vendido', 'Eliminar'];
        $counts = [];
        
        $sql = "SELECT COALESCE(status, 'Activo') as status, COUNT(*) as count FROM properties WHERE 1=1";
        $params = [];
        
        if ($sectionType) {
            $sql .= " AND section_type = ?";
            $params[] = $sectionType;
        }
        if ($partnerId) {
            $sql .= " AND partner_id = ?";
            $params[] = $partnerId;
        }
        
        $sql .= " GROUP BY COALESCE(status, 'Activo')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($statuses as $status) {
            $counts[$status] = $results[$status] ?? 0;
        }
        
        return $counts;
    }

    public function getAllForAdmin($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['section_type'])) {
            $sql .= " AND p.section_type = ?";
            $params[] = $filters['section_type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND COALESCE(p.status, 'Activo') = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['partner_id'])) {
            $sql .= " AND p.partner_id = ?";
            $params[] = $filters['partner_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY p.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateStatus($id, $status) {
        $validStatuses = ['Activo', 'Actualizar', 'Oferta', 'Armar', 'Stand By', 'Vendido', 'Eliminar'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = $driver === 'sqlite' ? "datetime('now')" : "NOW()";

        if ($status === 'Vendido') {
            $sql = "UPDATE properties SET status = ?, sold_at = {$now}, updated_at = {$now} WHERE id = ?";
        } else {
            $sql = "UPDATE properties SET status = ?, sold_at = NULL, updated_at = {$now} WHERE id = ?";
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    public function getValidStatuses() {
        return ['Activo', 'Actualizar', 'Oferta', 'Armar', 'Stand By', 'Vendido', 'Eliminar'];
    }
}
