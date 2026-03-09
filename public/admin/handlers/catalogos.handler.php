<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'create_property_type':
        $name = sanitizeInput($_POST['name'] ?? '');
        if ($name) {
            $propertyTypeModel->create(['name' => $name]);
        }
        break;

    case 'update_property_type':
        $id = (int)$_POST['id'];
        $name = sanitizeInput($_POST['name'] ?? '');
        if ($id && $name) {
            $propertyTypeModel->update($id, ['name' => $name]);
        }
        break;

    case 'create_region':
        $name = sanitizeInput($_POST['name'] ?? '');
        if ($name) {
            $locationModel->createRegion($name);
        }
        break;

    case 'create_comuna':
        $name = sanitizeInput($_POST['name'] ?? '');
        $regionId = (int)$_POST['region_id'];
        if ($name && $regionId) {
            $locationModel->createComuna($name, $regionId);
        }
        break;
}
