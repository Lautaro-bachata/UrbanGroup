<?php
require_once __DIR__ . '/../fpdf/fpdf.php';

class PDF_UrbanGroup extends FPDF
{
    private $sourceUrl = '';
    private $language = 'es';

    private $primary = [30, 30, 30];
    private $accent = [0, 120, 215];
    private $lightGray = [245, 245, 245];

    public function setSourceUrl($url) {
        $this->sourceUrl = $url;
    }

    public function setLanguage($lang) {
        $this->language = $lang;
    }

    private function encodeText($text) {
        if (!is_string($text)) return '';
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
    }

    private function normalizeValue($v) {
        if (!is_string($v) && !is_numeric($v)) return '';
        $s = (string)$v;
        $s = preg_replace("/\r\n|\r|\n/", "\n", $s);
        $s = preg_replace('/\n{2,}/', "\n", $s);
        $s = preg_replace('/[ \t]{2,}/', ' ', $s);
        $s = trim($s);
        return $s;
    }

    private function logPdfWarning($message) {
        try {
            $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $logFile = $logDir . DIRECTORY_SEPARATOR . 'pdf_warnings.log';
            $line = date('c') . " - " . trim($message) . "\n";
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {}
    }

    private function t($es, $en = null) {
        if ($this->language === 'en' && $en !== null) {
            return $en;
        }
        return $es;
    }

    function Header() {
        $this->SetFillColor(250, 250, 250);
        $this->Rect(0, 0, 210, 30, 'F');

        $this->SetFillColor(0, 120, 215);
        $this->Rect(0, 0, 210, 2, 'F');

        $logoPath = $this->resolveLocalPath('../uploads/logo.png');
        $logoFile = null;

        if (!empty($logoPath)) {
            if (preg_match('#^https?://#i', $logoPath)) {
                if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
                    $logoFile = $logoPath;
                }
            } else {
                if (file_exists($logoPath) && is_readable($logoPath)) {
                    $logoFile = $logoPath;
                }
            }
        }

        if (empty($logoFile)) {
            $altCandidates = [
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logo.png',
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logo.png',
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo.png'
            ];
            foreach ($altCandidates as $p) {
                if (file_exists($p) && is_readable($p)) {
                    $logoFile = $p;
                    break;
                }
            }
        }

        if (!empty($logoFile)) {
            $this->Image($logoFile, 170, 6, 30);
        }

        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(0, 120, 215);
        $this->SetXY(10, 8);
        $this->Cell(0, 8, $this->encodeText("URBAN GROUP"), 0, 0, 'L');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(120, 120, 120);
        $this->SetXY(10, 16);
        $this->Cell(0, 5, $this->encodeText($this->t("Portal Inmobiliario Profesional", "Professional Real Estate Portal")), 0, 0, 'L');

        $this->SetY(32);
    }

    function Footer() {
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(10, 278, 200, 278);

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->SetXY(10, 278);
        $leftText = "Urban Group © " . date("Y") . " - " . $this->t("Catalogo de Propiedades", "Property Catalog");
        $this->Cell(80, 6, $this->encodeText($leftText), 0, 0, 'L');

        if (!empty($this->sourceUrl)) {
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(0, 102, 204);
            $this->SetXY(90, 278);
            $this->Cell(20, 6, $this->encodeText($this->t('Ver:', 'View:')), 0, 0, 'L');
            $this->SetTextColor(0, 0, 180);
            $this->SetFont('Arial', 'U', 8);
            $this->SetXY(105, 278);
            $this->Write(6, $this->encodeText($this->sourceUrl), $this->sourceUrl);
            $this->SetTextColor(150, 150, 150);
        }

        $this->SetXY(160, 280);
        $this->SetFont('Arial', '', 8);
        $this->Cell(40, 4, $this->t("Pagina", "Page") . " " . $this->PageNo(), 0, 0, 'R');
    }

    function RenderGallery($photos = [], $max = 4) {
        if (empty($photos) || !is_array($photos)) return;
        $count = min($max, count($photos));
        $marginLeft = 10;
        $usableWidth = 190;
        $gap = 4;
        $slotWidth = ($usableWidth - ($gap * ($count - 1))) / $count;
        $y = $this->GetY();
        $imgHeight = 40;

        for ($i = 0; $i < $count; $i++) {
            $p = $photos[$i];
            $photoUrl = $p['photo_url'] ?? $p['url'] ?? '';
            $xSlot = $marginLeft + ($i * ($slotWidth + $gap));

            $filePath = $this->resolveLocalPath($photoUrl);
            if ($filePath && file_exists($filePath) && is_readable($filePath)) {
                $this->Image($filePath, $xSlot, $y, 0, $imgHeight);
            } else {
                $this->SetFillColor(240, 240, 240);
                $this->Rect($xSlot, $y, $slotWidth, $imgHeight, 'F');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(140, 140, 140);
                $this->SetXY($xSlot, $y + ($imgHeight / 2) - 4);
                $this->Cell($slotWidth, 8, $this->encodeText($this->t('Sin imagen', 'No image')), 0, 0, 'C');
            }
        }

        $this->SetY($y + $imgHeight + 6);
    }

    function RenderCover($property = [], $photos = []) {
        $title = $this->encodeText($this->normalizeValue($property['title'] ?? $property['name'] ?? 'Propiedad'));
        $address = $this->encodeText($this->normalizeValue($property['address'] ?? $property['ubicacion'] ?? ''));
        $price = $this->encodeText($property['price_display'] ?? $property['price'] ?? '');

        $this->AddPage();
        $this->SetY(40);

        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(0, 10, $title, 0, 1, 'C');

        if (!empty($address)) {
            $this->SetFont('Arial', '', 12);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(0, 8, $address, 0, 1, 'C');
        }

        if (!empty($price)) {
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(0, 120, 215);
            $this->Cell(0, 10, $price, 0, 1, 'C');
        }

        if (!empty($photos) && is_array($photos)) {
            $first = $photos[0] ?? null;
            $photoUrl = $first['photo_url'] ?? $first['url'] ?? '';
            $filePath = $this->resolveLocalPath($photoUrl);
            if ($filePath && file_exists($filePath) && is_readable($filePath)) {
                $this->Image($filePath, 30, $this->GetY() + 6, 150, 80);
                $this->SetY($this->GetY() + 80 + 14);
            } else {
                $this->Ln(12);
            }
        } else {
            $this->Ln(18);
        }
    }

    private function resolveLocalPath($url) {
        if (empty($url)) return null;
        $u = trim($url);
        if (strpos($u, '../') === 0) {
            $rel = substr($u, 3);
            return dirname(__DIR__) . DIRECTORY_SEPARATOR . $rel;
        }
        if (strpos($u, '/uploads/') === 0) {
            return dirname(__DIR__) . DIRECTORY_SEPARATOR . substr($u, 1);
        }
        if (preg_match('#^https?://#i', $u)) return $u;
        if (file_exists($u)) return $u;
        return null;
    }

    public function RenderTerrenoDetails($t) {
        if (empty($t) || !is_array($t)) return;
        
        $this->SectionTitle($this->t('DETALLES DEL TERRENO INMOBILIARIO', 'REAL ESTATE LAND DETAILS'));
        
        $this->InfoRow($this->t('Nombre del Proyecto', 'Project Name'), $t['nombre_proyecto'] ?? '');
        $this->InfoRow($this->t('Ubicacion', 'Location'), $t['ubicacion'] ?? '');
        $this->InfoRow($this->t('Ciudad', 'City'), $t['ciudad'] ?? '');
        $this->InfoRow($this->t('Estado', 'Status'), $t['estado'] ?? '');
        $this->InfoRow($this->t('Roles', 'Roles'), $t['roles'] ?? '');
        
        if (!empty($t['usos_suelo_permitidos']) || !empty($t['usos_suelo'])) {
            $this->SectionTitle($this->t('USOS DE SUELO', 'LAND USE'));
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(50, 50, 50);
            $this->MultiCell(0, 5, $this->encodeText($t['usos_suelo_permitidos'] ?? $t['usos_suelo'] ?? ''), 0, 'L');
            $this->Ln(2);
        }
        
        $this->SectionTitle($this->t('NORMATIVA URBANISTICA', 'URBAN REGULATIONS'));
        $this->TwoColumnRow($this->t('Zona PRC', 'PRC Zone'), $t['zona_prc_edificacion'] ?? '', 
                           $this->t('Sist. Agrupamiento', 'Grouping System'), $t['sistema_agrupamiento'] ?? '');
        $this->TwoColumnRow($this->t('Altura Maxima', 'Max Height'), $t['altura_maxima'] ?? '', 
                           $this->t('Rasante', 'Slope'), $t['rasante'] ?? '');
        $this->TwoColumnRow($this->t('Coef. Constructibilidad', 'Buildability Coef.'), $t['coef_constructibilidad_max'] ?? '', 
                           $this->t('Coef. Ocupacion Suelo', 'Land Occupation Coef.'), $t['coef_ocupacion_suelo_max'] ?? '');
        $this->TwoColumnRow($this->t('Area Libre Min.', 'Min Free Area'), $t['coef_area_libre_min'] ?? '', 
                           $this->t('Antejardin Min.', 'Min Front Garden'), $t['antejardin_min'] ?? '');
        $this->InfoRow($this->t('Distanciamientos', 'Setbacks'), $t['distanciamientos'] ?? '');
        $this->InfoRow($this->t('Articulos Normativos', 'Regulatory Articles'), $t['articulos_normativos'] ?? '');
        
        $this->SectionTitle($this->t('SUPERFICIES DEL TERRENO', 'LAND SURFACES'));
        $this->TwoColumnRow($this->t('Frente', 'Front'), ($t['frente'] ?? '') . ' m', 
                           $this->t('Fondo', 'Depth'), ($t['fondo'] ?? '') . ' m');
        $this->TwoColumnRow($this->t('Superficie Bruta', 'Gross Surface'), ($t['superficie_bruta'] ?? $t['sin_superficie_bruta'] ?? '') . ' m²', 
                           $this->t('Superficie Util', 'Usable Surface'), ($t['superficie_util'] ?? $t['sin_superficie_util'] ?? '') . ' m²');
        $this->TwoColumnRow($this->t('Superficie Total', 'Total Surface'), ($t['superficie_total_terreno'] ?? '') . ' m²', 
                           $this->t('Expropiacion', 'Expropriation'), ($t['expropiacion'] ?? $t['sin_superficie_expropiacion'] ?? '') . ' m²');
        $this->InfoRow($this->t('Superficie Predial Minima', 'Min Property Surface'), ($t['superficie_predial_min'] ?? '') . ' m²');
        
        $this->SectionTitle($this->t('DENSIDADES', 'DENSITIES'));
        $this->TwoColumnRow($this->t('Densidad Bruta Max (hab/ha)', 'Max Gross Density (inhab/ha)'), $t['densidad_bruta_max_hab_ha'] ?? '', 
                           $this->t('Densidad Bruta Max (viv/ha)', 'Max Gross Density (units/ha)'), $t['densidad_bruta_max_viv_ha'] ?? '');
        $this->TwoColumnRow($this->t('Densidad Neta Max (hab/ha)', 'Max Net Density (inhab/ha)'), $t['densidad_neta_max_hab_ha'] ?? '', 
                           $this->t('Densidad Neta Max (viv/ha)', 'Max Net Density (units/ha)'), $t['densidad_neta_max_viv_ha'] ?? '');
        $this->TwoColumnRow($this->t('Densidad Neta', 'Net Density'), $t['densidad_neta'] ?? '', 
                           $this->t('Densidad Maxima', 'Max Density'), $t['densidad_maxima'] ?? '');
        
        $this->SectionTitle($this->t('PERMISOS Y FECHAS', 'PERMITS AND DATES'));
        $this->TwoColumnRow($this->t('Fecha Permiso Edificacion', 'Building Permit Date'), $t['fecha_permiso_edificacion'] ?? '', 
                           $this->t('Fecha CIP', 'CIP Date'), $t['fecha_cip'] ?? '');
        
        if (!empty($t['has_anteproyecto'])) {
            $this->SectionTitle($this->t('ANTEPROYECTO APROBADO', 'APPROVED PRELIMINARY PROJECT'));
            $this->TwoColumnRow($this->t('Viviendas', 'Housing Units'), $t['num_viviendas'] ?? '', 
                               $this->t('Estacionamientos', 'Parking Spaces'), $t['num_estacionamientos'] ?? '');
            $this->TwoColumnRow($this->t('Est. Visitas', 'Visitor Parking'), $t['num_est_visitas'] ?? '', 
                               $this->t('Est. Bicicletas', 'Bicycle Parking'), $t['num_est_bicicletas'] ?? '');
            $this->TwoColumnRow($this->t('Locales Comerciales', 'Commercial Spaces'), $t['num_locales_comerciales'] ?? '', 
                               $this->t('Bodegas', 'Storage Units'), $t['num_bodegas'] ?? '');
            $this->TwoColumnRow($this->t('Superficie Edificada', 'Built Surface'), ($t['superficie_edificada'] ?? '') . ' m²', 
                               $this->t('Sup. Util Anteproyecto', 'Usable Surface Prelim.'), ($t['superficie_util_anteproyecto'] ?? '') . ' m²');
            
            if (!empty($t['ap_bajo_util']) || !empty($t['ap_sobre_util']) || !empty($t['ap_total_util'])) {
                $this->SectionTitle($this->t('SUPERFICIES APROBADAS ANTEPROYECTO', 'APPROVED PRELIMINARY PROJECT SURFACES'));
                $this->SetFont('Arial', 'B', 9);
                $this->SetFillColor(240, 248, 255);
                $this->Cell(63, 6, '', 1, 0, 'C', true);
                $this->Cell(42, 6, $this->encodeText($this->t('Util', 'Usable')), 1, 0, 'C', true);
                $this->Cell(42, 6, $this->encodeText($this->t('Comun', 'Common')), 1, 0, 'C', true);
                $this->Cell(43, 6, $this->encodeText($this->t('Total', 'Total')), 1, 1, 'C', true);
                
                $this->SetFont('Arial', '', 9);
                $this->Cell(63, 6, $this->encodeText($this->t('Edificada Bajo Terreno', 'Built Below Ground')), 1, 0, 'L');
                $this->Cell(42, 6, $this->encodeText(($t['ap_bajo_util'] ?? '') . ' m²'), 1, 0, 'C');
                $this->Cell(42, 6, $this->encodeText(($t['ap_bajo_comun'] ?? '') . ' m²'), 1, 0, 'C');
                $this->Cell(43, 6, $this->encodeText(($t['ap_bajo_total'] ?? '') . ' m²'), 1, 1, 'C');
                
                $this->Cell(63, 6, $this->encodeText($this->t('Edificada Sobre Terreno', 'Built Above Ground')), 1, 0, 'L');
                $this->Cell(42, 6, $this->encodeText(($t['ap_sobre_util'] ?? '') . ' m²'), 1, 0, 'C');
                $this->Cell(42, 6, $this->encodeText(($t['ap_sobre_comun'] ?? '') . ' m²'), 1, 0, 'C');
                $this->Cell(43, 6, $this->encodeText(($t['ap_sobre_total'] ?? '') . ' m²'), 1, 1, 'C');
                
                $this->SetFont('Arial', 'B', 9);
                $this->Cell(63, 6, $this->encodeText($this->t('Edificada Total', 'Total Built')), 1, 0, 'L');
                $this->Cell(42, 6, $this->encodeText(($t['ap_total_util'] ?? '') . ' m²'), 1, 0, 'C');
                $this->Cell(42, 6, $this->encodeText(($t['ap_total_comun'] ?? '') . ' m²'), 1, 0, 'C');
                $this->Cell(43, 6, $this->encodeText(($t['ap_total_total'] ?? '') . ' m²'), 1, 1, 'C');
                $this->Ln(3);
            }
        }
        
        $this->SectionTitle($this->t('INFORMACION COMERCIAL', 'COMMERCIAL INFORMATION'));
        $this->TwoColumnRow($this->t('Precio', 'Price'), $t['precio'] ?? '', 
                           $this->t('Precio UF/m²', 'Price UF/m²'), $t['precio_uf_m2'] ?? '');
        $this->InfoRow($this->t('Comision', 'Commission'), $t['comision'] ?? '');
        
        if (!empty($t['video_url'])) {
            $this->InfoRow($this->t('Video', 'Video'), $t['video_url']);
        }
        
        if (!empty($t['observaciones'])) {
            $this->SectionTitle($this->t('OBSERVACIONES', 'OBSERVATIONS'));
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(50, 50, 50);
            $this->MultiCell(0, 5, $this->encodeText($t['observaciones']), 0, 'L');
            $this->Ln(2);
        }
    }

    public function RenderUSADetails($u, $property = []) {
        if (empty($u) || !is_array($u)) return;
        
        $isEnglish = $this->language === 'en';
        
        $this->SectionTitle($this->t('DETALLES DE PROPIEDAD USA', 'USA PROPERTY DETAILS'));
        
        $this->TwoColumnRow($this->t('Ciudad', 'City'), $u['city'] ?? '', 
                           $this->t('Estado', 'State'), $u['state'] ?? '');
        $this->TwoColumnRow($this->t('Codigo Postal', 'ZIP Code'), $u['zip_code'] ?? '', 
                           $this->t('MLS ID', 'MLS ID'), $u['mls_id'] ?? '');
        
        $this->SectionTitle($this->t('INFORMACION FINANCIERA', 'FINANCIAL INFORMATION'));
        $priceUsd = !empty($u['price_usd']) ? '$' . number_format($u['price_usd'], 0, ',', '.') . ' USD' : '';
        $this->InfoRow($this->t('Precio', 'Price'), $priceUsd);
        $hoaFee = !empty($u['hoa_fee']) ? '$' . number_format($u['hoa_fee'], 2, '.', ',') . ' USD/' . $this->t('mes', 'month') : '';
        $this->TwoColumnRow($this->t('HOA Fee', 'HOA Fee'), $hoaFee, 
                           $this->t('Impuesto Propiedad', 'Property Tax'), !empty($u['property_tax']) ? '$' . number_format($u['property_tax'], 2, '.', ',') . ' USD/' . $this->t('año', 'year') : '');
        
        $this->SectionTitle($this->t('CARACTERISTICAS FISICAS', 'PHYSICAL CHARACTERISTICS'));
        $surfaceSqft = !empty($u['surface_sqft']) ? number_format($u['surface_sqft'], 0) . ' sqft' : '';
        $lotSizeSqft = !empty($u['lot_size_sqft']) ? number_format($u['lot_size_sqft'], 0) . ' sqft' : '';
        $this->TwoColumnRow($this->t('Superficie', 'Surface Area'), $surfaceSqft, 
                           $this->t('Tamaño del Lote', 'Lot Size'), $lotSizeSqft);
        $this->TwoColumnRow($this->t('Año de Construccion', 'Year Built'), $u['year_built'] ?? '', 
                           $this->t('Pisos', 'Stories'), $u['stories'] ?? '');
        $this->TwoColumnRow($this->t('Estacionamientos', 'Garage Spaces'), $u['garage_spaces'] ?? '', 
                           $this->t('Piscina', 'Pool'), !empty($u['pool']) ? $this->t('Si', 'Yes') : $this->t('No', 'No'));
        $this->TwoColumnRow($this->t('Frente al Agua', 'Waterfront'), !empty($u['waterfront']) ? $this->t('Si', 'Yes') : $this->t('No', 'No'), 
                           $this->t('Tipo de Vista', 'View Type'), $u['view_type'] ?? '');
        
        $this->SectionTitle($this->t('SISTEMAS Y ACABADOS', 'SYSTEMS AND FINISHES'));
        $this->TwoColumnRow($this->t('Calefaccion', 'Heating'), $u['heating'] ?? '', 
                           $this->t('Refrigeracion', 'Cooling'), $u['cooling'] ?? '');
        $this->InfoRow($this->t('Pisos', 'Flooring'), $u['flooring'] ?? '');
        $this->InfoRow($this->t('Electrodomesticos', 'Appliances'), $u['appliances'] ?? '');
        
        if (!empty($u['exterior_features'])) {
            $this->SectionTitle($this->t('CARACTERISTICAS EXTERIORES', 'EXTERIOR FEATURES'));
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(50, 50, 50);
            $this->MultiCell(0, 5, $this->encodeText($u['exterior_features']), 0, 'L');
            $this->Ln(2);
        }
        
        if (!empty($u['interior_features'])) {
            $this->SectionTitle($this->t('CARACTERISTICAS INTERIORES', 'INTERIOR FEATURES'));
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(50, 50, 50);
            $this->MultiCell(0, 5, $this->encodeText($u['interior_features']), 0, 'L');
            $this->Ln(2);
        }
        
        if (!empty($u['community_features'])) {
            $this->SectionTitle($this->t('CARACTERISTICAS DE LA COMUNIDAD', 'COMMUNITY FEATURES'));
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(50, 50, 50);
            $this->MultiCell(0, 5, $this->encodeText($u['community_features']), 0, 'L');
            $this->Ln(2);
        }
        
        if (!empty($u['is_project'])) {
            $this->SectionTitle($this->t('INFORMACION DEL PROYECTO', 'PROJECT INFORMATION'));
            $this->TwoColumnRow($this->t('Unidades del Proyecto', 'Project Units'), $u['project_units'] ?? '', 
                               $this->t('Desarrollador', 'Developer'), $u['project_developer'] ?? '');
            $this->InfoRow($this->t('Fecha de Terminacion', 'Completion Date'), $u['project_completion_date'] ?? '');
            if (!empty($u['project_amenities'])) {
                $this->InfoRow($this->t('Amenidades del Proyecto', 'Project Amenities'), $u['project_amenities']);
            }
        }
        
        $this->SectionTitle($this->t('CONTACTO', 'CONTACT'));
        if (!empty($u['whatsapp_number'])) {
            $this->InfoRow('WhatsApp', $u['whatsapp_number']);
        }
    }

    public function RenderActivoDetails($d, $property = []) {
        if (empty($d) || !is_array($d)) return;
        
        $this->SectionTitle($this->t('DETALLES DEL ACTIVO INMOBILIARIO', 'REAL ESTATE ASSET DETAILS'));
        
        if (!empty($d['property_category'])) {
            $this->InfoRow($this->t('Categoria', 'Category'), $d['property_category']);
        }
        if (!empty($d['brand'])) {
            $this->InfoRow($this->t('Marca', 'Brand'), $d['brand']);
        }
        if (!empty($d['asset_condition'])) {
            $this->InfoRow($this->t('Condicion', 'Condition'), $d['asset_condition']);
        }
        if (!empty($d['year'])) {
            $this->InfoRow($this->t('Año', 'Year'), $d['year']);
        }
        if (!empty($d['model'])) {
            $this->InfoRow($this->t('Modelo', 'Model'), $d['model']);
        }
        if (!empty($d['serial_number'])) {
            $this->InfoRow($this->t('Numero de Serie', 'Serial Number'), $d['serial_number']);
        }
        if (!empty($d['warranty'])) {
            $this->InfoRow($this->t('Garantia', 'Warranty'), $d['warranty']);
        }
        if (!empty($d['maintenance_cost'])) {
            $this->InfoRow($this->t('Costo de Mantenimiento', 'Maintenance Cost'), $d['maintenance_cost']);
        }
        if (!empty($d['income_potential'])) {
            $this->InfoRow($this->t('Potencial de Ingresos', 'Income Potential'), $d['income_potential']);
        }
        if (!empty($d['occupancy_rate'])) {
            $this->InfoRow($this->t('Tasa de Ocupacion', 'Occupancy Rate'), $d['occupancy_rate'] . '%');
        }
        if (!empty($d['cap_rate'])) {
            $this->InfoRow($this->t('Cap Rate', 'Cap Rate'), $d['cap_rate'] . '%');
        }
        if (!empty($d['net_operating_income'])) {
            $this->InfoRow($this->t('Ingreso Operativo Neto', 'Net Operating Income'), $d['net_operating_income']);
        }
        
        if (!empty($d['description'])) {
            $this->SectionTitle($this->t('DESCRIPCION DEL ACTIVO', 'ASSET DESCRIPTION'));
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(50, 50, 50);
            $this->MultiCell(0, 5, $this->encodeText($d['description']), 0, 'L');
            $this->Ln(2);
        }
        
        if (!empty($d['additional_info'])) {
            $this->SectionTitle($this->t('INFORMACION ADICIONAL', 'ADDITIONAL INFORMATION'));
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(50, 50, 50);
            $this->MultiCell(0, 5, $this->encodeText($d['additional_info']), 0, 'L');
            $this->Ln(2);
        }
    }

    public function RenderPropertyLink($url) {
        if (empty($url)) return;
        
        $this->Ln(5);
        $this->SetFillColor(240, 248, 255);
        $this->Rect(10, $this->GetY(), 190, 12, 'F');
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 120, 215);
        $this->SetXY(12, $this->GetY() + 2);
        $this->Cell(40, 8, $this->encodeText($this->t('Ver propiedad online:', 'View property online:')), 0, 0, 'L');
        
        $this->SetFont('Arial', 'U', 10);
        $this->SetTextColor(0, 0, 180);
        $this->Write(8, $this->encodeText($url), $url);
        
        $this->Ln(15);
    }

    function SectionTitle($text) {
        $this->Ln(4);

        $this->SetFillColor(240, 248, 255);
        $this->Rect(10, $this->GetY(), 190, 8, 'F');

        $this->SetFillColor(0, 120, 215);
        $this->Rect(10, $this->GetY(), 3, 8, 'F');

        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 120, 215);
        $this->SetXY(14, $this->GetY());
        $this->Cell(0, 8, $this->encodeText($text), 0, 1);

        $this->Ln(2);
    }

    function InfoRow($label, $value) {
        $val = $this->normalizeValue($value);
        if ($val === '') return;

        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(70, 6, $this->encodeText($label . ":"), 0, 0);

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(40, 40, 40);
        $this->MultiCell(0, 6, $this->encodeText($val), 0, 'L');
    }

    function TwoColumnRow($label1, $value1, $label2, $value2) {
        $x = $this->GetX();
        $y = $this->GetY();
        $v1 = $this->normalizeValue($value1);
        $v2 = $this->normalizeValue($value2);
        if ($v1 === '' && $v2 === '') return;

        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(80, 80, 80);
        $this->SetXY($x, $y);
        $this->Cell(40, 6, $this->encodeText($label1 . ":"), 0, 0);

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(40, 40, 40);
        $this->SetXY($x + 50, $y);
        $this->Cell(40, 6, $this->encodeText($v1), 0, 0);

        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(80, 80, 80);
        $this->SetXY($x + 100, $y);
        $this->Cell(40, 6, $this->encodeText($label2 . ":"), 0, 0);

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(40, 40, 40);
        $this->SetXY($x + 150, $y);
        $this->Cell(0, 6, $this->encodeText($v2), 0, 1);

        $this->Ln(2);
    }

    function FeaturesList($features) {
        if (!$features || !is_array($features)) return;

        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(50, 50, 50);

        foreach ($features as $feature) {
            $this->SetXY(15, $this->GetY());
            $this->Cell(5, 6, "*", 0, 0);
            $this->SetXY(20, $this->GetY());
            $this->MultiCell(180, 6, $this->encodeText($feature), 0, 'L');
        }

        $this->Ln(2);
    }
}
