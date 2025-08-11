<?php
require 'vendor/autoload.php';
require_once 'auth.php';
require_once 'conexao.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : die('ID do evento não fornecido');

// Buscar informações do evento
$stmt = $pdo->prepare("SELECT name FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    die('Evento não encontrado');
}

// Buscar todos os cartões de internet usados no evento
$stmt = $pdo->prepare("
    SELECT 
        p.name as pdv_name,
        e.serial_number as equipment_serial,
        et.name as equipment_type,
        ic.activation_number,
        ic.phone_number,
        ic.status as card_status,
        pe.status as equipment_status
    FROM pos p 
    JOIN event_pos ep ON ep.pos_id = p.id
    JOIN pos_equipment pe ON pe.pos_id = p.id
    JOIN equipment e ON e.id = pe.equipment_id
    JOIN equipment_types et ON e.type_id = et.id
    JOIN equipment_internet_cards eic ON eic.equipment_id = e.id
    JOIN internet_cards ic ON ic.id = eic.internet_card_id
    WHERE ep.event_id = ?
    ORDER BY p.name, et.name
");
$stmt->execute([$event_id]);
$cards = $stmt->fetchAll();

// Criar novo spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Definir título
$sheet->setCellValue('A1', 'Cartões de Internet - ' . $event['name']);
$sheet->mergeCells('A1:F1');

// Cabeçalhos
$headers = [
    'A2' => 'PDV',
    'B2' => 'Equipamento',
    'C2' => 'Tipo',
    'D2' => 'Número de Ativação',
    'E2' => 'Número de Telefone',
    'F2' => 'Status'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Dados
$row = 3;
foreach ($cards as $card) {
    $sheet->setCellValue('A' . $row, $card['pdv_name']);
    $sheet->setCellValue('B' . $row, $card['equipment_serial']);
    $sheet->setCellValue('C' . $row, $card['equipment_type']);
    
    // Formatar número de ativação como texto
    $sheet->setCellValueExplicit('D' . $row, $card['activation_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('E' . $row, $card['phone_number']);
    $sheet->setCellValue('F' . $row, $card['card_status']);
    $row++;
}

// Estilo
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

$lastRow = $row - 1;
$sheet->getStyle('A1:F' . $lastRow)->applyFromArray($styleArray);

// Ajustar largura das colunas
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Estilo do título
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Estilo dos cabeçalhos
$sheet->getStyle('A2:F2')->getFont()->setBold(true);
$sheet->getStyle('A2:F2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');

// Headers do HTTP para download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="cartoes_internet_' . $event['name'] . '.xlsx"');
header('Cache-Control: max-age=0');

// Gerar arquivo
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
