<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/PropertyModel.php';
require_once __DIR__ . '/../../includes/PDF.php';
require_once __DIR__ . '/../../includes/PhotoModel.php';
require_once __DIR__ . '/../../includes/TerrenoModel.php';
require_once __DIR__ . '/../../includes/USAModel.php';
require_once __DIR__ . '/../../includes/PropertyDetailsModel.php';
require_once __DIR__ . '/../../includes/ActivoModel.php';

function convertToISO($text) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'es';

if ($id <= 0) { die("ID invalido"); }

$propertyModel = new PropertyModel();
$property = $propertyModel->getById($id);

if (!$property) { die("Propiedad no encontrada"); }

$sectionType = $property['section_type'] ?? 'propiedades';

if ($sectionType === 'usa' && $lang === 'en') {
    $pdfLang = 'en';
} else {
    $pdfLang = 'es';
}

$features = [];
if (!empty($property['features'])) {
    $arr = json_decode($property['features'], true);
    if (is_array($arr)) {
        $features = $arr;
    }
}

$photoModel = new PhotoModel();
$photos = $photoModel->getByPropertyId($id);

$terrenoModel = new TerrenoModel();
$usaModel = new USAModel();
$propertyDetailsModel = new PropertyDetailsModel();

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/';
$propertyUrl = $baseUrl . 'propiedad.php?id=' . $id;

$pdf = new PDF_UrbanGroup();
$pdf->setSourceUrl($propertyUrl);
$pdf->setLanguage($pdfLang);
$pdf->AddPage();

$titleLabel = $pdfLang === 'en' ? 'PROPERTY INFORMATION SHEET' : 'FICHA DE PROPIEDAD';
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor(0, 120, 215);
$pdf->Cell(0, 15, convertToISO($titleLabel), 0, 1, 'C');

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(30, 30, 30);
$pdf->MultiCell(0, 8, convertToISO($property['title']));

$pdf->Ln(3);

$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(80, 80, 80);
$location = ($property['address'] ?? "") . " - " . ($property['comuna_name'] ?? "") . ", " . ($property['region_name'] ?? "");
$pdf->MultiCell(0, 7, convertToISO($location));

$pdf->Ln(5);

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(100, 100, 100);
$operacion = strtoupper($property['operation_type'] ?? "");
$opLabel = $pdfLang === 'en' ? 'Operation' : 'Operacion';
$pdf->Cell(0, 7, convertToISO("$opLabel: $operacion"), 0, 1, 'C');
$pdf->Ln(3);

$generalLabel = $pdfLang === 'en' ? 'GENERAL INFORMATION' : 'INFORMACION GENERAL';
$pdf->SectionTitle($generalLabel);

$precio = "$" . number_format($property['price'], 0, ',', '.');
if ($property['operation_type'] === 'Arriendo') {
    $precio .= " /" . ($pdfLang === 'en' ? 'month' : 'mes');
}

$priceLabel = $pdfLang === 'en' ? 'Price' : 'Precio';
$pdf->InfoRow($priceLabel, $precio);
$pdf->Ln(1);

if ($property['bedrooms'] || $property['bathrooms']) {
    $bedroomLabel = $pdfLang === 'en' ? 'Bedrooms' : 'Dormitorios';
    $bathroomLabel = $pdfLang === 'en' ? 'Bathrooms' : 'Banos';
    $pdf->TwoColumnRow($bedroomLabel, (string)($property['bedrooms'] ?? 0), $bathroomLabel, (string)($property['bathrooms'] ?? 0));
}

if ($property['built_area'] || $property['total_area']) {
    $builtLabel = $pdfLang === 'en' ? 'Built Area' : 'Area Construida';
    $totalLabel = $pdfLang === 'en' ? 'Total Area' : 'Area Total';
    $pdf->TwoColumnRow($builtLabel, round($property['built_area'] ?? 0) . " m2", $totalLabel, round($property['total_area'] ?? 0) . " m2");
}

if ($property['parking_spots']) {
    $parkingLabel = $pdfLang === 'en' ? 'Parking Spots' : 'Estacionamientos';
    $pdf->InfoRow($parkingLabel, (string)$property['parking_spots']);
    $pdf->Ln(1);
}

if (!empty($features)) {
    $featuresLabel = $pdfLang === 'en' ? 'HIGHLIGHTED FEATURES' : 'CARACTERISTICAS DESTACADAS';
    $pdf->SectionTitle($featuresLabel);
    $pdf->FeaturesList($features);
}

if (!empty($property['description'])) {
    $descLabel = $pdfLang === 'en' ? 'DESCRIPTION' : 'DESCRIPCION';
    $pdf->SectionTitle($descLabel);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->MultiCell(0, 6, convertToISO($property['description']), 0, 'L');
    $pdf->Ln(3);
}

if (!empty($property['youtube_url'])) {
    $videoLabel = $pdfLang === 'en' ? 'VIDEO' : 'VIDEO';
    $pdf->SectionTitle($videoLabel);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 180);
    $pdf->Write(6, convertToISO($property['youtube_url']), $property['youtube_url']);
    $pdf->Ln(6);
}

$locationLabel = $pdfLang === 'en' ? 'LOCATION' : 'UBICACION';
$pdf->SectionTitle($locationLabel);
$comunaLabel = $pdfLang === 'en' ? 'Municipality' : 'Comuna';
$regionLabel = $pdfLang === 'en' ? 'Region' : 'Region';
$pdf->TwoColumnRow($comunaLabel, $property['comuna_name'] ?? "N/A", $regionLabel, $property['region_name'] ?? "N/A");

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetFillColor(240, 248, 255);
$pdf->Rect(10, $pdf->GetY(), 190, 12, 'F');
$pdf->SetXY(12, $pdf->GetY() + 1);
$noteText = $pdfLang === 'en' 
    ? "Note: The location shown is approximate for privacy reasons. For detailed location information, contact our agents."
    : "Nota: La ubicacion mostrada es aproximada por razones de privacidad. Para informacion detallada de ubicacion, contacte a nuestros agentes.";
$pdf->MultiCell(0, 5, convertToISO($noteText), 0, 'L');

$pdf->Ln(3);

$mapLabel = $pdfLang === 'en' ? 'APPROXIMATE LOCATION MAP' : 'MAPA DE UBICACION APROXIMADA';
$pdf->SectionTitle($mapLabel);

// Get approximate location map
$address = ($property['address'] ?? '') . ', ' . ($property['comuna_name'] ?? '') . ', ' . ($property['region_name'] ?? '');
$nominatimUrl = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q=" . urlencode($address);
$nominatimData = @file_get_contents($nominatimUrl);
if ($nominatimData) {
    $nominatimJson = json_decode($nominatimData, true);
    if (!empty($nominatimJson)) {
        $lat = $nominatimJson[0]['lat'];
        $lng = $nominatimJson[0]['lon'];
        $mapUrl = "https://staticmap.openstreetmap.de/staticmap.php?center=$lat,$lng&zoom=12&size=400x300&maptype=mapnik";
        $mapData = @file_get_contents($mapUrl);
        if ($mapData) {
            $tempFile = tempnam(sys_get_temp_dir(), 'map');
            file_put_contents($tempFile, $mapData);
            $pdf->Image($tempFile, 10, $pdf->GetY(), 190, 0, 'PNG');
            unlink($tempFile);
            $pdf->Ln(5);
        }
    }
}

$pdf->Ln(3);

$additionalLabel = $pdfLang === 'en' ? 'ADDITIONAL INFORMATION' : 'INFORMACION ADICIONAL';
$pdf->SectionTitle($additionalLabel);
$codeLabel = $pdfLang === 'en' ? 'Property Code' : 'Codigo de Propiedad';
$typeLabel = $pdfLang === 'en' ? 'Property Type' : 'Tipo de Propiedad';
$pdf->TwoColumnRow($codeLabel, (string)$property['id'], $typeLabel, convertToISO(ucfirst($property['property_type'] ?? "N/A")));

$generatedLabel = $pdfLang === 'en' ? 'Generated' : 'Generado';
$timeLabel = $pdfLang === 'en' ? 'Time' : 'Hora';
$pdf->TwoColumnRow($generatedLabel, date("d/m/Y"), $timeLabel, date("H:i:s"));

$pdf->Ln(3);

if ($sectionType === 'terrenos') {
    $terrenoDetails = $terrenoModel->getDetailsByPropertyId($id);
    $pdf->RenderTerrenoDetails($terrenoDetails ?: []);
} elseif ($sectionType === 'usa') {
    $usaDetails = $usaModel->getUSADetailsByPropertyId($id);
    $pdf->RenderUSADetails($usaDetails ?: [], $property);
} elseif ($sectionType === 'activos') {
    $pd = $propertyDetailsModel->getByPropertyId($id);
    $activosDetails = $pd['details'] ?? [];
    $activosDetails['property_category'] = $pd['property_category'] ?? '';
    
    if (class_exists('ActivoModel')) {
        $activoModel = new ActivoModel();
        $activoData = $activoModel->getDetailsByPropertyId($id);
        if ($activoData) {
            $activosDetails = array_merge($activosDetails, $activoData);
        }
    }
    
    $pdf->RenderActivoDetails($activosDetails, $property);
}

$pdf->RenderPropertyLink($propertyUrl);

$contactLabel = $pdfLang === 'en' ? 'CONTACT' : 'CONTACTO';
$pdf->SectionTitle($contactLabel);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(50, 50, 50);
$portalText = $pdfLang === 'en' ? 'Professional Real Estate Portal' : 'Portal Inmobiliario Profesional';
$pdf->Cell(0, 6, "Urban Group - " . convertToISO($portalText), 0, 1);
$pdf->Cell(0, 6, "www.urbangroup.cl", 0, 1);

if (defined('ADMIN_PHONE')) {
    $pdf->Cell(0, 6, "Tel: " . ADMIN_PHONE, 0, 1);
} else {
    $pdf->Cell(0, 6, "Tel: +56 2 XXXX XXXX", 0, 1);
}

if (ob_get_length()) ob_end_clean();

$langSuffix = $pdfLang === 'en' ? '_en' : '';
// create filename based on property title, sanitize to remove spaces/special chars
$cleanTitle = preg_replace('/[^A-Za-z0-9\-\_\s]/u', '_', $property['title']); // allow spaces, replace others
$cleanTitle = trim($cleanTitle); // remove leading/trailing spaces
$cleanTitle = preg_replace('/\s+/', '_', $cleanTitle); // replace spaces with _
$filename = $cleanTitle ?: "propiedad_" . $property['id'];
$filename .= $langSuffix . ".pdf";

$pdf->Output('D', $filename);
exit;
