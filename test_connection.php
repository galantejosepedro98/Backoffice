<?php
$host = '185.32.188.4';
$db   = 'eventses_events';
$user = 'eventses';
$pass = ';g2)EhYi3mD53M';
$charset = 'utf8mb4';

echo "=== TESTE DE CONEXÃO MySQL ===\n";
echo "Host: $host\n";
echo "Database: $db\n";
echo "User: $user\n";
echo "Password: " . (strlen($pass) > 0 ? str_repeat('*', strlen($pass)) : 'VAZIO') . "\n";
echo "Charset: $charset\n\n";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    echo "Tentando conectar...\n";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ CONEXÃO ESTABELECIDA COM SUCESSO!\n";
    
    // Teste simples
    $stmt = $pdo->query("SELECT VERSION() as version, NOW() as current_time");
    $result = $stmt->fetch();
    
    echo "Versão MySQL: " . $result['version'] . "\n";
    echo "Hora servidor: " . $result['current_time'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO NA CONEXÃO:\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    
    // Informações adicionais de debug
    echo "\nInformações de debug:\n";
    echo "DSN usado: $dsn\n";
    echo "Driver PDO MySQL disponível: " . (extension_loaded('pdo_mysql') ? 'SIM' : 'NÃO') . "\n";
}
?>