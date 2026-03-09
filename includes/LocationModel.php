<?php
require_once __DIR__ . '/../config/database.php';

class LocationModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getRegions() {
        try {
            $stmt = $this->db->query("SELECT * FROM regions ORDER BY name ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('LocationModel::getRegions error: ' . $e->getMessage());
            return [];
        }
    }

    public function getComunas($regionId = null) {
        try {
            if ($regionId) {
                $stmt = $this->db->prepare("SELECT * FROM comunas WHERE region_id = ? ORDER BY name ASC");
                $stmt->execute([$regionId]);
            } else {
                $stmt = $this->db->query("SELECT c.*, r.name as region_name FROM comunas c JOIN regions r ON c.region_id = r.id ORDER BY r.name, c.name ASC");
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('LocationModel::getComunas error: ' . $e->getMessage());
            return [];
        }
    }

    public function getComunasByRegion($regionId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM comunas WHERE region_id = ? ORDER BY name ASC");
            $stmt->execute([$regionId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('LocationModel::getComunasByRegion error: ' . $e->getMessage());
            return [];
        }
    }

    public function getRegionById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM regions WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('LocationModel::getRegionById error: ' . $e->getMessage());
            return null;
        }
    }

    public function getComunaById($id) {
        try {
            $stmt = $this->db->prepare("SELECT c.*, r.name as region_name FROM comunas c JOIN regions r ON c.region_id = r.id WHERE c.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('LocationModel::getComunaById error: ' . $e->getMessage());
            return null;
        }
    }

    public function createRegion($name, $code = '') {
        try {
            $stmt = $this->db->prepare("INSERT INTO regions (name, code) VALUES (?, ?)");
            $stmt->execute([$name, $code]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('LocationModel::createRegion error: ' . $e->getMessage());
            return false;
        }
    }

    public function updateRegion($id, $name, $code = '') {
        try {
            $stmt = $this->db->prepare("UPDATE regions SET name = ?, code = ? WHERE id = ?");
            return $stmt->execute([$name, $code, $id]);
        } catch (PDOException $e) {
            error_log('LocationModel::updateRegion error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteRegion($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM regions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('LocationModel::deleteRegion error: ' . $e->getMessage());
            return false;
        }
    }

    public function createComuna($name, $regionId) {
        try {
            $stmt = $this->db->prepare("INSERT INTO comunas (name, region_id) VALUES (?, ?)");
            $stmt->execute([$name, $regionId]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('LocationModel::createComuna error: ' . $e->getMessage());
            return false;
        }
    }

    public function updateComuna($id, $name, $regionId) {
        try {
            $stmt = $this->db->prepare("UPDATE comunas SET name = ?, region_id = ? WHERE id = ?");
            return $stmt->execute([$name, $regionId, $id]);
        } catch (PDOException $e) {
            error_log('LocationModel::updateComuna error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteComuna($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM comunas WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('LocationModel::deleteComuna error: ' . $e->getMessage());
            return false;
        }
    }
}
