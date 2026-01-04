<?php
// Este arquivo é incluído por index.php.
// index.php já incluiu auth_check.php e database.php,
// então $pdo e $_SESSION['admin_user'] já estão disponíveis.

// Definir o fuso horário para São Paulo (GMT-3)
date_default_timezone_set('America/Sao_Paulo');


$admin_user = $_SESSION['admin_user'] ?? 'Admin';
$page = 'financeiro'; // Define a página atual para o menu de navegação lateral

// Define a visualização atual
// Views: 'overview', 'deposits_by_user', 'user_deposits', 'withdrawals_by_user', 'user_withdrawals'
$view = $_GET['view'] ?? 'overview';
$user_id_selected = $_GET['user_id'] ?? null;
$selected_withdrawal_status_filter = $_GET['withdrawal_status_filter'] ?? 'all'; // Novo filtro de status para user_withdrawals


$data = []; // Variável para armazenar os dados de qualquer visualização

// Processamento de mensagens de sucesso/erro
$message = '';
$message_type = ''; // 'success' or 'error'

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message_type = 'success';
        if (isset($_GET['message'])) {
            $message = htmlspecialchars($_GET['message']);
        } else if (isset($_GET['action'])) { // Use isset($_GET['action']) para verificar qual ação resultou em sucesso
            if ($_GET['action'] === 'deposit_deleted') {
                $message = 'Depósito excluído com sucesso!';
            } else if ($_GET['action'] === 'all_deposits_deleted') {
                $message = 'Todos os depósitos do usuário foram excluídos com sucesso!';
            } else if ($_GET['action'] === 'withdrawal_approved') {
                $message = 'Saque aprovado com sucesso!';
            } else if ($_GET['action'] === 'withdrawal_rejected') {
                $message = 'Saque rejeitado com sucesso!';
            }
        }
    } elseif ($_GET['status'] === 'error') {
        $message_type = 'error';
        $message = htmlspecialchars($_GET['message'] ?? 'Ocorreu um erro desconhecido.');
    }
}


try {
    global $pdo; // Garante acesso ao objeto PDO (vindo de db.php que index.php deve incluir)

    switch ($view) {
        case 'overview':
            // Não carrega dados específicos, apenas mostra as opções.
            break;

        case 'deposits_by_user':
            // Busca usuários que têm pelo menos um depósito.
            $users_with_deposits_stmt = $pdo->query("
                SELECT DISTINCT u.id, u.name, u.email, u.created_at
                FROM users u
                JOIN transactions t ON u.id = t.user_id
                WHERE t.amount > 0 AND (t.status = 'APPROVED' OR t.status = 'PENDING')
                ORDER BY u.name ASC
            ");
            $data['users_with_deposits'] = $users_with_deposits_stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'user_deposits':
            if ($user_id_selected && is_numeric($user_id_selected)) {
                $user_name_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $user_name_stmt->execute([$user_id_selected]);
                $data['selected_user_name'] = $user_name_stmt->fetchColumn();

                $user_transactions_stmt = $pdo->prepare("
                    SELECT t.id, t.amount, t.status, t.created_at,
                        t.provider, t.provider_transaction_id, t.description, t.type
                    FROM transactions t
                    WHERE t.user_id = ?
                    AND t.amount > 0
                    AND (UPPER(t.type) = 'DEPOSIT' OR t.type IS NULL)
                    ORDER BY t.created_at DESC
                ");

                $user_transactions_stmt->execute([$user_id_selected]);
                $data['user_deposits'] = $user_transactions_stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                header("Location: index.php?page=financeiro&view=deposits_by_user&status=error&message=" . urlencode("ID de usuário inválido."));
                exit();
            }
            break;

        case 'withdrawals_by_user':
            // Busca usuários que têm pelo menos um saque PENDENTE
            $users_with_pending_withdrawals_stmt = $pdo->query("
                SELECT DISTINCT u.id, u.name, u.email, u.created_at
                FROM users u
                JOIN withdrawals w ON u.id = w.user_id
                WHERE w.status = 'PENDING'
                ORDER BY u.name ASC
            ");
            $data['users_with_pending_withdrawals'] = $users_with_pending_withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'user_withdrawals':
            if ($user_id_selected && is_numeric($user_id_selected)) {
                $user_name_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $user_name_stmt->execute([$user_id_selected]);
                $data['selected_user_name'] = $user_name_stmt->fetchColumn();

                // Lógica para filtrar por status de saque (TODOS, PENDENTES, APROVADOS, REJEITADOS)
                $withdrawal_status_condition = "";
                if ($selected_withdrawal_status_filter !== 'all') {
                    $withdrawal_status_condition = " AND w.status = '" . strtoupper($selected_withdrawal_status_filter) . "'";
                }

                $user_withdrawals_stmt = $pdo->prepare("
                    SELECT
                        w.id, w.user_id, w.amount, w.pix_key_type, w.pix_key, w.status, w.created_at,
                        u.name as user_name, u.email as user_email, u.document as user_document, u.phone as user_phone,
                        w.rejection_reason
                    FROM withdrawals w
                    JOIN users u ON w.user_id = u.id
                    WHERE w.user_id = ? {$withdrawal_status_condition}
                    ORDER BY w.created_at DESC
                ");
                $user_withdrawals_stmt->execute([$user_id_selected]);
                $data['user_withdrawals'] = $user_withdrawals_stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                header("Location: index.php?page=financeiro&view=withdrawals_by_user&status=error&message=" . urlencode("ID de usuário inválido."));
                exit();
            }
            break;
            // A view 'all_withdrawals' foi removida conforme solicitado.
    }

} catch (PDOException $e) {
    error_log("Erro no financeiro.php: " . $e->getMessage());
    echo "<div style='color: red; background-color: #ffe6e6; padding: 15px; border: 1px solid red; border-radius: 8px;'>";
    echo "<h3>Erro ao carregar dados financeiros:</h3>";
    echo "<p>Por favor, verifique a conexão com o banco de dados e os nomes das tabelas/colunas.</p>";
    echo "<p>Detalhes do erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Financeiro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Estilos base (mantidos para consistência se não houver um common.css) */
        :root {
            --bg-dark: #0D1117; --bg-light: #161B22; --border-color: #30363D;
            --text-primary: #c9d1d9; --text-secondary: #8b949e;
            --accent-blue: #58A6FF; --accent-green: #3FB950;
            --accent-red: #E24C4C; /* Para status de falha/cancelado */
            --accent-yellow: #FFC107; /* Para status pendente */
            --card-bg: #1e2a3a; /* Tom para os cards */
            --delete-btn-bg: #E24C4C; /* Cor para o botão de exclusão */
            --delete-btn-hover: #c43c3c;
            --delete-all-btn-bg: #8b0000; /* Cor mais forte para "excluir tudo" */
            --delete-all-btn-hover: #6a0000;
            --approve-btn-bg: #3FB950; /* Cor para aprovar */
            --approve-btn-hover: #359f44;
            --reject-btn-bg: #FFC107; /* Cor para rejeitar (laranja/amarelo) */
            --reject-btn-hover: #d4a700;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: var(--bg-dark); color: var(--text-primary); }
        .dashboard-grid { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; padding: 2rem; min-height: 100vh; }
        .sidebar { background-color: var(--bg-light); border: 1px solid var(--border-color); border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; }
        .admin-profile { text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; }
        .admin-profile .icon { font-size: 4rem; color: var(--accent-blue); }
        .admin-profile h3 { margin: 0.5rem 0 0.2rem 0; }
        .admin-profile p { margin: 0; font-size: 0.9rem; color: var(--text-secondary); }
        .sidebar-nav { list-style: none; padding: 0; margin: 1.5rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; transition: all 0.2s; }
        .sidebar-nav a:hover { background-color: #2a3038; color: var(--text-primary); }
        .sidebar-nav a.active { background-color: var(--accent-blue); color: #fff; font-weight: 600; }
        .logout-btn { margin-top: auto; }
        .logout-btn a { display: flex; align-items: center; gap: 0.75rem; color: var(--text-secondary); text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 500; border: 1px solid var(--text-secondary); }
        .logout-btn a:hover { background-color: #2a3038; color: var(--text-primary); }
        .main-content { overflow-y: auto; }
        h1, h2 { font-weight: 600; }
        .page-header { border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem; }

        /* Estilos Específicos para a Página Financeiro */
        .option-buttons {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .option-button {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            flex: 1;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.8rem;
            transition: transform 0.2s, background-color 0.2s, border-color 0.2s;
        }
        .option-button:hover {
            transform: translateY(-5px);
            background-color: #2a3038;
            border-color: var(--accent-blue);
        }
        .option-button .icon {
            font-size: 3rem;
            color: var(--accent-blue);
        }

        /* Estilos para lista de usuários com depósitos/saques (similar ao de usuários, mas mais simples) */
        .user-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .user-finance-card { /* Classe genérica para cards de usuário em financeiro */
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.2rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none; /* Para o link */
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .user-finance-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
            border-color: var(--accent-blue);
        }
        .user-finance-card h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--accent-blue);
        }
        .user-finance-card p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        #user-search-input {
            width: 100%;
            padding: 12px;
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            margin-bottom: 1.5rem;
        }
        #user-search-input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.2);
        }

        /* Estilos para cards de transação (reutilizados do anterior) */
        .transaction-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .transaction-card {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        /* Remover o ::before do hover para .transaction-card nos saques, se desejar */
        .transaction-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
        }
        .transaction-card::before { /* Manter para depósitos */
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--accent-blue), var(--accent-green));
            z-index: -1;
            filter: blur(8px);
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            border-radius: 12px;
        }
        /* Overrides para saques: remover o efeito de glow para saques */
        .transaction-card.withdrawal-card:hover::before {
             opacity: 0; /* Desliga o glow para cards de saque */
        }
        /* Ou, se quiser um glow diferente para saques, defina outro aqui */


        .transaction-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 1rem;
        }
        .transaction-card-header .transaction-icon {
            font-size: 2.2rem;
            color: var(--accent-blue);
            background-color: #2a3038;
            border-radius: 50%;
            padding: 0.4rem;
        }
        .transaction-card-header .transaction-id {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .transaction-card-info p {
            margin: 0.5rem 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        .transaction-card-info strong {
            color: var(--text-primary);
        }
        .transaction-card-info .user-link {
            color: var(--accent-blue);
            text-decoration: none;
            transition: color 0.2s;
        }
        .transaction-card-info .user-link:hover {
            text-decoration: underline;
        }
        .transaction-card-amount {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 1rem;
            text-align: right;
            border-top: 1px dashed var(--border-color);
            padding-top: 1rem;
        }
        /* Cores baseadas no status, para transações de depósito (verde) ou saque (vermelho) */
        .transaction-card-amount.deposit { color: var(--accent-green); }
        .transaction-card-amount.withdrawal { color: var(--accent-red); }

        .transaction-card-amount span {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-secondary);
            display: block;
            margin-bottom: 0.2rem;
        }
        .transaction-card-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 0.8rem;
            align-self: flex-start;
        }
        /* Cores de status - atualizadas para refletir o 'APPROVED' e 'PENDING' */
        .status-approved { background-color: rgba(63, 185, 80, 0.2); color: var(--accent-green); border: 1px solid var(--accent-green); }
        .status-pending { background-color: rgba(255, 193, 7, 0.2); color: var(--accent-yellow); border: 1px solid var(--accent-yellow); }
        .status-failed, .status-cancelled, .status-rejected { background-color: rgba(226, 76, 76, 0.2); color: var(--accent-red); border: 1px solid var(--accent-red); }
        .status-processing { background-color: rgba(88, 166, 255, 0.2); color: var(--accent-blue); border: 1px solid var(--accent-blue); }

        /* Mensagens de Vazio */
        .no-data-found {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem;
            border: 1px dashed var(--border-color);
            border-radius: 8px;
            margin-top: 2rem;
        }
        .no-data-found i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-blue);
        }

        /* Botão Voltar */
        .back-button {
            background-color: var(--accent-blue);
            color: #fff;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .back-button:hover {
            background-color: #4a90e2;
            transform: translateY(-1px);
        }

        /* Botões de Ação na Transação (Excluir) */
        .transaction-card-actions {
            display: flex; /* Para alinhar botões horizontalmente */
            justify-content: flex-end; /* Alinha à direita */
            gap: 0.5rem;
            margin-top: 1rem; /* Espaçamento do conteúdo acima */
            padding-top: 1rem; /* Espaçamento da borda superior */
            border-top: 1px dashed var(--border-color);
        }

        .action-button {
            background-color: var(--delete-btn-bg);
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background-color 0.2s, transform 0.1s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .action-button:hover {
            background-color: var(--delete-btn-hover);
            transform: translateY(-1px);
        }
        /* Estilos para botões de Aprovar/Rejeitar Saque */
        .action-button.approve-btn {
            background-color: var(--approve-btn-bg);
        }
        .action-button.approve-btn:hover {
            background-color: var(--approve-btn-hover);
        }
        .action-button.reject-btn {
            background-color: var(--reject-btn-bg);
        }
        .action-button.reject-btn:hover {
            background-color: var(--reject-btn-hover);
        }

        /* Botão "Apagar Todos os Depósitos" */
        .delete-all-deposits-btn {
            background-color: var(--delete-all-btn-bg);
            color: #fff;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem; /* Espaço para o próximo conteúdo */
        }
        .delete-all-deposits-btn:hover {
            background-color: var(--delete-all-btn-hover);
            transform: translateY(-1px);
        }

        /* Mensagens de Sucesso/Erro */
        .alert-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .alert-message.success {
            background-color: rgba(63, 185, 80, 0.2);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }
        .alert-message.error {
            background-color: rgba(226, 76, 76, 0.2);
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
        }
        .alert-message i {
            font-size: 1.5rem;
        }

        /* Estilos do Modal de Confirmação */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden; /* Escondido por padrão */
            opacity: 0;
            transition: visibility 0s, opacity 0.3s ease-in-out;
        }
        .confirmation-modal.show {
            visibility: visible;
            opacity: 1;
        }

        .modal-content {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 2rem;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            text-align: center;
            position: relative;
        }
        .modal-content h3 {
            color: var(--accent-red);
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        .modal-content h3 i {
            font-size: 2rem;
        }
        .modal-content p {
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .modal-button {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            border: none;
        }
        .modal-button.cancel {
            background-color: var(--text-secondary);
            color: var(--text-primary);
        }
        .modal-button.cancel:hover {
            background-color: #7a828b;
            transform: translateY(-1px);
        }
        .modal-button.confirm {
            background-color: var(--delete-btn-bg);
            color: #fff;
        }
        .modal-button.confirm:hover {
            background-color: var(--delete-btn-hover);
            transform: translateY(-1px);
        }
        /* ---------------------------------------------------- */
        /* RESPONSIVIDADE */
        /* ---------------------------------------------------- */

        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem;
            }
            .sidebar {
                order: 2;
            }
            .main-content {
                order: 1;
            }
            .option-buttons {
                flex-direction: column; /* Empilha os botões de opção */
                gap: 1rem;
            }
            .option-button {
                padding: 1rem; /* Reduz o padding dos botões de opção */
            }
            .user-list-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }
            .transaction-cards-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1rem;
            }
            .transaction-card-actions {
                justify-content: center; /* Centraliza os botões de ação na transação */
                flex-wrap: wrap;
            }
            .delete-all-deposits-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                padding: 1rem;
            }
            .sidebar, .option-buttons, .user-list-grid, .transaction-cards-grid {
                padding: 1rem;
            }
            h1, h2 {
                font-size: 1.5rem;
            }
            .page-header {
                margin-bottom: 1rem;
            }
            .transaction-card-header .transaction-id {
                font-size: 1rem;
            }
            .transaction-card-info p, .transaction-card-info strong {
                font-size: 0.9rem;
            }
            .transaction-card-amount {
                font-size: 1.5rem;
            }
            .transaction-card-actions {
                gap: 0.5rem;
            }
            .action-button {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            .pagination {
                flex-wrap: wrap;
            }
            .pagination a, .pagination span {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-grid {
                padding: 0.5rem;
                gap: 0.5rem;
            }
            .sidebar {
                padding: 0.75rem;
            }
            h1 {
                font-size: 1.2rem;
            }
            .option-button {
                font-size: 1rem;
                padding: 0.75rem;
                gap: 0.5rem;
            }
            .option-button .icon {
                font-size: 2.5rem;
            }
            .user-list-grid, .transaction-cards-grid {
                grid-template-columns: 1fr; /* Empilha os cards em uma única coluna */
                gap: 0.75rem;
            }
            .transaction-card {
                padding: 1rem;
            }
            .transaction-card-header {
                flex-direction: column; /* Empilha o ícone e o ID da transação */
                align-items: flex-start;
                gap: 0.5rem;
            }
            .transaction-card-actions {
                flex-direction: column; /* Empilha os botões de ação */
                gap: 0.5rem;
            }
            .action-button {
                width: 100%;
                justify-content: center;
            }
            .delete-all-deposits-btn {
                width: 100%;
            }
            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
            .modal-buttons {
                flex-direction: column;
                gap: 0.75rem;
            }
            .modal-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="admin-profile">
                <i class="bi bi-person-circle icon"></i>
                <h3><?= htmlspecialchars($admin_user) ?></h3>
                <p>Administrador</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="index.php?page=dashboard" class="<?= ($page === 'dashboard') ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
                <li><a href="index.php?page=usuarios" class="<?= ($page === 'usuarios') ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> Usuários</a></li>
                <li><a href="index.php?page=financeiro" class="<?= ($page === 'financeiro') ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Financeiro</a></li>
                <li><a href="index.php?page=relatorios" class="<?= ($page === 'relatorios') ? 'active' : '' ?>"><i class="bi bi-file-earmark-bar-graph-fill"></i> Relatórios</a></li>
                <li><a href="index.php?page=contasdemo" class="<?= ($page === 'contasdemo') ? 'active' : '' ?>"><i class="bi bi-joystick"></i> Contas Demo</a></li>
                <li><a href="index.php?page=gerenciarjogos" class="<?= ($page === 'gerenciarjogos') ? 'active' : '' ?>"><i class="bi bi-gear-fill"></i> Gerenciar Jogos</a></li>
                <li><a href="index.php?page=gerenciarpagamentos" class="<?= ($page === 'gerenciarpagamentos') ? 'active' : '' ?>"><i class="bi bi-credit-card-fill"></i> Métodos de Pagamento</a></li>
            </ul>
            <div class="logout-btn">
                <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Sair da Conta</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Painel Financeiro</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert-message <?= $message_type ?>">
                    <i class="bi <?= $message_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($view === 'overview'): ?>
                <h2>Selecione uma opção:</h2>
                <div class="option-buttons">
                    <a href="index.php?page=financeiro&view=deposits_by_user" class="option-button">
                        <i class="bi bi-cash-stack icon"></i>
                        <span>Depósitos dos Usuários</span>
                    </a>
                    <a href="index.php?page=financeiro&view=withdrawals_by_user" class="option-button">
                        <i class="bi bi-credit-card-2-front icon"></i>
                        <span>Solicitações de Saque</span>
                    </a>
                </div>

            <?php elseif ($view === 'deposits_by_user'): ?>
                <a href="index.php?page=financeiro" class="back-button"><i class="bi bi-arrow-left"></i> Voltar às Opções</a>
                <h2>Depósitos por Usuário</h2>
                <input type="text" id="user-search-input" placeholder="Buscar usuário por nome ou email...">

                <div class="user-list-grid" id="user-list-grid">
                    <?php if (!empty($data['users_with_deposits'])): ?>
                        <?php foreach ($data['users_with_deposits'] as $user): ?>
                            <a href="index.php?page=financeiro&view=user_deposits&user_id=<?= htmlspecialchars($user['id']) ?>"
                               class="user-finance-card"
                               data-name="<?= htmlspecialchars(strtolower($user['name'])) ?>"
                               data-email="<?= htmlspecialchars(strtolower($user['email'])) ?>">
                                <h3><i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['name']) ?></h3>
                                <p><?= htmlspecialchars($user['email']) ?></p>
                                <p class="text-secondary">Membro desde: <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-found">
                            <i class="bi bi-people"></i>
                            <p>Nenhum usuário com depósitos encontrados.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($view === 'user_deposits'): ?>
                <a href="index.php?page=financeiro&view=deposits_by_user" class="back-button"><i class="bi bi-arrow-left"></i> Voltar à Lista de Usuários</a>
                <h2>Depósitos de <?= htmlspecialchars($data['selected_user_name'] ?? 'Usuário Desconhecido') ?></h2>

                <?php if (!empty($data['user_deposits']) && $user_id_selected): ?>
                    <button type="button" class="delete-all-deposits-btn"
                            data-user-id="<?= htmlspecialchars($user_id_selected) ?>"
                            data-user-name="<?= htmlspecialchars($data['selected_user_name'] ?? 'este usuário') ?>"
                            data-delete-type="all_deposits"> <i class="bi bi-trash-fill"></i> Apagar Todos os Depósitos de <?= htmlspecialchars($data['selected_user_name'] ?? 'este usuário') ?>
                    </button>
                <?php endif; ?>

                <div class="transaction-cards-grid">
                    <?php if (!empty($data['user_deposits'])): ?>
                        <?php foreach ($data['user_deposits'] as $transaction):
                            $transaction_date = date('d/m/Y H:i', strtotime($transaction['created_at']));
                            $status_class = 'status-' . strtolower(str_replace(' ', '-', $transaction['status']));
                        ?>
                            <div class="transaction-card">
                                <div class="transaction-card-header">
                                    <i class="bi bi-receipt transaction-icon"></i>
                                    <span class="transaction-id">Depósito ID: <?= htmlspecialchars($transaction['id']) ?></span>
                                </div>
                                <div class="transaction-card-info">
                                    <p><i class="bi bi-box"></i> <strong>Provedor:</strong> <?= htmlspecialchars($transaction['provider'] ?? 'N/A') ?></p>
                                    <p><i class="bi bi-hash"></i> <strong>ID Provedor:</strong> <?= htmlspecialchars($transaction['provider_transaction_id'] ?? 'N/A') ?></p>
                                    <p><i class="bi bi-calendar-event"></i> <strong>Data:</strong> <?= $transaction_date ?></p>
                                </div>
                                <div class="transaction-card-amount deposit"> <span>Valor:</span> R$ <?= number_format($transaction['amount'], 2, ',', '.') ?>
                                </div>
                                <span class="transaction-card-status <?= $status_class ?>">
                                    <?= htmlspecialchars($transaction['status']) ?>
                                </span>
                                <div class="transaction-card-actions">
                                    <button type="button" class="action-button delete-trigger"
                                            data-transaction-id="<?= htmlspecialchars($transaction['id']) ?>"
                                            data-user-id="<?= htmlspecialchars($user_id_selected) ?>"
                                            data-delete-type="single_deposit"> <i class="bi bi-trash"></i> Excluir Depósito
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-found">
                            <i class="bi bi-coin"></i>
                            <p>Nenhum depósito encontrado para este usuário.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($view === 'withdrawals_by_user'): // TELA: LISTA USUÁRIOS COM SAQUES PENDENTES ?>
                <a href="index.php?page=financeiro" class="back-button"><i class="bi bi-arrow-left"></i> Voltar às Opções</a>
                <h2>Usuários com Saques Pendentes</h2>
                <input type="text" id="user-search-input" placeholder="Buscar usuário por nome ou email...">

                <div class="user-list-grid" id="user-list-grid">
                    <?php if (!empty($data['users_with_pending_withdrawals'])): ?>
                        <?php foreach ($data['users_with_pending_withdrawals'] as $user): ?>
                            <a href="index.php?page=financeiro&view=user_withdrawals&user_id=<?= htmlspecialchars($user['id']) ?>"
                               class="user-finance-card"
                               data-name="<?= htmlspecialchars(strtolower($user['name'])) ?>"
                               data-email="<?= htmlspecialchars(strtolower($user['email'])) ?>">
                                <h3><i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['name']) ?></h3>
                                <p><?= htmlspecialchars($user['email']) ?></p>
                                <p class="text-secondary">Membro desde: <?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-found">
                            <i class="bi bi-people"></i>
                            <p>Nenhum usuário com saques pendentes encontrados.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($view === 'user_withdrawals'): // TELA: SAQUES DE UM USUÁRIO ESPECÍFICO (PENDENTES E PROCESSADOS) ?>
                <a href="index.php?page=financeiro&view=withdrawals_by_user" class="back-button"><i class="bi bi-arrow-left"></i> Voltar à Lista de Saques por Usuário</a>
                <h2>Saques de <?= htmlspecialchars($data['selected_user_name'] ?? 'Usuário Desconhecido') ?></h2>

                <div class="status-select-filter" style="margin-bottom: 1.5rem;">
                    <a href="?<?= http_build_query(array_merge($_GET, ['withdrawal_status_filter' => 'all', 'user_id' => $user_id_selected])) ?>"
                       class="<?= $selected_withdrawal_status_filter === 'all' ? 'active' : '' ?>">Todos</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['withdrawal_status_filter' => 'pending', 'user_id' => $user_id_selected])) ?>"
                       class="<?= $selected_withdrawal_status_filter === 'pending' ? 'active' : '' ?>">Pendentes</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['withdrawal_status_filter' => 'approved', 'user_id' => $user_id_selected])) ?>"
                       class="<?= $selected_withdrawal_status_filter === 'approved' ? 'active' : '' ?>">Aprovados</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['withdrawal_status_filter' => 'rejected', 'user_id' => $user_id_selected])) ?>"
                       class="<?= $selected_withdrawal_status_filter === 'rejected' ? 'active' : '' ?>">Rejeitados</a>
                </div>


                <div class="transaction-cards-grid">
                    <?php if (!empty($data['user_withdrawals'])): ?>
                        <?php foreach ($data['user_withdrawals'] as $withdrawal):
                            $withdrawal_date = date('d/m/Y H:i', strtotime($withdrawal['created_at']));
                            $status_class = 'status-' . strtolower(str_replace(' ', '-', $withdrawal['status']));

                            // Formatando a chave PIX para exibição
                            $display_pix_key = htmlspecialchars($withdrawal['pix_key']);
                            if ($withdrawal['pix_key_type'] == 'cpf' && strlen($display_pix_key) == 11) {
                                $display_pix_key = substr($display_pix_key, 0, 3) . '.' .
                                                   substr($display_pix_key, 3, 3) . '.' .
                                                   substr($display_pix_key, 6, 3) . '-' .
                                                   substr($display_pix_key, 9, 2);
                            } elseif ($withdrawal['pix_key_type'] == 'phone' && (strlen($display_pix_key) == 10 || strlen($display_pix_key) == 11)) {
                                if (strlen($display_pix_key) == 11) {
                                    $display_pix_key = '(' . substr($display_pix_key, 0, 2) . ') ' .
                                                       substr($display_pix_key, 2, 5) . '-' .
                                                       substr($display_pix_key, 7, 4);
                                } else { // 10 dígitos
                                    $display_pix_key = '(' . substr($display_pix_key, 0, 2) . ') ' .
                                                       substr($display_pix_key, 2, 4) . '-' .
                                                       substr($display_pix_key, 6, 4);
                                }
                            } elseif ($withdrawal['pix_key_type'] == 'cnpj' && strlen($display_pix_key) == 14) {
                                $display_pix_key = substr($display_pix_key, 0, 2) . '.' .
                                                   substr($display_pix_key, 2, 3) . '.' .
                                                   substr($display_pix_key, 5, 3) . '/' .
                                                   substr($display_pix_key, 8, 4) . '-' .
                                                   substr($display_pix_key, 12, 2);
                            }
                        ?>
                            <div class="transaction-card">
                                <div class="transaction-card-header">
                                    <i class="bi bi-credit-card-2-front transaction-icon"></i>
                                    <span class="transaction-id">Saque ID: <?= htmlspecialchars($withdrawal['id']) ?></span>
                                </div>
                                <div class="transaction-card-info">
                                    <p><i class="bi bi-person"></i> <strong>Usuário:</strong> <a href="index.php?page=usuarios&id=<?= $withdrawal['user_id'] ?>" class="user-link"><?= htmlspecialchars($withdrawal['user_name']) ?></a></p>
                                    <p><i class="bi bi-envelope"></i> <strong>Email:</strong> <?= htmlspecialchars($withdrawal['user_email']) ?></p>
                                    <p><i class="bi bi-file-earmark-person"></i> <strong>CPF:</strong> <?= htmlspecialchars($withdrawal['user_document']) ?></p>
                                    <p><i class="bi bi-key"></i> <strong>Chave PIX:</strong> <?= htmlspecialchars(strtoupper($withdrawal['pix_key_type'])) ?> - <?= $display_pix_key ?></p>
                                    <p><i class="bi bi-calendar-event"></i> <strong>Data:</strong> <?= $withdrawal_date ?></p>
                                    <?php if ($withdrawal['rejection_reason']): ?>
                                    <p><i class="bi bi-exclamation-triangle"></i> <strong>Motivo Rejeição:</strong> <span style="color: var(--accent-red);"><?= htmlspecialchars($withdrawal['rejection_reason']) ?></span></p>
                                    <?php endif; ?>
                                </div>
                                <div class="transaction-card-amount withdrawal">
                                    <span>Valor Solicitado:</span> R$ <?= number_format($withdrawal['amount'], 2, ',', '.') ?>
                                </div>
                                <span class="transaction-card-status <?= $status_class ?>">
                                    <?= htmlspecialchars($withdrawal['status']) ?>
                                </span>
                                <?php if ($withdrawal['status'] === 'PENDING'): // Botões de ação só aparecem se o status for PENDING ?>
                                <div class="transaction-card-actions">
                                    <button type="button" class="action-button approve-btn"
                                            data-withdrawal-id="<?= htmlspecialchars($withdrawal['id']) ?>"
                                            data-user-id="<?= htmlspecialchars($withdrawal['user_id']) ?>"
                                            data-amount="<?= htmlspecialchars($withdrawal['amount']) ?>"
                                            data-action-type="approve_withdrawal">
                                        <i class="bi bi-check-circle"></i> Aprovar
                                    </button>
                                    <button type="button" class="action-button reject-btn"
                                            data-withdrawal-id="<?= htmlspecialchars($withdrawal['id']) ?>"
                                            data-user-id="<?= htmlspecialchars($withdrawal['user_id']) ?>"
                                            data-amount="<?= htmlspecialchars($withdrawal['amount']) ?>"
                                            data-action-type="reject_withdrawal">
                                        <i class="bi bi-x-circle"></i> Rejeitar
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-found">
                            <i class="bi bi-credit-card-2-front"></i>
                            <p>Nenhuma solicitação de saque encontrada para este usuário e filtro de status.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <div id="confirmationModal" class="confirmation-modal">
        <div class="modal-content">
            <h3><i class="bi bi-exclamation-triangle-fill"></i> Confirmação de Ação</h3>
            <p id="modalMessage"></p>
            <div class="modal-buttons">
                <button type="button" class="modal-button cancel" id="cancelAction">Cancelar</button>
                <button type="button" class="modal-button confirm" id="confirmAction">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Lógica de busca para a lista de usuários com depósitos/saques
            const userSearchInput = document.getElementById('user-search-input');
            if (userSearchInput) {
                userSearchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const userCards = document.querySelectorAll('.user-finance-card'); // Usa a classe genérica
                    let foundUsers = false;

                    userCards.forEach(card => {
                        const name = card.dataset.name;
                        const email = card.dataset.email;

                        if (name.includes(filter) || email.includes(filter)) {
                            card.style.display = "";
                            foundUsers = true;
                        } else {
                            card.style.display = "none";
                        }
                    });

                    const noDataFoundMessage = document.querySelector('.no-data-found');
                    if (noDataFoundMessage) {
                        if (!foundUsers && filter !== '') {
                            noDataFoundMessage.style.display = 'block';
                            noDataFoundMessage.innerHTML = '<i class="bi bi-people"></i><p>Nenhum usuário encontrado com esse filtro.</p>';
                        } else if (filter === '' && userCards.length > 0) {
                             noDataFoundMessage.style.display = 'none';
                        } else if (filter === '' && userCards.length === 0) {
                            // Restaurar mensagem original se nenhum usuário com depósitos/saques existir
                            const currentView = new URLSearchParams(window.location.search).get('view');
                            if (currentView === 'withdrawals_by_user') {
                                noDataFoundMessage.innerHTML = '<i class="bi bi-people"></i><p>Nenhum usuário com saques pendentes encontrados.</p>';
                            } else { // deposits_by_user
                                noDataFoundMessage.innerHTML = '<i class="bi bi-people"></i><p>Nenhum usuário com depósitos encontrados.</p>';
                            }
                            noDataFoundMessage.style.display = 'block';
                        }
                    }
                });
            }

            // --- Lógica do Modal de Confirmação (Centralizada) ---
            const confirmationModal = document.getElementById('confirmationModal');
            const modalMessage = document.getElementById('modalMessage');
            const confirmActionBtn = document.getElementById('confirmAction');
            const cancelActionBtn = document.getElementById('cancelAction');

            let actionToExecute = null; // Para armazenar a função de callback

            function showConfirmationModal(message, onConfirmCallback, confirmButtonText = 'Confirmar') {
                modalMessage.innerHTML = message;
                confirmActionBtn.textContent = confirmButtonText;
                confirmationModal.classList.add('show');
                actionToExecute = onConfirmCallback;
            }

            function hideConfirmationModal() {
                confirmationModal.classList.remove('show');
                actionToExecute = null;
            }

            confirmActionBtn.addEventListener('click', function() {
                if (actionToExecute) {
                    actionToExecute();
                }
                hideConfirmationModal();
            });

            cancelActionBtn.addEventListener('click', hideConfirmationModal);

            confirmationModal.addEventListener('click', function(event) {
                if (event.target === confirmationModal) {
                    hideConfirmationModal();
                }
            });

            // --- Ativar botões de ação (Excluir Depósito / Aprovar Saque / Rejeitar Saque) ---

            // 1. Excluir Depósito Individual (EXISTENTE)
            document.querySelectorAll('.delete-trigger').forEach(button => {
                button.addEventListener('click', function() {
                    const transactionId = this.dataset.transactionId;
                    const userId = this.dataset.userId;
                    const userNameElement = this.closest('.transaction-card').querySelector('.user-link');
                    const userName = userNameElement ? userNameElement.textContent : 'este usuário';

                    const message = `Tem certeza que deseja EXCLUIR o depósito ID <strong>${transactionId}</strong> do usuário <strong>${userName}</strong>? \nEsta ação é irreversível e afetará os registros financeiros.`;

                    showConfirmationModal(message, function() {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/delete_transaction.php';

                        const inputAction = document.createElement('input');
                        inputAction.type = 'hidden';
                        inputAction.name = 'action';
                        inputAction.value = 'delete_single_deposit';
                        form.appendChild(inputAction);

                        const inputTransId = document.createElement('input');
                        inputTransId.type = 'hidden';
                        inputTransId.name = 'transaction_id';
                        inputTransId.value = transactionId;
                        form.appendChild(inputTransId);

                        const inputUserId = document.createElement('input');
                        inputUserId.type = 'hidden';
                        inputUserId.name = 'user_id';
                        inputUserId.value = userId;
                        form.appendChild(inputUserId);

                        const inputRedirect = document.createElement('input');
                        inputRedirect.type = 'hidden';
                        inputRedirect.name = 'redirect_to';
                        inputRedirect.value = window.location.href;
                        form.appendChild(inputRedirect);

                        document.body.appendChild(form);
                        form.submit();
                    }, 'Excluir Depósito');
                });
            });

            // 2. Apagar Todos os Depósitos do Usuário (EXISTENTE)
            const deleteAllDepositsBtn = document.querySelector('.delete-all-deposits-btn');
            if (deleteAllDepositsBtn) {
                deleteAllDepositsBtn.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const userName = this.dataset.userName;
                    const message = `ATENÇÃO: Você está prestes a EXCLUIR TODOS os depósitos do usuário <strong>${userName}</strong>. \n\nESTA AÇÃO É IRREVERSÍVEL E PODE AFETAR GRAVEMENTE OS RELATÓRIOS FINANCEIROS!\n\nConfirma a exclusão de TODOS os depósitos?`;

                    showConfirmationModal(message, function() {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/delete_transaction.php';

                        const inputAction = document.createElement('input');
                        inputAction.type = 'hidden';
                        inputAction.name = 'action';
                        inputAction.value = 'delete_all_deposits_from_user';
                        form.appendChild(inputAction);

                        const inputUserId = document.createElement('input');
                        inputUserId.type = 'hidden';
                        inputUserId.name = 'user_id';
                        inputUserId.value = userId;
                        form.appendChild(inputUserId);

                        const inputRedirect = document.createElement('input');
                        inputRedirect.type = 'hidden';
                        inputRedirect.name = 'redirect_to';
                        inputRedirect.value = window.location.href;
                        form.appendChild(inputRedirect);

                        document.body.appendChild(form);
                        form.submit();
                    }, 'Confirmar Exclusão Massiva');
                });
            }

            // 3. Aprovar/Rejeitar Saque
            document.querySelectorAll('.approve-btn, .reject-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const withdrawalId = this.dataset.withdrawalId;
                    const userId = this.dataset.userId;
                    const amount = this.dataset.amount;
                    const actionType = this.dataset.actionType;
                    const isApprove = actionType === 'approve_withdrawal';

                    const messageAction = isApprove ? 'APROVAR' : 'REJEITAR';
                    const confirmButtonText = isApprove ? 'Aprovar Saque' : 'Rejeitar Saque';
                    const messageColor = isApprove ? 'var(--accent-green)' : 'var(--accent-red)';

                    // CORREÇÃO: Acessar o user-link de forma mais segura e flexível
                    const parentCard = this.closest('.transaction-card');
                    const userNameElement = parentCard ? parentCard.querySelector('.transaction-card-info p strong') : null; // Pega o strong dentro do p do usuário
                    let userName = userNameElement ? userNameElement.textContent : 'usuário desconhecido';

                    let message = `Tem certeza que deseja <strong style="color: ${messageColor};">${messageAction}</strong> o saque ID <strong>${withdrawalId}</strong>, no valor de R$ <strong>${Number(amount).toFixed(2).replace('.', ',')}</strong>, solicitado por <strong>${userName}</strong>?`;

                    if (!isApprove) {
                        message += `<br><br>Ao REJEITAR, o valor será estornado para o saldo do usuário. Você será solicitado a informar um motivo.`;
                    } else {
                        message += `<br><br>Ao APROVAR, o valor NÃO será estornado. Tenha certeza de que o pagamento foi/será processado externamente.`;
                    }

                    showConfirmationModal(message, async function() {
                        let rejectionReason = null;
                        if (!isApprove) {
                            rejectionReason = prompt("Por favor, digite o motivo da rejeição do saque:");
                            if (rejectionReason === null || rejectionReason.trim() === "") {
                                alert("Rejeição cancelada. Motivo é obrigatório para rejeitar.");
                                return;
                            }
                        }

                        const apiResponse = await fetch('/admin_process_withdrawal.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                withdrawal_id: withdrawalId,
                                user_id: userId,
                                amount: amount,
                                action: actionType,
                                rejection_reason: rejectionReason
                            })
                        });
                        const result = await apiResponse.json();

                        if (result.success) {
                            // Mantém o filtro de status atual ao redirecionar
                            const currentWithdrawalStatusFilter = new URLSearchParams(window.location.search).get('withdrawal_status_filter') || 'all';
                            window.location.href = `index.php?page=financeiro&view=user_withdrawals&user_id=${userId}&withdrawal_status_filter=${currentWithdrawalStatusFilter}&status=success&message=${encodeURIComponent(result.message)}`;
                        } else {
                            // Mantém o filtro de status atual ao redirecionar
                            const currentWithdrawalStatusFilter = new URLSearchParams(window.location.search).get('withdrawal_status_filter') || 'all';
                            window.location.href = `index.php?page=financeiro&view=user_withdrawals&user_id=${userId}&withdrawal_status_filter=${currentWithdrawalStatusFilter}&status=error&message=${encodeURIComponent(result.message || 'Erro ao processar a solicitação de saque.')}`;
                        }
                    }, confirmButtonText);
                });
            });

        });
    </script>
</body>
</html>