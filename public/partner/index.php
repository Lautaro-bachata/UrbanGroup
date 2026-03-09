    <?php
    session_start();
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/helpers.php';
    require_once __DIR__ . '/../../includes/PropertyModel.php';
    require_once __DIR__ . '/../../includes/PhotoModel.php';
    require_once __DIR__ . '/../../includes/UserModel.php';
    require_once __DIR__ . '/../../includes/LocationModel.php';
    require_once __DIR__ . '/../../includes/PropertyDetailsModel.php';
    require_once __DIR__ . '/../../includes/TerrenoModel.php';
    require_once __DIR__ . '/../../includes/USAModel.php';
    require_once __DIR__ . '/../../includes/EmailHelper.php';
    require_once __DIR__ . '/../../config/contact_settings.php';

    requirePartner();

    $propertyModel = new PropertyModel();
    $photoModel = new PhotoModel();
    $userModel = new UserModel();
    $locationModel = new LocationModel();
    $propertyDetailsModel = new PropertyDetailsModel();
    $terrenoModel = new TerrenoModel();
    $usaModel = new USAModel();

    $action = $_GET['action'] ?? 'dashboard';
    $propertyId = (int)($_GET['id'] ?? 0);
    $currentPartnerId = $_SESSION['user_id'];

    // Move photo ordering - Partner can only move photos of their own properties
    if (isset($_GET['move_photo']) && isset($_GET['move_to'])) {
        $photoId = (int)$_GET['move_photo'];
        $newOrder = (int)$_GET['move_to'];
        $propertyId = (int)($_GET['id'] ?? 0);
        
        $photo = $photoModel->getById($photoId);
        $prop = $propertyModel->getById($propertyId);
        
        // Verify property belongs to this partner
        if ($photo && $photo['property_id'] == $propertyId && $prop && $prop['partner_id'] == $currentPartnerId) {
            $allPhotos = $photoModel->getByPropertyId($propertyId);
            $oldOrder = $photo['display_order'];
            
            if ($newOrder > $oldOrder) {
                foreach ($allPhotos as $p) {
                    if ($p['display_order'] > $oldOrder && $p['display_order'] <= $newOrder) {
                        $photoModel->updateDisplayOrder($p['id'], $p['display_order'] - 1);
                    }
                }
            } else {
                foreach ($allPhotos as $p) {
                    if ($p['display_order'] >= $newOrder && $p['display_order'] < $oldOrder) {
                        $photoModel->updateDisplayOrder($p['id'], $p['display_order'] + 1);
                    }
                }
            }
            $photoModel->updateDisplayOrder($photoId, $newOrder);
        }
        $ptype = isset($prop['section_type']) && $prop['section_type'] ? $prop['section_type'] : 'propiedades';
        header('Location: ?action=edit&id=' . $propertyId . '&type=' . urlencode($ptype));
        exit;
    }
    // require_once __DIR__ . '/handlers/catalogos.handler.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postAction = $_POST['action'] ?? '';
        
        // ---------- CREATE PROPERTY ----------
        if ($postAction === 'create_property') {
        // Use property_category instead of property_type_id
        $propCategory = sanitizeInput($_POST['property_category'] ?? '');
        $categories = PropertyDetailsModel::getPropertyCategories();
        $propTypeName = $categories[$propCategory] ?? '';
        
        // Resolver Region y Comuna (incluyendo fallbacks)
        $regionId = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
        $comunaId = !empty($_POST['comuna_id']) ? (int)$_POST['comuna_id'] : null;
        if (!$regionId && !empty($_POST['region_id_terreno'])) $regionId = (int)$_POST['region_id_terreno'];
        if (!$comunaId && !empty($_POST['comuna_id_terreno'])) $comunaId = (int)$_POST['comuna_id_terreno'];

        $propertyId = $propertyModel->create([
            'title' => sanitizeInput($_POST['title']),
            'description' => sanitizeInput($_POST['description'] ?? ''),
            'property_type' => $propTypeName,
            'operation_type' => sanitizeInput($_POST['operation_type']),
            'price' => (float)($_POST['price'] ?? 0),
            'currency' => 'CLP',
            'bedrooms' => (int)($_POST['bedrooms'] ?? 0),
            'bathrooms' => (int)($_POST['bathrooms'] ?? 0),
            'built_area' => (float)($_POST['built_area'] ?? 0),
            'total_area' => (float)($_POST['total_area'] ?? 0),
            'parking_spots' => (int)($_POST['parking_spots'] ?? 0),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'comuna_id' => $comunaId,
            'region_id' => $regionId,
            'images' => json_encode([]),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'partner_id' => $currentPartnerId,
            'section_type' => sanitizeInput($_POST['section_type'] ?? 'propiedades'),
            'property_category' => $propCategory
        ]);

        // (el resto del código del bloque permanece igual: propertyDetailsModel->save, fotos, redirect)
        if ($propertyId) {
            $detailsData = [];
            $featuresData = [];
            $costsData = [];

            if (!empty($_POST['details']) && is_array($_POST['details'])) {
                $detailsData = array_filter($_POST['details'], fn($v) => $v !== '');
            }
            if (!empty($_POST['property_features']) && is_array($_POST['property_features'])) {
                $featuresData = $_POST['property_features'];
            }
            if (!empty($_POST['costs']) && is_array($_POST['costs'])) {
                $costsData = array_filter($_POST['costs'], fn($v) => $v !== '');
            }

            $propertyDetailsModel->save($propertyId, [
                'property_category' => sanitizeInput($_POST['property_category'] ?? ''),
                'section_type' => sanitizeInput($_POST['section_type'] ?? 'propiedades'),
                'details' => $detailsData,
                'features' => $featuresData,
                'costs' => $costsData
            ]);
        }
            // handle photos upload
            if ($propertyId && isset($_FILES['property_photos']) && is_array($_FILES['property_photos']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/properties/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $displayOrder = 0;
                for ($i = 0; $i < count($_FILES['property_photos']['name']); $i++) {
                    if ($_FILES['property_photos']['error'][$i] === UPLOAD_ERR_OK && $displayOrder < 12) {
                        $ext = strtolower(pathinfo($_FILES['property_photos']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                            $filename = time() . '_' . uniqid() . '.' . $ext;
                            $fullPath = $uploadDir . $filename;
                            if (@move_uploaded_file($_FILES['property_photos']['tmp_name'][$i], $fullPath)) {
                                @chmod($fullPath, 0644);
                                $photoModel->create($propertyId, '../uploads/properties/' . $filename, $displayOrder);
                                $displayOrder++;
                            }
                        }
                    }
                }
            }
            
            // Notificar al admin por email
            if ($propertyId) {
                try {
                    $emailHelper = new EmailHelper();
                    $partnerData = $userModel->getById($currentPartnerId);
                    $propertyData = [
                        'title' => sanitizeInput($_POST['title']),
                        'section_type' => sanitizeInput($_POST['section_type'] ?? 'propiedades'),
                        'property_type' => $propTypeName,
                        'operation_type' => sanitizeInput($_POST['operation_type']),
                        'price' => (float)($_POST['price'] ?? 0),
                        'currency' => 'CLP',
                        'address' => sanitizeInput($_POST['address'] ?? '')
                    ];
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
                    $emailHelper->sendPartnerPropertyNotification($partnerData, $propertyData, $propertyId, $baseUrl);
                } catch (Exception $e) {
                    error_log("Error enviando notificación de nueva propiedad: " . $e->getMessage());
                }
            }
            
            header('Location: ?action=properties');
            exit;
        }

        // ---------- CREATE SPECIAL PROPERTY (admin) ----------
        if ($postAction === 'create_special_property' || ($postAction === 'create_property' && $specialType !== 'propiedades')) {
            $specialType = sanitizeInput($_POST['special_type'] ?? 'propiedades');

        // Use property_category instead of property_type_id
        $propCategory = sanitizeInput($_POST['property_category'] ?? '');
        $categories = PropertyDetailsModel::getPropertyCategories();
        $propTypeName = $categories[$propCategory] ?? '';
        
        // Resolver Region y Comuna (con fallbacks para terrenos/activos)
        $regionId = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
        $comunaId = !empty($_POST['comuna_id']) ? (int)$_POST['comuna_id'] : null;
        if (!$regionId && !empty($_POST['region_id_terreno'])) $regionId = (int)$_POST['region_id_terreno'];
        if (!$comunaId && !empty($_POST['comuna_id_terreno'])) $comunaId = (int)$_POST['comuna_id_terreno'];

        // Get address from terreno ubicacion for terrenos
        $address = sanitizeInput($_POST['address'] ?? '');
        if ($specialType === 'terrenos' && !empty($_POST['terreno']['ubicacion'])) {
            $address = sanitizeInput($_POST['terreno']['ubicacion']);
        }

        // Determine status from form
        $status = 'Activo';
        if (!empty($_POST['status'])) {
            $status = sanitizeInput($_POST['status']);
        } elseif (!empty($_POST['terreno']['estado'])) {
            $status = sanitizeInput($_POST['terreno']['estado']);
        } elseif (!empty($_POST['usa']['status'])) {
            $status = sanitizeInput($_POST['usa']['status']);
        }

        // base create
        $propertyId = $propertyModel->create([
            'title' => sanitizeInput($_POST['title']),
            'description' => sanitizeInput($_POST['description'] ?? ''),
            'property_type' => $propTypeName,
            'operation_type' => sanitizeInput($_POST['operation_type'] ?? 'Venta'),
            'price' => (float)($_POST['price'] ?? 0),
            'currency' => sanitizeInput($_POST['currency'] ?? 'UF'),
            'bedrooms' => (int)($_POST['bedrooms'] ?? 0),
            'bathrooms' => (int)($_POST['bathrooms'] ?? 0),
            'built_area' => (float)($_POST['built_area'] ?? 0),
            'total_area' => (float)($_POST['total_area'] ?? 0),
            'parking_spots' => (int)($_POST['parking_spots'] ?? 0),
            'address' => $address,
            'comuna_id' => $comunaId,
            'region_id' => $regionId,
            'images' => json_encode([]),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'partner_id' => $currentPartnerId,
            'section_type' => $specialType,
            'property_category' => sanitizeInput($_POST['property_category'] ?? ''),
            'status' => $status,
            'sold_at' => ($status === 'Vendido') ? date('Y-m-d H:i:s') : null
        ]);

        if ($propertyId) {
            // Handle Terrenos Inmobiliarios with comprehensive form
            if ($specialType === 'terrenos' && !empty($_POST['terreno'])) {
                $terrenoData = [];
                $terrenoFields = [
                    'nombre_proyecto', 'ubicacion', 'usos_suelo_permitidos',
                    'roles', 'fecha_permiso_edificacion', 'zona_prc_edificacion', 'fecha_cip',
                    'usos_suelo', 'sistema_agrupamiento', 'altura_maxima', 'rasante',
                    'coef_constructibilidad_max', 'coef_ocupacion_suelo_max', 'coef_area_libre_min',
                    'antejardin_min', 'distanciamientos', 'articulos_normativos',
                    'frente', 'fondo', 'superficie_util', 'superficie_bruta', 'expropiacion',
                    'superficie_predial_min', 'densidad_bruta_max_hab_ha', 'densidad_bruta_max_viv_ha',
                    'densidad_neta_max_hab_ha', 'densidad_neta_max_viv_ha',
                    'num_viviendas', 'superficie_edificada', 'superficie_util_anteproyecto',
                    'densidad_neta', 'densidad_maxima', 'num_estacionamientos',
                    'num_est_visitas', 'num_est_bicicletas', 'num_locales_comerciales', 'num_bodegas', 'superficies_aprobadas',
                    'ap_bajo_util', 'ap_bajo_comun', 'ap_bajo_total',
                    'ap_sobre_util', 'ap_sobre_comun', 'ap_sobre_total',
                    'ap_total_util', 'ap_total_comun', 'ap_total_total',
                    'precio', 'precio_uf_m2', 'comision', 'observaciones', 'video_url', 'has_anteproyecto', 'estado', 'ciudad'
                ];

                foreach ($terrenoFields as $field) {
                    if (isset($_POST['terreno'][$field])) {
                        $value = $_POST['terreno'][$field];
                        if ($value !== '' && $value !== null) {
                            $terrenoData[$field] = is_string($value) ? sanitizeInput($value) : $value;
                        }
                    }
                }

                // Handle sistema_agrupamiento as array of checkboxes
                if (isset($_POST['terreno']['sistema_agrupamiento']) && is_array($_POST['terreno']['sistema_agrupamiento'])) {
                    // Sanitize and join multiple selections with commas
                    $agrupamientos = array_map('sanitizeInput', $_POST['terreno']['sistema_agrupamiento']);
                    $terrenoData['sistema_agrupamiento'] = implode(',', $agrupamientos);
                }

                // Handle PDF upload
                if (isset($_FILES['pdf_documento']) && $_FILES['pdf_documento']['error'] === UPLOAD_ERR_OK) {
                    $pdfDir = __DIR__ . '/../uploads/terrenos/';
                    if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

                    $ext = strtolower(pathinfo($_FILES['pdf_documento']['name'], PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $pdfFilename = 'terreno_' . $propertyId . '_' . time() . '.pdf';
                        $pdfPath = $pdfDir . $pdfFilename;
                        if (@move_uploaded_file($_FILES['pdf_documento']['tmp_name'], $pdfPath)) {
                            @chmod($pdfPath, 0644);
                            // store path without navigating back, view uses SITE_URL
                            $terrenoData['pdf_documento'] = 'uploads/terrenos/' . $pdfFilename;
                        }
                    }
                }

                // Save to property_terreno_details
                $terrenoModel->createOrUpdate($propertyId, $terrenoData);

            } elseif ($specialType === 'usa' && !empty($_POST['usa'])) {
                $usaData = [];
                $usaFields = [
                    'is_project', 'surface_sqft', 'lot_size_sqft', 'price_usd',
                    'hoa_fee', 'property_tax', 'year_built', 'stories', 'garage_spaces',
                    'pool', 'waterfront', 'view_type', 'heating', 'cooling', 'flooring',
                    'appliances', 'exterior_features', 'interior_features', 'community_features',
                    'project_units', 'project_developer', 'project_completion_date', 'project_amenities',
                    'whatsapp_number', 'mls_id', 'state', 'city', 'zip_code'
                ];

                foreach ($usaFields as $field) {
                    if (isset($_POST['usa'][$field])) {
                        $value = $_POST['usa'][$field];
                        if ($value !== '' && $value !== null) {
                            if (in_array($field, ['pool', 'waterfront', 'is_project'])) {
                                $usaData[$field] = (int)$value;
                            } elseif (in_array($field, ['surface_sqft', 'lot_size_sqft', 'price_usd', 'hoa_fee', 'property_tax'])) {
                                $usaData[$field] = (float)$value;
                            } elseif (in_array($field, ['year_built', 'stories', 'garage_spaces', 'project_units'])) {
                                $usaData[$field] = (int)$value;
                            } else {
                                $usaData[$field] = is_string($value) ? sanitizeInput($value) : $value;
                            }
                        }
                    }
                }

                if (isset($_POST['usa']['is_project']) && $_POST['usa']['is_project']) {
                    $propertyModel->update($propertyId, ['is_project' => 1]);
                }

                $usaModel->createOrUpdateUSADetails($propertyId, $usaData);

            } else {
                $details = [];
                if ($specialType === 'activos') {
                    $details['asset_condition'] = sanitizeInput($_POST['asset_condition'] ?? '');
                    $details['brand'] = sanitizeInput($_POST['brand'] ?? '');
                }

                $propertyDetailsModel->save($propertyId, [
                    'property_category' => sanitizeInput($_POST['property_category'] ?? ''),
                    'section_type' => $specialType,
                    'details' => $details,
                    'features' => $_POST['property_features'] ?? [],
                    'costs' => $_POST['costs'] ?? []
                ]);
            }

            // Handle photos upload for all special types
            if (isset($_FILES['property_photos']) && is_array($_FILES['property_photos']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/properties/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $displayOrder = 0;
                for ($i = 0; $i < count($_FILES['property_photos']['name']); $i++) {
                    if ($_FILES['property_photos']['error'][$i] === UPLOAD_ERR_OK && $displayOrder < 12) {
                        $ext = strtolower(pathinfo($_FILES['property_photos']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                            $filename = time() . '_' . uniqid() . '.' . $ext;
                            $fullPath = $uploadDir . $filename;
                            if (@move_uploaded_file($_FILES['property_photos']['tmp_name'][$i], $fullPath)) {
                                @chmod($fullPath, 0644);
                                $photoModel->create($propertyId, '../uploads/properties/' . $filename, $displayOrder);
                                $displayOrder++;
                            }
                        }
                    }
                }
            }
        }
        
        // Notificar al admin por email
        if ($propertyId) {
            try {
                $emailHelper = new EmailHelper();
                $partnerData = $userModel->getById($currentPartnerId);
                $propertyData = [
                    'title' => sanitizeInput($_POST['title']),
                    'section_type' => $specialType,
                    'property_type' => $propTypeName,
                    'operation_type' => sanitizeInput($_POST['operation_type']),
                    'price' => (float)($_POST['price'] ?? 0),
                    'currency' => sanitizeInput($_POST['currency'] ?? 'UF'),
                    'address' => $address
                ];
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
                $emailHelper->sendPartnerPropertyNotification($partnerData, $propertyData, $propertyId, $baseUrl);
            } catch (Exception $e) {
                error_log("Error enviando notificación de nueva propiedad especial: " . $e->getMessage());
            }
        }

        header('Location: ?action=special_list&type=' . urlencode($specialType));
        exit;
    }
        
        // ---------- UPDATE PROPERTY ----------
        if (($postAction === 'update_property' || $postAction === 'update_special_property') && !empty($_POST['property_id'])) {
            $propertyId = (int)$_POST['property_id'];
            
            // PARTNER PERMISSION CHECK - verify property belongs to this partner
            $checkProperty = $propertyModel->getById($propertyId);
            if (!$checkProperty || $checkProperty['partner_id'] != $currentPartnerId) {
                header('Location: ?action=properties');
                exit;
            }
            
            // Use property_category instead of property_type_id
                $propCategory = sanitizeInput($_POST['property_category'] ?? '');
                $categories = PropertyDetailsModel::getPropertyCategories();
                $propTypeName = $categories[$propCategory] ?? '';
                
            // Resolver Region y Comuna (fallback terrenos)
            $regionId = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
            $comunaId = !empty($_POST['comuna_id']) ? (int)$_POST['comuna_id'] : null;
            if (!$regionId && !empty($_POST['region_id_terreno'])) $regionId = (int)$_POST['region_id_terreno'];
            if (!$comunaId && !empty($_POST['comuna_id_terreno'])) $comunaId = (int)$_POST['comuna_id_terreno'];

            // Handle status update with sold_at timestamp
            $newStatus = sanitizeInput($_POST['status'] ?? 'Activo');
            $existingProperty = $propertyModel->getById($propertyId);
            $soldAt = $existingProperty['sold_at'] ?? null;
            
            if ($newStatus === 'Vendido' && ($existingProperty['status'] ?? '') !== 'Vendido') {
                $soldAt = date('Y-m-d H:i:s');
            } elseif ($newStatus !== 'Vendido') {
                $soldAt = null;
            }
            
        $propertyModel->update($propertyId, [
            'title' => sanitizeInput($_POST['title']),
            'description' => sanitizeInput($_POST['description'] ?? ''),
            'property_type' => $propTypeName,
            'property_category' => $propCategory,
            'operation_type' => sanitizeInput($_POST['operation_type']),
            'price' => (float)($_POST['price'] ?? 0),
                'bedrooms' => (int)($_POST['bedrooms'] ?? 0),
                'bathrooms' => (int)($_POST['bathrooms'] ?? 0),
                'built_area' => (float)($_POST['built_area'] ?? 0),
                'total_area' => (float)($_POST['total_area'] ?? 0),
                'parking_spots' => (int)($_POST['parking_spots'] ?? 0),
                'address' => sanitizeInput($_POST['address'] ?? ''),
                'comuna_id' => $comunaId,
                'region_id' => $regionId,
                'images' => json_encode([]),
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'section_type' => sanitizeInput($_POST['section_type'] ?? 'propiedades'),
                'property_category' => sanitizeInput($_POST['property_category'] ?? ''),
                'status' => $newStatus,
                'sold_at' => $soldAt
            ]);
            
            $detailsData = [];
            $featuresData = [];
            $costsData = [];
            
            if (!empty($_POST['details']) && is_array($_POST['details'])) {
                $detailsData = array_filter($_POST['details'], fn($v) => $v !== '');
            }
            // If this is an 'activos' update, the form uses top-level fields for asset details
            if (isset($_POST['asset_condition']) || isset($_POST['brand'])) {
                if (!is_array($detailsData)) $detailsData = [];
                if (isset($_POST['asset_condition'])) {
                    $detailsData['asset_condition'] = sanitizeInput($_POST['asset_condition']);
                }
                if (isset($_POST['brand'])) {
                    $detailsData['brand'] = sanitizeInput($_POST['brand']);
                }
            }
            if (!empty($_POST['property_features']) && is_array($_POST['property_features'])) {
                $featuresData = $_POST['property_features'];
            }
            if (!empty($_POST['costs']) && is_array($_POST['costs'])) {
                $costsData = array_filter($_POST['costs'], fn($v) => $v !== '');
            }
            
            $propertyDetailsModel->save($propertyId, [
                'property_category' => sanitizeInput($_POST['property_category'] ?? ''),
                'section_type' => sanitizeInput($_POST['section_type'] ?? 'propiedades'),
                'details' => $detailsData,
                'features' => $featuresData,
                'costs' => $costsData
            ]);
            
            $sectionType = sanitizeInput($_POST['section_type'] ?? 'propiedades');
            
            if ($sectionType === 'terrenos' && !empty($_POST['terreno'])) {
                $terrenoData = [];
                $terrenoFields = [
                    'nombre_proyecto', 'ubicacion', 'usos_suelo_permitidos',
                    'roles', 'fecha_permiso_edificacion', 'zona_prc_edificacion', 'fecha_cip',
                    'usos_suelo', 'sistema_agrupamiento', 'altura_maxima', 'rasante',
                    'coef_constructibilidad_max', 'coef_ocupacion_suelo_max', 'coef_area_libre_min',
                    'antejardin_min', 'distanciamientos', 'articulos_normativos',
                    'frente', 'fondo', 'superficie_util', 'superficie_bruta', 'expropiacion',
                    'superficie_predial_min', 'densidad_bruta_max_hab_ha', 'densidad_bruta_max_viv_ha',
                    'densidad_neta_max_hab_ha', 'densidad_neta_max_viv_ha',
                    'num_viviendas', 'superficie_edificada', 'superficie_util_anteproyecto',
                    'densidad_neta', 'densidad_maxima', 'num_estacionamientos',
                    'num_est_visitas', 'num_est_bicicletas', 'num_locales_comerciales', 'num_bodegas', 'superficies_aprobadas',
                    'ap_bajo_util', 'ap_bajo_comun', 'ap_bajo_total',
                    'ap_sobre_util', 'ap_sobre_comun', 'ap_sobre_total',
                    'ap_total_util', 'ap_total_comun', 'ap_total_total',
                    'precio', 'precio_uf_m2', 'comision', 'observaciones', 'video_url', 'has_anteproyecto', 'estado', 'ciudad'
                ];
                
                foreach ($terrenoFields as $field) {
                    if (isset($_POST['terreno'][$field])) {
                        $value = $_POST['terreno'][$field];
                        if ($value !== '' && $value !== null) {
                            $terrenoData[$field] = is_string($value) ? sanitizeInput($value) : $value;
                        }
                    }
                }
                
                // Handle sistema_agrupamiento as array of checkboxes
                if (isset($_POST['terreno']['sistema_agrupamiento']) && is_array($_POST['terreno']['sistema_agrupamiento'])) {
                    // Sanitize and join multiple selections with commas
                    $agrupamientos = array_map('sanitizeInput', $_POST['terreno']['sistema_agrupamiento']);
                    $terrenoData['sistema_agrupamiento'] = implode(',', $agrupamientos);
                }
                
                $terrenoModel->createOrUpdate($propertyId, $terrenoData);
            }

            // If this is a USA property update, persist USA-specific fields as done on create
            if ($sectionType === 'usa' && !empty($_POST['usa'])) {
                $usaData = [];
                $usaFields = [
                    'is_project', 'surface_sqft', 'lot_size_sqft', 'price_usd',
                    'hoa_fee', 'property_tax', 'year_built', 'stories', 'garage_spaces',
                    'pool', 'waterfront', 'view_type', 'heating', 'cooling', 'flooring',
                    'appliances', 'exterior_features', 'interior_features', 'community_features',
                    'project_units', 'project_developer', 'project_completion_date', 'project_amenities',
                    'whatsapp_number', 'mls_id', 'state', 'city', 'zip_code'
                ];

                foreach ($usaFields as $field) {
                    if (isset($_POST['usa'][$field])) {
                        $value = $_POST['usa'][$field];
                        if ($value !== '' && $value !== null) {
                            if (in_array($field, ['pool', 'waterfront', 'is_project'])) {
                                $usaData[$field] = (int)$value;
                            } elseif (in_array($field, ['surface_sqft', 'lot_size_sqft', 'price_usd', 'hoa_fee', 'property_tax'])) {
                                $usaData[$field] = (float)$value;
                            } elseif (in_array($field, ['year_built', 'stories', 'garage_spaces', 'project_units'])) {
                                $usaData[$field] = (int)$value;
                            } else {
                                $usaData[$field] = is_string($value) ? sanitizeInput($value) : $value;
                            }
                        }
                    }
                }

                if (isset($_POST['usa']['is_project']) && $_POST['usa']['is_project']) {
                    $propertyModel->update($propertyId, ['is_project' => 1]);
                }

                $usaModel->createOrUpdateUSADetails($propertyId, $usaData);
            }
            
            // delete selected photos
            if (isset($_POST['delete_photos']) && is_array($_POST['delete_photos'])) {
                foreach ($_POST['delete_photos'] as $photoIdToDelete) {
                    $photo = $photoModel->getById((int)$photoIdToDelete);
                    if ($photo && $photo['property_id'] == $propertyId) {
                        $photoFile = __DIR__ . '/../uploads/properties/' . basename($photo['photo_url']);
                        if (file_exists($photoFile)) @unlink($photoFile);
                        $photoModel->delete((int)$photoIdToDelete);
                    }
                }
                $prop = $propertyModel->getById($propertyId);
                $ptype = isset($prop['section_type']) && $prop['section_type'] ? $prop['section_type'] : 'propiedades';
                header('Location: ?action=edit&id=' . $propertyId . '&type=' . urlencode($ptype));
                exit;
            }
            
            // ajax delete photo
            if (isset($_GET['delete_photo_ajax']) && isset($_GET['photo_id'])) {
                $photoId = (int)$_GET['photo_id'];
                $photo = $photoModel->getById($photoId);
                if ($photo) {
                    $photoFile = __DIR__ . '/../uploads/properties/' . basename($photo['photo_url']);
                    if (file_exists($photoFile)) @unlink($photoFile);
                    $photoModel->delete($photoId);
                    echo json_encode(['success' => true]);
                    exit;
                }
                echo json_encode(['success' => false]);
                exit;
            }
            
            // reorder photos (bulk)
            if (isset($_POST['reorder_photos']) && is_array($_POST['reorder_photos'])) {
                foreach ($_POST['reorder_photos'] as $order => $photoId) {
                    $photo = $photoModel->getById((int)$photoId);
                    if ($photo && $photo['property_id'] == $propertyId) {
                        $photoModel->updateDisplayOrder((int)$photoId, (int)$order);
                    }
                }
            }
            
            // add additional photos
            if (isset($_FILES['property_photos']) && is_array($_FILES['property_photos']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/properties/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $existingPhotos = $photoModel->getByPropertyId($propertyId);
                $displayOrder = count($existingPhotos);
                
                for ($i = 0; $i < count($_FILES['property_photos']['name']); $i++) {
                    if ($_FILES['property_photos']['error'][$i] === UPLOAD_ERR_OK && $displayOrder < 12) {
                        $ext = strtolower(pathinfo($_FILES['property_photos']['name'][$i], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                            $filename = time() . '_' . uniqid() . '.' . $ext;
                            $fullPath = $uploadDir . $filename;
                            if (@move_uploaded_file($_FILES['property_photos']['tmp_name'][$i], $fullPath)) {
                                @chmod($fullPath, 0644);
                                $photoModel->create($propertyId, '../uploads/properties/' . $filename, $displayOrder);
                                $displayOrder++;
                            }
                        }
                    }
                }
            }
            
            header('Location: ?action=properties');
            exit;
        }
        
        // ---------- DELETE PROPERTY ----------
        if ($postAction === 'delete_property' && !empty($_POST['property_id'])) {
            $propertyId = (int)$_POST['property_id'];
            $property = $propertyModel->getById($propertyId);
            // PARTNER PERMISSION CHECK
            if ($property && $property['partner_id'] == $currentPartnerId) {
                $photoModel->deleteByPropertyId($propertyId);
                $propertyModel->delete($propertyId);
            }
            header('Location: ?action=properties');
            exit;
        }
        
        // ---------- TOGGLE FEATURED ----------
        if ($postAction === 'toggle_featured' && !empty($_POST['property_id'])) {
            $property = $propertyModel->getById((int)$_POST['property_id']);
            // PARTNER PERMISSION CHECK
            if ($property && $property['partner_id'] == $currentPartnerId) {
                $propertyModel->update((int)$_POST['property_id'], ['is_featured' => $property['is_featured'] ? 0 : 1]);
            }
            header('Location: ?action=properties');
            exit;
        }
        
        // ---------- UPDATE STATUS ----------
        if ($postAction === 'update_status' && !empty($_POST['property_id']) && !empty($_POST['status'])) {
            $property = $propertyModel->getById((int)$_POST['property_id']);
            // PARTNER PERMISSION CHECK
            if ($property && $property['partner_id'] == $currentPartnerId) {
                $propertyModel->updateStatus((int)$_POST['property_id'], $_POST['status']);
            }
            header('Location: ?action=properties');
            exit;
        }
        
    }

    // Fetch data for views - Partner sees ONLY their own properties
    $properties = $propertyModel->getByPartnerId($currentPartnerId);
    $propertyCategories = PropertyDetailsModel::getPropertyCategories();

    $editProperty = null;
    $regions = $locationModel->getRegions();

    $totalProperties = count($properties);
    $featuredCount = count(array_filter($properties, fn($p) => $p['is_featured']));
    $activeProperties = count(array_filter($properties, fn($p) => $p['is_active']));

    $propiedadesCount = count(array_filter($properties, fn($p) => ($p['section_type'] ?? 'propiedades') === 'propiedades'));
    $terrenosCount = count(array_filter($properties, fn($p) => ($p['section_type'] ?? '') === 'terrenos'));
    $activosCount = count(array_filter($properties, fn($p) => ($p['section_type'] ?? '') === 'activos'));
    $usaCount = count(array_filter($properties, fn($p) => ($p['section_type'] ?? '') === 'usa'));

    $sectionLabels = [
        'propiedades' => 'Propiedades',
        'terrenos' => 'Terrenos',
        'activos' => 'Activos',
        'usa' => 'Prop. USA'
    ];

    $editPropertyDetails = ['details' => [], 'features' => [], 'costs' => []];
    $terrenoDetails = null;
    $usaDetails = null;
    if ($action === 'edit' && $propertyId) {
        $editProperty = $propertyModel->getById($propertyId);
        
        // PARTNER PERMISSION CHECK: Only allow editing own properties
        if (!$editProperty || $editProperty['partner_id'] != $currentPartnerId) {
            header('Location: ?action=properties');
            exit;
        }
        
        $editPropertyDetails = $propertyDetailsModel->getByPropertyId($propertyId);

        // Allow forcing the UI section via ?type=... when opening edit (helps coming from special lists)
        $requestedType = $_GET['type'] ?? null;
        $dbSection = $editProperty['section_type'] ?? null;
        $uiSectionForEdit = $requestedType ?? $dbSection ?? 'propiedades';
        $editProperty['section_type'] = $uiSectionForEdit;

        if ($uiSectionForEdit === 'terrenos') {
            $terrenoDetails = $terrenoModel->getDetailsByPropertyId($propertyId);
        } elseif ($uiSectionForEdit === 'usa') {
            $usaDetails = $usaModel->getUSADetailsByPropertyId($propertyId);
        }
    }

    $propertyCategories = PropertyDetailsModel::getPropertyCategories();
    $sectionTypes = PropertyDetailsModel::getSectionTypes();

    // If admin is opening Add Property with ?section=terrenos|activos|usa preselect it
    if ($action === 'add' && isset($_GET['section'])) {
        $sectionParam = $_GET['section'];
        if (in_array($sectionParam, array_keys($sectionTypes))) {
            $editProperty = ['section_type' => $sectionParam];
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Panel de Socio - Urban Group</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { font-family: 'Inter', sans-serif; }
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    </head>
    <body class="bg-gray-50">

    <!-- Navbar -->
    <header class="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4 flex items-center justify-between">
            <a href="../index.php" class="flex items-center gap-2 text-xl lg:text-2xl font-bold text-blue-600">
                <!-- icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 lg:w-8 lg:h-8" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"/>
                    <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z"/>
                </svg>
                <span class="hidden sm:inline">UrbanGroup</span>
            </a>
        
            <a href="../logout.php" class="px-3 lg:px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Cerrar</a>
        </div>
    </header>

    <div class="flex h-screen flex-col lg:flex-row">
        <!-- Sidebar -->
        <aside class="hidden lg:flex flex-col w-64 bg-slate-900 text-white border-r border-slate-700 overflow-y-auto">
            <div class="p-6 border-b border-slate-700">
                <h3 class="text-xs font-semibold text-slate-400 mb-2">MI CUENTA</h3>
                <p class="text-sm font-medium truncate"><?= htmlspecialchars($_SESSION['name']) ?></p>
            </div>
            
            <nav class="flex-1 p-4 space-y-1">
                <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $action === 'dashboard' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?> transition text-sm">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-3m0 0l7-4 7 4M5 9v10a1 1 0 001 1h12a1 1 0 001-1V9m-9 11l4-2m-4 2l-4-2"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="?action=properties" class="flex items-center gap-3 px-4 py-3 rounded-lg <?= $action === 'properties' ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?> transition text-sm">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span>Propiedades</span>
                </a>
                <!-- Secciones Especiales -->
                <div class="px-4 py-2">
                    <p class="text-xs font-semibold text-slate-400 mb-2">SECCIONES</p>
                    <div class="space-y-1">
                        <a href="?action=special_list&type=terrenos" class="flex items-center gap-3 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                            <span>Terrenos Inmo</span>
                        </a>
                        <a href="?action=special_list&type=activos" class="flex items-center gap-3 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                            <span>Activos Inmo</span>
                        </a>
                        <a href="?action=special_list&type=usa" class="flex items-center gap-3 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 transition text-sm">
                            <span>Prop. USA</span>
                        </a>
                    </div>
                </div>
                <!-- Agregar -->
                <div class="px-4 py-2">
                    <p class="text-xs font-semibold text-slate-400 mb-2">AGREGAR</p>
                    <div class="space-y-1">
                        <a href="?action=add&section=propiedades" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-400 hover:bg-slate-800 transition text-sm">
                            <span>+ Nueva Propiedad</span>
                        </a>
                        <a href="?action=add_special&type=terrenos" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-400 hover:bg-slate-800 transition text-sm">
                            <span>+ Nuevo Terreno</span>
                        </a>
                        <a href="?action=add_special&type=activos" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-400 hover:bg-slate-800 transition text-sm">
                            <span>+ Nuevo Activo</span>
                        </a>
                        <a href="?action=add_special&type=usa" class="flex items-center gap-3 px-4 py-2 rounded-lg text-green-400 hover:bg-slate-800 transition text-sm">
                            <span>+ Nueva Prop. USA</span>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Mobile Nav -->
        <div class="lg:hidden bg-white border-b border-gray-200 px-4 py-2 flex gap-2 overflow-x-auto scrollbar-hide">
            <a href="" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap <?= $action === 'dashboard' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">Dashboard</a>
            <a href="?action=properties" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap <?= $action === 'properties' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">Propiedades</a>
            <a href="?action=special_list&type=terrenos" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Terrenos</a>
            <a href="?action=special_list&type=activos" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">Activos</a>
            <a href="?action=special_list&type=usa" class="px-3 py-2 text-xs font-medium rounded-lg whitespace-nowrap bg-gray-100 text-gray-700">USA</a>
        </div>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto p-4 lg:p-8">
            
            <?php if ($action === 'special_list' && isset($_GET['type'])):
                $specialType = $_GET['type'];
                $filtered = array_values(array_filter($properties, fn($p) => ($p['section_type'] ?? 'propiedades') === $specialType));
            ?>
                <h1 class="text-3xl font-bold text-gray-900 mb-6"><?= htmlspecialchars(ucfirst($specialType)) ?> - Propiedades</h1>
                <div class="mb-4">
                    <a href="?action=add_special&type=<?= urlencode($specialType) ?>" class="px-4 py-2 bg-blue-600 text-white rounded">Agregar <?= htmlspecialchars(ucfirst($specialType)) ?></a>
                    <a href="?action=properties" class="ml-3 px-3 py-2 border rounded">Volver a Todas</a>
                </div>
                <?php if (empty($filtered)): ?>
                    <div class="bg-white rounded-lg shadow p-6 text-center">No hay propiedades en esta sección.</div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Propiedad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Precio</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered as $property): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-6 py-4"><?= htmlspecialchars($property['title']) ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($property['property_type'] ?? '') ?></td>
                                        <td class="px-6 py-4 font-bold"><?= formatPrice($property['price']) ?></td>
                                        <td class="px-6 py-4">
                                            <?php 
                                            $status = $property['status'] ?? 'Activo';
                                            $statusColors = [
                                                'Activo' => 'bg-green-100 text-green-800',
                                                'Actualizar' => 'bg-yellow-100 text-yellow-800',
                                                'Oferta' => 'bg-purple-100 text-purple-800',
                                                'Armar' => 'bg-blue-100 text-blue-800',
                                                'Stand By' => 'bg-gray-100 text-gray-800',
                                                'Vendido' => 'bg-red-100 text-red-800',
                                                'Eliminar' => 'bg-red-200 text-red-900'
                                            ];
                                            $statusClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="?action=edit&id=<?= $property['id'] ?>&type=<?= urlencode($property['section_type'] ?? 'propiedades') ?>" class="text-blue-600">Editar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($action === 'add_special' && isset($_GET['type'])):
                $specialType = $_GET['type'];
                $editProperty = $editProperty ?? ['section_type' => $specialType];
            ?>
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Agregar <?= htmlspecialchars(ucfirst($specialType)) ?></h1>
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update_property' : 'create_special_property' ?>">
                        <input type="hidden" name="special_type" value="<?= htmlspecialchars($specialType) ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="property_id" value="<?= (int)$propertyId ?>">
                        <?php endif; ?>
                        
                        <?php if ($specialType === 'terrenos'): ?>
                        <!-- ======================= TERRENOS INMOBILIARIOS COMPREHENSIVE FORM ======================= -->
                        
                        <!-- INFORMACIÓN GENERAL -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-blue-600">INFORMACIÓN GENERAL</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- la publicación se controla en la sección "Opciones de Publicación" más abajo -->
                                <div>
                                    <label class="block text-sm font-medium mb-1">Nombre del Proyecto *</label>
                                    <input name="terreno[nombre_proyecto]" required class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Título (para listado) *</label>
                                    <input name="title" required class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
        <label class="block text-sm font-medium mb-1">Categoría de Propiedad</label>

        <select name="property_category"
                class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                required>
            <?php if ($specialType === 'terrenos'): ?>
                <option value="terreno_inmobiliario" selected>Terreno Inmobiliario</option>
            <?php else: ?>
                <option value="">Seleccionar categoría</option>
                <?php foreach ($propertyCategories as $key => $label): ?>
                    <option value="<?= $key ?>">
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Ubicación</label>
                                    <input name="terreno[ubicacion]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Dirección completa o referencia" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Ciudad</label>
                                    <input name="terreno[ciudad]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Fecha CIP</label>
                                    <input name="terreno[fecha_cip]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Ej: 15/01/2025" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Región</label>
                                    <select name="region_id" id="region_id_terreno" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                                        <option value="">Seleccionar región</option>
                                        <?php foreach ($regions as $r): ?>
                                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Comuna</label>
                                    <select name="comuna_id" id="comuna_id_terreno" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                                        <option value="">Seleccionar comuna</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Roles (separados por coma)</label>
                                    <input name="terreno[roles]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Ej: 1234-5, 1234-6" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie Predial Min (m²)</label>
                                    <input name="terreno[superficie_predial_min]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-1">Usos de Suelo Permitidos</label>
                                    <textarea name="terreno[usos_suelo_permitidos]" rows="2" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Ej: Residencial, Comercial, Mixto..."></textarea>
                                </div>
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-1">Descripción</label>
                                    <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- PARÁMETROS NORMATIVOS -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-green-600">PARÁMETROS NORMATIVOS</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Fecha Permiso Edificación</label>
                                    <input name="terreno[fecha_permiso_edificacion]" type="date" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Zona PRC Edificación</label>
                                    <input name="terreno[zona_prc_edificacion]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Usos de Suelo</label>
                                    <input name="terreno[usos_suelo]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-2">Sistema Agrupamiento</label>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Aislado" class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                            <span class="ml-2 text-sm">Aislado</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Pareado" class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                            <span class="ml-2 text-sm">Pareado</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Continuo" class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                            <span class="ml-2 text-sm">Continuo</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Mixto" class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                            <span class="ml-2 text-sm">Mixto</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Altura Máxima (m)</label>
                                    <input name="terreno[altura_maxima]" type="text" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Rasante</label>
                                    <input name="terreno[rasante]" type="text" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Coef. Constructibilidad Máx.</label>
                                    <input name="terreno[coef_constructibilidad_max]" type="text" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Coef. Ocupación Suelo Máx.</label>
                                    <input name="terreno[coef_ocupacion_suelo_max]" type="text" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Área Libre Mínimo (%)</label>
                                    <input name="terreno[coef_area_libre_min]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Antejardín Mínimo (m)</label>
                                    <input name="terreno[antejardin_min]" type="text" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Distanciamientos</label>
                                    <input name="terreno[distanciamientos]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" />
                                </div>
                                <div class="md:col-span-2 lg:col-span-4">
                                    <label class="block text-sm font-medium mb-1">Artículos Normativos Aplicables</label>
                                    <textarea name="terreno[articulos_normativos]" rows="2" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="Artículos de la normativa aplicable..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- DENSIDADES -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-cyan-600">DENSIDADES</h2>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Densidad Bruta Max Hab/Ha</label>
                                    <input name="terreno[densidad_bruta_max_hab_ha]" type="number" step="0.1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-cyan-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Densidad Bruta Max Viv/Ha</label>
                                    <input name="terreno[densidad_bruta_max_viv_ha]" type="number" step="0.1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-cyan-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Densidad Neta Max Hab/Ha</label>
                                    <input name="terreno[densidad_neta_max_hab_ha]" type="number" step="0.1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-cyan-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Densidad Neta Max Viv/Ha</label>
                                    <input name="terreno[densidad_neta_max_viv_ha]" type="number" step="0.1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-cyan-500" />
                                </div>
                            </div>
                        </div>

                        <!-- DATOS DIMENSIONALES DEL TERRENO -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-amber-600">DATOS DIMENSIONALES DEL TERRENO</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Frente (m)</label>
                                    <input name="terreno[frente]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Fondo (m)</label>
                                    <input name="terreno[fondo]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie Bruta (m²)</label>
                                    <input name="terreno[superficie_bruta]" type="number" step="0.01" id="admin_sup_bruta" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Expropiación (m²)</label>
                                    <input name="terreno[expropiacion]" type="number" step="0.01" id="admin_sup_expropiacion" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie Neta (Útil) (m²)</label>
                                    <input name="terreno[superficie_util]" type="number" step="0.01" id="admin_sup_util" readonly class="w-full px-4 py-2 border rounded bg-gray-100 focus:ring-2 focus:ring-amber-500" title="Calculado: Bruta - Expropiación" />
                                    <p class="text-xs text-gray-500">= Bruta - Expropiación</p>
                                </div>
                            </div>
                        </div>

                        <!-- DATOS CON ANTEPROYECTO -->
                        <div class="mb-8">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-bold text-gray-800 pb-2 border-b-2 border-purple-600">DATOS CON ANTEPROYECTO</h2>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="terreno[has_anteproyecto]" value="1" id="admin_has_anteproyecto" class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span class="ml-2 text-sm font-medium">CON ANTEPROYECTO</span>
                                </label>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Viviendas</label>
                                    <input name="terreno[num_viviendas]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Estacionamientos</label>
                                    <input name="terreno[num_estacionamientos]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Est. Bicicletas</label>
                                    <input name="terreno[num_est_bicicletas]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Locales Comerciales</label>
                                    <input name="terreno[num_locales_comerciales]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Bodegas</label>
                                    <input name="terreno[num_bodegas]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" />
                                </div>
                            </div>

                            <div id="admin_tabla_ap_container" class="hidden">
                                <h4 class="font-medium text-purple-700 mb-3">Superficies Aprobadas Anteproyecto</h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border border-purple-200">
                                        <thead class="bg-purple-100">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Tipo</th>
                                                <th class="px-3 py-2 text-center">Útil (m²)</th>
                                                <th class="px-3 py-2 text-center">Común (m²)</th>
                                                <th class="px-3 py-2 text-center">Total (m²)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-t">
                                                <td class="px-3 py-2 font-medium">Edificada Bajo Terreno</td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_bajo_util]" class="w-full px-2 py-1 border rounded"></td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_bajo_comun]" class="w-full px-2 py-1 border rounded"></td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_bajo_total]" class="w-full px-2 py-1 border rounded"></td>
                                            </tr>
                                            <tr class="border-t bg-purple-50">
                                                <td class="px-3 py-2 font-medium">Edificada Sobre Terreno</td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_sobre_util]" class="w-full px-2 py-1 border rounded"></td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_sobre_comun]" class="w-full px-2 py-1 border rounded"></td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_sobre_total]" class="w-full px-2 py-1 border rounded"></td>
                                            </tr>
                                            <tr class="border-t font-semibold">
                                                <td class="px-3 py-2">Edificada Total</td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_total_util]" class="w-full px-2 py-1 border rounded bg-purple-100"></td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_total_comun]" class="w-full px-2 py-1 border rounded bg-purple-100"></td>
                                                <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_total_total]" class="w-full px-2 py-1 border rounded bg-purple-100"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- DATOS SIN ANTEPROYECTO -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-gray-600">DATOS SIN ANTEPROYECTO</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie Bruta (m²)</label>
                                    <input name="terreno[sin_superficie_bruta]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-gray-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie Útil (m²)</label>
                                    <input name="terreno[sin_superficie_util]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-gray-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie Expropiación (m²)</label>
                                    <input name="terreno[sin_superficie_expropiacion]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-gray-500" />
                                </div>
                            </div>
                        </div>

                        <!-- DATOS COMERCIALES -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-red-600">DATOS COMERCIALES</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Precio (UF)</label>
                                    <input name="terreno[precio]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Precio UF x m²</label>
                                    <input name="terreno[precio_uf_m2]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Precio Listado</label>
                                    <input name="price" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" placeholder="Precio para mostrar en listados" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Comisión (%)</label>
                                    <input name="terreno[comision]" type="number" step="0.01" value="2.00" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Moneda</label>
                                    <select name="currency" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500">
                                        <option value="UF">UF</option>
                                        <option value="CLP">CLP</option>
                                        <option value="USD">USD</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Video (YouTube)</label>
                                    <input name="terreno[video_url]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" placeholder="https://www.youtube.com/watch?v=..." />
                                </div>
                                <div class="md:col-span-2 lg:col-span-4">
                                    <label class="block text-sm font-medium mb-1">Observaciones / Comentarios</label>
                                    <textarea name="terreno[observaciones]" rows="3" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" placeholder="Notas adicionales sobre el terreno..."></textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Documento PDF (opcional)</label>
                                    <input name="pdf_documento" type="file" accept=".pdf" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" />
                                    <p class="text-xs text-gray-500 mt-1">Sube un documento PDF con información adicional del terreno</p>
                                </div>
                            </div>
                        </div>

                        <!-- FOTOS -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-indigo-600">FOTOS DEL TERRENO</h2>
                            <div>
                                <label class="block text-sm font-medium mb-1">Subir Fotos (máximo 12)</label>
                                <input name="property_photos[]" type="file" accept="image/*" multiple class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500" />
                                <p class="text-xs text-gray-500 mt-1">Formatos permitidos: JPG, JPEG, PNG</p>
                            </div>
                        </div>

                        <!-- OPCIONES DE PUBLICACIÓN -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-teal-600">OPCIONES DE PUBLICACIÓN</h2>
                            <div class="flex flex-wrap gap-6 items-center">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" checked class="w-5 h-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                    <span class="ml-2 text-sm font-medium">Publicar</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_featured" value="1" class="w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <span class="ml-2 text-sm font-medium">Destacar</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">Estado:</span>
                                    <select name="status" class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-teal-500">
                                        <option value="Activo" selected>Activo</option>
                                        <option value="Actualizar">Actualizar</option>
                                        <option value="Oferta">Oferta</option>
                                        <option value="Armar">Armar</option>
                                        <option value="Stand By">Stand By</option>
                                        <option value="Vendido">Vendido</option>
                                        <option value="Eliminar">Eliminar</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">Crear Terreno</button>
                            <a href="?action=special_list&type=terrenos" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancelar</a>
                        </div>

                        <?php elseif ($specialType === 'usa'): ?>
                        <!-- ======================= USA PROPERTIES COMPREHENSIVE FORM ======================= -->
                        
                        <!-- INFORMACIÓN GENERAL -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-red-600">INFORMACIÓN GENERAL</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Título de la Propiedad *</label>
                                    <input name="title" required class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" placeholder="Ej: Beautiful Condo in Miami Beach" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">MLS ID</label>
                                    <input name="usa[mls_id]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500" placeholder="Ej: A12345678" />
                                </div>
                            <div>
        <label class="block text-sm font-medium mb-1">Tipo de Propiedad</label>

        <?php $usaPropertyTypes = $usaModel->getUSAPropertyTypes(); ?>

        <select name="property_type_id"
                class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500"
                required>
            <option value="">Seleccionar tipo</option>

            <?php foreach ($usaPropertyTypes as $pt): ?>
                <option value="<?= $pt['id'] ?>">
                    <?= htmlspecialchars($pt['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Tipo de Operación</label>
                                    <select name="operation_type" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-red-500">
                                        <option value="Venta">Venta</option>
                                        <option value="Arriendo">Arriendo</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="inline-flex items-center cursor-pointer mt-6">
                                        <input type="checkbox" name="usa[is_project]" value="1" id="is_project_checkbox" class="w-5 h-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        <span class="ml-2 text-sm font-medium">Es un Proyecto Inmobiliario</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- UBICACIÓN -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-blue-600">UBICACIÓN</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Dirección</label>
                                    <input name="address" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Ej: 123 Ocean Drive" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Ciudad</label>
                                    <input name="usa[city]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Ej: Miami" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Estado (State)</label>
                                    <select name="usa[state]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                                        <option value="">Seleccionar estado</option>
                                        <option value="FL">Florida (FL)</option>
                                        <option value="CA">California (CA)</option>
                                        <option value="TX">Texas (TX)</option>
                                        <option value="NY">New York (NY)</option>
                                        <option value="NJ">New Jersey (NJ)</option>
                                        <option value="GA">Georgia (GA)</option>
                                        <option value="NC">North Carolina (NC)</option>
                                        <option value="AZ">Arizona (AZ)</option>
                                        <option value="NV">Nevada (NV)</option>
                                        <option value="CO">Colorado (CO)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Código Postal (ZIP)</label>
                                    <input name="usa[zip_code]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-blue-500" placeholder="Ej: 33139" />
                                </div>
                            </div>
                        </div>

                        <!-- PRECIO Y COSTOS -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-green-600">PRECIO Y COSTOS (USD)</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Precio (USD) *</label>
                                    <input name="usa[price_usd]" type="number" step="1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="450000" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Precio para Listado</label>
                                    <input name="price" type="number" step="1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="Precio en USD" />
                                    <p class="text-xs text-gray-500 mt-1">Se mostrará en el listado general</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">HOA Fee (mensual USD)</label>
                                    <input name="usa[hoa_fee]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Property Tax (anual USD)</label>
                                    <input name="usa[property_tax]" type="number" step="0.01" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-green-500" placeholder="8000" />
                                </div>
                            </div>
                        </div>

                        <!-- CARACTERÍSTICAS PRINCIPALES -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-amber-600">CARACTERÍSTICAS PRINCIPALES</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie (sqft)</label>
                                    <input name="usa[surface_sqft]" type="number" step="1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="1500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Tamaño del Lote (sqft)</label>
                                    <input name="usa[lot_size_sqft]" type="number" step="1" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="5000" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Dormitorios</label>
                                    <input name="bedrooms" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="3" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Baños</label>
                                    <input name="bathrooms" type="number" step="0.5" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Año de Construcción</label>
                                    <input name="usa[year_built]" type="number" min="1900" max="2030" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="2020" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Pisos (Stories)</label>
                                    <input name="usa[stories]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Garage (espacios)</label>
                                    <input name="usa[garage_spaces]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="2" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Estacionamientos</label>
                                    <input name="parking_spots" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500" placeholder="2" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                                <div>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="usa[pool]" value="1" class="w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <span class="ml-2 text-sm font-medium">Tiene Piscina</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="usa[waterfront]" value="1" class="w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <span class="ml-2 text-sm font-medium">Waterfront</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Tipo de Vista</label>
                                    <select name="usa[view_type]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-amber-500">
                                        <option value="">Seleccionar</option>
                                        <option value="Ocean">Ocean View</option>
                                        <option value="Bay">Bay View</option>
                                        <option value="City">City View</option>
                                        <option value="Golf">Golf View</option>
                                        <option value="Pool">Pool View</option>
                                        <option value="Garden">Garden View</option>
                                        <option value="None">Sin vista especial</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- SISTEMAS Y ACABADOS -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-purple-600">SISTEMAS Y ACABADOS</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Calefacción</label>
                                    <select name="usa[heating]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500">
                                        <option value="">Seleccionar</option>
                                        <option value="Central">Central</option>
                                        <option value="Electric">Eléctrica</option>
                                        <option value="Gas">Gas</option>
                                        <option value="Heat Pump">Heat Pump</option>
                                        <option value="None">Sin calefacción</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Aire Acondicionado</label>
                                    <select name="usa[cooling]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500">
                                        <option value="">Seleccionar</option>
                                        <option value="Central">Central A/C</option>
                                        <option value="Split">Split Units</option>
                                        <option value="Window">Window Units</option>
                                        <option value="None">Sin A/C</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Tipo de Piso</label>
                                    <input name="usa[flooring]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" placeholder="Ej: Porcelain Tile, Hardwood" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Electrodomésticos</label>
                                    <input name="usa[appliances]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" placeholder="Ej: Stainless Steel, Washer/Dryer" />
                                </div>
                            </div>
                        </div>

                        <!-- CARACTERÍSTICAS ADICIONALES -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-indigo-600">CARACTERÍSTICAS ADICIONALES</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Características Exteriores</label>
                                    <textarea name="usa[exterior_features]" rows="2" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500" placeholder="Ej: Hurricane Shutters, Private Dock, Covered Patio"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Características Interiores</label>
                                    <textarea name="usa[interior_features]" rows="2" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500" placeholder="Ej: Walk-in Closets, Smart Home, High Ceilings"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Amenidades del Edificio/Comunidad</label>
                                    <textarea name="usa[community_features]" rows="2" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500" placeholder="Ej: Gym, Pool, Concierge, Valet Parking"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- DATOS DE PROYECTO (condicional) -->
                        <div class="mb-8" id="project_section" style="display: none;">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-orange-600">DATOS DEL PROYECTO</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Número de Unidades</label>
                                    <input name="usa[project_units]" type="number" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-orange-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Desarrollador</label>
                                    <input name="usa[project_developer]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-orange-500" placeholder="Nombre del desarrollador" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Fecha Estimada de Entrega</label>
                                    <input name="usa[project_completion_date]" type="date" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-orange-500" />
                                </div>
                                <div class="md:col-span-2 lg:col-span-4">
                                    <label class="block text-sm font-medium mb-1">Amenidades del Proyecto</label>
                                    <textarea name="usa[project_amenities]" rows="2" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-orange-500" placeholder="Ej: Rooftop Pool, Sky Lounge, Private Beach Club, Spa"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- CONTACTO -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-teal-600">CONTACTO</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Número de WhatsApp</label>
                                    <input name="usa[whatsapp_number]" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-teal-500" placeholder="+1 305 123 4567" />
                                    <p class="text-xs text-gray-500 mt-1">Incluir código de país (ej: +1 para USA)</p>
                                </div>
                            </div>
                        </div>

                        <!-- DESCRIPCIÓN -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-gray-600">DESCRIPCIÓN</h2>
                            <div>
                                <label class="block text-sm font-medium mb-1">Descripción Completa</label>
                                <textarea name="description" rows="5" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-gray-500" placeholder="Descripción detallada de la propiedad..."></textarea>
                            </div>
                        </div>

                        <!-- FOTOS -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-pink-600">FOTOS DE LA PROPIEDAD</h2>
                            <div>
                                <label class="block text-sm font-medium mb-1">Subir Fotos (máximo 12)</label>
                                <input name="property_photos[]" type="file" accept="image/*" multiple class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-pink-500" />
                                <p class="text-xs text-gray-500 mt-1">Formatos permitidos: JPG, JPEG, PNG</p>
                            </div>
                        </div>

                        <!-- OPCIONES DE PUBLICACIÓN -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-slate-600">OPCIONES DE PUBLICACIÓN</h2>
                            <div class="flex flex-wrap gap-6 items-center">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" checked class="w-5 h-5 rounded border-gray-300 text-slate-600 focus:ring-slate-500">
                                    <span class="ml-2 text-sm font-medium">Publicar</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_featured" value="1" class="w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <span class="ml-2 text-sm font-medium">Destacar</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">Estado:</span>
                                    <select name="status" class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-slate-500">
                                        <option value="Activo" selected>Activo</option>
                                        <option value="Actualizar">Actualizar</option>
                                        <option value="Oferta">Oferta</option>
                                        <option value="Armar">Armar</option>
                                        <option value="Stand By">Stand By</option>
                                        <option value="Vendido">Vendido</option>
                                        <option value="Eliminar">Eliminar</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                            <div class="mt-6 flex gap-3">
                                <button type="submit" class="px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">
                                    <?= $action === 'edit' ? 'Guardar Cambios' : 'Crear Propiedad USA' ?>
                                </button>
                                <a href="?action=special_list&type=usa" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancelar</a>
                            </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const projectCheckbox = document.getElementById('is_project_checkbox');
                            const projectSection = document.getElementById('project_section');
                            
                            if (projectCheckbox && projectSection) {
                                projectCheckbox.addEventListener('change', function() {
                                    projectSection.style.display = this.checked ? 'block' : 'none';
                                });
                            }
                        });
                        </script>

                        <?php else: ?>
                        <!-- OTRAS SECCIONES (Activos) - con campos dinámicos -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-purple-600">INFORMACIÓN GENERAL</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Título *</label>
                                    <input name="title" required class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Precio</label>
                                    <input name="price" type="number" step="any" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Categoría de Propiedad *</label>
                                    <select name="property_category" id="propertyCategorySelectActivos" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500" required onchange="updateDynamicFieldsActivos()">
                                        <option value="">Seleccionar categoría</option>
                                        <?php foreach ($propertyCategories as $key => $label): ?>
                                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Comuna</label>
                                    <select name="comuna_id" class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-purple-500">
                                        <option value="">Seleccionar</option>
                                        <?php foreach ($regions as $r): ?>
                                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- CAMPOS DINÁMICOS SEGÚN CATEGORÍA -->
                        <div id="dynamicFieldsSectionActivos" class="mb-8 hidden">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-blue-600">CARACTERÍSTICAS ESPECÍFICAS</h2>
                            <div id="dynamicFieldsContainerActivos" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
                        </div>

                        <!-- CARACTERÍSTICAS DINÁMICAS -->
                        <div id="dynamicFeaturesSectionActivos" class="mb-8 hidden">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-green-600">CARACTERÍSTICAS</h2>
                            <div id="dynamicFeaturesContainerActivos" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3"></div>
                        </div>

                        <!-- COSTOS DINÁMICOS -->
                        <div id="dynamicCostsSectionActivos" class="mb-8 hidden">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-amber-600">COSTOS</h2>
                            <div id="dynamicCostsContainerActivos" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                        </div>

                        <!-- FOTOS -->
                        <div class="mb-8 mt-6">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-indigo-600">FOTOS</h2>
                            <div>
                                <label class="block text-sm font-medium mb-1">Subir Fotos (máximo 12)</label>
                                <input name="property_photos[]" type="file" accept="image/*" multiple class="w-full px-4 py-2 border rounded focus:ring-2 focus:ring-indigo-500" />
                                <p class="text-xs text-gray-500 mt-1">Formatos permitidos: JPG, JPEG, PNG</p>
                            </div>
                        </div>

                        <!-- OPCIONES DE PUBLICACIÓN -->
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-800 mb-4 pb-2 border-b-2 border-purple-600">OPCIONES DE PUBLICACIÓN</h2>
                            <div class="flex flex-wrap gap-6 items-center">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" checked class="w-5 h-5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                    <span class="ml-2 text-sm font-medium">Publicar</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_featured" value="1" class="w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <span class="ml-2 text-sm font-medium">Destacar</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">Estado:</span>
                                    <select name="status" class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                                        <option value="Activo" selected>Activo</option>
                                        <option value="Actualizar">Actualizar</option>
                                        <option value="Oferta">Oferta</option>
                                        <option value="Armar">Armar</option>
                                        <option value="Stand By">Stand By</option>
                                        <option value="Vendido">Vendido</option>
                                        <option value="Eliminar">Eliminar</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
                                <?= $action === 'edit' ? 'Guardar Cambios' : 'Crear ' . htmlspecialchars(ucfirst($specialType)) ?>
                            </button>
                            <a href="?action=special_list&type=<?= urlencode($specialType) ?>" class="ml-3 px-3 py-2 border rounded">Cancelar</a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- JavaScript para cargar comunas dinámicamente -->
                <script>
                (function() {
                    const BASE_URL_LOCAL = '<?= BASE_URL ?>';
                    const regionSelect = document.getElementById('region_id_terreno');
                    const comunaSelect = document.getElementById('comuna_id_terreno');
                    
                    if (regionSelect && comunaSelect) {
                        regionSelect.addEventListener('change', function() {
                            const regionId = this.value;
                            comunaSelect.innerHTML = '<option value="">Cargando...</option>';
                            
                            if (regionId) {
                                fetch(BASE_URL_LOCAL + 'api/comunas.php?region_id=' + regionId)
                                    .then(response => {
                                        if (!response.ok) throw new Error('Network error');
                                        return response.json();
                                    })
                                    .then(data => {
                                        comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
                                        if (Array.isArray(data)) {
                                            data.forEach(comuna => {
                                                const option = document.createElement('option');
                                                option.value = comuna.id;
                                                option.textContent = comuna.name;
                                                comunaSelect.appendChild(option);
                                            });
                                        } else if (data.error) {
                                            comunaSelect.innerHTML = '<option value="">Error: ' + data.error + '</option>';
                                        }
                                    })
                                    .catch(err => {
                                        console.error('Error loading comunas:', err);
                                        comunaSelect.innerHTML = '<option value="">Error al cargar comunas</option>';
                                    });
                            } else {
                                comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
                            }
                        });
                    }
                })();
                </script>

            <?php elseif ($action === 'dashboard'): ?>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-8">Dashboard</h1>
                
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-4 lg:p-6 border-l-4 border-blue-600">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-xs lg:text-sm font-medium">Total Propiedades</p>
                                <p class="text-2xl lg:text-3xl font-bold text-gray-900 mt-1"><?= $totalProperties ?></p>
                            </div>
                            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 lg:w-6 lg:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                <!-- Quick Actions: Add property by section -->
                

                    <div class="bg-white rounded-lg shadow p-4 lg:p-6 border-l-4 border-amber-600">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-xs lg:text-sm font-medium">Destacadas</p>
                                <p class="text-2xl lg:text-3xl font-bold text-gray-900 mt-1"><?= $featuredCount ?></p>
                            </div>
                            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 lg:w-6 lg:h-6 text-amber-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Recent Properties Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-4 lg:px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Últimas Propiedades</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Propiedad</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Precio</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($properties, 0, 5) as $property): ?>
                                    <?php 
                                    $propPhotos = $photoModel->getByPropertyId($property['id']);
                                    $thumbPhoto = !empty($propPhotos) ? getPropertyPhotoUrl($propPhotos[0]['photo_url']) : getFirstImage($property['images']);
                                    ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-4 lg:px-6 py-4">
                                            <div class="flex items-center gap-2 lg:gap-3">
                                                <img src="<?= $thumbPhoto ?>" class="w-10 h-8 lg:w-12 lg:h-10 object-cover rounded flex-shrink-0" alt="">
                                                <div class="min-w-0">
                                                    <p class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars(truncateText($property['title'], 30)) ?></p>
                                                    <p class="text-xs text-gray-600 truncate"><?= htmlspecialchars($property['comuna_name'] ?? '') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 text-sm"><?= htmlspecialchars($property['property_type'] ?? 'N/A') ?></td>
                                        <td class="px-4 lg:px-6 py-4 font-medium text-sm"><?= formatPrice($property['price']) ?></td>
                                        <td class="px-4 lg:px-6 py-4">
                                            <div class="flex gap-1 flex-wrap">
                                                <?php if ($property['is_featured']): ?>
                                                    <span class="inline-block px-2 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded">⭐</span>
                                                <?php endif; ?>
                                                <span class="inline-block px-2 py-1 <?= $property['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> text-xs font-semibold rounded">
                                                    <?= $property['is_active'] ? 'Activa' : 'Inactiva' ?>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($action === 'properties'): ?>
                <?php
                    // Show normal properties by default (exclude special sections).
                    $currentSection = $_GET['type'] ?? null; // null means default view
                    $uiSection = $currentSection ?? 'propiedades'; // used for UI labels and links

                    if ($currentSection === null) {
                        // Exclude special sections so only normal properties are shown
                        $filteredProps = array_values(array_filter($properties, fn($p) => !in_array(($p['section_type'] ?? ''), ['terrenos','activos','usa'])));
                    } else {
                        // Show only the requested special section
                        $filteredProps = array_values(array_filter($properties, fn($p) => ($p['section_type'] ?? '') === $currentSection));
                    }
                ?>
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900"><?= $sectionLabels[$uiSection] ?? 'Propiedades' ?></h1>
                        <p class="text-gray-600 text-sm mt-1">Gestiona todas las <?= strtolower($sectionLabels[$uiSection] ?? 'propiedades') ?></p>
                    </div>
                    <?php
                        if (in_array($uiSection, ['terrenos', 'activos', 'usa'])) {
                            $addUrl = "?action=add_special&type=" . urlencode($uiSection);
                        } else {
                            $addUrl = "?action=add&section=" . urlencode($uiSection);
                        }
                    ?>
                    <a href="<?= $addUrl ?>" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition text-sm">+ Agregar <?= $sectionLabels[$uiSection] ?? 'Propiedad' ?></a>
                </div>
                
                <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
                    <a href="?action=properties" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $uiSection === 'propiedades' ? 'bg-blue-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">Propiedades</a>
                    <a href="?action=properties&type=terrenos" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $uiSection === 'terrenos' ? 'bg-green-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">Terrenos (<?= $terrenosCount ?>)</a>
                    <a href="?action=properties&type=activos" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $uiSection === 'activos' ? 'bg-purple-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">Activos (<?= $activosCount ?>)</a>
                    <a href="?action=properties&type=usa" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $uiSection === 'usa' ? 'bg-red-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">USA (<?= $usaCount ?>)</a>
                </div>
                
                <?php if (empty($filteredProps)): ?>
                    <div class="bg-white rounded-lg shadow-md p-12 text-center border-t-4 border-gray-300">
                        <div class="text-5xl mb-3">📭</div>
                        <p class="text-gray-700 font-medium text-lg">No hay <?= strtolower($sectionLabels[$uiSection] ?? 'propiedades') ?> registradas</p>
                        <p class="text-gray-500 text-sm mt-2">Comienza agregando la primera <?= strtolower($sectionLabels[$uiSection] ?? 'propiedad') ?></p>
                    </div>
                <?php else: ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Propiedad</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Tipo</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Precio</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Destacar</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Estado</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredProps as $property): ?>
                                    <?php 
                                    $propPhotos = $photoModel->getByPropertyId($property['id']);
                                    $thumbPhoto = !empty($propPhotos) ? getPropertyPhotoUrl($propPhotos[0]['photo_url']) : getFirstImage($property['images']);
                                    ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-4 lg:px-6 py-4">
                                            <div class="flex items-center gap-2 lg:gap-3">
                                                <img src="<?= $thumbPhoto ?>" class="w-10 h-8 lg:w-12 lg:h-10 object-cover rounded flex-shrink-0" alt="">
                                                <div class="min-w-0">
                                                    <p class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars(truncateText($property['title'], 30)) ?></p>
                                                    <p class="text-xs text-gray-600 truncate"><?= htmlspecialchars($property['comuna_name'] ?? '') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4 text-sm"><?= htmlspecialchars($property['property_type'] ?? 'N/A') ?></td>
                                        <td class="px-4 lg:px-6 py-4 font-medium text-sm"><?= formatPrice($property['price']) ?></td>
                                        <td class="px-4 lg:px-6 py-4">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_featured">
                                                <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                                                <button type="submit" class="inline-block px-2 py-1 <?= $property['is_featured'] ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700' ?> text-xs font-semibold rounded hover:opacity-75 transition whitespace-nowrap">
                                                    <?= $property['is_featured'] ? '⭐ Si' : '☆ No' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4">
                                            <?php 
                                            $currentStatus = $property['status'] ?? 'Activo';
                                            $statusColors = [
                                                'Activo' => 'bg-green-100 text-green-800 border-green-300',
                                                'Actualizar' => 'bg-blue-100 text-blue-800 border-blue-300',
                                                'Oferta' => 'bg-purple-100 text-purple-800 border-purple-300',
                                                'Armar' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                                'Stand By' => 'bg-gray-100 text-gray-800 border-gray-300',
                                                'Vendido' => 'bg-red-100 text-red-800 border-red-300',
                                                'Eliminar' => 'bg-red-200 text-red-900 border-red-400'
                                            ];
                                            $allStatuses = ['Activo', 'Actualizar', 'Oferta', 'Armar', 'Stand By', 'Vendido', 'Eliminar'];
                                            ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                                                <select name="status" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded border <?= $statusColors[$currentStatus] ?? 'bg-gray-100' ?> cursor-pointer">
                                                    <?php foreach ($allStatuses as $st): ?>
                                                        <option value="<?= $st ?>" <?= $currentStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="px-4 lg:px-6 py-4">
                                            <div class="flex gap-1 flex-wrap">
                                                <a href="../propiedad.php?id=<?= $property['id'] ?>&section_type=<?= $uiSection ?>" target="_blank" class="inline-block px-2 lg:px-3 py-1 bg-blue-600 text-white text-xs font-semibold rounded hover:bg-blue-700 transition whitespace-nowrap">Ver</a>
                                                <a href="?action=edit&id=<?= $property['id'] ?>&type=<?= urlencode($property['section_type'] ?? 'propiedades') ?>" class="inline-block px-2 lg:px-3 py-1 bg-amber-600 text-white text-xs font-semibold rounded hover:bg-amber-700 transition whitespace-nowrap">Editar</a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_property">
                                                    <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                                                    <input type="hidden" name="section_type" value="<?= $uiSection ?>">
                                                    <button type="submit" onclick="return confirm('¿Eliminar propiedad?')" class="inline-block px-2 lg:px-3 py-1 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition whitespace-nowrap">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <div class="flex items-center gap-2 mb-8">
                    <a href="?action=properties" class="px-3 py-2 text-gray-600 hover:text-gray-900">←</a>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900"><?= $action === 'edit' ? 'Editar Propiedad' : 'Agregar Nueva Propiedad' ?></h1>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4 lg:p-8 max-w-4xl">
                    <form method="POST" class="space-y-6" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update_property' : 'create_property' ?>">
                        <?php if ($action === 'edit' && $editProperty): ?>
                            <input type="hidden" name="property_id" value="<?= $editProperty['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="bg-blue-50 rounded-lg p-4 mb-6">
                            <h3 class="text-sm font-semibold text-blue-800 mb-3">Clasificación de la Propiedad</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Sección *</label>
                                    <select name="section_type" id="sectionTypeSelect" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white" onchange="toggleSectionFields()">
                                        <?php foreach ($sectionTypes as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= (($editProperty['section_type'] ?? 'propiedades') === $key) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Categoría de Propiedad *</label>
                                    <select name="property_category" id="propertyCategorySelect" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm bg-white" onchange="updateDynamicFields()">
                                        <option value="">Seleccionar categoría</option>
                                        <?php foreach ($propertyCategories as $key => $label): ?>
                                            <option value="<?= $key ?>" <?= (($editProperty['property_category'] ?? ($editPropertyDetails['property_category'] ?? '')) === $key) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Título de la Propiedad *</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($editProperty['title'] ?? '') ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Operación *</label>
                                <select name="operation_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                    <option value="Venta" <?= ($editProperty['operation_type'] ?? '') === 'Venta' ? 'selected' : '' ?>>Venta</option>
                                    <option value="Arriendo" <?= ($editProperty['operation_type'] ?? '') === 'Arriendo' ? 'selected' : '' ?>>Arriendo</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Precio (CLP) *</label>
                                <input type="number" name="price" value="<?= $editProperty['price'] ?? '' ?>" required step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Región</label>
                                <select name="region_id" id="regionSelectAdmin" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" onchange="loadComunasAdmin(this.value, '')">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($regions as $region): ?>
                                        <option value="<?= $region['id'] ?>" <?= ($editProperty['region_id'] ?? '') == $region['id'] ? 'selected' : '' ?>><?= htmlspecialchars($region['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Comuna</label>
                                <select name="comuna_id" id="comunaSelectAdmin" data-selected="<?= $editProperty['comuna_id'] ?? '' ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                    <option value="">Seleccionar</option>
                                    <?php if (!empty($editProperty['region_id'])): ?>
                                        <?php $comunas = $locationModel->getComunas($editProperty['region_id']); foreach ($comunas as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($editProperty['comuna_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; endif; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Dormitorios</label>
                                <input type="number" name="bedrooms" value="<?= $editProperty['bedrooms'] ?? 0 ?>" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Baños</label>
                                <input type="number" name="bathrooms" value="<?= $editProperty['bathrooms'] ?? 0 ?>" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Construcción (m²)</label>
                                <input type="number" name="built_area" value="<?= $editProperty['built_area'] ?? 0 ?>" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Total (m²)</label>
                                <input type="number" name="total_area" value="<?= $editProperty['total_area'] ?? 0 ?>" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Estacionamientos</label>
                                <input type="number" name="parking_spots" value="<?= $editProperty['parking_spots'] ?? 0 ?>" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Dirección</label>
                                <input type="text" name="address" value="<?= htmlspecialchars($editProperty['address'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                                <textarea name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm resize-none"><?= htmlspecialchars($editProperty['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div id="terrenoFieldsSection" class="mt-6 border-t border-gray-200 pt-6 <?= ($editProperty['section_type'] ?? '') !== 'terrenos' ? 'hidden' : '' ?>">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Datos Específicos del Terreno</h3>
                            <?php $t = $terrenoDetails ?? ($editPropertyDetails['details'] ?? []); ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Proyecto</label>
                                    <input type="text" name="terreno[nombre_proyecto]" value="<?= htmlspecialchars($t['nombre_proyecto'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
                                    <input type="text" name="terreno[ubicacion]" value="<?= htmlspecialchars($t['ubicacion'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Dirección completa o referencia" />
                                </div>
                                <div class="md:col-span-3 lg:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Usos de Suelo Permitidos</label>
                                    <textarea name="terreno[usos_suelo_permitidos]" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"><?= htmlspecialchars($t['usos_suelo_permitidos'] ?? $t['usos_suelo'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Roles (separados por coma)</label>
                                    <input type="text" name="terreno[roles]" value="<?= htmlspecialchars($t['roles'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Ej: 1234-5, 1234-6" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                                    <input type="text" name="terreno[ciudad]" value="<?= htmlspecialchars($t['ciudad'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                    <select name="terreno[estado]" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                        <?php $estados = ['Activo','Actualizar','Oferta','Armar','Stand By','Vendido','Eliminar']; foreach($estados as $st): ?>
                                            <option value="<?= $st ?>" <?= (isset($t['estado']) && $t['estado'] === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha CIP</label>
                                    <input type="text" name="terreno[fecha_cip]" value="<?= htmlspecialchars($t['fecha_cip'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Ej: 15/01/2025" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Zona PRC Edificación</label>
                                    <input type="text" name="terreno[zona_prc_edificacion]" value="<?= htmlspecialchars($t['zona_prc_edificacion'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Usos de Suelo</label>
                                    <input type="text" name="terreno[usos_suelo]" value="<?= htmlspecialchars($t['usos_suelo'] ?? $t['usos_suelo_permitidos'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Densidad Bruta Máx (hab/ha)</label>
                                    <input type="text" name="terreno[densidad_bruta_max_hab_ha]" value="<?= htmlspecialchars($t['densidad_bruta_max_hab_ha'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Densidad Bruta Máx Viv/Ha</label>
                                    <input type="number" name="terreno[densidad_bruta_max_viv_ha]" value="<?= htmlspecialchars($t['densidad_bruta_max_viv_ha'] ?? '') ?>" step="0.1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Densidad Neta Máx Hab/Ha</label>
                                    <input type="number" name="terreno[densidad_neta_max_hab_ha]" value="<?= htmlspecialchars($t['densidad_neta_max_hab_ha'] ?? '') ?>" step="0.1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Densidad Neta Máx Viv/Ha</label>
                                    <input type="number" name="terreno[densidad_neta_max_viv_ha]" value="<?= htmlspecialchars($t['densidad_neta_max_viv_ha'] ?? '') ?>" step="0.1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Sistema Agrupamiento</label>
                                    <div class="flex flex-wrap gap-4">
                                        <?php $selected_agrupamiento = isset($t['sistema_agrupamiento']) ? (is_array($t['sistema_agrupamiento']) ? $t['sistema_agrupamiento'] : explode(',', $t['sistema_agrupamiento'])) : []; ?>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Aislado" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" <?= in_array('Aislado', $selected_agrupamiento) ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm">Aislado</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Pareado" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" <?= in_array('Pareado', $selected_agrupamiento) ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm">Pareado</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Continuo" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" <?= in_array('Continuo', $selected_agrupamiento) ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm">Continuo</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="terreno[sistema_agrupamiento][]" value="Mixto" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" <?= in_array('Mixto', $selected_agrupamiento) ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm">Mixto</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Rasante</label>
                                    <input type="text" name="terreno[rasante]" value="<?= htmlspecialchars($t['rasante'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Coef. Ocupación Suelo Máx.</label>
                                    <input type="text" name="terreno[coef_ocupacion_suelo_max]" value="<?= htmlspecialchars($t['coef_ocupacion_suelo_max'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Área Libre Mín (%)</label>
                                    <input type="number" name="terreno[coef_area_libre_min]" value="<?= htmlspecialchars($t['coef_area_libre_min'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Antejardín Mínimo (m)</label>
                                    <input type="text" name="terreno[antejardin_min]" value="<?= htmlspecialchars($t['antejardin_min'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Distanciamientos</label>
                                    <input type="text" name="terreno[distanciamientos]" value="<?= htmlspecialchars($t['distanciamientos'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div class="md:col-span-3 lg:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Artículos Normativos Aplicables</label>
                                    <textarea name="terreno[articulos_normativos]" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"><?= htmlspecialchars($t['articulos_normativos'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Superficie Útil (m²)</label>
                                    <input type="number" name="terreno[superficie_util]" value="<?= htmlspecialchars($t['superficie_util'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio UF/m²</label>
                                    <input type="number" name="terreno[precio_uf_m2]" value="<?= htmlspecialchars($t['precio_uf_m2'] ?? $t['precio_uf'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Altura Máxima</label>
                                    <input type="text" name="terreno[altura_maxima]" value="<?= htmlspecialchars($t['altura_maxima'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Coef. Constructibilidad</label>
                                    <input type="text" name="terreno[coef_constructibilidad_max]" value="<?= htmlspecialchars($t['coef_constructibilidad_max'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Frente (m)</label>
                                    <input type="number" name="terreno[frente]" value="<?= htmlspecialchars($t['frente'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fondo (m)</label>
                                    <input type="number" name="terreno[fondo]" value="<?= htmlspecialchars($t['fondo'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Expropiación (m²)</label>
                                    <input type="number" name="terreno[expropiacion]" value="<?= htmlspecialchars($t['expropiacion'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Superficie Bruta (m²)</label>
                                    <input type="number" name="terreno[superficie_bruta]" value="<?= htmlspecialchars($t['superficie_bruta'] ?? '') ?>" step="0.01" id="admin_sup_bruta" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Superficie Neta (Útil) (m²)</label>
                                    <input type="number" name="terreno[superficie_util]" value="<?= htmlspecialchars($t['superficie_util'] ?? '') ?>" step="0.01" id="admin_sup_util" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 focus:ring-2 focus:ring-amber-500 text-sm" title="Calculado: Bruta - Expropiación">
                                </div>
                            </div>

                            <div class="mt-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="font-medium text-gray-800">Datos de Anteproyecto</h4>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="terreno[has_anteproyecto]" value="1" id="edit_has_anteproyecto" <?= !empty($t['has_anteproyecto']) ? 'checked' : '' ?> class="w-5 h-5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                        <span class="ml-2 text-sm font-medium text-purple-700">CON ANTEPROYECTO</span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Viviendas</label>
                                        <input type="number" name="terreno[num_viviendas]" value="<?= htmlspecialchars($t['num_viviendas'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Estacionamientos</label>
                                        <input type="number" name="terreno[num_estacionamientos]" value="<?= htmlspecialchars($t['num_estacionamientos'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Bodegas</label>
                                        <input type="number" name="terreno[num_bodegas]" value="<?= htmlspecialchars($t['num_bodegas'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                    </div>
                                </div>

                                <div id="edit_tabla_ap_container" class="<?= !empty($t['has_anteproyecto']) ? '' : 'hidden' ?>">
                                    <h4 class="font-medium text-purple-700 mb-3">Superficies Aprobadas Anteproyecto</h4>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm border border-purple-200">
                                            <thead class="bg-purple-100">
                                                <tr>
                                                    <th class="px-3 py-2 text-left">Tipo</th>
                                                    <th class="px-3 py-2 text-center">Útil (m²)</th>
                                                    <th class="px-3 py-2 text-center">Común (m²)</th>
                                                    <th class="px-3 py-2 text-center">Total (m²)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="border-t">
                                                    <td class="px-3 py-2 font-medium">Edificada Bajo Terreno</td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_bajo_util]" value="<?= htmlspecialchars($t['ap_bajo_util'] ?? '') ?>" class="w-full px-2 py-1 border rounded"></td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_bajo_comun]" value="<?= htmlspecialchars($t['ap_bajo_comun'] ?? '') ?>" class="w-full px-2 py-1 border rounded"></td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_bajo_total]" value="<?= htmlspecialchars($t['ap_bajo_total'] ?? '') ?>" class="w-full px-2 py-1 border rounded"></td>
                                                </tr>
                                                <tr class="border-t bg-purple-50">
                                                    <td class="px-3 py-2 font-medium">Edificada Sobre Terreno</td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_sobre_util]" value="<?= htmlspecialchars($t['ap_sobre_util'] ?? '') ?>" class="w-full px-2 py-1 border rounded"></td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_sobre_comun]" value="<?= htmlspecialchars($t['ap_sobre_comun'] ?? '') ?>" class="w-full px-2 py-1 border rounded"></td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_sobre_total]" value="<?= htmlspecialchars($t['ap_sobre_total'] ?? '') ?>" class="w-full px-2 py-1 border rounded"></td>
                                                </tr>
                                                <tr class="border-t font-semibold">
                                                    <td class="px-3 py-2">Edificada Total</td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_total_util]" value="<?= htmlspecialchars($t['ap_total_util'] ?? '') ?>" class="w-full px-2 py-1 border rounded bg-purple-100"></td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_total_comun]" value="<?= htmlspecialchars($t['ap_total_comun'] ?? '') ?>" class="w-full px-2 py-1 border rounded bg-purple-100"></td>
                                                    <td class="px-3 py-2"><input type="number" step="0.01" name="terreno[ap_total_total]" value="<?= htmlspecialchars($t['ap_total_total'] ?? '') ?>" class="w-full px-2 py-1 border rounded bg-purple-100"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Superficie Predial Min (m²)</label>
                                    <input type="number" name="terreno[superficie_predial_min]" value="<?= htmlspecialchars($t['superficie_predial_min'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Superficie Edificada (m²)</label>
                                    <input type="number" name="terreno[superficie_edificada]" value="<?= htmlspecialchars($t['superficie_edificada'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Superficie Útil Anteproyecto (m²)</label>
                                    <input type="number" name="terreno[superficie_util_anteproyecto]" value="<?= htmlspecialchars($t['superficie_util_anteproyecto'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Densidad Neta</label>
                                    <input type="text" name="terreno[densidad_neta]" value="<?= htmlspecialchars($t['densidad_neta'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Densidad Máxima</label>
                                    <input type="text" name="terreno[densidad_maxima]" value="<?= htmlspecialchars($t['densidad_maxima'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">N° Est. Visitas</label>
                                    <input type="number" name="terreno[num_est_visitas]" value="<?= htmlspecialchars($t['num_est_visitas'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">N° Est. Bicicletas</label>
                                    <input type="number" name="terreno[num_est_bicicletas]" value="<?= htmlspecialchars($t['num_est_bicicletas'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">N° Locales Comerciales</label>
                                    <input type="number" name="terreno[num_locales_comerciales]" value="<?= htmlspecialchars($t['num_locales_comerciales'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Superficies Aprobadas</label>
                                    <input type="text" name="terreno[superficies_aprobadas]" value="<?= htmlspecialchars($t['superficies_aprobadas'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio (UF)</label>
                                    <input type="number" name="terreno[precio]" value="<?= htmlspecialchars($t['precio'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Comisión (%)</label>
                                    <input type="number" name="terreno[comision]" value="<?= htmlspecialchars($t['comision'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Video (YouTube)</label>
                                    <input type="text" name="terreno[video_url]" value="<?= htmlspecialchars($t['video_url'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" placeholder="https://www.youtube.com/watch?v=...">
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                                <textarea name="terreno[observaciones]" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"><?= htmlspecialchars($t['observaciones'] ?? '') ?></textarea>
                            </div>

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Documento PDF (opcional)</label>
                                <input name="pdf_documento" type="file" accept=".pdf" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm" />
                                <p class="text-xs text-gray-500 mt-1">Sube un documento PDF con información adicional del terreno</p>
                                <?php if (!empty($t['pdf_documento'])): ?>
                                    <p class="text-sm text-green-600 mt-1">PDF actual: <a href="<?= BASE_URL . ltrim($t['pdf_documento'], '/') ?>" target="_blank" class="underline">Ver PDF</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div id="usaFieldsSection" class="mt-6 border-t border-gray-200 pt-6 <?= ($editProperty['section_type'] ?? '') !== 'usa' ? 'hidden' : '' ?>">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Datos Específicos USA</h3>
                            <?php $u = $usaDetails ?? ($editPropertyDetails['details'] ?? []); ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Dirección</label>
                                    <input name="address" value="<?= htmlspecialchars($editProperty['address'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">MLS ID</label>
                                    <input name="usa[mls_id]" value="<?= htmlspecialchars($u['mls_id'] ?? $u['mls_number'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Tipo de Operación</label>
                                    <select name="operation_type" class="w-full px-4 py-2 border rounded">
                                        <option value="Venta" <?= ($editProperty['operation_type'] ?? '') === 'Venta' ? 'selected' : '' ?>>Venta</option>
                                        <option value="Arriendo" <?= ($editProperty['operation_type'] ?? '') === 'Arriendo' ? 'selected' : '' ?>>Arriendo</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Ciudad</label>
                                    <input type="text" name="usa[city]" value="<?= htmlspecialchars($u['city'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Estado (State)</label>
                                    <select name="usa[state]" class="w-full px-4 py-2 border rounded">
                                        <option value="">Seleccionar</option>
                                        <?php foreach ([ 'FL'=>'Florida','CA'=>'California','TX'=>'Texas','NY'=>'New York','NJ'=>'New Jersey','GA'=>'Georgia','NC'=>'North Carolina','AZ'=>'Arizona','NV'=>'Nevada','CO'=>'Colorado'] as $code => $name): ?>
                                            <option value="<?= $code ?>" <?= ($u['state'] ?? '') === $code ? 'selected' : '' ?>><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">ZIP Code</label>
                                    <input type="text" name="usa[zip_code]" value="<?= htmlspecialchars($u['zip_code'] ?? $u['zipcode'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Precio (USD)</label>
                                    <input type="number" name="usa[price_usd]" value="<?= htmlspecialchars($u['price_usd'] ?? '') ?>" step="1" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Superficie (sqft)</label>
                                    <input type="number" name="usa[surface_sqft]" value="<?= htmlspecialchars($u['surface_sqft'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Lot Size (sqft)</label>
                                    <input type="number" name="usa[lot_size_sqft]" value="<?= htmlspecialchars($u['lot_size_sqft'] ?? $u['lot_size'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">HOA Fee (USD/mes)</label>
                                    <input type="number" name="usa[hoa_fee]" value="<?= htmlspecialchars($u['hoa_fee'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Property Tax (USD anual)</label>
                                    <input type="number" name="usa[property_tax]" value="<?= htmlspecialchars($u['property_tax'] ?? $u['tax_annual'] ?? '') ?>" step="0.01" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Año Construcción</label>
                                    <input type="number" name="usa[year_built]" value="<?= htmlspecialchars($u['year_built'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Pisos (Stories)</label>
                                    <input type="number" name="usa[stories]" value="<?= htmlspecialchars($u['stories'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Garage (espacios)</label>
                                    <input type="number" name="usa[garage_spaces]" value="<?= htmlspecialchars($u['garage_spaces'] ?? '') ?>" class="w-full px-4 py-2 border rounded">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Tipo de Vista</label>
                                    <select name="usa[view_type]" class="w-full px-4 py-2 border rounded">
                                        <option value="">Seleccionar</option>
                                        <option value="Ocean" <?= ($u['view_type'] ?? '') === 'Ocean' ? 'selected' : '' ?>>Ocean View</option>
                                        <option value="Bay" <?= ($u['view_type'] ?? '') === 'Bay' ? 'selected' : '' ?>>Bay View</option>
                                        <option value="City" <?= ($u['view_type'] ?? '') === 'City' ? 'selected' : '' ?>>City View</option>
                                        <option value="Golf" <?= ($u['view_type'] ?? '') === 'Golf' ? 'selected' : '' ?>>Golf View</option>
                                        <option value="Pool" <?= ($u['view_type'] ?? '') === 'Pool' ? 'selected' : '' ?>>Pool View</option>
                                        <option value="Garden" <?= ($u['view_type'] ?? '') === 'Garden' ? 'selected' : '' ?>>Garden View</option>
                                        <option value="None" <?= ($u['view_type'] ?? '') === 'None' ? 'selected' : '' ?>>Sin vista especial</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Calefacción</label>
                                    <select name="usa[heating]" class="w-full px-4 py-2 border rounded">
                                        <option value="">Seleccionar</option>
                                        <option value="Central" <?= ($u['heating'] ?? '') === 'Central' ? 'selected' : '' ?>>Central</option>
                                        <option value="Electric" <?= ($u['heating'] ?? '') === 'Electric' ? 'selected' : '' ?>>Eléctrica</option>
                                        <option value="Gas" <?= ($u['heating'] ?? '') === 'Gas' ? 'selected' : '' ?>>Gas</option>
                                        <option value="Heat Pump" <?= ($u['heating'] ?? '') === 'Heat Pump' ? 'selected' : '' ?>>Heat Pump</option>
                                        <option value="None" <?= ($u['heating'] ?? '') === 'None' ? 'selected' : '' ?>>Sin calefacción</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Aire Acondicionado</label>
                                    <select name="usa[cooling]" class="w-full px-4 py-2 border rounded">
                                        <option value="">Seleccionar</option>
                                        <option value="Central" <?= ($u['cooling'] ?? '') === 'Central' ? 'selected' : '' ?>>Central A/C</option>
                                        <option value="Split" <?= ($u['cooling'] ?? '') === 'Split' ? 'selected' : '' ?>>Split Units</option>
                                        <option value="Window" <?= ($u['cooling'] ?? '') === 'Window' ? 'selected' : '' ?>>Window Units</option>
                                        <option value="None" <?= ($u['cooling'] ?? '') === 'None' ? 'selected' : '' ?>>Sin A/C</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Tipo de Piso</label>
                                    <input type="text" name="usa[flooring]" value="<?= htmlspecialchars($u['flooring'] ?? '') ?>" class="w-full px-4 py-2 border rounded" placeholder="Ej: Porcelain Tile, Hardwood">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Electrodomésticos</label>
                                    <input type="text" name="usa[appliances]" value="<?= htmlspecialchars($u['appliances'] ?? '') ?>" class="w-full px-4 py-2 border rounded" placeholder="Ej: Stainless Steel, Washer/Dryer">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="usa[pool]" value="1" <?= !empty($u['pool']) ? 'checked' : '' ?> class="w-4 h-4 text-red-600 rounded">
                                    <span class="text-sm font-medium text-gray-700">Pool</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="usa[waterfront]" value="1" <?= !empty($u['waterfront']) ? 'checked' : '' ?> class="w-4 h-4 text-red-600 rounded">
                                    <span class="text-sm font-medium text-gray-700">Waterfront</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="usa[is_project]" value="1" <?= !empty($u['is_project']) ? 'checked' : '' ?> class="w-4 h-4 text-red-600 rounded">
                                    <span class="text-sm font-medium text-gray-700">Es Proyecto</span>
                                </label>
                            </div>
                            
                            <div class="mt-4 grid grid-cols-1 gap-4">
                                <label class="block text-sm font-medium mb-1">Características Exteriores</label>
                                <textarea name="usa[exterior_features]" rows="2" class="w-full px-4 py-2 border rounded"><?= htmlspecialchars($u['exterior_features'] ?? '') ?></textarea>
                                <label class="block text-sm font-medium mb-1">Características Interiores</label>
                                <textarea name="usa[interior_features]" rows="2" class="w-full px-4 py-2 border rounded"><?= htmlspecialchars($u['interior_features'] ?? '') ?></textarea>
                                <label class="block text-sm font-medium mb-1">Amenidades Comunidad/Proyecto</label>
                                <textarea name="usa[community_features]" rows="2" class="w-full px-4 py-2 border rounded"><?= htmlspecialchars($u['community_features'] ?? $u['project_amenities'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6 mt-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fotos de la Propiedad (hasta 12)</label>
                                <?php if ($action === 'edit' && isset($editProperty['id'])): ?>
                                    <?php $propertyPhotos = $photoModel->getByPropertyId($editProperty['id']); ?>
                                    <?php if (!empty($propertyPhotos)): ?>
                                        <div class="mb-4">
                                            <p class="text-xs font-medium text-gray-600 mb-2">Fotos Actuales (<?= count($propertyPhotos) ?>):</p>
                                            <div class="space-y-3">
                                                <?php foreach ($propertyPhotos as $index => $photo): ?>
                                                    <div class="flex gap-2 items-center bg-gray-50 p-2 rounded-lg">
                                                        <div class="w-20 h-20 flex-shrink-0 rounded-lg overflow-hidden border border-gray-200">
                                                            <img src="<?= getPropertyPhotoUrl($photo['photo_url']) ?>" alt="Foto <?= $index + 1 ?>" class="w-full h-full object-cover">
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-xs text-gray-600">Orden: <span class="font-bold"><?= $index + 1 ?></span></p>
                                                            <p class="text-xs text-gray-500 truncate"><?= basename($photo['photo_url']) ?></p>
                                                        </div>
                                                        <div class="flex flex-col gap-1">
                                                            <?php if ($index > 0): ?>
                                                                <a href="?action=edit&id=<?= $editProperty['id'] ?>&type=<?= urlencode($editProperty['section_type'] ?? 'propiedades') ?>&move_photo=<?= $photo['id'] ?>&move_to=<?= $index - 1 ?>" class="px-2 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 text-center">↑</a>
                                                            <?php endif; ?>
                                                            <button type="button" onclick="deletePhoto(<?= $photo['id'] ?>, this)" class="px-2 py-1 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700 text-center">Eliminar</button>
                                                            <?php if ($index < count($propertyPhotos) - 1): ?>
                                                                <a href="?action=edit&id=<?= $editProperty['id'] ?>&type=<?= urlencode($editProperty['section_type'] ?? 'propiedades') ?>&move_photo=<?= $photo['id'] ?>&move_to=<?= $index + 1 ?>" class="px-2 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 text-center">↓</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition">
                                    <input type="file" name="property_photos[]" multiple accept="image/jpeg,image/png" class="hidden" id="propertyPhotosInput">
                                    <label for="propertyPhotosInput" class="cursor-pointer block">
                                        <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-700">Haz clic para subir fotos</p>
                                        <p class="text-xs text-gray-500">JPG o PNG (máx 12 fotos)</p>
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 mt-2" id="photoCount">Ninguna foto seleccionada</p>
                            </div>
                            
                            <script>
                            const photosInput = document.getElementById('propertyPhotosInput');
                            if (photosInput) {
                                photosInput.addEventListener('change', function() {
                                    const count = this.files.length;
                                    document.getElementById('photoCount').textContent = count === 0 ? 'Ninguna foto seleccionada' : count + ' foto(s) seleccionada(s)';
                                });
                            }
                            </script>
                            
                            <div class="md:col-span-2 space-y-3">
                                <div class="flex flex-wrap gap-6 items-center">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="is_featured" <?= ($editProperty['is_featured'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded">
                                        <span class="text-sm font-medium text-gray-700">Destacar</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="is_active" <?= ($editProperty['is_active'] ?? 1) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded">
                                        <span class="text-sm font-medium text-gray-700">Publicar</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-700">Estado:</span>
                                        <?php $currentEditStatus = $editProperty['status'] ?? 'Activo'; ?>
                                        <select name="status" class="px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                            <?php foreach (['Activo', 'Actualizar', 'Oferta', 'Armar', 'Stand By', 'Vendido', 'Eliminar'] as $st): ?>
                                                <option value="<?= $st ?>" <?= $currentEditStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dynamicFieldsSection" class="mt-6 border-t border-gray-200 pt-6 hidden">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Datos Específicos de la Propiedad</h3>
                            <div id="dynamicFieldsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
                            
                            <div id="dynamicCostsSection" class="mt-6 hidden">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Costos</h4>
                                <div id="dynamicCostsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
                            </div>
                            
                            <div id="dynamicFeaturesSection" class="mt-6 hidden">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Características (SI/NO)</h4>
                                <div id="dynamicFeaturesContainer" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3"></div>
                            </div>
                        </div>
                        
                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition text-sm">
                                <?= $action === 'edit' ? 'Guardar Cambios' : 'Crear Propiedad' ?>
                            </button>
                            <a href="?action=properties" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm">Cancelar</a>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

        </main>
    </div>
    <script>
                        document.getElementById('add-property-btn').addEventListener('click', function() {
                            document.getElementById('add-property-menu').classList.toggle('hidden');
                        });
                    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // función reutilizable para cargar comunas dado un select de region y un select de comuna
        function setupComunaLoader(regionId, comunaId, initialComunaValue) {
            const regionSelect = document.getElementById(regionId);
            const comunaSelect = document.getElementById(comunaId);
            if (!regionSelect || !comunaSelect) return;

            const fetchComunas = (selectedRegion, selectedComuna) => {
                if (!selectedRegion) {
                    comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
                    comunaSelect.disabled = true;
                    return;
                }
                comunaSelect.disabled = false;
                comunaSelect.innerHTML = '<option value="">Cargando...</option>';
                fetch('../api/comunas.php?region_id=' + encodeURIComponent(selectedRegion))
                    .then(r => r.json())
                    .then(data => {
                        let options = '<option value="">Seleccionar comuna</option>';
                        data.forEach(comuna => {
                            const selected = (comuna.id == selectedComuna) ? ' selected' : '';
                            options += `<option value="${comuna.id}"${selected}>${comuna.name}</option>`;
                        });
                        comunaSelect.innerHTML = options;
                    })
                    .catch(err => {
                        comunaSelect.innerHTML = '<option value="">Error cargando comunas</option>';
                    });
            };

            regionSelect.addEventListener('change', () => fetchComunas(regionSelect.value, null));
            // Si ya hay region seleccionada (edición), cargar comunas
            if (regionSelect.value) {
                fetchComunas(regionSelect.value, initialComunaValue);
            }
        }

        // Configurar cargador de comunas para cada formulario con sus IDs específicos
        setupComunaLoader('region_id', 'comuna_id', '<?= $editProperty['comuna_id'] ?? '' ?>');
        setupComunaLoader('region_id_terreno', 'comuna_id_terreno', '<?= $editProperty['comuna_id'] ?? '' ?>');
        setupComunaLoader('regionSelectAdmin', 'comunaSelectAdmin', '<?= $editProperty['comuna_id'] ?? '' ?>');
        setupComunaLoader('regionSelectActivos', 'comunaSelectActivos', '<?= $editProperty['comuna_id'] ?? '' ?>');
    });
    </script>
    <script>
    const BASE_URL = '<?= BASE_URL ?>';

    function deletePhoto(photoId, button) {
        if (!confirm('¿Eliminar esta foto?')) return;
        
        fetch('?delete_photo_ajax=1&photo_id=' + photoId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const photoDiv = button.closest('.flex.gap-2.items-center');
                    if (photoDiv) {
                        photoDiv.style.opacity = '0';
                        photoDiv.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => photoDiv.remove(), 300);
                    } else {
                        location.reload();
                    }
                } else {
                    alert('No se pudo eliminar la foto.');
                }
            });
    }

    const categoryFields = {
        'casa': {
            details: [
                {name: 'n_pisos', label: 'N° de Pisos', type: 'number'},
                {name: 'superficie_construida', label: 'Superficie Construida (m²)', type: 'number'},
                {name: 'superficie_total', label: 'Superficie Total (m²)', type: 'number'},
                {name: 'n_dormitorios', label: 'N° Dormitorios', type: 'number'},
                {name: 'n_banos', label: 'N° Baños', type: 'number'},
                {name: 'n_estacionamientos', label: 'N° Estacionamientos', type: 'number'},
                {name: 'orientacion', label: 'Orientación', type: 'select', options: ['Norte', 'Sur', 'Este', 'Oeste', 'Noreste', 'Noroeste', 'Sureste', 'Suroeste']},
                {name: 'ano_construccion', label: 'Año de Construcción', type: 'number'}
            ],
            features: ['Sala de Estar', 'Comedor', 'Living', 'Cocina', 'Logia', 'Bodega', 'Piscina', 'Quincho', 'Jardín', 'Portón Eléctrico', 'Calefacción Central', 'Aire Acondicionado', 'Alarma', 'Citófono'],
            costs: ['contribuciones', 'gastos_comunes']
        },
        'departamento': {
            details: [
                {name: 'piso', label: 'Piso del Departamento', type: 'number'},
                {name: 'superficie_util', label: 'Superficie Útil (m²)', type: 'number'},
                {name: 'superficie_terraza', label: 'Superficie Terraza (m²)', type: 'number'},
                {name: 'n_dormitorios', label: 'N° Dormitorios', type: 'number'},
                {name: 'n_banos', label: 'N° Baños', type: 'number'},
                {name: 'n_estacionamientos', label: 'N° Estacionamientos', type: 'number'},
                {name: 'n_bodegas', label: 'N° Bodegas', type: 'number'},
                {name: 'orientacion', label: 'Orientación', type: 'select', options: ['Norte', 'Sur', 'Este', 'Oeste', 'Noreste', 'Noroeste', 'Sureste', 'Suroeste']},
                {name: 'ano_construccion', label: 'Año de Construcción', type: 'number'}
            ],
            features: ['Sala de Estar', 'Comedor', 'Living', 'Cocina Americana', 'Logia', 'Terraza', 'Balcón', 'Calefacción Central', 'Aire Acondicionado', 'Gimnasio', 'Piscina Común', 'Sala de Eventos', 'Conserjería 24hrs', 'Citófono'],
            costs: ['contribuciones', 'gastos_comunes']
        },
        'oficina': {
            details: [
                {name: 'piso', label: 'Piso', type: 'number'},
                {name: 'superficie_util', label: 'Superficie Útil (m²)', type: 'number'},
                {name: 'n_banos', label: 'N° Baños', type: 'number'},
                {name: 'n_estacionamientos', label: 'N° Estacionamientos', type: 'number'},
                {name: 'ano_construccion', label: 'Año de Construcción', type: 'number'}
            ],
            features: ['Recepción', 'Sala de Reuniones', 'Cocina/Kitchenette', 'Bodega', 'Aire Acondicionado', 'Calefacción', 'Piso Flotante', 'Cielo Modular', 'Ascensor', 'Conserjería'],
            costs: ['contribuciones', 'gastos_comunes']
        },
        'bodega': {
            details: [
                {name: 'superficie_cubierta', label: 'Superficie Cubierta (m²)', type: 'number'},
                {name: 'superficie_patio', label: 'Superficie Patio (m²)', type: 'number'},
                {name: 'altura_util', label: 'Altura Útil (m)', type: 'number'},
                {name: 'capacidad_carga', label: 'Capacidad de Carga (kg/m²)', type: 'number'},
                {name: 'n_accesos', label: 'N° Accesos Vehiculares', type: 'number'}
            ],
            features: ['Galpón', 'Oficinas', 'Baños', 'Portón Industrial', 'Andén de Carga', 'Sistema Contra Incendios', 'Vigilancia 24hrs', 'Patio de Maniobras'],
            costs: ['contribuciones', 'gastos_comunes']
        },
        'local_comercial': {
            details: [
                {name: 'superficie_local', label: 'Superficie Local (m²)', type: 'number'},
                {name: 'superficie_bodega', label: 'Superficie Bodega (m²)', type: 'number'},
                {name: 'frente_vitrina', label: 'Frente/Vitrina (m)', type: 'number'},
                {name: 'n_banos', label: 'N° Baños', type: 'number'},
                {name: 'n_estacionamientos', label: 'N° Estacionamientos', type: 'number'}
            ],
            features: ['Vitrina', 'Bodega', 'Baño Clientes', 'Baño Personal', 'Cortina Metálica', 'Aire Acondicionado', 'Sistema Seguridad', 'Acceso Discapacitados'],
            costs: ['contribuciones', 'gastos_comunes', 'arriendo_mensual']
        },
        'parcela_con_casa': {
            details: [
                {name: 'superficie_terreno', label: 'Superficie Terreno (m²)', type: 'number'},
                {name: 'superficie_construida', label: 'Superficie Construida (m²)', type: 'number'},
                {name: 'n_dormitorios', label: 'N° Dormitorios', type: 'number'},
                {name: 'n_banos', label: 'N° Baños', type: 'number'},
                {name: 'tipo_agua', label: 'Tipo de Agua', type: 'select', options: ['APR', 'Pozo', 'Canal', 'Red Pública']},
                {name: 'tipo_electricidad', label: 'Electricidad', type: 'select', options: ['Monofásica', 'Trifásica', 'Solar', 'Sin conexión']}
            ],
            features: ['Casa Principal', 'Casa Cuidador', 'Galpón', 'Bodega', 'Quincho', 'Piscina', 'Huerto', 'Frutales', 'Corral', 'Riego Tecnificado', 'Derechos de Agua'],
            costs: ['contribuciones']
        },
        'parcela_sin_casa': {
            details: [
                {name: 'superficie_terreno', label: 'Superficie Terreno (m²)', type: 'number'},
                {name: 'tipo_suelo', label: 'Tipo de Suelo', type: 'select', options: ['Agrícola', 'Forestal', 'Mixto', 'Residencial']},
                {name: 'acceso_agua', label: 'Acceso a Agua', type: 'select', options: ['Sí', 'No', 'Factible']},
                {name: 'acceso_electricidad', label: 'Acceso a Electricidad', type: 'select', options: ['Sí', 'No', 'Factible']},
                {name: 'acceso_camino', label: 'Tipo de Acceso/Camino', type: 'select', options: ['Pavimentado', 'Ripio', 'Tierra', 'Servidumbre']}
            ],
            features: ['Derechos de Agua', 'Factibilidad Construcción', 'Cerco Perimetral', 'Portón de Acceso', 'Árboles', 'Vista Panorámica'],
            costs: ['contribuciones']
        },
        'terreno_industrial': {
            details: [
                {name: 'superficie_terreno', label: 'Superficie Terreno (m²)', type: 'number'},
                {name: 'frente_calle', label: 'Frente a Calle (m)', type: 'number'},
                {name: 'fondo_terreno', label: 'Fondo del Terreno (m)', type: 'number'},
                {name: 'uso_suelo', label: 'Uso de Suelo', type: 'select', options: ['Industrial', 'Bodegaje', 'Comercial', 'Mixto']},
                {name: 'capacidad_electrica', label: 'Capacidad Eléctrica (kVA)', type: 'number'}
            ],
            features: ['Urbanizado', 'Cierre Perimetral', 'Portón Vehicular', 'Alcantarillado', 'Gas Natural', 'Fibra Óptica', 'Guardianía'],
            costs: ['contribuciones']
        },
        'fundo': {
            details: [
                {name: 'superficie_hectareas', label: 'Superficie (Hectáreas)', type: 'number'},
                {name: 'superficie_regadio', label: 'Superficie Regadío (Ha)', type: 'number'},
                {name: 'superficie_secano', label: 'Superficie Secano (Ha)', type: 'number'},
                {name: 'derechos_agua', label: 'Derechos de Agua (L/s)', type: 'number'},
                {name: 'n_casas', label: 'N° Casas en el Predio', type: 'number'}
            ],
            features: ['Casa Patronal', 'Casa Cuidador', 'Galpones', 'Bodegas', 'Corrales', 'Sistema Riego', 'Maquinaria', 'Plantaciones', 'Ganado', 'Reservorio Agua'],
            costs: ['contribuciones']
        },
        'derechos_llave': {
            details: [
                {name: 'tipo_negocio', label: 'Tipo de Negocio', type: 'text'},
                {name: 'anos_funcionamiento', label: 'Años de Funcionamiento', type: 'number'},
                {name: 'facturacion_mensual', label: 'Facturación Mensual Promedio', type: 'number'},
                {name: 'n_empleados', label: 'N° de Empleados', type: 'number'},
                {name: 'superficie_local', label: 'Superficie Local (m²)', type: 'number'}
            ],
            features: ['Clientela Establecida', 'Marca Registrada', 'Página Web', 'Redes Sociales', 'Mobiliario', 'Equipamiento', 'Stock', 'Contratos Vigentes', 'Licencias/Patentes'],
            costs: ['arriendo_mensual', 'gastos_operacionales', 'patente_comercial']
        },
        'terreno_con_anteproyecto': {
            details: [
                {name: 'superficie_terreno', label: 'Superficie Terreno (m²)', type: 'number'},
                {name: 'superficie_construible', label: 'Superficie Construible (m²)', type: 'number'},
                {name: 'n_pisos_permitidos', label: 'N° Pisos Permitidos', type: 'number'},
                {name: 'n_unidades_proyecto', label: 'N° Unidades del Proyecto', type: 'number'},
                {name: 'estado_anteproyecto', label: 'Estado del Anteproyecto', type: 'select', options: ['Aprobado', 'En Trámite', 'Con Observaciones']},
                {name: 'vigencia_anteproyecto', label: 'Vigencia Anteproyecto (meses)', type: 'number'}
            ],
            features: ['Estudio de Suelo', 'Factibilidad Sanitaria', 'Factibilidad Eléctrica', 'Planos Arquitectura', 'Memorias de Cálculo', 'Presupuesto Construcción'],
            costs: ['contribuciones']
        },
        'terreno_sin_anteproyecto': {
            details: [
                {name: 'superficie_terreno', label: 'Superficie Terreno (m²)', type: 'number'},
                {name: 'frente_calle', label: 'Frente a Calle (m)', type: 'number'},
                {name: 'fondo_terreno', label: 'Fondo del Terreno (m)', type: 'number'},
                {name: 'uso_suelo', label: 'Uso de Suelo Permitido', type: 'select', options: ['Residencial', 'Comercial', 'Mixto', 'Industrial']},
                {name: 'coeficiente_constructibilidad', label: 'Coeficiente Constructibilidad', type: 'number'}
            ],
            features: ['Urbanizado', 'Factibilidad Agua', 'Factibilidad Eléctrica', 'Factibilidad Gas', 'Factibilidad Alcantarillado', 'Cerco', 'Esquina'],
            costs: ['contribuciones']
        }
    };

    const existingDetails = <?= json_encode($editPropertyDetails['details'] ?? []) ?>;
    const existingFeatures = <?= json_encode($editPropertyDetails['features'] ?? []) ?>;
    const existingCosts = <?= json_encode($editPropertyDetails['costs'] ?? []) ?>;

    function toggleSectionFields() {
        const sectionType = document.getElementById('sectionTypeSelect')?.value;
        const terrenoSection = document.getElementById('terrenoFieldsSection');
        const usaSection = document.getElementById('usaFieldsSection');
        const activosSection = document.getElementById('activosFieldsSection');
        
        if (terrenoSection) {
            if (sectionType === 'terrenos') {
                terrenoSection.classList.remove('hidden');
            } else {
                terrenoSection.classList.add('hidden');
            }
        }
        
        if (usaSection) {
            if (sectionType === 'usa') {
                usaSection.classList.remove('hidden');
            } else {
                usaSection.classList.add('hidden');
            }
        }

        if (activosSection) {
            if (sectionType === 'activos') {
                activosSection.classList.remove('hidden');
            } else {
                activosSection.classList.add('hidden');
            }
        }

        // Filtrar categorías: no permitir "Terreno Inmobiliario" en activos, propiedades normales o usa
        const categorySelect = document.getElementById('propertyCategorySelect');
        if (categorySelect) {
            const options = categorySelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.textContent === 'Terreno Inmobiliario') {
                    if (sectionType === 'terrenos') {
                        option.style.display = '';  // Mostrar en terrenos
                        option.disabled = false;
                    } else {
                        option.style.display = 'none';  // Ocultar en otras secciones
                        option.disabled = true;
                    }
                }
            });
        }
    }

    function updateDynamicFields() {
        const category = document.getElementById('propertyCategorySelect')?.value;
        const section = document.getElementById('dynamicFieldsSection');
        const container = document.getElementById('dynamicFieldsContainer');
        const costsSection = document.getElementById('dynamicCostsSection');
        const costsContainer = document.getElementById('dynamicCostsContainer');
        const featuresSection = document.getElementById('dynamicFeaturesSection');
        const featuresContainer = document.getElementById('dynamicFeaturesContainer');
        
        if (!category || !categoryFields[category]) {
            if (section) section.classList.add('hidden');
            return;
        }
        
        const config = categoryFields[category];
        section.classList.remove('hidden');
        
        container.innerHTML = '';
        config.details.forEach(field => {
            const existingValue = existingDetails[field.name] || '';
            let inputHtml = '';
            
            if (field.type === 'select') {
                inputHtml = `<select name="details[${field.name}]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Seleccionar</option>
                    ${field.options.map(opt => `<option value="${opt}" ${existingValue === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                </select>`;
            } else {
                inputHtml = `<input type="${field.type}" name="details[${field.name}]" value="${existingValue}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" ${field.type === 'number' ? 'step="any"' : ''}>`;
            }
            
            container.innerHTML += `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">${field.label}</label>
                    ${inputHtml}
                </div>
            `;
        });
        
        if (config.costs && config.costs.length > 0) {
            costsSection.classList.remove('hidden');
            costsContainer.innerHTML = '';
            const costLabels = {
                'contribuciones': 'Contribuciones (UF/año)',
                'gastos_comunes': 'Gastos Comunes (CLP/mes)',
                'arriendo_mensual': 'Arriendo Mensual (CLP)',
                'gastos_operacionales': 'Gastos Operacionales (CLP/mes)',
                'patente_comercial': 'Patente Comercial (CLP/año)'
            };
            config.costs.forEach(cost => {
                const existingValue = existingCosts[cost] || '';
                costsContainer.innerHTML += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">${costLabels[cost] || cost}</label>
                        <input type="number" name="costs[${cost}]" value="${existingValue}" step="any" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                `;
            });
        } else {
            costsSection.classList.add('hidden');
        }
        
        if (config.features && config.features.length > 0) {
            featuresSection.classList.remove('hidden');
            featuresContainer.innerHTML = '';
            config.features.forEach(feature => {
                const isChecked = existingFeatures.includes(feature);
                featuresContainer.innerHTML += `
                    <label class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition">
                        <input type="checkbox" name="property_features[]" value="${feature}" ${isChecked ? 'checked' : ''} class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm text-gray-700">${feature}</span>
                    </label>
                `;
            });
        } else {
            featuresSection.classList.add('hidden');
        }
    }

    function updateDynamicFieldsActivos() {
        const category = document.getElementById('propertyCategorySelectActivos')?.value;
        const section = document.getElementById('dynamicFieldsSectionActivos');
        const container = document.getElementById('dynamicFieldsContainerActivos');
        const costsSection = document.getElementById('dynamicCostsSectionActivos');
        const costsContainer = document.getElementById('dynamicCostsContainerActivos');
        const featuresSection = document.getElementById('dynamicFeaturesSectionActivos');
        const featuresContainer = document.getElementById('dynamicFeaturesContainerActivos');
        
        if (!category || !categoryFields[category]) {
            if (section) section.classList.add('hidden');
            if (costsSection) costsSection.classList.add('hidden');
            if (featuresSection) featuresSection.classList.add('hidden');
            return;
        }
        
        const config = categoryFields[category];
        if (section) section.classList.remove('hidden');
        
        // Actualizar campos específicos
        if (container) {
            container.innerHTML = '';
            config.details.forEach(field => {
                const existingValue = existingDetails[field.name] || '';
                let inputHtml = '';
                
                if (field.type === 'select') {
                    inputHtml = `<select name="details[${field.name}]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccionar</option>
                        ${field.options.map(opt => `<option value="${opt}" ${existingValue === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>`;
                } else {
                    inputHtml = `<input type="${field.type}" name="details[${field.name}]" value="${existingValue}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" ${field.type === 'number' ? 'step="any"' : ''}>`;
                }
                
                container.innerHTML += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">${field.label}</label>
                        ${inputHtml}
                    </div>
                `;
            });
        }
        
        // Actualizar costos
        if (config.costs && config.costs.length > 0 && costsSection) {
            costsSection.classList.remove('hidden');
            costsContainer.innerHTML = '';
            const costLabels = {
                'contribuciones': 'Contribuciones (UF/año)',
                'gastos_comunes': 'Gastos Comunes (CLP/mes)',
                'arriendo_mensual': 'Arriendo Mensual (CLP)',
                'gastos_operacionales': 'Gastos Operacionales (CLP/mes)',
                'patente_comercial': 'Patente Comercial (CLP/año)'
            };
            config.costs.forEach(cost => {
                const existingValue = existingCosts[cost] || '';
                costsContainer.innerHTML += `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">${costLabels[cost] || cost}</label>
                        <input type="number" name="costs[${cost}]" value="${existingValue}" step="any" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                `;
            });
        } else if (costsSection) {
            costsSection.classList.add('hidden');
        }
        
        // Actualizar características
        if (config.features && config.features.length > 0 && featuresSection) {
            featuresSection.classList.remove('hidden');
            featuresContainer.innerHTML = '';
            config.features.forEach(feature => {
                const isChecked = existingFeatures.includes(feature);
                featuresContainer.innerHTML += `
                    <label class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition">
                        <input type="checkbox" name="property_features[]" value="${feature}" ${isChecked ? 'checked' : ''} class="w-4 h-4 text-blue-600 rounded">
                        <span class="text-sm text-gray-700">${feature}</span>
                    </label>
                `;
            });
        } else if (featuresSection) {
            featuresSection.classList.add('hidden');
        }
    }

    function loadComunasActivos(regionId, selectedComunaId = '') {
        const comunaSelect = document.getElementById('comunaSelectActivos');
        if (!comunaSelect) return;
        comunaSelect.innerHTML = '<option value="">Cargando...</option>';
        if (!regionId) {
            comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
            return;
        }
        fetch(BASE_URL + 'api/comunas.php?region_id=' + regionId)
            .then(r => {
                if (!r.ok) throw new Error('Network error');
                return r.json();
            })
            .then(data => {
                comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
                if (Array.isArray(data)) {
                    data.forEach(c => {
                        const selected = (c.id == selectedComunaId) ? 'selected' : '';
                        comunaSelect.innerHTML += `<option value="${c.id}" ${selected}>${c.name}</option>`;
                    });
                } else if (data.error) {
                    comunaSelect.innerHTML = '<option value="">Error: ' + data.error + '</option>';
                }
            })
            .catch(err => {
                console.error('Error loading comunas:', err);
                comunaSelect.innerHTML = '<option value="">Error al cargar comunas</option>';
            });
    }

    function loadComunasAdmin(regionId, selectedComunaId = '') {
        const comunaSelect = document.getElementById('comunaSelectAdmin');
        if (!comunaSelect) return;
        comunaSelect.innerHTML = '<option value="">Cargando...</option>';
        if (!regionId) {
            comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
            return;
        }
        fetch(BASE_URL + 'api/comunas.php?region_id=' + regionId)
            .then(r => {
                if (!r.ok) throw new Error('Network error');
                return r.json();
            })
            .then(data => {
                comunaSelect.innerHTML = '<option value="">Seleccionar comuna</option>';
                if (Array.isArray(data)) {
                    data.forEach(c => {
                        const selected = (c.id == selectedComunaId) ? 'selected' : '';
                        comunaSelect.innerHTML += `<option value="${c.id}" ${selected}>${c.name}</option>`;
                    });
                } else if (data.error) {
                    comunaSelect.innerHTML = '<option value="">Error: ' + data.error + '</option>';
                }
            })
            .catch(err => {
                console.error('Error loading comunas:', err);
                comunaSelect.innerHTML = '<option value="">Error al cargar comunas</option>';
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateDynamicFields();
        // Ensure section-specific blocks (terreno/usa/activos) visibility matches current select
        if (typeof toggleSectionFields === 'function') toggleSectionFields();
        
        // Pre-load comunas if a region is already selected (for edit forms)
        // Only reload if PHP didn't pre-populate the select (options <= 1 means only placeholder)
        const regionAdminSelect = document.getElementById('regionSelectAdmin');
        const comunaAdminSelect = document.getElementById('comunaSelectAdmin');
        if (regionAdminSelect && comunaAdminSelect && regionAdminSelect.value) {
            const currentComunaId = comunaAdminSelect.getAttribute('data-selected') || '';
            // Only load via JS if PHP didn't already populate options
            if (comunaAdminSelect.options.length <= 1) {
                loadComunasAdmin(regionAdminSelect.value, currentComunaId);
            }
        }
        
        // Pre-load comunas for Activos if region is already selected
        const regionActivosSelect = document.getElementById('regionSelectActivos');
        const comunaActivosSelect = document.getElementById('comunaSelectActivos');
        if (regionActivosSelect && comunaActivosSelect && regionActivosSelect.value) {
            const currentComunaIdActivos = comunaActivosSelect.getAttribute('data-selected') || '';
            // Only load via JS if PHP didn't already populate options
            if (comunaActivosSelect.options.length <= 1) {
                loadComunasActivos(regionActivosSelect.value, currentComunaIdActivos);
            }
        }
        
        // Toggle tabla AP para terrenos (formulario crear)
        const adminHasAP = document.getElementById('admin_has_anteproyecto');
        const adminTablaAP = document.getElementById('admin_tabla_ap_container');
        if (adminHasAP && adminTablaAP) {
            adminHasAP.addEventListener('change', function() {
                adminTablaAP.classList.toggle('hidden', !this.checked);
            });
        }
        
        // Toggle tabla AP para terrenos (formulario editar)
        const editHasAP = document.getElementById('edit_has_anteproyecto');
        const editTablaAP = document.getElementById('edit_tabla_ap_container');
        if (editHasAP && editTablaAP) {
            editHasAP.addEventListener('change', function() {
                editTablaAP.classList.toggle('hidden', !this.checked);
            });
        }
        
        // Calcular superficie útil = bruta - expropiación
        const adminSupBruta = document.getElementById('admin_sup_bruta');
        const adminSupExpropiacion = document.getElementById('admin_sup_expropiacion');
        const adminSupUtil = document.getElementById('admin_sup_util');
        function calcularSuperficieUtilAdmin() {
            const bruta = parseFloat(adminSupBruta?.value) || 0;
            const expropiacion = parseFloat(adminSupExpropiacion?.value) || 0;
            if (adminSupUtil) adminSupUtil.value = (bruta - expropiacion).toFixed(2);
        }
        if (adminSupBruta) adminSupBruta.addEventListener('input', calcularSuperficieUtilAdmin);
        if (adminSupExpropiacion) adminSupExpropiacion.addEventListener('input', calcularSuperficieUtilAdmin);
    });
    </script>

    </body>
    </html>
