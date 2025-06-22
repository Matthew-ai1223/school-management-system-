<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../database.php';

class Settings {
    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = Database::getInstance();
        $this->initializeSettings();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeSettings() {
        $sql = "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $this->db->getConnection()->query($sql);

        // Initialize default settings if they don't exist
        $this->setDefaultSetting('payment_verification_required', '1');
    }

    private function setDefaultSetting($key, $value) {
        $sql = "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }

    public function getSetting($key) {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['setting_value'];
        }
        return null;
    }

    public function updateSetting($key, $value) {
        $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param('ss', $value, $key);
        return $stmt->execute();
    }
} 