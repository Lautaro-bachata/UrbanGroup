<?php
require_once __DIR__ . '/../config/database.php';

class PropertyDetailsModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByPropertyId($propertyId) {
        $stmt = $this->db->prepare("SELECT * FROM property_details WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        $result = $stmt->fetch();
        
        if ($result) {
            $result['details'] = json_decode($result['details_json'] ?? '[]', true) ?: [];
            $result['features'] = json_decode($result['features_json'] ?? '[]', true) ?: [];
            $result['costs'] = json_decode($result['costs_json'] ?? '[]', true) ?: [];
        }
        
        return $result ?: ['details' => [], 'features' => [], 'costs' => []];
    }

    public function save($propertyId, $data) {
        $stmt = $this->db->prepare("SELECT id FROM property_details WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE property_details SET 
                    property_category = ?,
                    section_type = ?,
                    details_json = ?,
                    features_json = ?,
                    costs_json = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE property_id = ?
            ");
            return $stmt->execute([
                $data['property_category'] ?? null,
                $data['section_type'] ?? 'propiedades',
                json_encode($data['details'] ?? []),
                json_encode($data['features'] ?? []),
                json_encode($data['costs'] ?? []),
                $propertyId
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO property_details (property_id, property_category, section_type, details_json, features_json, costs_json)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $propertyId,
                $data['property_category'] ?? null,
                $data['section_type'] ?? 'propiedades',
                json_encode($data['details'] ?? []),
                json_encode($data['features'] ?? []),
                json_encode($data['costs'] ?? [])
            ]);
        }
    }

    public function delete($propertyId) {
        $stmt = $this->db->prepare("DELETE FROM property_details WHERE property_id = ?");
        return $stmt->execute([$propertyId]);
    }

    public static function getPropertyCategories() {
        return [
            'casa' => 'Casa',
            'departamento' => 'Departamento',
            'oficina' => 'Oficina / Casa Comercial',
            'bodega' => 'Bodega / Galpón',
            'local_comercial' => 'Local Comercial',
            'parcela_con_casa' => 'Parcela con Casa',
            'parcela_sin_casa' => 'Parcela sin Casa / Loteo',
            'terreno_industrial' => 'Terreno Industrial',
            'fundo' => 'Fundo',
            'derechos_llave' => 'Derechos de Llave',
            'terreno_inmobiliario' => 'Terreno Inmobiliario'
        ];
    }

    public static function getSectionTypes() {
        return [
            'propiedades' => 'Propiedades',
            'terrenos' => 'Terrenos Inmobiliarios',
            'activos' => 'Activos Inmobiliarios',
            'usa' => 'Propiedades USA'
        ];
    }

    public static function getCategoryFields($category) {
        $fields = [
            'casa' => [
                'datos' => [
                    'num_pisos' => ['label' => 'Nº de Pisos', 'type' => 'number'],
                    'superficie_construida' => ['label' => 'Superficie Construida (m²)', 'type' => 'number'],
                    'superficie_terreno' => ['label' => 'Superficie Terreno (m²)', 'type' => 'number'],
                    'num_dormitorios' => ['label' => 'Nº Dormitorios', 'type' => 'number'],
                    'num_banos' => ['label' => 'Nº Baños', 'type' => 'number'],
                    'estacionamientos_cubiertos' => ['label' => 'Estacionamientos cubiertos', 'type' => 'number'],
                    'estacionamientos_descubiertos' => ['label' => 'Estacionamientos descubiertos', 'type' => 'number'],
                    'num_bodega' => ['label' => 'Nº Bodega', 'type' => 'number'],
                    'ano_construccion' => ['label' => 'Año de Construcción', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number'],
                    'gastos_comunes' => ['label' => '$ Gastos Comunes', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'amoblada', 'lavavajillas', 'loggia', 'terraza_techada', 'pieza_servicio',
                    'termopaneles', 'generador_electrico', 'piscina', 'quincho', 'gimnasio',
                    'cancha_multiuso', 'aire_acondicionado', 'calefaccion', 'porton_electrico',
                    'camaras_seguridad', 'cerco_electrico', 'alarma', 'recepcion_final',
                    'en_condominio', 'cercano_locomocion'
                ]
            ],
            'departamento' => [
                'datos' => [
                    'num_piso' => ['label' => 'Nº de Piso', 'type' => 'number'],
                    'superficie_util' => ['label' => 'Superficie Útil', 'type' => 'number'],
                    'superficie_terraza' => ['label' => 'Superficie Terraza', 'type' => 'number'],
                    'num_dormitorios' => ['label' => 'Nº Dormitorios', 'type' => 'number'],
                    'num_pieza_servicio' => ['label' => 'Nº Pieza de Servicio', 'type' => 'number'],
                    'num_banos' => ['label' => 'Nº Baños', 'type' => 'number'],
                    'estacionamientos_cubiertos' => ['label' => 'Estacionamientos cubiertos', 'type' => 'number'],
                    'estacionamientos_descubiertos' => ['label' => 'Estacionamientos descubiertos', 'type' => 'number'],
                    'num_bodega' => ['label' => 'Nº Bodega', 'type' => 'number'],
                    'ano_construccion' => ['label' => 'Año de Construcción', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number'],
                    'gastos_comunes' => ['label' => '$ Gastos Comunes', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'amoblada', 'alarma', 'lavavajillas', 'loggia', 'terraza', 'termopaneles',
                    'piscina', 'quincho', 'gimnasio', 'sala_multiuso', 'aire_acondicionado',
                    'calefaccion_central', 'camaras_seguridad', 'conserjeria_24_7',
                    'estacionamientos_visitas', 'cercano_locomocion'
                ]
            ],
            'oficina' => [
                'datos' => [
                    'num_piso' => ['label' => 'Nº de Piso', 'type' => 'number'],
                    'superficie' => ['label' => 'Superficie', 'type' => 'number'],
                    'num_privados' => ['label' => 'Nº Privados', 'type' => 'number'],
                    'num_salas_reuniones' => ['label' => 'Nº Salas de Reuniones', 'type' => 'number'],
                    'num_banos' => ['label' => 'Nº Baños', 'type' => 'number'],
                    'num_estacionamientos' => ['label' => 'Nº Estacionamientos', 'type' => 'number'],
                    'num_bodega' => ['label' => 'Nº Bodega', 'type' => 'number'],
                    'ano_construccion' => ['label' => 'Año Construcción', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number'],
                    'gastos_comunes' => ['label' => '$ Gastos Comunes', 'type' => 'number'],
                    'costo_estacionamiento' => ['label' => '$ Estacionamiento', 'type' => 'number'],
                    'costo_bodega' => ['label' => '$ Bodega', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'amoblada', 'planta_libre', 'habilitada', 'alarma', 'kitchenette',
                    'aire_acondicionado', 'climatizador', 'calefaccion_central', 'certificacion_leed',
                    'sprinklers', 'camaras_seguridad', 'conserjeria_24_7', 'estacionamientos_visitas',
                    'cercano_locomocion'
                ]
            ],
            'bodega' => [
                'datos' => [
                    'superficie_terreno' => ['label' => 'Superficie Terreno', 'type' => 'number'],
                    'superficie_galpon' => ['label' => 'Superficie Galpón', 'type' => 'number'],
                    'altura_galpon' => ['label' => 'Altura Galpón', 'type' => 'number'],
                    'amp_trifasica' => ['label' => 'AMP Trifásica', 'type' => 'number'],
                    'superficie_oficinas' => ['label' => 'Superficie Oficinas', 'type' => 'number'],
                    'num_privados' => ['label' => 'Nº Privados', 'type' => 'number'],
                    'num_salas_reuniones' => ['label' => 'Nº Salas de Reuniones', 'type' => 'number'],
                    'num_banos' => ['label' => 'Nº Baños', 'type' => 'number'],
                    'num_estacionamientos' => ['label' => 'Nº Estacionamientos', 'type' => 'number'],
                    'ano_construccion' => ['label' => 'Año Construcción', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'industria_molesta', 'trifasica', 'alarma', 'camaras_frio', 'rampa_carga',
                    'oficinas_aire_acondicionado', 'cierre_panderetas', 'recepcion_final',
                    'camaras_seguridad', 'en_condominio', 'conserjeria_24_7', 'estacionamientos_visitas',
                    'cercano_locomocion'
                ]
            ],
            'local_comercial' => [
                'datos' => [
                    'num_pisos' => ['label' => 'Nº Pisos', 'type' => 'number'],
                    'superficie' => ['label' => 'Superficie', 'type' => 'number'],
                    'amp_trifasica' => ['label' => 'AMP Trifásica', 'type' => 'number'],
                    'superficie_oficinas' => ['label' => 'Superficie Oficinas', 'type' => 'number'],
                    'num_privados' => ['label' => 'Nº Privados', 'type' => 'number'],
                    'num_salas_reuniones' => ['label' => 'Nº Salas de Reuniones', 'type' => 'number'],
                    'num_banos' => ['label' => 'Nº Baños', 'type' => 'number'],
                    'num_estacionamientos' => ['label' => 'Nº Estacionamientos', 'type' => 'number'],
                    'ano_construccion' => ['label' => 'Año Construcción', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'extraccion_olores', 'trifasica', 'alarma', 'camaras_frio',
                    'oficinas_aire_acondicionado', 'permite_restaurante', 'permite_alcoholes',
                    'recepcion_final', 'camaras_seguridad', 'en_strip_center', 'conserjeria_24_7',
                    'av_alto_flujo'
                ]
            ],
            'parcela_con_casa' => [
                'datos' => [
                    'superficie_terreno' => ['label' => 'Superficie Terreno', 'type' => 'number'],
                    'superficie_construida' => ['label' => 'Superficie Construida', 'type' => 'number'],
                    'num_pisos' => ['label' => 'Nº de Pisos', 'type' => 'number'],
                    'num_dormitorios' => ['label' => 'Nº Dormitorios', 'type' => 'number'],
                    'num_banos' => ['label' => 'Nº Baños', 'type' => 'number'],
                    'estacionamientos_cubiertos' => ['label' => 'Estacionamientos cubiertos', 'type' => 'number'],
                    'estacionamientos_descubiertos' => ['label' => 'Estacionamientos descubiertos', 'type' => 'number'],
                    'num_bodega' => ['label' => 'Nº Bodega', 'type' => 'number'],
                    'ano_construccion' => ['label' => 'Año Construcción', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number'],
                    'gastos_comunes' => ['label' => '$ Gastos Comunes', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'agua_pozo', 'empalme_electricidad', 'alarma', 'recepcion_final',
                    'camaras_seguridad', 'en_condominio', 'conserjeria_24_7', 'av_alto_flujo'
                ]
            ],
            'parcela_sin_casa' => [
                'datos' => [
                    'superficie_terreno' => ['label' => 'Superficie Terreno', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number'],
                    'gastos_comunes' => ['label' => '$ Gastos Comunes', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'bonita_vista', 'apto_agua_pozo', 'apto_empalme_electricidad', 'cierre_perimetral',
                    'camino_asfaltado', 'en_condominio', 'conserjeria_24_7', 'av_alto_flujo'
                ]
            ],
            'terreno_industrial' => [
                'datos' => [
                    'superficie_terreno' => ['label' => 'Superficie Terreno', 'type' => 'number']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number'],
                    'gastos_comunes' => ['label' => '$ Gastos Comunes', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'industria_molesta', 'trifasica', 'alarma', 'cierre_panderetas',
                    'en_condominio', 'conserjeria_24_7', 'cercano_locomocion'
                ]
            ],
            'fundo' => [
                'datos' => [
                    'superficie_terreno' => ['label' => 'Superficie Terreno', 'type' => 'number'],
                    'plantaciones' => ['label' => 'Plantaciones', 'type' => 'text'],
                    'volumen_agua' => ['label' => 'Volumen de Agua', 'type' => 'text']
                ],
                'costos' => [
                    'contribuciones' => ['label' => '$ Contribuciones', 'type' => 'number']
                ],
                'caracteristicas' => [
                    'sistema_riego', 'derechos_agua', 'apto_ganaderia', 'casa_capataz',
                    'cercano_locomocion'
                ]
            ],
            'derechos_llave' => [
                'datos' => [
                    'superficie_local' => ['label' => 'Superficie Local', 'type' => 'number'],
                    'rubro' => ['label' => 'Rubro', 'type' => 'text'],
                    'arriendo_local' => ['label' => '$ Arriendo del Local', 'type' => 'number']
                ],
                'costos' => [],
                'caracteristicas' => [
                    'en_strip_center'
                ]
            ],
            'terreno_inmobiliario' => [
                'datos' => [
                    'superficie_terreno' => ['label' => 'Superficie Terreno', 'type' => 'number'],
                    'rol' => ['label' => 'Rol', 'type' => 'text'],
                    'zonificacion' => ['label' => 'Zonificación', 'type' => 'text'],
                    'usos_permitidos' => ['label' => 'Usos Permitidos', 'type' => 'textarea'],
                    'altura_maxima' => ['label' => 'Altura Máxima', 'type' => 'text'],
                    'densidad_maxima' => ['label' => 'Densidad Máxima', 'type' => 'text'],
                    'coef_constructibilidad' => ['label' => 'Coef. Constructibilidad', 'type' => 'number'],
                    'coef_ocupacion_suelo' => ['label' => 'Coef. Ocupación del Suelo', 'type' => 'number'],
                    'frente_minimo' => ['label' => 'Frente Mínimo', 'type' => 'number'],
                    'fondo_minimo' => ['label' => 'Fondo Mínimo', 'type' => 'number'],
                    'antejardin' => ['label' => 'Antejardín', 'type' => 'number'],
                    'distanciamientos' => ['label' => 'Distanciamientos', 'type' => 'text']
                ],
                'costos' => [],
                'caracteristicas' => [
                    'factibilidad_electrica', 'factibilidad_agua', 'factibilidad_alcantarillado',
                    'factibilidad_gas', 'es_esquinero', 'topografia_regular', 'urbanizado',
                    'cerrado_perimetralmente', 'con_proyecto_preliminar', 'con_anteproyecto',
                    'entrega_inmediata', 'zona_alta_plusvalia'
                ],
                'anteproyecto' => [
                    'superficie_bruta' => ['label' => 'Superficie Bruta', 'type' => 'number'],
                    'superficie_util' => ['label' => 'Superficie Útil', 'type' => 'number'],
                    'superficie_expropiacion' => ['label' => 'Superficie Expropiación', 'type' => 'number'],
                    'descuento_expropiacion' => ['label' => '% Descuento Expropiación', 'type' => 'number'],
                    'num_viviendas_permitidas' => ['label' => 'Nº de Viviendas Permitidas', 'type' => 'number'],
                    'm2_edificacion_maxima' => ['label' => 'm² Edificación Máxima', 'type' => 'number'],
                    'm2_edificacion_util' => ['label' => 'm² Edificación Útil', 'type' => 'number'],
                    'estacionamientos_totales' => ['label' => 'Estacionamientos Totales', 'type' => 'number'],
                    'bicicleteros' => ['label' => 'Bicicleteros', 'type' => 'number'],
                    'locales_comerciales' => ['label' => 'Locales Comerciales', 'type' => 'number'],
                    'bodegas' => ['label' => 'Bodegas', 'type' => 'number'],
                    'comision' => ['label' => 'Comisión (%)', 'type' => 'number']
                ]
            ]
        ];

        return $fields[$category] ?? [];
    }

    public static function getFeaturesOptions() {
        $allFeatures = [];
        $categories = ['casa', 'departamento', 'oficina', 'bodega', 'local_comercial', 
                       'parcela_con_casa', 'parcela_sin_casa', 'terreno_industrial', 
                       'fundo', 'derechos_llave', 'terreno_inmobiliario'];
        
        foreach ($categories as $category) {
            $fields = self::getCategoryFields($category);
            if (!empty($fields['caracteristicas'])) {
                foreach ($fields['caracteristicas'] as $feature) {
                    if (!isset($allFeatures[$feature])) {
                        $allFeatures[$feature] = self::getFeatureLabel($feature);
                    }
                }
            }
        }
        
        return $allFeatures;
    }

    public static function getCostsOptions() {
        $allCosts = [];
        $categories = ['casa', 'departamento', 'oficina', 'bodega', 'local_comercial', 
                       'parcela_con_casa', 'parcela_sin_casa', 'terreno_industrial', 
                       'fundo', 'derechos_llave', 'terreno_inmobiliario'];
        
        foreach ($categories as $category) {
            $fields = self::getCategoryFields($category);
            if (!empty($fields['costos'])) {
                foreach ($fields['costos'] as $key => $data) {
                    if (!isset($allCosts[$key])) {
                        $allCosts[$key] = $data['label'];
                    }
                }
            }
        }
        
        return $allCosts;
    }

    public static function getFeatureLabel($feature) {
        $labels = [
            'amoblada' => 'Amoblada',
            'lavavajillas' => 'Lavavajillas',
            'loggia' => 'Loggia',
            'terraza_techada' => 'Terraza Techada',
            'terraza' => 'Terraza',
            'pieza_servicio' => 'Pieza de Servicio',
            'termopaneles' => 'Termopaneles',
            'generador_electrico' => 'Generador Eléctrico',
            'piscina' => 'Piscina',
            'quincho' => 'Quincho',
            'gimnasio' => 'Gimnasio',
            'cancha_multiuso' => 'Cancha Multiuso',
            'sala_multiuso' => 'Sala Multiuso',
            'aire_acondicionado' => 'Aire Acondicionado',
            'calefaccion' => 'Calefacción',
            'calefaccion_central' => 'Calefacción Central',
            'porton_electrico' => 'Portón Eléctrico',
            'camaras_seguridad' => 'Cámaras de Seguridad',
            'cerco_electrico' => 'Cerco Eléctrico',
            'alarma' => 'Alarma',
            'recepcion_final' => 'Recepción Final',
            'en_condominio' => 'En Condominio',
            'cercano_locomocion' => 'Cercano a Locomoción',
            'conserjeria_24_7' => 'Conserjería 24/7',
            'estacionamientos_visitas' => 'Estacionamientos de Visitas',
            'planta_libre' => 'Planta Libre',
            'habilitada' => 'Habilitada',
            'kitchenette' => 'Kitchenette',
            'climatizador' => 'Climatizador',
            'certificacion_leed' => 'Certificación LEED',
            'sprinklers' => 'Sprinklers',
            'industria_molesta' => 'Industria Molesta',
            'trifasica' => 'Trifásica',
            'camaras_frio' => 'Cámaras de Frío',
            'rampa_carga' => 'Rampa de Carga',
            'oficinas_aire_acondicionado' => 'Oficinas con Aire Acondicionado',
            'cierre_panderetas' => 'Cierre con Panderetas',
            'extraccion_olores' => 'Extracción de Olores',
            'permite_restaurante' => 'Permite Restaurante',
            'permite_alcoholes' => 'Permite Alcoholes',
            'en_strip_center' => 'En Strip Center / C. Comercial',
            'av_alto_flujo' => 'Av. de Alto Flujo',
            'agua_pozo' => 'Agua de Pozo',
            'empalme_electricidad' => 'Empalme Electricidad',
            'bonita_vista' => 'Bonita Vista',
            'apto_agua_pozo' => 'Apto Agua de Pozo',
            'apto_empalme_electricidad' => 'Apto Empalme Electricidad',
            'cierre_perimetral' => 'Cierre Perimetral',
            'camino_asfaltado' => 'Camino Asfaltado',
            'sistema_riego' => 'Sistema de Riego',
            'derechos_agua' => 'Derechos de Agua',
            'apto_ganaderia' => 'Apto Ganadería',
            'casa_capataz' => 'Casa Capataz',
            'factibilidad_electrica' => 'Factibilidad Eléctrica',
            'factibilidad_agua' => 'Factibilidad Agua',
            'factibilidad_alcantarillado' => 'Factibilidad Alcantarillado',
            'factibilidad_gas' => 'Factibilidad Gas',
            'es_esquinero' => 'Es Esquinero',
            'topografia_regular' => 'Topografía Regular',
            'urbanizado' => 'Urbanizado',
            'cerrado_perimetralmente' => 'Cerrado Perimetralmente',
            'con_proyecto_preliminar' => 'Con Proyecto Preliminar',
            'con_anteproyecto' => 'Con Anteproyecto',
            'entrega_inmediata' => 'Entrega Inmediata',
            'zona_alta_plusvalia' => 'En Zona de Alta Plusvalía'
        ];
        
        return $labels[$feature] ?? ucfirst(str_replace('_', ' ', $feature));
    }
}
