<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController {
    private $conn;
    private $user;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->user = AuthMiddleware::authenticate();
    }

    public function processRequest($method, $segments) {
        $action = $segments[0] ?? null;

        if ($method === 'GET' && $action === 'template') {
            $this->downloadTemplate();
        } elseif ($method === 'POST' && $action === 'upload') {
            $this->uploadExcel();
        } elseif ($method === 'POST' && $action === 'save') {
            $this->saveImportedData();
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Import endpoint not found"]);
        }
    }

    private function downloadTemplate() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheet->setCellValue('A1', 'Type (income/expense)');
        $sheet->setCellValue('B1', 'Amount');
        $sheet->setCellValue('C1', 'Date (YYYY-MM-DD)');
        $sheet->setCellValue('D1', 'Category');
        $sheet->setCellValue('E1', 'Note (Loss, Mandatory, Daily expense, etc)');

        // Example row
        $sheet->setCellValue('A2', 'expense');
        $sheet->setCellValue('B2', '50.00');
        $sheet->setCellValue('C2', date('Y-m-d'));
        $sheet->setCellValue('D2', 'Food');
        $sheet->setCellValue('E2', 'Daily expense');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="IncomeExpenseTemplate.xlsx"');
        header('Cache-Control: max-age=0');
        
        ob_clean(); // Ensure no output before excel data
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function uploadExcel() {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["message" => "File upload failed or no file provided."]);
            return;
        }

        $tmpPath = $_FILES['file']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();
            
            $previewData = [];
            for ($row = 2; $row <= $highestRow; $row++) { // Skip header
                $type = $sheet->getCell('A' . $row)->getValue();
                $amount = $sheet->getCell('B' . $row)->getValue();
                
                // Handle possible date format issues from excel
                $dateCell = $sheet->getCell('C' . $row);
                if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($dateCell)) {
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateCell->getValue())->format('Y-m-d');
                } else {
                    $date = $dateCell->getValue();
                }
                
                $category = $sheet->getCell('D' . $row)->getValue();
                $note = $sheet->getCell('E' . $row)->getValue();

                if ($type && $amount && $date && $category) {
                    $previewData[] = [
                        'type' => strtolower($type),
                        'amount' => $amount,
                        'date' => $date,
                        'category' => $category,
                        'note' => $note
                    ];
                }
            }

            Logger::action($this->user->id, 'Import', 'Uploaded and parsed Excel file', ['preview_count' => count($previewData)]);
            http_response_code(200);
            echo json_encode([
                "message" => "File parsed successfully.",
                "data" => $previewData
            ]);

        } catch (Exception $e) {
            Logger::error('Import', 'Error parsing excel file', $this->user->id, $e->getMessage());
            http_response_code(500);
            echo json_encode(["message" => "Error parsing file: " . $e->getMessage()]);
        }
    }

    private function saveImportedData() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['transactions']) || !is_array($data['transactions'])) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid data format."]);
            return;
        }

        $successCount = 0;
        $query = "INSERT INTO transactions SET user_id = :user_id, type = :type, amount = :amount, date = :date, category = :category, note = :note";
        $stmt = $this->conn->prepare($query);

        foreach ($data['transactions'] as $tx) {
            $type = strtolower($tx['type']) === 'expense' ? 'expense' : 'income';
            $stmt->bindParam(':user_id', $this->user->id);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':amount', $tx['amount']);
            $stmt->bindParam(':date', $tx['date']);
            $stmt->bindParam(':category', $tx['category']);
            $n = $tx['note'] ?? '';
            $stmt->bindParam(':note', $n);
            if ($stmt->execute()) {
                $successCount++;
            }
        }

        Logger::action($this->user->id, 'Import', 'Saved imported transactions', ['success_count' => $successCount]);

        http_response_code(201);
        echo json_encode(["message" => "$successCount transactions saved successfully."]);
    }
}
?>
