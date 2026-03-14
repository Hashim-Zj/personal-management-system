<?php
require_once __DIR__ . '/../config/database.php';

class Logger {

    private static $conn = null;

    private static function init() {
        if (self::$conn === null) {
            try {
                $db = new Database();
                self::$conn = $db->getConnection();
            } catch (Exception $e) {
                error_log("Logger DB init failed: " . $e->getMessage());
            }
        }
    }

    private static function writeFile($file, $message) {
        $logDir = __DIR__ . '/../../logs/';
        $date = date("Y-m-d H:i:s");

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $line = "[$date] $message" . PHP_EOL;

        file_put_contents($logDir . $file, $line, FILE_APPEND);
    }

    /**
     * DEBUG LOG
     */
    public static function debug($message) {
        if (!empty($_ENV['DEBUG_LOG']) && $_ENV['DEBUG_LOG'] === "true") {
            self::writeFile("debug.log", $message);
        }
    }

    /**
     * CRON LOG
     */
    public static function cron($message) {
        if (!empty($_ENV['CRON_LOG']) && $_ENV['CRON_LOG'] === "true") {
            self::writeFile("cron.log", $message);
        }
    }

    /**
     * JS ERROR LOG
     */
    public static function js($message) {
        if (!empty($_ENV['JS_LOG']) && $_ENV['JS_LOG'] === "true") {
            self::writeFile("js.log", $message);
        }
    }

    /**
     * ERROR LOG (DB + FILE)
     */
    public static function error($module, $message, $userId = null, $trace = null) {

        self::init();

        try {
            if (self::$conn) {
                $query = "INSERT INTO error_logs (user_id, module, message, trace)
                          VALUES (:user_id, :module, :message, :trace)";

                $stmt = self::$conn->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':module', $module);
                $stmt->bindParam(':message', $message);
                $stmt->bindParam(':trace', $trace);
                $stmt->execute();
            }

        } catch (Exception $e) {
            error_log("Failed DB error_logs: " . $e->getMessage());
        }

        // Also write to file
        self::writeFile("debug.log", "[$module] $message | TRACE: $trace");
    }

    /**
     * USER ACTION LOG
     */
    public static function action($userId, $module, $action, $details = null) {

        self::init();

        try {
            if (self::$conn) {
                $query = "INSERT INTO user_action_logs (user_id, module, action, details)
                          VALUES (:user_id, :module, :action, :details)";

                $stmt = self::$conn->prepare($query);

                $detailsStr = is_array($details) || is_object($details)
                    ? json_encode($details)
                    : $details;

                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':module', $module);
                $stmt->bindParam(':action', $action);
                $stmt->bindParam(':details', $detailsStr);
                $stmt->execute();
            }

        } catch (Exception $e) {
            error_log("Failed user_action_logs: " . $e->getMessage());
        }

        self::debug("USER ACTION [$module] $action | user:$userId | details:" . json_encode($details));
    }

    /**
     * SMTP LOG
     */
    public static function smtp($recipient, $subject, $status, $errorMessage = null, $userId = null, $taskId = null) {

        self::init();

        try {

            if (self::$conn) {
                $query = "INSERT INTO smtp_logs
                          (user_id, task_id, recipient, subject, status, error_message)
                          VALUES (:user_id, :task_id, :recipient, :subject, :status, :error_message)";

                $stmt = self::$conn->prepare($query);

                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':task_id', $taskId);
                $stmt->bindParam(':recipient', $recipient);
                $stmt->bindParam(':subject', $subject);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':error_message', $errorMessage);

                $stmt->execute();
            }

        } catch (Exception $e) {
            error_log("Failed smtp_logs: " . $e->getMessage());
        }

        self::debug("SMTP [$status] $recipient | $subject | $errorMessage");
    }
}
?>