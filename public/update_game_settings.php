<?php
// public/update_game_settings.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /index.php?page=gerenciarjogos");
    exit();
}

global $pdo;

$game_name = $_POST['game_name'] ?? null;
$win_chance = filter_input(INPUT_POST, 'win_chance', FILTER_VALIDATE_FLOAT);

if (empty($game_name)) {
    session_start();
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Nome do jogo inválido.'];
    header("Location: /index.php?page=gerenciarjogos");
    exit();
}

// Verifica se o valor da chance de ganho é válido e dentro do limite
if ($win_chance === false || $win_chance < 0 || $win_chance > 100) {
    session_start();
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Valor da chance de ganho é inválido (deve ser entre 0 e 100).'];
    header("Location: /index.php?page=gerenciarjogos");
    exit();
}

try {
    // Prepara a consulta SQL para atualizar a chance de ganho do jogo
    $stmt = $pdo->prepare("UPDATE games SET win_chance_percent = ? WHERE name = ?");
    $stmt->execute([$win_chance, $game_name]);

    $_SESSION['form_feedback'] = ['type' => 'success', 'message' => "Chance de ganho de '$game_name' atualizada para {$win_chance}%."];
    header("Location: /index.php?page=gerenciarjogos");
    exit();
} catch (PDOException $e) {
    error_log("Erro ao atualizar config do jogo: " . $e->getMessage());
    session_start();
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Erro no banco de dados ao atualizar a chance de ganho.'];
    header("Location: /index.php?page=gerenciarjogos");
    exit();
}