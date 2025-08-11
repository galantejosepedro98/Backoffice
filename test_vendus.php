<?php
// Ativar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Teste de ligação à API Vendus
$apiKey = '074811a8471b34de0e6eca204230dada';
$url = 'https://www.vendus.pt/ws/v1.1/account';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ":");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json"
]);

$response = curl_exec($ch);
if ($response === false) {
    echo 'Erro cURL: ' . curl_error($ch) . '<br>';
}
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "Ligação bem sucedida!<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "Erro na ligação! HTTP $httpCode<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
