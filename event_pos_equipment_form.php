<?php
include 'auth.php';
include 'conexao.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
$pos_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : null;
$error = null;

// Buscar informações do evento e PDV
$stmt = $pdo->prepare("
    SELECT e.*, p.name as pos_name 
    FROM events e 
    JOIN pos p ON p.id = ?
    WHERE e.id = ?
");
$stmt->execute([$pos_id, $event_id]);
$info = $stmt->fetch();

if (!$info) {
    header("Location: event_pos_form.php?id=" . $event_id);
    exit;
}

// Buscar equipamentos já associados a este PDV neste evento
$stmt = $pdo->prepare("
    SELECT epe.*, e.name as equipment_name, e.serial_number,
           et.name as type_name, ic.activation_number
    FROM event_pos_equipment epe
    JOIN equipment e ON e.id = epe.equipment_id
    JOIN equipment_types et ON et.id = e.type_id
    LEFT JOIN internet_cards ic ON ic.id = epe.internet_card_id
    WHERE epe.event_id = ? AND epe.pos_id = ?
    ORDER BY et.name, e.name
");
$stmt->execute([$event_id, $pos_id]);
$current_assignments = $stmt->fetchAll();

// Buscar todos os equipamentos disponíveis
$stmt = $pdo->query("
    SELECT e.*, et.name as type_name
    FROM equipment e
    JOIN equipment_types et ON et.id = e.type_id
    WHERE e.status = 'available' 
    OR e.id IN (
        SELECT equipment_id 
        FROM event_pos_equipment 
        WHERE event_id = {$event_id} 
        AND pos_id = {$pos_id}
    )
    ORDER BY et.name, e.name
");
$equipment = $stmt->fetchAll();

// Buscar cartões de internet disponíveis
$stmt = $pdo->query("
    SELECT * FROM internet_cards 
    WHERE status = 'available'
    OR id IN (
        SELECT internet_card_id 
        FROM event_pos_equipment 
        WHERE event_id = {$event_id}
        AND pos_id = {$pos_id}
        AND internet_card_id IS NOT NULL
    )
    ORDER BY activation_number
");
$internet_cards = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Remover associações existentes
        $stmt = $pdo->prepare("
            DELETE FROM event_pos_equipment 
            WHERE event_id = ? AND pos_id = ?
        ");
        $stmt->execute([$event_id, $pos_id]);

        // Adicionar novas associações
        if (isset($_POST['equipment']) && is_array($_POST['equipment'])) {
            $stmt = $pdo->prepare("
                INSERT INTO event_pos_equipment 
                (event_id, pos_id, equipment_id, internet_card_id, assigned_at, status, notes)
                VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");

            foreach ($_POST['equipment'] as $equipment_id) {
                $internet_card_id = isset($_POST['internet_card'][$equipment_id]) ? 
                                  $_POST['internet_card'][$equipment_id] : null;
                
                $status = $_POST['status'][$equipment_id] ?? 'assigned';
                $notes = $_POST['notes'][$equipment_id] ?? null;

                $stmt->execute([
                    $event_id,
                    $pos_id,
                    $equipment_id,
                    $internet_card_id,
                    $status,
                    $notes
                ]);
            }
        }

        $pdo->commit();
        header("Location: event_pos_form.php?id=" . $event_id);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Equipamentos do PDV no Evento</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px; 
            margin: 0;
        }
        .info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .equipment-list {
            margin-top: 20px;
        }
        .equipment-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .equipment-item:hover {
            background-color: #f9f9f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 60px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #666;
            color: white;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .equipment-type {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #eee;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <h1>Gerenciar Equipamentos do PDV no Evento</h1>

    <div class="info">
        <h3><?php echo htmlspecialchars($info['name']); ?></h3>
        <p>PDV: <?php echo htmlspecialchars($info['pos_name']); ?></p>        <?php if ($info['location']): ?>
            <p>Local: <?php echo htmlspecialchars($info['location']); ?></p>
        <?php endif; ?>
        <p>Data do Evento: <?php echo date('d/m/Y', strtotime($info['start_at'])); ?></p>
    </div>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php 
        $current_type = null;
        foreach ($equipment as $eq): 
            if ($eq['type_name'] !== $current_type) {
                $current_type = $eq['type_name'];
                echo '<div class="equipment-type">' . htmlspecialchars($current_type) . '</div>';
            }
        ?>
            <?php
            $is_assigned = false;
            $current_assignment = null;
            foreach ($current_assignments as $assignment) {
                if ($assignment['equipment_id'] == $eq['id']) {
                    $is_assigned = true;
                    $current_assignment = $assignment;
                    break;
                }
            }
            ?>
            <div class="equipment-item">
                <label>
                    <input type="checkbox" name="equipment[]" value="<?php echo $eq['id']; ?>"
                           <?php echo $is_assigned ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($eq['name']); ?>
                    <?php if ($eq['serial_number']): ?>
                        (S/N: <?php echo htmlspecialchars($eq['serial_number']); ?>)
                    <?php endif; ?>
                </label>

                <div class="form-group" style="margin-top: 10px;">
                    <label>Status:</label>
                    <select name="status[<?php echo $eq['id']; ?>]">
                        <option value="assigned" <?php echo (!$current_assignment || $current_assignment['status'] === 'assigned') ? 'selected' : ''; ?>>
                            Atribuído
                        </option>
                        <option value="returned" <?php echo ($current_assignment && $current_assignment['status'] === 'returned') ? 'selected' : ''; ?>>
                            Devolvido
                        </option>
                        <option value="lost" <?php echo ($current_assignment && $current_assignment['status'] === 'lost') ? 'selected' : ''; ?>>
                            Perdido
                        </option>
                    </select>
                </div>

                <?php if ($internet_cards): ?>
                    <div class="form-group">
                        <label>Cartão de Internet:</label>
                        <select name="internet_card[<?php echo $eq['id']; ?>]">
                            <option value="">Selecione um cartão...</option>
                            <?php foreach ($internet_cards as $card): ?>
                                <option value="<?php echo $card['id']; ?>" 
                                    <?php echo ($current_assignment && $current_assignment['internet_card_id'] == $card['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($card['activation_number']); ?>
                                    <?php if ($card['phone_number']): ?>
                                        (Tel: <?php echo htmlspecialchars($card['phone_number']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Observações:</label>
                    <textarea name="notes[<?php echo $eq['id']; ?>]" 
                            placeholder="Ex: Local de instalação, pessoa responsável, observações sobre o uso..."><?php 
                        echo $current_assignment ? htmlspecialchars($current_assignment['notes']) : ''; 
                    ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="event_pos_form.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">Voltar</a>
        </div>
    </form>
</body>
</html>
