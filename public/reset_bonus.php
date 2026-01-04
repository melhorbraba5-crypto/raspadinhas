<?php
// public/reset_bonus.php
require_once __DIR__ . '/../config/database.php';

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=gerenciarjogos');
    exit;
}

// Pega o nome do jogo a partir do formulário
$game_name = $_POST['game_name'] ?? null;

if (!$game_name) {
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Nome do jogo não fornecido.'
    ];
    header('Location: index.php?page=gerenciarjogos');
    exit;
}

try {
    // Prepara a query para zerar o bônus pago atual do jogo especificado
    $stmt = $pdo->prepare("UPDATE bonus_system SET current_bonus_paid = 0.00 WHERE game_name = ?");
    $stmt->execute([$game_name]);

    // Verifica se alguma linha foi afetada para confirmar a atualização
    if ($stmt->rowCount() > 0) {
        $_SESSION['form_feedback'] = [
            'type' => 'success',
            'message' => 'Bônus pago do jogo ' . htmlspecialchars($game_name) . ' foi zerado com sucesso!'
        ];
    } else {
        $_SESSION['form_feedback'] = [
            'type' => 'error',
            'message' => 'Ocorreu um erro ou o jogo não foi encontrado.'
        ];
    }

} catch (PDOException $e) {
    // Em caso de erro no banco de dados, armazena uma mensagem de erro genérica
    error_log("Erro ao zerar bônus pago: " . $e->getMessage());
    $_SESSION['form_feedback'] = [
        'type' => 'error',
        'message' => 'Erro de banco de dados ao tentar zerar o bônus pago.'
    ];
}

// Redireciona de volta para a página de gerenciamento
header('Location: index.php?page=gerenciarjogos');
exit;