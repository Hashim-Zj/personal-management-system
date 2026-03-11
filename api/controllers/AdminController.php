<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Logger.php';

class AdminController {
    private $conn;
    private $user;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->user = AuthMiddleware::authenticate();

        if ($this->user->role !== 'admin') {
            http_response_code(403);
            echo json_encode(["message" => "Access denied. Admin privileges required."]);
            exit();
        }
    }

    public function processRequest($method, $segments) {
        $action = $segments[0] ?? null;
        $id = $segments[1] ?? null;

        if ($method === 'GET' && $action === 'users') {
            $this->getUsers();
        } elseif ($method === 'PUT' && $action === 'permissions' && $id) {
            $this->updatePermissions($id);
        } elseif ($method === 'GET' && $action === 'logs') {
            $type = $segments[1] ?? 'errors';
            $this->getLogs($type);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Admin endpoint not found"]);
        }
    }

    private function getUsers() {
        $query = "SELECT u.id, u.username, u.email, u.role, p.access_tasks, p.access_transactions, p.access_reports, p.export_pdf, p.smtp_reminders 
                  FROM users u 
                  LEFT JOIN user_permissions p ON u.id = p.user_id 
                  ORDER BY u.id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($users);
    }

    private function updatePermissions($userId) {
        $data = json_decode(file_get_contents("php://input"));
        
        // Ensure user permissions row exists
        $check = $this->conn->prepare("SELECT user_id FROM user_permissions WHERE user_id = :id");
        $check->bindParam(':id', $userId);
        $check->execute();
        
        if ($check->rowCount() === 0) {
            $init = $this->conn->prepare("INSERT INTO user_permissions (user_id) VALUES (:id)");
            $init->bindParam(':id', $userId);
            $init->execute();
        }

        $query = "UPDATE user_permissions SET 
                  access_tasks = :access_tasks, 
                  access_transactions = :access_transactions, 
                  access_reports = :access_reports, 
                  export_pdf = :export_pdf, 
                  smtp_reminders = :smtp_reminders 
                  WHERE user_id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $t = isset($data->access_tasks) ? (int)$data->access_tasks : 0;
        $tx = isset($data->access_transactions) ? (int)$data->access_transactions : 0;
        $r = isset($data->access_reports) ? (int)$data->access_reports : 0;
        $p = isset($data->export_pdf) ? (int)$data->export_pdf : 0;
        $s = isset($data->smtp_reminders) ? (int)$data->smtp_reminders : 0;

        $stmt->bindParam(':access_tasks', $t);
        $stmt->bindParam(':access_transactions', $tx);
        $stmt->bindParam(':access_reports', $r);
        $stmt->bindParam(':export_pdf', $p);
        $stmt->bindParam(':smtp_reminders', $s);
        $stmt->bindParam(':id', $userId);

        if ($stmt->execute()) {
            Logger::action($this->user->id, 'Admin', 'Updated user permissions', ['target_user_id' => $userId]);
            http_response_code(200);
            echo json_encode(["message" => "Permissions updated successfully."]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "Unable to update permissions."]);
        }
    }

    private function getLogs($type) {
        // Simple log fetching
        if ($type === 'errors') {
            $query = "SELECT l.*, u.username FROM error_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 100";
        } elseif ($type === 'actions') {
            $query = "SELECT l.*, u.username FROM user_action_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 100";
        } elseif ($type === 'smtp') {
            $query = "SELECT l.*, u.username FROM smtp_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 100";
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid log type"]);
            return;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($logs);
    }
}
?>
