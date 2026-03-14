<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/JwtHandler.php';
require_once __DIR__ . '/../utils/Logger.php';

class AuthController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function processRequest($method, $segments) {
        $action = $segments[0] ?? null;

        if ($method === 'POST' && $action === 'register') {
            $this->register();
        } elseif ($method === 'POST' && $action === 'login') {
            $this->login();
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Auth endpoint not found"]);
        }
    }

    private function register() {
        $data = json_decode(file_get_contents("php://input"));
        error_log($data);
        if (!empty($data->username) && !empty($data->email) && !empty($data->password)) {
            // Check if email exists
            $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $data->email);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "Email already exists."]);
                return;
            }

            // Insert new user
            $query = "INSERT INTO users SET username = :username, email = :email, password_hash = :password_hash";
            $stmt = $this->conn->prepare($query);

            $username = htmlspecialchars(strip_tags($data->username));
            $email = htmlspecialchars(strip_tags($data->email));
            $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);

            if ($stmt->execute()) {
                $newUserId = $this->conn->lastInsertId();
                // Assign default permissions
                $permQuery = "INSERT INTO user_permissions (user_id) VALUES (:user_id)";
                $pStmt = $this->conn->prepare($permQuery);
                $pStmt->bindParam(':user_id', $newUserId);
                $pStmt->execute();

                Logger::action($newUserId, 'Auth', 'User registered', ['email' => $email]);

                http_response_code(201);
                echo json_encode(["message" => "User registered successfully."]);
            } else {
                Logger::error('Auth', 'Registration execution failed.', null, implode(" ", $stmt->errorInfo()));
                http_response_code(503);
                echo json_encode(["message" => "Unable to register user."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data."]);
        }
    }

    private function login() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->email) && !empty($data->password)) {
            $query = "SELECT id, username, password_hash FROM users WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $data->email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $id = $row['id'];
                $username = $row['username'];
                $password_hash = $row['password_hash'];

                if (password_verify($data->password, $password_hash)) {
                    $jwtHandler = new JwtHandler();
                    $token = $jwtHandler->jwtEncodeData(
                        "http://localhost",
                        array("id" => $id, "username" => $username, "email" => $data->email)
                    );

                    Logger::action($id, 'Auth', 'User logged in');

                    http_response_code(200);
                    echo json_encode([
                        "message" => "Successful login.",
                        "token" => $token,
                        "user" => [
                            "id" => $id,
                            "username" => $username,
                            "email" => $data->email
                        ]
                    ]);
                } else {
                    Logger::action($id, 'Auth', 'Failed login attempt (Wrong password)');
                    http_response_code(401);
                    echo json_encode(["message" => "Login failed. Incorrect password."]);
                }
            } else {
                Logger::action(null, 'Auth', 'Failed login attempt (Unknown email)', ['email' => $data->email]);
                http_response_code(401);
                echo json_encode(["message" => "Login failed. User not found."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data."]);
        }
    }
}
?>
