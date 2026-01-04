<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado.']);
    exit;
}

try {
    global $pdo;

    // Busca os dados dos jogos
    $games_stmt = $pdo->prepare("SELECT id, name, bet_cost FROM games");
    $games_stmt->execute();
    $games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Busca os dados do sistema de bônus
    $bonus_system_stmt = $pdo->prepare("SELECT * FROM bonus_system");
    $bonus_system_stmt->execute();
    $bonus_systems = $bonus_system_stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ CORREÇÃO: Itera sobre os bônus para corrigir o status visual
    foreach ($bonus_systems as &$bonus) {
        if ($bonus['is_bonus_active'] && (float)$bonus['current_bonus_paid'] >= (float)$bonus['bonus_amount']) {
            $bonus['is_bonus_active'] = false; // Define como inativo para a exibição no painel
        }
    }
    unset($bonus); // Desvincula a referência para evitar problemas

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'games' => $games,
            'bonus_systems' => $bonus_systems
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Erro no banco de dados em get-game-and-bonus-data.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno no servidor.']);
}