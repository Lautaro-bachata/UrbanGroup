<?php
require_once __DIR__ . '/../config/database.php';

class PhotoModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByPropertyId($propertyId) {
        // Devuelve primero la foto marcada como main, luego por orden explícito
        $stmt = $this->db->prepare("SELECT id, property_id, url as photo_url, is_main, sort_order as display_order, created_at FROM property_photos WHERE property_id = ? ORDER BY is_main DESC, sort_order ASC");
        $stmt->execute([$propertyId]);
        return $stmt->fetchAll();
    }

    public function create($propertyId, $photoUrl, $displayOrder = 0) {
        $stmt = $this->db->prepare("INSERT INTO property_photos (property_id, url, sort_order) VALUES (?, ?, ?)");
        return $stmt->execute([$propertyId, $photoUrl, $displayOrder]);
    }

    public function delete($photoId) {
        $stmt = $this->db->prepare("DELETE FROM property_photos WHERE id = ?");
        return $stmt->execute([$photoId]);
    }

    public function deleteByPropertyId($propertyId) {
        $stmt = $this->db->prepare("DELETE FROM property_photos WHERE property_id = ?");
        return $stmt->execute([$propertyId]);
    }

    public function getById($photoId) {
        $stmt = $this->db->prepare("SELECT id, property_id, url as photo_url, is_main, sort_order as display_order, created_at FROM property_photos WHERE id = ?");
        $stmt->execute([$photoId]);
        return $stmt->fetch();
    }

    public function updateDisplayOrder($photoId, $displayOrder) {
        $stmt = $this->db->prepare("UPDATE property_photos SET sort_order = ? WHERE id = ?");
        return $stmt->execute([$displayOrder, $photoId]);
    }

    public function setAsMain($photoId, $propertyId) {
        $this->db->prepare("UPDATE property_photos SET is_main = 0 WHERE property_id = ?")->execute([$propertyId]);
        $stmt = $this->db->prepare("UPDATE property_photos SET is_main = 1 WHERE id = ?");
        return $stmt->execute([$photoId]);
    }
}
