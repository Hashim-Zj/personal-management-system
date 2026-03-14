<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

echo json_encode([
  'API_URL' => $_ENV['API_URL'] ?? 'http://localhost/api/index.php'
]);
