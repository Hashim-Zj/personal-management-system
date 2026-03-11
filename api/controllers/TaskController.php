<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Logger.php';

class TaskController {
    private $conn;
    private $user;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->user = AuthMiddleware::authenticate();
    }

    public function processRequest($method, $segments) {
        $id = $segments[0] ?? null;

        if ($method === 'GET' && $id) {
            $this->getTask($id);
        } elseif ($method === 'GET') {
            $this->getTasks();
        } elseif ($method === 'POST') {
            $this->createTask();
        } elseif ($method === 'PUT' && $id) {
            $this->updateTask($id);
        } elseif ($method === 'DELETE' && $id) {
            $this->deleteTask($id);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Endpoint not found"]);
        }
    }

    private function getTasks() {
        $query = "SELECT * FROM tasks WHERE user_id = :user_id ORDER BY deadline ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user->id);
        $stmt->execute();
        
        $tasks = $stmt->fetchAll();
        http_response_code(200);
        echo json_encode($tasks);
    }

    private function getTask($id) {
        $query = "SELECT * FROM tasks WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $this->user->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $task = $stmt->fetch();
            http_response_code(200);
            echo json_encode($task);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Task not found."]);
        }
    }

    private function createTask() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->title) && !empty($data->deadline)) {
            $query = "INSERT INTO tasks 
                SET user_id = :user_id, title = :title, description = :description, deadline = :deadline, 
                reminder_start_days_before = :rem_days, reminder_interval_hours = :rem_hours, next_reminder_time = :next_rem";
            $stmt = $this->conn->prepare($query);

            $desc = $data->description ?? '';
            $rem_days = $data->reminder_start_days_before ?? 1;
            $rem_hours = $data->reminder_interval_hours ?? 3;
            // SMTP Reminders allowed for all users
            $rem_days = $data->reminder_start_days_before ?? 1;
            $rem_hours = $data->reminder_interval_hours ?? 3;

            // Calculate first reminder time = deadline - X days
            if ($rem_hours > 0) {
                $deadline_time = strtotime($data->deadline);
                $next_rem = date('Y-m-d H:i:s', $deadline_time - ($rem_days * 86400));
            } else {
                $next_rem = null;
            }

            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':title', $data->title);
            $stmt->bindParam(':description', $desc);
            $stmt->bindParam(':deadline', $data->deadline);
            $stmt->bindParam(':rem_days', $rem_days);
            $stmt->bindParam(':rem_hours', $rem_hours);
            $stmt->bindParam(':next_rem', $next_rem);

            if ($stmt->execute()) {
                Logger::action($this->user->id, 'Tasks', 'Task created', ['title' => $data->title]);
                http_response_code(201);
                echo json_encode(["message" => "Task created successfully."]);
            } else {
                Logger::error('Tasks', 'Unable to create task', $this->user->id, implode(" ", $stmt->errorInfo()));
                http_response_code(503);
                echo json_encode(["message" => "Unable to create task."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data. Title and deadline are required."]);
        }
    }

    private function updateTask($id) {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->title) && !empty($data->deadline)) {
            $query = "UPDATE tasks 
                SET title = :title, description = :description, deadline = :deadline, 
                reminder_start_days_before = :rem_days, reminder_interval_hours = :rem_hours, next_reminder_time = :next_rem
                WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->conn->prepare($query);

            $desc = $data->description ?? '';
            $rem_days = $data->reminder_start_days_before ?? 1;
            $rem_hours = $data->reminder_interval_hours ?? 3;
            // SMTP Reminders allowed for all users
            $rem_days = $data->reminder_start_days_before ?? 1;
            $rem_hours = $data->reminder_interval_hours ?? 3;

            if ($rem_hours > 0) {
                $deadline_time = strtotime($data->deadline);
                $next_rem = date('Y-m-d H:i:s', $deadline_time - ($rem_days * 86400));
            } else {
                $next_rem = null;
            }

            $stmt->bindParam(':title', $data->title);
            $stmt->bindParam(':description', $desc);
            $stmt->bindParam(':deadline', $data->deadline);
            $stmt->bindParam(':rem_days', $rem_days);
            $stmt->bindParam(':rem_hours', $rem_hours);
            $stmt->bindParam(':next_rem', $next_rem);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $this->user->id);

            if ($stmt->execute()) {
                Logger::action($this->user->id, 'Tasks', 'Task updated', ['task_id' => $id]);
                http_response_code(200);
                echo json_encode(["message" => "Task updated successfully."]);
            } else {
                Logger::error('Tasks', 'Unable to update task', $this->user->id, implode(" ", $stmt->errorInfo()));
                http_response_code(503);
                echo json_encode(["message" => "Unable to update task."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data. Title and deadline are required."]);
        }
    }

    private function deleteTask($id) {
        $query = "DELETE FROM tasks WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $this->user->id);

        if ($stmt->execute()) {
            Logger::action($this->user->id, 'Tasks', 'Task deleted', ['task_id' => $id]);
            http_response_code(200);
            echo json_encode(["message" => "Task deleted."]);
        } else {
            Logger::error('Tasks', 'Unable to delete task', $this->user->id, implode(" ", $stmt->errorInfo()));
            http_response_code(503);
            echo json_encode(["message" => "Unable to delete task."]);
        }
    }
}
?>
