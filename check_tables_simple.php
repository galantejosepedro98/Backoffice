<?php
include 'auth.php';
include 'conexao.php';

echo "Starting checks...\n";

try {
    echo "Checking equipment_internet_cards table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'equipment_internet_cards'");
    $result = $stmt->fetchAll();
    if (count($result) > 0) {
        echo "✅ Table equipment_internet_cards exists\n";
    } else {
        echo "❌ Table equipment_internet_cards does NOT exist\n";
    }

    echo "Checking internet_cards table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'internet_cards'");
    $result = $stmt->fetchAll();
    if (count($result) > 0) {
        echo "✅ Table internet_cards exists\n";
    } else {
        echo "❌ Table internet_cards does NOT exist\n";
    }
    
    echo "Checking if event_pos_equipment has internet_card_id column...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM event_pos_equipment LIKE 'internet_card_id'");
    $result = $stmt->fetchAll();
    if (count($result) > 0) {
        echo "✅ Column internet_card_id exists in event_pos_equipment\n";
    } else {
        echo "❌ Column internet_card_id does NOT exist in event_pos_equipment\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
