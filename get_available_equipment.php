<?php
include 'auth.php';
include 'conexao.php';

// Buscar equipamentos que não estão em uso (não têm associação ativa com PDV)
$stmt = $pdo->query("
    SELECT DISTINCT
        e.id,
        e.serial_number,
        et.name as type_name
    FROM equipment e
    LEFT JOIN equipment_types et ON e.type_id = et.id
    LEFT JOIN pos_equipment pe ON e.id = pe.equipment_id
    WHERE pe.id IS NULL 
    OR NOT EXISTS (
        SELECT 1 
        FROM pos_equipment pe2 
        WHERE pe2.equipment_id = e.id 
        AND pe2.status = 'active'
    )
    ORDER BY e.serial_number
");

$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($equipment);
