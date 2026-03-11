<?php
require_once __DIR__ . '/../config/database.php';

class Logger {
    private static $conn = null;

    private static function init() {
        if (self::$conn === null) {
            $db = new Database();
            self::$conn = $db->getConnection();
        }
    }

    /**
     * Log an error to the database.
     */
    public static function error($module, $message, $userId = null, $trace = null) {
        self::init();
        try {
            $query = "INSERT INTO error_logs (user_id, module, message, trace) VALUES (:user_id, :module, :message, :trace)";
            $stmt = self::$conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':module', $module);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':trace', $trace);
            $stmt->execute();
        } catch (Exception $e) {
            // Fallback to error_log if db fails
            error_log("Failed to write to error_logs: " . $e->getMessage() . " | Original Error: $message");
        }
    }

    /**
     * Log a user action explicitly.
     */
    public static function action($userId, $module, $action, $details = null) {
        self::init();
        try {
            $query = "INSERT INTO user_action_logs (user_id, module, action, details) VALUES (:user_id, :module, :action, :details)";
            $stmt = self::$conn->prepare($query);
            
            $detailsStr = is_array($details) || is_object($details) ? json_encode($details) : $details;

            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':module', $module);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $detailsStr);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to write user_action_logs: " . $e->getMessage());
        }
    }

    /**
     * Log an SMTP event.
     */
    public static function smtp($recipient, $subject, $status, $errorMessage = null, $userId = null, $taskId = null) {
        self::init();
        try {
            $query = "INSERT INTO smtp_logs (user_id, task_id, recipient, subject, status, error_message) 
                      VALUES (:user_id, :task_id, :recipient, :subject, :status, :error_message)";
            $stmt = self::$conn->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':task_id', $taskId);
            $stmt->bindParam(':recipient', $recipient);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':error_message', $errorMessage);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to write smtp_logs: " . $e->getMessage());
        }
    }
}
?>
