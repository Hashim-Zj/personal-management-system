<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Logger.php';

class TransactionController {
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
            $this->getTransaction($id);
        } elseif ($method === 'GET') {
            $this->getTransactions();
        } elseif ($method === 'POST') {
            $this->createTransaction();
        } elseif ($method === 'PUT' && $id) {
            $this->updateTransaction($id);
        } elseif ($method === 'DELETE' && $id) {
            $this->deleteTransaction($id);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Endpoint not found"]);
        }
    }

    private function getTransactions() {
        // Basic get all for user
        $query = "SELECT * FROM transactions WHERE user_id = :user_id ORDER BY date DESC, id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user->id);
        $stmt->execute();
        
        $txs = $stmt->fetchAll();
        http_response_code(200);
        echo json_encode($txs);
    }

    private function getTransaction($id) {
        $query = "SELECT * FROM transactions WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $this->user->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $tx = $stmt->fetch();
            http_response_code(200);
            echo json_encode($tx);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Transaction not found."]);
        }
    }

    private function createTransaction() {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->type) && !empty($data->amount) && !empty($data->date) && !empty($data->category)) {
            $query = "INSERT INTO transactions 
                SET user_id = :user_id, type = :type, amount = :amount, date = :date, category = :category, note = :note";
            $stmt = $this->conn->prepare($query);

            $note = $data->note ?? '';
            $type = strtolower($data->type) === 'expense' ? 'expense' : 'income';

            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':amount', $data->amount);
            $stmt->bindParam(':date', $data->date);
            $stmt->bindParam(':category', $data->category);
            $stmt->bindParam(':note', $note);

            if ($stmt->execute()) {
                Logger::action($this->user->id, 'Transactions', 'Transaction created', ['amount' => $data->amount, 'type' => $type]);
                http_response_code(201);
                echo json_encode(["message" => "Transaction created successfully."]);
            } else {
                Logger::error('Transactions', 'Unable to create transaction', $this->user->id, implode(" ", $stmt->errorInfo()));
                http_response_code(503);
                echo json_encode(["message" => "Unable to create transaction."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data. Type, amount, date, and category are required."]);
        }
    }

    private function updateTransaction($id) {
        $data = json_decode(file_get_contents("php://input"));
        
        if (!empty($data->type) && !empty($data->amount) && !empty($data->date) && !empty($data->category)) {
            $query = "UPDATE transactions 
                SET type = :type, amount = :amount, date = :date, category = :category, note = :note
                WHERE id = :id AND user_id = :user_id";
            
            $stmt = $this->conn->prepare($query);

            $note = $data->note ?? '';
            $type = strtolower($data->type) === 'expense' ? 'expense' : 'income';

            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':amount', $data->amount);
            $stmt->bindParam(':date', $data->date);
            $stmt->bindParam(':category', $data->category);
            $stmt->bindParam(':note', $note);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $this->user->id);

            if ($stmt->execute()) {
                Logger::action($this->user->id, 'Transactions', 'Transaction updated', ['tx_id' => $id]);
                http_response_code(200);
                echo json_encode(["message" => "Transaction updated successfully."]);
            } else {
                Logger::error('Transactions', 'Unable to update transaction', $this->user->id, implode(" ", $stmt->errorInfo()));
                http_response_code(503);
                echo json_encode(["message" => "Unable to update transaction."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data."]);
        }
    }

    private function deleteTransaction($id) {
        $query = "DELETE FROM transactions WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $this->user->id);

        if ($stmt->execute()) {
            Logger::action($this->user->id, 'Transactions', 'Transaction deleted', ['tx_id' => $id]);
            http_response_code(200);
            echo json_encode(["message" => "Transaction deleted."]);
        } else {
            Logger::error('Transactions', 'Unable to delete transaction', $this->user->id, implode(" ", $stmt->errorInfo()));
            http_response_code(503);
            echo json_encode(["message" => "Unable to delete transaction."]);
        }
    }
}
?>
