<?php
// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();


require_once __DIR__ . '/utils/Logger.php';

Logger::debug(
  $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']
);


set_error_handler(function ($errno, $errstr, $errfile, $errline) {

  Logger::error(
    "PHP",
    $errstr,
    null,
    "$errfile:$errline"
  );
});

// Basic error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Simple router
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $request_uri);

$apiIndex = array_search('api', $uri);
if ($apiIndex === false) {
  echo json_encode(["message" => "Invalid API Path"]);
  exit();
}

// Ensure the controller exists
$resource = $uri[$apiIndex + 1] ?? null;

if (!$resource) {
  echo json_encode(["message" => "API is running."]);
  exit();
}

// Pass remaining URI segments to controllers if needed
$segments = array_slice($uri, $apiIndex + 2);


switch ($resource) {
  case 'auth':
    require_once __DIR__ . '/controllers/AuthController.php';
    $controller = new AuthController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $segments);
    break;
  case 'tasks':
    require_once __DIR__ . '/controllers/TaskController.php';
    $controller = new TaskController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $segments);
    break;
  case 'transactions':
    require_once __DIR__ . '/controllers/TransactionController.php';
    $controller = new TransactionController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $segments);
    break;
  case 'import':
    require_once __DIR__ . '/controllers/ImportController.php';
    $controller = new ImportController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $segments);
    break;
  case 'reports':
    require_once __DIR__ . '/controllers/ReportController.php';
    $controller = new ReportController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $segments);
    break;
  case 'admin':
    require_once __DIR__ . '/controllers/AdminController.php';
    $controller = new AdminController();
    $controller->processRequest($_SERVER['REQUEST_METHOD'], $segments);
    break;
  default:
    http_response_code(404);
    echo json_encode(["message" => "Endpoint '$resource' not found"]);
    break;
}
