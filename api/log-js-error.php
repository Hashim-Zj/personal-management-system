<?php

require_once __DIR__ . '/utils/Logger.php';

$data = json_decode(file_get_contents("php://input"), true);

Logger::js(
    $data['message'] .
    " | " . $data['source'] .
    " line:" . $data['lineno']
);

echo json_encode(["status"=>"logged"]);