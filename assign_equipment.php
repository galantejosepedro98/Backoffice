<?php
include 'auth.php';
include 'conexao.php';

header('Content-Type: application/json');

// Get POST parameters
$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
$pos_id = isset($_POST['pos_id']) ? (int)$_POST['pos_id'] : null;
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;

if (!$equipment_id || !$pos_id || !$event_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetros inválidos'
    ]);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if equipment is available (not already assigned)
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM pos_equipment 
        WHERE equipment_id = ? 
        AND status = 'active'
    ");
    $stmt->execute([$equipment_id]);
    if ($stmt->fetch()) {
        throw new Exception('Este equipamento já está em uso em outro PDV');
    }

    // Check if equipment exists
    $stmt = $pdo->prepare("SELECT 1 FROM equipment WHERE id = ?");
    $stmt->execute([$equipment_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Equipamento não encontrado');
    }

    // Check if PDV exists and is in the event
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM event_pos 
        WHERE pos_id = ? AND event_id = ?
    ");
    $stmt->execute([$pos_id, $event_id]);
    if (!$stmt->fetch()) {
        throw new Exception('PDV não encontrado ou não está associado ao evento');
    }

    // Insert new equipment assignment
    $stmt = $pdo->prepare("
        INSERT INTO pos_equipment 
        (pos_id, equipment_id, status, assigned_at, created_at)
        VALUES (?, ?, 'active', NOW(), NOW())
    ");
    $stmt->execute([$pos_id, $equipment_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Equipamento associado com sucesso'
    ]);

} catch (Exception $e) {
    // Rollback transaction if there was an error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
