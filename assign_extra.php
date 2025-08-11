<?php
include 'auth.php';
include 'conexao.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get the POST data
$raw_data = file_get_contents('php://input');
error_log('Received raw data: ' . $raw_data);

$data = json_decode($raw_data, true);
error_log('Decoded data: ' . print_r($data, true));

if (!isset($data['extra_id']) || !isset($data['pos_id']) || !isset($data['event_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Dados inválidos',
        'received' => $data,
        'error_info' => 'Missing required fields: ' . 
            (!isset($data['extra_id']) ? 'extra_id ' : '') . 
            (!isset($data['pos_id']) ? 'pos_id ' : '') . 
            (!isset($data['event_id']) ? 'event_id' : '')
    ]);
    exit;
}

$extra_id = (int)$data['extra_id'];
$pos_id = (int)$data['pos_id'];
$event_id = (int)$data['event_id'];

try {
    // Check if the extra belongs to the event
    $stmtCheck = $pdo->prepare("SELECT 1 FROM extras WHERE id = ? AND event_id = ?");
    $stmtCheck->execute([$extra_id, $event_id]);
    if (!$stmtCheck->fetch()) {
        throw new Exception('Extra não pertence a este evento');
    }

    // Check if the association already exists
    $stmtExists = $pdo->prepare("SELECT 1 FROM extra_pos WHERE extra_id = ? AND pos_id = ?");
    $stmtExists->execute([$extra_id, $pos_id]);
    if ($stmtExists->fetch()) {
        echo json_encode([
            'success' => true,
            'message' => 'Extra já está associado a este PDV'
        ]);
        exit;
    }    // Create the association with timestamp
    $stmt = $pdo->prepare("INSERT INTO extra_pos (extra_id, pos_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$extra_id, $pos_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Extra associado com sucesso'
    ]);

} catch (Exception $e) {
    error_log('Error in assign_extra.php: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
