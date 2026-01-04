<?php
// public/toggle_bonus_status.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Location: gerenciarjogos.php');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Acesso não autorizado.'];
    exit;
}

if (!isset($_POST['game_name'])) {
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Nome do jogo não especificado.'];
    exit;
}

$game_name = $_POST['game_name'];

try {
    global $pdo;

    // Busca o status atual do bônus
    $stmt = $pdo->prepare("SELECT is_bonus_active FROM bonus_system WHERE game_name = ?");
    $stmt->execute([$game_name]);
    $bonus = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bonus) {
        if ($bonus['is_bonus_active']) {
            // Lógica para desativar o bônus
            $sql = "UPDATE bonus_system SET is_bonus_active = FALSE WHERE game_name = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$game_name]);

            $_SESSION['form_feedback'] = ['type' => 'success', 'message' => "Bônus para '{$game_name}' desativado com sucesso."];
        } else {
            // ✅ LÓGICA CORRIGIDA: Lógica para ativar o bônus
            // Zera o bônus pago, mas MANTÉM o faturamento atual.
            $sql = "UPDATE bonus_system SET is_bonus_active = TRUE, current_bonus_paid = 0 WHERE game_name = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$game_name]);

            $_SESSION['form_feedback'] = ['type' => 'success', 'message' => "Bônus para '{$game_name}' ativado com sucesso. O contador de bônus pago foi zerado."];
        }
    } else {
        $_SESSION['form_feedback'] = ['type' => 'error', 'message' => "Jogo '{$game_name}' não encontrado no sistema de bônus."];
    }
} catch (PDOException $e) {
    $_SESSION['form_feedback'] = ['type' => 'error', 'message' => 'Erro ao atualizar o status do bônus: ' . $e->getMessage()];
}

exit;