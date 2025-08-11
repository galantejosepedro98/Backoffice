<?php
include 'auth.php';
include 'conexao.php';

// Recebe o event_id via GET
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if (!$event_id) {
    die("Evento não especificado.");
}

// Buscar nome do PDV, users (email e password) e equipamentos (tipo e serial number)
$stmt = $pdo->prepare("
    SELECT 
        p.id as pos_id, p.name as pos_name, 
        u.email, sp.clear_password as password,
        e.id as equipment_id, et.name as equipment_type, e.serial_number
    FROM pos p
    JOIN event_pos ep ON ep.pos_id = p.id
    LEFT JOIN users u ON u.pos_id = p.id
    LEFT JOIN staff_passwords sp ON sp.user_id = u.id AND sp.created_at = (
        SELECT MAX(created_at) FROM staff_passwords WHERE user_id = u.id
    )
    LEFT JOIN pos_equipment pe ON pe.pos_id = p.id
    LEFT JOIN equipment e ON e.id = pe.equipment_id
    LEFT JOIN equipment_types et ON e.type_id = et.id
    WHERE ep.event_id = ?
    ORDER BY p.id, u.email, et.name, e.serial_number
");
$stmt->execute([$event_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por PDV
$pdvs = [];
foreach ($results as $row) {
    $pos_id = $row['pos_id'];
    if (!isset($pdvs[$pos_id])) {
        $pdvs[$pos_id] = [
            'pos' => $row['pos_name'],
            'users' => [],
            'equipments' => []
        ];
    }
    // Adiciona user se não existir ainda
    if (!empty($row['email']) && !empty($row['password'])) {
        $user = [ 'email' => $row['email'], 'password' => $row['password'] ];
        if (!in_array($user, $pdvs[$pos_id]['users'])) {
            $pdvs[$pos_id]['users'][] = $user;
        }
    }
    // Adiciona equipamento se não existir ainda
    if (!empty($row['equipment_id'])) {
        $equip = [
            'type' => $row['equipment_type'],
            'serial_number' => $row['serial_number']
        ];
        // Buscar cartão de internet associado a este equipamento
        $stmt_card = $pdo->prepare("
            SELECT ic.activation_number, ic.pin, ic.puk
            FROM equipment_internet_cards eic
            JOIN internet_cards ic ON ic.id = eic.internet_card_id
            WHERE eic.equipment_id = ?
            LIMIT 1
        ");
        $stmt_card->execute([$row['equipment_id']]);
        $card = $stmt_card->fetch(PDO::FETCH_ASSOC);
        if ($card) {
            $equip['internet_card_activation'] = $card['activation_number'];
            $equip['internet_card_pin'] = $card['pin'];
            $equip['internet_card_puk'] = $card['puk'];
        }
        if (!in_array($equip, $pdvs[$pos_id]['equipments'])) {
            $pdvs[$pos_id]['equipments'][] = $equip;
        }
    }
}
$dados = array_values($pdvs);

if (empty($dados)) {
    echo "<h2>Nenhum equipamento para exportar.</h2>";
    exit;
}

// Debug: mostrar o que seria enviado
$debug = isset($_GET['debug']) && $_GET['debug'] == 1;
if ($debug) {
    echo "<h1>Dados a serem enviados:</h1>";
    echo "<pre>" . htmlspecialchars(json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    exit;
}

// Configuração para debug (remover em produção)
$debug = isset($_GET['debug']) && $_GET['debug'] == 1;

// Adiciona o nome do evento aos dados (pra ajudar no Apps Script)
$stmt = $pdo->prepare("SELECT name, start_at FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
$event_name = $event ? $event['name'] : '';
$event_date = $event ? $event['start_at'] : '';

// Envia para o Apps Script
$webapp_url = "https://script.google.com/macros/s/AKfycbxSVpYR5ucIkjOnjQjBE2sbBmw5MG44-ZJRvUHScRgOGYMy-5piKV0arnGZZwElMWqI/exec";
$json_data = json_encode([
    'pontos_venda' => $dados,
    'evento' => $event_name,
    'data_evento' => $event_date,
    'versao' => '2.0' // Indicando que é a nova versão do formato
]);

// Se estiver em debug, mostra os dados que serão enviados
if ($debug) {
    echo "<h1>Dados a serem enviados:</h1>";
    echo "<pre>" . htmlspecialchars(json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    echo "<hr>";
}

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => $json_data,
        'timeout' => 120 // aumentado para 2 minutos
    ]
];
$context = stream_context_create($options);

// Tentar enviar os dados com tratamento de erro melhorado
try {
    $result = @file_get_contents($webapp_url, false, $context);
    
    if ($result === FALSE) {
        $error = error_get_last();
        throw new Exception("Erro na comunicação: " . ($error['message'] ?? "Desconhecido"));
    }
    
    $response = json_decode($result, true);
    
    if ($debug) {
        echo "<!DOCTYPE html>
              <html>
              <head>
                  <meta charset='UTF-8'>
                  <title>Debug - Geração de Instruções PDV</title>
                  <style>
                      body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                      h1 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
                      pre { background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px; overflow: auto; }
                      .success { color: green; }
                      .btn { display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; 
                             text-decoration: none; border-radius: 4px; margin: 10px 0; }
                      .btn:hover { background: #45a049; }
                  </style>
              </head>
              <body>";
              
        echo "<h1>Resposta do Google Apps Script:</h1>";
        echo "<pre>" . htmlspecialchars(print_r($response, true)) . "</pre>";
        echo "<hr>";
        echo "<p class='success'>Documento gerado com sucesso!</p>";
        echo "<a href='{$response['url']}' class='btn' target='_blank'>Abrir documento</a> ";
        echo "<a href='event_management.php?id={$event_id}' class='btn'>Voltar para o Evento</a>";
        echo "</body></html>";
        exit;
    }
    
    if (isset($response['status']) && $response['status'] === 'success' && isset($response['url'])) {
        // Redireciona para o Google Docs gerado
        header("Location: " . $response['url']);
        exit;
    } else {
        throw new Exception($response['message'] ?? 'Resposta inválida do servidor');
    }
} catch (Exception $e) {
    $error_msg = "Erro ao gerar instruções: " . $e->getMessage();
    
    if ($debug) {
        echo "<!DOCTYPE html>
              <html>
              <head>
                  <meta charset='UTF-8'>
                  <title>Erro - Geração de Instruções PDV</title>
                  <style>
                      body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                      h1 { color: #d32f2f; }
                      pre { background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px; overflow: auto; }
                      .error { color: #d32f2f; font-weight: bold; }
                      .btn { display: inline-block; background: #4CAF50; color: white; padding: 10px 15px;
                             text-decoration: none; border-radius: 4px; margin: 10px 0; }
                  </style>
              </head>
              <body>";
        echo "<h1>Erro:</h1>";
        echo "<p class='error'>{$error_msg}</p>";
        echo "<h2>Detalhes técnicos:</h2>";
        echo "<pre>" . htmlspecialchars(print_r(error_get_last(), true)) . "</pre>";
        
        // Mostrar a estrutura de dados que estava tentando enviar
        echo "<h2>Dados que estavam sendo enviados:</h2>";
        echo "<pre>" . htmlspecialchars(json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        echo "<a href='event_management.php?id={$event_id}' class='btn'>Voltar para o Evento</a>";
        echo "</body></html>";
    } else {
        echo "<!DOCTYPE html>
              <html>
              <head>
                  <meta charset='UTF-8'>
                  <title>Erro - Geração de Instruções PDV</title>
                  <style>
                      body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                      .error { color: #d32f2f; font-weight: bold; margin: 20px 0; }
                      .btn { display: inline-block; background: #4CAF50; color: white; padding: 10px 15px;
                             text-decoration: none; border-radius: 4px; margin: 10px 0; }
                  </style>
              </head>
              <body>
                  <div class='error'>{$error_msg}</div>
                  <a href='event_management.php?id={$event_id}' class='btn'>Voltar para o Evento</a>
              </body>
              </html>";
    }
}
?>