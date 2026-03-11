<?php
// cron/reminders.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$db = new Database();
$conn = $db->getConnection();

$now = date('Y-m-d H:i:s');

// Get all tasks whose next_reminder_time is <= now
$query = "SELECT t.*, u.email, u.username 
          FROM tasks t 
          JOIN users u ON t.user_id = u.id 
          WHERE t.next_reminder_time IS NOT NULL AND t.next_reminder_time <= :now";

$stmt = $conn->prepare($query);
$stmt->bindParam(':now', $now);
$stmt->execute();

$tasksToRemind = $stmt->fetchAll();

foreach ($tasksToRemind as $task) {
    if (sendReminderEmail($task)) {
        // Calculate next reminder time based on interval hours
        $intervalHours = (int)$task['reminder_interval_hours'];
        if ($intervalHours > 0) {
            $nextRemTime = strtotime($task['next_reminder_time']) + ($intervalHours * 3600);
            $deadlineTime = strtotime($task['deadline']);
            
            // Stop reminding if it's past the deadline
            if ($nextRemTime > $deadlineTime) {
                $newNextRem = null;
            } else {
                $newNextRem = date('Y-m-d H:i:s', $nextRemTime);
            }
        } else {
            $newNextRem = null; // No repeat
        }

        $updateQuery = "UPDATE tasks SET next_reminder_time = :next_rem WHERE id = :id";
        $upStmt = $conn->prepare($updateQuery);
        $upStmt->bindParam(':next_rem', $newNextRem);
        $upStmt->bindParam(':id', $task['id']);
        $upStmt->execute();
    }
}

function sendReminderEmail($task) {
    if (!isset($_ENV['SMTP_HOST']) || empty($_ENV['SMTP_HOST'])) {
        // Fallback to basic mail if no SMTP configured
        $subject = "Task Reminder: " . $task['title'];
        $message = "Hello " . $task['username'] . ",\n\nThis is a reminder for your task: " . $task['title'] . "\nDeadline: " . $task['deadline'] . "\n\nDescription:\n" . $task['description'] . "\n\nBest,\nPMS System";
        $headers = "From: no-reply@pms.local\r\n";
        
        $success = @mail($to, $subject, $message, $headers);
        if ($success) {
            Logger::smtp($to, $subject, 'success', null, $task['user_id'], $task['id']);
        } else {
            Logger::smtp($to, $subject, 'failed', 'Native PHP mail() failed', $task['user_id'], $task['id']);
        }
        return $success;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

        $mail->setFrom($_ENV['SMTP_USER'], 'PMS System');
        $mail->addAddress($task['email'], $task['username']);

        $mail->isHTML(false);
        $mail->Subject = 'Task Reminder: ' . $task['title'];
        $mail->Body    = "Hello " . $task['username'] . ",\n\nThis is a reminder for your task: " . $task['title'] . "\nDeadline: " . $task['deadline'] . "\n\nDescription:\n" . $task['description'] . "\n\nBest,\nPMS System";

        $mail->send();
        Logger::smtp($task['email'], $mail->Subject, 'success', null, $task['user_id'], $task['id']);
        return true;
    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
        Logger::smtp($task['email'], 'Task Reminder', 'failed', $errorMsg, $task['user_id'], $task['id']);
        error_log("Message could not be sent. Mailer Error: {$errorMsg}");
        return false;
    }
}
?>
