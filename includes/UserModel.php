<?php
require_once __DIR__ . '/../config/database.php';

class UserModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        // Ensure expected columns exist to avoid SQL errors on older schemas
        $this->ensureUsersColumns();
    }

    public function authenticate($identifier, $password) {
        // Accept either username or email. Use is_active when available.
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $useIsActive = $this->columnExists('users', 'is_active');

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $sql = "SELECT * FROM users WHERE email = ?" . ($useIsActive ? " AND is_active = 1" : "");
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$identifier]);
        } else {
            $sql = "SELECT * FROM users WHERE username = ?" . ($useIsActive ? " AND is_active = 1" : "");
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$identifier]);
        }

        $user = $stmt->fetch();

        if ($user) {
            // If password matches a hash, it's valid.
            if (password_verify($password, $user['password'])) {
                return $user;
            }
            
            // If not, check if it's a plain text password (insecure legacy).
            if ($password === $user['password']) {
                // This is an insecure password. Let's upgrade it to a hash automatically.
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $user['id']]);
                
                // Return the user, allowing login this one time with the plain text password.
                return $user;
            }
        }
        
        return false;
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getPartners() {
        $stmt = $this->db->query("SELECT * FROM users WHERE role = 'partner' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function create($data) {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, password, name, email, phone, role, company_name, photo_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['username'],
            $hashedPassword,
            $data['name'],
            $data['email'],
            $data['phone'] ?? '',
            $data['role'] ?? 'partner',
            $data['company_name'] ?? '',
            $data['photo_url'] ?? null,
            $data['is_active'] ?? 1
        ];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['name', 'email', 'phone', 'company_name', 'photo_url', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (!empty($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($id) {
        // Safer delete: dissociate properties instead of removing them, then soft-delete user.
        // If you truly want to remove data, run an explicit admin action.
        try {
            if ($this->columnExists('properties', 'partner_id')) {
                $stmt = $this->db->prepare("UPDATE properties SET partner_id = NULL WHERE partner_id = ?");
                $stmt->execute([$id]);
            }

            // Prefer soft-delete: set is_active = 0 if column exists, otherwise remove user row.
            if ($this->columnExists('users', 'is_active')) {
                $stmt = $this->db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                return $stmt->execute([$id]);
            } else {
                $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
                return $stmt->execute([$id]);
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Ensure commonly-used columns exist on the users table to avoid runtime SQL errors.
     */
    private function ensureUsersColumns() {
        // If table doesn't exist, nothing we can do here.
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            // Ensure is_active exists
            if (!$this->columnExists('users', 'is_active')) {
                if ($driver === 'sqlite') {
                    $this->db->exec("ALTER TABLE users ADD COLUMN is_active INTEGER DEFAULT 1");
                } else {
                    $this->db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
                }
            }
            
            // Ensure phone exists
            if (!$this->columnExists('users', 'phone')) {
                $this->db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20)");
            }
            
            // Ensure company_name exists
            if (!$this->columnExists('users', 'company_name')) {
                $this->db->exec("ALTER TABLE users ADD COLUMN company_name VARCHAR(255)");
            }
            
            // Ensure photo_url exists
            if (!$this->columnExists('users', 'photo_url')) {
                $this->db->exec("ALTER TABLE users ADD COLUMN photo_url VARCHAR(500)");
            }
        } catch (PDOException $e) {
            // If ALTER fails due to permissions or other reasons, don't break the app — other code paths will handle missing column.
        }
    }

    private function columnExists($table, $column) {
        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->db->prepare("PRAGMA table_info($table)");
                $stmt->execute();
                $cols = $stmt->fetchAll();
                foreach ($cols as $c) {
                    if (strcasecmp($c['name'], $column) === 0) return true;
                }
                return false;
            } else {
                // MySQL / MariaDB
                $dbName = defined('DB_NAME') ? DB_NAME : null;
                if ($dbName) {
                    $stmt = $this->db->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1");
                    $stmt->execute([$dbName, $table, $column]);
                    $r = $stmt->fetch();
                    return !empty($r);
                }
                return false;
            }
        } catch (PDOException $e) {
            return false;
        }
    }

    public function toggleActive($id) {
        $stmt = $this->db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getPropertyCount($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM properties WHERE partner_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'];
    }
}
