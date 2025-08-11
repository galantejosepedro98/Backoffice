<?php
include 'auth.php';
include 'conexao.php';

header('Content-Type: application/json');

$pos_id = isset($_GET['pos_id']) ? (int)$_GET['pos_id'] : 0;
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if (!$pos_id || !$event_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetros inválidos'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        e.id as equipment_id,
        e.serial_number,
        pe.status as equipment_status,
        pe.notes as equipment_notes,
        et.name as equipment_type,
        ic.activation_number as internet_card,
        ic.phone_number as internet_card_phone,
        ic.status as internet_card_status
    FROM pos_equipment pe
    JOIN equipment e ON e.id = pe.equipment_id
    LEFT JOIN equipment_types et ON e.type_id = et.id
    LEFT JOIN equipment_internet_cards eic ON eic.equipment_id = e.id
    LEFT JOIN internet_cards ic ON ic.id = eic.internet_card_id
    WHERE pe.pos_id = ?
    ORDER BY et.name
");

try {
    $stmt->execute([$pos_id]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    if (!empty($equipment)) {
    $html .= '<table>';
    $html .= '<thead>';
    $html .= '<tr>';
    $html .= '<th>Tipo</th>';
    $html .= '<th>Número de Série</th>';
    $html .= '<th>Status</th>';
    $html .= '<th>Cartões de Internet</th>';
    $html .= '<th>Ações</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    
    foreach ($equipment as $item) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($item['equipment_type']) . '</td>';
        $html .= '<td>' . htmlspecialchars($item['serial_number']) . '</td>';
        $html .= '<td>';
        $html .= '<span class="status-badge status-' . $item['equipment_status'] . '">';
        switch ($item['equipment_status']) {
            case 'active':
                $html .= 'Em Uso';
                break;
            case 'inactive':
                $html .= 'Inativo';
                break;
            default:
                $html .= ucfirst($item['equipment_status']);
        }
        $html .= '</span>';
        $html .= '</td>';
        $html .= '<td>';
        if ($item['internet_card']) {
            $html .= htmlspecialchars($item['internet_card']);
            if ($item['internet_card_phone']) {
                $html .= ' [' . htmlspecialchars($item['internet_card_phone']) . ']';
            }
            if ($item['internet_card_status']) {
                $html .= '<span class="status-badge status-' . $item['internet_card_status'] . '">';
                $html .= $item['internet_card_status'] === 'active' ? 'Ativo' : 'Inativo';
                $html .= '</span>';
            }
            $html .= ' <a href="equipment_internet_form.php?id=' . $item['equipment_id'] . '&event_id=' . $event_id . '" class="btn btn-edit">Editar</a>';
        } else {
            $html .= '<a href="equipment_internet_form.php?id=' . $item['equipment_id'] . '&event_id=' . $event_id . '" class="btn btn-edit">Adicionar</a>';
        }
        $html .= '</td>';
        $html .= '<td class="action-buttons">';
        $html .= '<a href="equipment_form.php?id=' . $item['equipment_id'] . '&event_id=' . $event_id . '" class="btn btn-edit">Editar</a>';
        $html .= '<button class="btn btn-remove" onclick="showModal(\'remove\', ' . $item['equipment_id'] . ')">Remover</button>';
        $html .= '<button class="btn btn-delete" onclick="showModal(\'delete\', ' . $item['equipment_id'] . ')">Excluir</button>';
        $html .= '</td>';
        $html .= '</tr>';
    }
      $html .= '</tbody>';
    $html .= '</table>';
} else {
    $html .= '<p>Nenhum equipamento associado a este PDV.</p>';
}

echo json_encode([
    'success' => true,
    'html' => $html
]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar equipamentos: ' . $e->getMessage()
    ]);
}
