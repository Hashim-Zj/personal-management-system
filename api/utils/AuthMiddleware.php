<?php
require_once __DIR__ . '/JwtHandler.php';
require_once __DIR__ . '/../config/database.php';

class AuthMiddleware
{
  public static function authenticate()
  {
    // Fallback for apache configurations
    $headers = [];
    if (function_exists('getallheaders')) {
      $headers = getallheaders();
    } else {
      foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
          $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
      }
    }

    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    $jwt = null;

    if ($authHeader) {
      $parts = explode(" ", $authHeader);
      if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
        $jwt = $parts[1];
      }
    }

    if (!$jwt && isset($_GET['token'])) {
      $jwt = $_GET['token'];
    }

    if (!$jwt) {
      http_response_code(401);
      echo json_encode(["message" => "Access denied. Token missing."]);
      exit();
    }

    $jwtHandler = new JwtHandler();
    $decodedData = $jwtHandler->jwtDecodeData($jwt);

    if ($decodedData) {
      $db = new Database();
      $conn = $db->getConnection();

      // fetch user record
      $query = "SELECT u.role, p.* FROM users u 
                      LEFT JOIN user_permissions p ON u.id = p.user_id 
                      WHERE u.id = :id LIMIT 1";
      $stmt = $conn->prepare($query);
      $stmt->bindParam(':id', $decodedData->id);
      $stmt->execute();

      if ($stmt->rowCount() > 0) {
        $userRec = $stmt->fetch(PDO::FETCH_ASSOC);
        $decodedData->role = $userRec['role'];
        $decodedData->permissions = [
          'access_tasks' => (bool)$userRec['access_tasks'],
          'access_transactions' => (bool)$userRec['access_transactions'],
          'access_reports' => (bool)$userRec['access_reports'],
          'export_pdf' => (bool)$userRec['export_pdf'],
          'smtp_reminders' => (bool)$userRec['smtp_reminders']
        ];
        return $decodedData;
      }
    }

    http_response_code(401);
    echo json_encode(["message" => "Access denied. Invalid token or user."]);
    exit();
  }
}
