<?php
require 'vendor/autoload.php';
require_once 'auth.php';
require_once 'conexao.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Preservar os filtros da página de origem
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Consulta base para buscar equipamentos com informações de uso
$sql = "
    SELECT 
        e.id,
        e.serial_number,
        et.name as type_name,
        CASE 
            WHEN (SELECT COUNT(*) FROM pos_equipment pe WHERE pe.equipment_id = e.id AND pe.status = 'active') > 0 
            THEN 'Em uso' 
            ELSE 'Disponível' 
        END as status,
        (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') 
         FROM pos p 
         JOIN pos_equipment pe ON p.id = pe.pos_id 
         WHERE pe.equipment_id = e.id AND pe.status = 'active') as pos_name
    FROM equipment e
    LEFT JOIN equipment_types et ON e.type_id = et.id
    WHERE 1=1
";

$params = [];

// Adicionar condições baseadas nos filtros
if (!empty($search_term)) {
    $sql .= " AND (e.serial_number LIKE ? OR e.notes LIKE ?)";
    $params[] = "%{$search_term}%";
    $params[] = "%{$search_term}%";
}

if ($type_filter > 0) {
    $sql .= " AND e.type_id = ?";
    $params[] = $type_filter;
}

if ($status_filter !== '') {
    if ($status_filter === 'inuse') {
        $sql .= " AND (SELECT COUNT(*) FROM pos_equipment pe WHERE pe.equipment_id = e.id AND pe.status = 'active') > 0";
    } else if ($status_filter === 'available') {
        $sql .= " AND (SELECT COUNT(*) FROM pos_equipment pe WHERE pe.equipment_id = e.id AND pe.status = 'active') = 0";
    }
}

$sql .= " ORDER BY e.serial_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipments = $stmt->fetchAll();

// Criar novo spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Definir título
$sheet->setCellValue('A1', 'Listagem de Equipamentos');
$sheet->mergeCells('A1:D1');

// Cabeçalhos
$headers = [
    'A2' => 'Tipo',
    'B2' => 'Número de Série',
    'C2' => 'Status',
    'D2' => 'Ponto de Venda'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Dados
$row = 3;
foreach ($equipments as $equipment) {
    $sheet->setCellValue('A' . $row, $equipment['type_name']);
    $sheet->setCellValue('B' . $row, $equipment['serial_number']);
    $sheet->setCellValue('C' . $row, $equipment['status']);
    $sheet->setCellValue('D' . $row, $equipment['pos_name'] ?: 'Não associado');
    $row++;
}

// Estilo
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
];

// Aplicar estilos à área de dados
$lastRow = count($equipments) + 2;
$sheet->getStyle('A2:D' . $lastRow)->applyFromArray($styleArray);

// Estilo para o título
$sheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Estilo para os cabeçalhos
$sheet->getStyle('A2:D2')->getFont()->setBold(true);
$sheet->getStyle('A2:D2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2:D2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('DDDDDD');

// Ajustar largura das colunas
foreach (range('A', 'D') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Salvar arquivo
$filename = 'equipamentos_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Salvar o arquivo e envia-lo para download
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
