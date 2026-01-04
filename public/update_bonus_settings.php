<?php
// public/update_bonus_settings.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /index.php?page=gerenciarjogos");
    exit();
}

global $pdo;

$game_name = $_POST['game_name'] ?? null;
$faturamento_meta = filter_input(INPUT_POST, 'faturamento_meta', FILTER_VALIDATE_FLOAT);
$bonus_amount = filter_input(INPUT_POST, 'bonus_amount', FILTER_VALIDATE_FLOAT);

if (empty($game_name)) {
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Nome do jogo inválido.'];
    header("Location: /index.php?page=gerenciarjogos");
    exit();
}

try {
    $update_fields = [];
    $update_values = [];

    // Verifica se o campo faturamento_meta foi enviado e é válido
    if (isset($_POST['faturamento_meta']) && $faturamento_meta !== false && $faturamento_meta >= 0) {
        $update_fields[] = 'faturamento_meta = ?';
        $update_values[] = $faturamento_meta;
    }

    // Verifica se o campo bonus_amount foi enviado e é válido
    if (isset($_POST['bonus_amount']) && $bonus_amount !== false && $bonus_amount >= 0) {
        $update_fields[] = 'bonus_amount = ?';
        $update_values[] = $bonus_amount;
    }

    if (empty($update_fields)) {
        $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Nenhum valor válido para atualizar.'];
        header("Location: /index.php?page=gerenciarjogos");
        exit();
    }

    $sql = "UPDATE bonus_system SET " . implode(', ', $update_fields) . " WHERE game_name = ?";
    $update_values[] = $game_name;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($update_values);

    $_SESSION['form_feedback'] = ['type' => 'success', 'message' => "Configuração de bônus para '$game_name' atualizada."];
    header("Location: /index.php?page=gerenciarjogos");
    exit();
} catch (PDOException $e) {
    error_log("Erro ao atualizar bônus do jogo: " . $e->getMessage());
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Erro no banco de dados.'];
    header("Location: /index.php?page=gerenciarjogos");
    exit();
}