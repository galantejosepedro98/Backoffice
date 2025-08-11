<?php
include 'auth.php';
include 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: eventos.php");
    exit;
}

$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$pos_category_id = isset($_POST['pos_category']) ? (int)$_POST['pos_category'] : 0;
$equipment_type_id = isset($_POST['equipment_type']) ? (int)$_POST['equipment_type'] : 0;

if (!$event_id || !$pos_category_id || !$equipment_type_id) {
    header("Location: event_management.php?id=" . $event_id);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Buscar todos os PDVs do evento que pertencem à categoria selecionada
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id
        FROM pos p
        JOIN event_pos ep ON ep.pos_id = p.id
        JOIN pos_categories_assignments pca ON pca.pos_id = p.id
        LEFT JOIN pos_equipment pe ON pe.pos_id = p.id AND pe.status = 'active'
        WHERE ep.event_id = ?
        AND pca.category_id = ?
        AND pe.id IS NULL
        ORDER BY p.name
    ");
    $stmt->execute([$event_id, $pos_category_id]);
    $pos_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($pos_ids)) {
        $_SESSION['message'] = "Não foram encontrados PDVs disponíveis nesta categoria.";
        header("Location: event_management.php?id=" . $event_id);
        exit;
    }

    // 2. Buscar equipamentos disponíveis do tipo selecionado
    $stmt = $pdo->prepare("
        SELECT e.id
        FROM equipment e
        LEFT JOIN pos_equipment pe ON pe.equipment_id = e.id AND pe.status = 'active'
        WHERE e.type_id = ?
        AND pe.id IS NULL
        ORDER BY e.serial_number
    ");
    $stmt->execute([$equipment_type_id]);
    $equipment_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($equipment_ids)) {
        $_SESSION['message'] = "Não há equipamentos disponíveis deste tipo.";
        header("Location: event_management.php?id=" . $event_id);
        exit;
    }

    // 3. Associar um equipamento a cada PDV
    $stmt = $pdo->prepare("
        INSERT INTO pos_equipment (pos_id, equipment_id, status, created_at)
        VALUES (?, ?, 'active', NOW())
    ");

    $assigned = 0;
    foreach ($pos_ids as $index => $pos_id) {
        if (isset($equipment_ids[$index])) {
            $stmt->execute([$pos_id, $equipment_ids[$index]]);
            $assigned++;
        } else {
            break; // Não há mais equipamentos disponíveis
        }
    }

    $pdo->commit();

    // Mensagem de sucesso
    if ($assigned == count($pos_ids)) {
        $_SESSION['message'] = "Todos os {$assigned} PDVs receberam equipamentos com sucesso!";
    } else {
        $_SESSION['message'] = "{$assigned} PDVs receberam equipamentos. Não havia equipamentos suficientes para todos os PDVs.";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Erro ao associar equipamentos: " . $e->getMessage();
}

header("Location: event_management.php?id=" . $event_id);
exit;
