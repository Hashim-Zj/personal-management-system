<?php
// cron/reminders.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/config/database.php';
set_time_limit(0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

require_once __DIR__ . '/../api/utils/Logger.php';

Logger::cron("Reminder cron started");


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

function sendReminderEmail($task)
{
  $email = $task['email'];

  // 1. Basic email format validation
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Logger::cron("Invalid email format skipped: {$email} for task '{$task['title']}'");
    return false;
  }

  // 2. Optional: Check if email domain exists (DNS MX record)
  $domain = substr(strrchr($email, "@"), 1);
  if ($domain && !checkdnsrr($domain, "MX")) {
    Logger::cron("Email domain not found or no MX record: {$email} for task '{$task['title']}'");
    return false;
  }

  // 3. Skip sending if SMTP is not configured
  if (empty($_ENV['SMTP_HOST'])) {
    Logger::cron("SMTP not configured, skipping email for task '{$task['title']}' to {$email}");
    return false;
  }

  // 4. Prepare SMTP email using PHPMailer
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

    // Prepare variables for HTML email
    $username    = htmlspecialchars($task['username']);
    $title       = htmlspecialchars($task['title']);
    $deadline    = htmlspecialchars($task['deadline']);
    $description = nl2br(htmlspecialchars($task['description']));

    $now = new DateTime();
    $deadlineDate = new DateTime($task['deadline']);
    $interval = $now->diff($deadlineDate);
    $daysLeft = $interval->format('%r%a');

    // Compose HTML email
    $htmlBody = "
        <html>
        <head>
          <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .header { background: #007acc; color: white; padding: 10px; font-size: 20px; font-weight: bold; }
            .content { padding: 20px; }
            .footer { font-size: 12px; color: #666; padding: 10px; text-align: center; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #007acc; color: white; }
          </style>
        </head>
        <body>
          <div class='header'>Task Reminder</div>
          <div class='content'>
            <p>Hello <strong>{$username}</strong>,</p>
            <p>This is a reminder for your task:</p>
            <table>
              <tr><th>Title</th><td>{$title}</td></tr>
              <tr><th>Deadline</th><td>{$deadline}</td></tr>
              <tr><th>Days Remaining</th><td>{$daysLeft}</td></tr>
              <tr><th>Description</th><td>{$description}</td></tr>
            </table>
            <p>Best regards,<br>PMS System</p>
          </div>
          <div class='footer'>Please do not reply to this automated email.</div>
        </body>
        </html>
        ";

    $mail->isHTML(true);
    $mail->Subject = 'Task Reminder: ' . $task['title'];
    $mail->Body    = $htmlBody;

    $mail->send();
    Logger::smtp($email, $mail->Subject, 'success', null, $task['user_id'], $task['id']);
    Logger::cron("Reminder sent to {$email} for task '{$task['title']}' [Status: success]");

    return true;
  } catch (Exception $e) {
    $errorMsg = $mail->ErrorInfo ?: $e->getMessage();
    Logger::smtp($email, 'Task Reminder', 'failed', $errorMsg, $task['user_id'], $task['id']);
    Logger::cron("Reminder failed to {$email} for task '{$task['title']}' [Error: {$errorMsg}]");
    return false;
  }
}
