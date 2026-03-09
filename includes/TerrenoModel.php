<?php
require_once __DIR__ . '/../config/database.php';

class TerrenoModel {
    private $db;

    private $allowedFields = [
        'nombre_proyecto', 'ubicacion', 'usos_suelo_permitidos',
        'roles', 'fecha_permiso_edificacion', 'zona_prc_edificacion',
        'usos_suelo', 'sistema_agrupamiento', 'altura_maxima', 'rasante',
        'coef_constructibilidad_max', 'coef_ocupacion_suelo_max', 'coef_area_libre_min',
        'antejardin_min', 'distanciamientos', 'articulos_normativos',
        'frente', 'fondo', 'superficie_util', 'superficie_bruta', 'expropiacion',
        'num_viviendas', 'superficie_edificada', 'superficie_util_anteproyecto',
        'densidad_neta', 'densidad_maxima', 'num_estacionamientos',
        'num_est_visitas', 'num_est_bicicletas', 'num_locales_comerciales', 'num_bodegas', 'superficies_aprobadas',
        'precio', 'comision', 'observaciones', 'pdf_documento',
        'has_anteproyecto',
        'estado', 'ciudad', 'fecha_cip',
        'densidad_bruta_max_hab_ha', 'densidad_bruta_max_viv_ha',
        'densidad_neta_max_hab_ha', 'densidad_neta_max_viv_ha',
        'superficie_predial_min', 'precio_uf_m2', 'video_url',
        'archivo_adjunto_1', 'archivo_adjunto_2', 'archivo_adjunto_3', 'archivo_adjunto_4', 'archivo_adjunto_5',
        'info_solicitada',
        'ap_bajo_util', 'ap_bajo_comun', 'ap_bajo_total',
        'ap_sobre_util', 'ap_sobre_comun', 'ap_sobre_total',
        'ap_total_util', 'ap_total_comun', 'ap_total_total'
    ];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getDetailsByPropertyId($propertyId) {
        $stmt = $this->db->prepare("SELECT * FROM property_terreno_details WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        return $stmt->fetch();
    }

    public function createOrUpdate($propertyId, $data) {
        $existing = $this->getDetailsByPropertyId($propertyId);
        
        if ($existing) {
            return $this->update($propertyId, $data);
        } else {
            return $this->create($propertyId, $data);
        }
    }

    private $numericFields = [
        'altura_maxima', 'coef_area_libre_min', 'frente', 'fondo',
        'superficie_util', 'superficie_bruta', 'expropiacion', 'superficie_predial_min',
        'densidad_neta', 'densidad_maxima', 'densidad_bruta_max_hab_ha', 'densidad_bruta_max_viv_ha',
        'densidad_neta_max_hab_ha', 'densidad_neta_max_viv_ha', 'superficie_edificada',
        'superficie_util_anteproyecto', 'precio', 'precio_uf_m2', 'comision',
        'ap_bajo_util', 'ap_bajo_comun', 'ap_bajo_total', 'ap_sobre_util', 'ap_sobre_comun',
        'ap_sobre_total', 'ap_total_util', 'ap_total_comun', 'ap_total_total'
    ];

    private $integerFields = [
        'num_viviendas', 'num_estacionamientos', 'num_est_visitas', 'num_est_bicicletas',
        'num_locales_comerciales', 'num_bodegas', 'has_anteproyecto', 'info_solicitada'
    ];

    private function castValue($field, $value) {
        if ($value === '' || $value === null) {
            return null;
        }
        if (in_array($field, $this->numericFields)) {
            return (float)$value;
        }
        if (in_array($field, $this->integerFields)) {
            return (int)$value;
        }
        return $value;
    }

    public function create($propertyId, $data) {
        $fields = ['property_id'];
        $placeholders = ['?'];
        $values = [$propertyId];

        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $this->castValue($field, $data[$field]);
            }
        }

        $sql = "INSERT INTO property_terreno_details (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function update($propertyId, $data) {
        $fields = [];
        $params = [];

        foreach ($this->allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $this->castValue($field, $data[$field]);
            }
        }

        if (empty($fields)) return true;

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $propertyId;

        $sql = "UPDATE property_terreno_details SET " . implode(', ', $fields) . " WHERE property_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($propertyId) {
        $stmt = $this->db->prepare("DELETE FROM property_terreno_details WHERE property_id = ?");
        return $stmt->execute([$propertyId]);
    }

    public function getTerrenos($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name,
                       td.*
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
                LEFT JOIN property_terreno_details td ON p.id = td.property_id
                WHERE p.is_active = 1 AND p.section_type = 'terrenos'";

        $params = [];

        // Note: estado filter removed - column does not exist in current schema

        if (!empty($filters['has_anteproyecto'])) {
            $sql .= " AND td.has_anteproyecto = 1";
        }

        if (!empty($filters['anteproyecto_filter'])) {
            if ($filters['anteproyecto_filter'] === 'con') {
                $sql .= " AND td.has_anteproyecto = 1";
            } elseif ($filters['anteproyecto_filter'] === 'sin') {
                $sql .= " AND (td.has_anteproyecto = 0 OR td.has_anteproyecto IS NULL)";
            }
        }

        if (!empty($filters['region_id'])) {
            $sql .= " AND p.region_id = ?";
            $params[] = $filters['region_id'];
        }

        if (!empty($filters['comuna_id'])) {
            $sql .= " AND p.comuna_id = ?";
            $params[] = $filters['comuna_id'];
        }

        if (!empty($filters['zona_prc_edificacion'])) {
            $sql .= " AND td.zona_prc_edificacion = ?";
            $params[] = $filters['zona_prc_edificacion'];
        }

        if (!empty($filters['usos_suelo'])) {
            $sql .= " AND td.usos_suelo LIKE ?";
            $params[] = '%' . $filters['usos_suelo'] . '%';
        }

        if (!empty($filters['min_densidad_bruta_hab'])) {
            $sql .= " AND td.densidad_bruta_max_hab_ha >= ?";
            $params[] = (float)$filters['min_densidad_bruta_hab'];
        }

        if (!empty($filters['max_densidad_bruta_hab'])) {
            $sql .= " AND td.densidad_bruta_max_hab_ha <= ?";
            $params[] = (float)$filters['max_densidad_bruta_hab'];
        }

        if (!empty($filters['min_densidad_neta_hab'])) {
            $sql .= " AND td.densidad_neta_max_hab_ha >= ?";
            $params[] = (float)$filters['min_densidad_neta_hab'];
        }

        if (!empty($filters['max_densidad_neta_hab'])) {
            $sql .= " AND td.densidad_neta_max_hab_ha <= ?";
            $params[] = (float)$filters['max_densidad_neta_hab'];
        }

        if (!empty($filters['min_superficie_util'])) {
            $sql .= " AND td.superficie_util >= ?";
            $params[] = (float)$filters['min_superficie_util'];
        }

        if (!empty($filters['max_superficie_util'])) {
            $sql .= " AND td.superficie_util <= ?";
            $params[] = (float)$filters['max_superficie_util'];
        }

        if (!empty($filters['min_precio_uf_m2'])) {
            $sql .= " AND td.precio_uf_m2 >= ?";
            $params[] = (float)$filters['min_precio_uf_m2'];
        }

        if (!empty($filters['max_precio_uf_m2'])) {
            $sql .= " AND td.precio_uf_m2 <= ?";
            $params[] = (float)$filters['max_precio_uf_m2'];
        }

        if (!empty($filters['min_price'])) {
            $sql .= " AND p.price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= " AND p.price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['min_area'])) {
            $sql .= " AND p.total_area >= ?";
            $params[] = (float)$filters['min_area'];
        }

        if (!empty($filters['max_area'])) {
            $sql .= " AND p.total_area <= ?";
            $params[] = (float)$filters['max_area'];
        }

        $sql .= " ORDER BY p.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countTerrenos($filters = []) {
        $sql = "SELECT COUNT(*) as total
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
                LEFT JOIN property_terreno_details td ON p.id = td.property_id
                WHERE p.is_active = 1 AND p.section_type = 'terrenos'";

        $params = [];

        if (!empty($filters['has_anteproyecto'])) {
            $sql .= " AND td.has_anteproyecto = 1";
        }

        if (!empty($filters['anteproyecto_filter'])) {
            if ($filters['anteproyecto_filter'] === 'con') {
                $sql .= " AND td.has_anteproyecto = 1";
            } elseif ($filters['anteproyecto_filter'] === 'sin') {
                $sql .= " AND (td.has_anteproyecto = 0 OR td.has_anteproyecto IS NULL)";
            }
        }

        if (!empty($filters['region_id'])) {
            $sql .= " AND p.region_id = ?";
            $params[] = $filters['region_id'];
        }

        if (!empty($filters['comuna_id'])) {
            $sql .= " AND p.comuna_id = ?";
            $params[] = $filters['comuna_id'];
        }

        if (!empty($filters['zona_prc_edificacion'])) {
            $sql .= " AND td.zona_prc_edificacion = ?";
            $params[] = $filters['zona_prc_edificacion'];
        }

        if (!empty($filters['usos_suelo'])) {
            $sql .= " AND td.usos_suelo LIKE ?";
            $params[] = '%' . $filters['usos_suelo'] . '%';
        }

        if (!empty($filters['min_densidad_bruta_hab'])) {
            $sql .= " AND td.densidad_bruta_max_hab_ha >= ?";
            $params[] = (float)$filters['min_densidad_bruta_hab'];
        }

        if (!empty($filters['max_densidad_bruta_hab'])) {
            $sql .= " AND td.densidad_bruta_max_hab_ha <= ?";
            $params[] = (float)$filters['max_densidad_bruta_hab'];
        }

        if (!empty($filters['min_superficie_util'])) {
            $sql .= " AND td.superficie_util >= ?";
            $params[] = (float)$filters['min_superficie_util'];
        }

        if (!empty($filters['max_superficie_util'])) {
            $sql .= " AND td.superficie_util <= ?";
            $params[] = (float)$filters['max_superficie_util'];
        }

        if (!empty($filters['min_precio_uf_m2'])) {
            $sql .= " AND td.precio_uf_m2 >= ?";
            $params[] = (float)$filters['min_precio_uf_m2'];
        }

        if (!empty($filters['max_precio_uf_m2'])) {
            $sql .= " AND td.precio_uf_m2 <= ?";
            $params[] = (float)$filters['max_precio_uf_m2'];
        }

        if (!empty($filters['min_price'])) {
            $sql .= " AND p.price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= " AND p.price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['min_area'])) {
            $sql .= " AND p.total_area >= ?";
            $params[] = (float)$filters['min_area'];
        }

        if (!empty($filters['max_area'])) {
            $sql .= " AND p.total_area <= ?";
            $params[] = (float)$filters['max_area'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (p.title LIKE ? OR p.address LIKE ? OR p.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? (int)$row['total'] : 0;
    }

    public function getDistinctZonasPRC() {
        $sql = "SELECT DISTINCT td.zona_prc_edificacion 
                FROM property_terreno_details td 
                INNER JOIN properties p ON td.property_id = p.id 
                WHERE p.is_active = 1 AND p.section_type = 'terrenos' 
                AND td.zona_prc_edificacion IS NOT NULL AND td.zona_prc_edificacion != ''
                ORDER BY td.zona_prc_edificacion";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctUsosSuelo() {
        $sql = "SELECT DISTINCT td.usos_suelo 
                FROM property_terreno_details td 
                INNER JOIN properties p ON td.property_id = p.id 
                WHERE p.is_active = 1 AND p.section_type = 'terrenos' 
                AND td.usos_suelo IS NOT NULL AND td.usos_suelo != ''
                ORDER BY td.usos_suelo";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getZonasPRCByComuna($comunaId) {
        $sql = "SELECT DISTINCT td.zona_prc_edificacion 
                FROM property_terreno_details td 
                INNER JOIN properties p ON td.property_id = p.id 
                WHERE p.is_active = 1 AND p.section_type = 'terrenos' 
                AND p.comuna_id = ?
                AND td.zona_prc_edificacion IS NOT NULL AND td.zona_prc_edificacion != ''
                ORDER BY td.zona_prc_edificacion";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$comunaId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getUsosSueloByComuna($comunaId) {
        $sql = "SELECT DISTINCT td.usos_suelo 
                FROM property_terreno_details td 
                INNER JOIN properties p ON td.property_id = p.id 
                WHERE p.is_active = 1 AND p.section_type = 'terrenos' 
                AND p.comuna_id = ?
                AND td.usos_suelo IS NOT NULL AND td.usos_suelo != ''
                ORDER BY td.usos_suelo";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$comunaId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getActivosInmobiliarios($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
                WHERE p.is_active = 1 AND p.section_type = 'activos'";

        $params = [];

        if (!empty($filters['operation_type'])) {
            $sql .= " AND p.operation_type = ?";
            $params[] = $filters['operation_type'];
        }

        if (!empty($filters['property_type'])) {
            $sql .= " AND p.property_type = ?";
            $params[] = $filters['property_type'];
        }

        if (!empty($filters['region_id'])) {
            $sql .= " AND p.region_id = ?";
            $params[] = $filters['region_id'];
        }

        if (!empty($filters['comuna_id'])) {
            $sql .= " AND p.comuna_id = ?";
            $params[] = $filters['comuna_id'];
        }

        if (!empty($filters['min_price'])) {
            $sql .= " AND p.price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= " AND p.price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['min_area'])) {
            $sql .= " AND p.total_area >= ?";
            $params[] = (float)$filters['min_area'];
        }

        if (!empty($filters['max_area'])) {
            $sql .= " AND p.total_area <= ?";
            $params[] = (float)$filters['max_area'];
        }

        if (!empty($filters['bedrooms'])) {
            $sql .= " AND p.bedrooms >= ?";
            $params[] = (int)$filters['bedrooms'];
        }

        if (!empty($filters['bathrooms'])) {
            $sql .= " AND p.bathrooms >= ?";
            $params[] = (int)$filters['bathrooms'];
        }

        $sql .= " ORDER BY p.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getUSAProperties($filters = [], $limit = null, $offset = 0) {
        $sql = "SELECT p.*, 
                       c.name as comuna_name, 
                       r.name as region_name
                FROM properties p 
                LEFT JOIN comunas c ON p.comuna_id = c.id 
                LEFT JOIN regions r ON p.region_id = r.id
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

        $sql .= " ORDER BY p.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAllowedFields() {
        return $this->allowedFields;
    }
}
