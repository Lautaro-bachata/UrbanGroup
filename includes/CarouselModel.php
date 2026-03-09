<?php
require_once __DIR__ . '/../config/database.php';

class CarouselModel {
    private $db;
    private $uploadDir;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->uploadDir = __DIR__ . '/../public/uploads/carousel/';
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        // Ensure carousel_images has expected columns (prevent fatal errors on older schemas)
        $this->ensureCarouselColumns();
    }

    private function ensureCarouselColumns() {
        try {
            $required = [
                'file_path' => [
                    'sqlite' => "ALTER TABLE carousel_images ADD COLUMN file_path VARCHAR(255)",
                    'mysql'  => "ALTER TABLE carousel_images ADD COLUMN file_path VARCHAR(255)"
                ],
                'title' => [
                    'sqlite' => "ALTER TABLE carousel_images ADD COLUMN title VARCHAR(255) DEFAULT ''",
                    'mysql'  => "ALTER TABLE carousel_images ADD COLUMN title VARCHAR(255) DEFAULT ''"
                ],
                'alt_text' => [
                    'sqlite' => "ALTER TABLE carousel_images ADD COLUMN alt_text VARCHAR(255) DEFAULT ''",
                    'mysql'  => "ALTER TABLE carousel_images ADD COLUMN alt_text VARCHAR(255) DEFAULT ''"
                ],
                'display_order' => [
                    'sqlite' => "ALTER TABLE carousel_images ADD COLUMN display_order INTEGER DEFAULT 0",
                    'mysql'  => "ALTER TABLE carousel_images ADD COLUMN display_order INT DEFAULT 0"
                ],
                'is_active' => [
                    'sqlite' => "ALTER TABLE carousel_images ADD COLUMN is_active INTEGER DEFAULT 1",
                    'mysql'  => "ALTER TABLE carousel_images ADD COLUMN is_active TINYINT(1) DEFAULT 1"
                ],
                'created_at' => [
                    'sqlite' => "ALTER TABLE carousel_images ADD COLUMN created_at DATETIME",
                    'mysql'  => "ALTER TABLE carousel_images ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
                ],
                'updated_at' => [
                    'sqlite' => "ALTER TABLE carousel_images ADD COLUMN updated_at DATETIME",
                    'mysql'  => "ALTER TABLE carousel_images ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
                ]
            ];

            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $this->db->query("PRAGMA table_info('carousel_images')");
                $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $existing = array_map(function($c){ return strtolower($c['name']); }, $cols);
                foreach ($required as $col => $sqls) {
                    if (!in_array(strtolower($col), $existing)) {
                        try { $this->db->exec($sqls['sqlite']); } catch (Exception $e) { /* ignore */ }
                    }
                }
            } else {
                $dbName = defined('DB_NAME') ? DB_NAME : null;
                $existing = [];
                if ($dbName) {
                    $stmt = $this->db->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = 'carousel_images'");
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
            // non-fatal
        }
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM carousel_images ORDER BY display_order ASC, id ASC");
        return $stmt->fetchAll();
    }

    public function getActive() {
        $stmt = $this->db->query("SELECT * FROM carousel_images WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM carousel_images WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function create($data, $file = null) {
        $filePath = null;
        
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $filePath = $this->uploadImage($file);
            if (!$filePath) {
                return ['error' => 'Error al subir la imagen'];
            }
        }
        
        if (!$filePath && empty($data['file_path'])) {
            return ['error' => 'Se requiere una imagen'];
        }

        $maxOrder = $this->db->query("SELECT MAX(display_order) as max_order FROM carousel_images")->fetch();
        $nextOrder = ($maxOrder['max_order'] ?? 0) + 1;

        $sql = "INSERT INTO carousel_images (file_path, title, alt_text, display_order, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $filePath ?? $data['file_path'],
            $data['title'] ?? '',
            $data['alt_text'] ?? '',
            $data['display_order'] ?? $nextOrder,
            $data['is_active'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }

    public function update($id, $data, $file = null) {
        $image = $this->getById($id);
        if (!$image) {
            return ['error' => 'Imagen no encontrada'];
        }

        $fields = [];
        $params = [];

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $filePath = $this->uploadImage($file);
            if ($filePath) {
                $this->deleteImageFile($image['file_path']);
                $fields[] = "file_path = ?";
                $params[] = $filePath;
            }
        }

        $allowedFields = ['title', 'alt_text', 'display_order', 'is_active'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE carousel_images SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $image = $this->getById($id);
        if ($image) {
            $this->deleteImageFile($image['file_path']);
        }
        
        $stmt = $this->db->prepare("DELETE FROM carousel_images WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function toggleActive($id) {
        $stmt = $this->db->prepare("UPDATE carousel_images SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function updateOrder($orderedIds) {
        foreach ($orderedIds as $order => $id) {
            $stmt = $this->db->prepare("UPDATE carousel_images SET display_order = ? WHERE id = ?");
            $stmt->execute([$order + 1, $id]);
        }
        return true;
    }

    public function count() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM carousel_images");
        $result = $stmt->fetch();
        return $result['total'];
    }

    public function countActive() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM carousel_images WHERE is_active = 1");
        $result = $stmt->fetch();
        return $result['total'];
    }

    private function uploadImage($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        if ($file['size'] > $maxSize) {
            return null;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $destination = $this->uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return 'uploads/carousel/' . $filename;
        }

        return null;
    }

    private function deleteImageFile($filePath) {
        if ($filePath) {
            $fullPath = __DIR__ . '/../public/' . $filePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }
}
