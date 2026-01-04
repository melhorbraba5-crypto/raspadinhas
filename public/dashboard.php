<?php
// public/dashboard.php
// Este arquivo será incluído por index.php.
// index.php já incluiu auth_check.php e database.php,
// então $pdo e $_SESSION['admin_user'] já estão disponíveis.

// Definir o fuso horário para São Paulo (GMT-3)
date_default_timezone_set('America/Sao_Paulo');

$admin_user = $_SESSION['admin_user'] ?? 'Admin';
$page = 'dashboard';

// --- Lógica da Função Auxiliar ---
function get_period_query_parts($param_name, $date_column = 'created_at', $default_period = 'today')
{
    $period = $_GET[$param_name] ?? $default_period;
    $start_date = $_GET[$param_name . '_start'] ?? null;
    $end_date = $_GET[$param_name . '_end'] ?? null;
    $params = [];
    $where_clause = "";
    $label = "Hoje";

    switch ($period) {
        case 'today':
            $where_clause = "{$date_column} >= CURRENT_DATE AND {$date_column} < (CURRENT_DATE + INTERVAL '1 day')";
            $label = "Hoje";
            break;
        case 'week':
            $where_clause = "{$date_column} >= date_trunc('week', CURRENT_TIMESTAMP) AND {$date_column} < (date_trunc('week', CURRENT_TIMESTAMP) + INTERVAL '1 week')";
            $label = "Esta Semana";
            break;
        case 'month':
            $where_clause = "{$date_column} >= date_trunc('month', CURRENT_TIMESTAMP) AND {$date_column} < (date_trunc('month', CURRENT_TIMESTAMP) + INTERVAL '1 month')";
            $label = "Este Mês";
            break;
        case 'custom':
            if ($start_date && $end_date) {
                $where_clause = "DATE({$date_column}) BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
                $label = date('d/m', strtotime($start_date)) . " a " . date('d/m', strtotime($end_date));
            } else {
                $period = 'today';
                $where_clause = "{$date_column} >= CURRENT_DATE AND {$date_column} < (CURRENT_DATE + INTERVAL '1 day')";
                $label = "Hoje";
            }
            break;
        case 'all_time':
            $where_clause = "";
            $params = [];
            $label = "Todo o Período";
            break;
        default:
            $period = $default_period;
            if ($default_period == 'today') {
                $where_clause = "{$date_column} >= CURRENT_DATE AND {$date_column} < (CURRENT_DATE + INTERVAL '1 day')";
                $label = "Hoje";
            } else if ($default_period == 'all_time') {
                $where_clause = "";
                $label = "Todo o Período";
            }
            break;
    }
    return ['where' => $where_clause, 'params' => $params, 'label' => $label, 'period' => $period];
}

// --- Executa as consultas específicas do dashboard ---
global $pdo;

// Filtro padrão para a dashboard ("Hoje")
$today_filter = get_period_query_parts('dashboard_period', 'created_at', 'today');
$today_params = $today_filter['params'];

// --- ✨ LÓGICA DE CONSULTA CORRIGIDA ✨ ---

// Função auxiliar para montar a cláusula WHERE de forma segura
function build_where_clause($base_conditions, $date_filter)
{
    $conditions = $base_conditions;
    if (!empty($date_filter['where'])) {
        $conditions[] = $date_filter['where'];
    }
    return 'WHERE ' . implode(' AND ', $conditions);
}

// --- Estatísticas que SEMPRE são "Todos os Tempos" ---
$total_users = $pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();
$total_affiliates = $pdo->query("SELECT COUNT(id) FROM users WHERE referrer_id IS NOT NULL")->fetchColumn();

// --- Estatísticas do DIA ATUAL ---

// 1. Novos Usuários NO DIA ATUAL
$new_users_where = build_where_clause([], $today_filter);
$new_users_stmt = $pdo->prepare("SELECT COUNT(id) FROM users {$new_users_where}");
$new_users_stmt->execute($today_params);
$dashboard_new_users = $new_users_stmt->fetchColumn();

// 2. Valor Total de Depósitos Aprovados NO DIA ATUAL
$approved_deposits_where = build_where_clause(["status = 'APPROVED'", "amount > 0"], $today_filter);
$total_deposits_amount_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions {$approved_deposits_where}");
$total_deposits_amount_stmt->execute($today_params);
$dashboard_total_deposits = $total_deposits_amount_stmt->fetchColumn();

// 3. Número de Depósitos Aprovados NO DIA ATUAL
// A cláusula WHERE é a mesma da consulta anterior
$count_approved_deposits_stmt = $pdo->prepare("SELECT COUNT(id) FROM transactions {$approved_deposits_where}");
$count_approved_deposits_stmt->execute($today_params);
$dashboard_approved_deposits_count = $count_approved_deposits_stmt->fetchColumn();

// 4. Número de Depósitos Pendentes NO DIA ATUAL
$pending_deposits_where = build_where_clause(["status = 'PENDING'", "amount > 0"], $today_filter);
$count_pending_deposits_stmt = $pdo->prepare("SELECT COUNT(id) FROM transactions {$pending_deposits_where}");
$count_pending_deposits_stmt->execute($today_params);
$dashboard_pending_deposits_count = $count_pending_deposits_stmt->fetchColumn();

// 5. Valor Total de Saques Aprovados NO DIA ATUAL
$approved_withdrawals_where = build_where_clause(["status = 'APPROVED'", "amount < 0"], $today_filter);
$total_withdrawals_amount_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions {$approved_withdrawals_where}");
$total_withdrawals_amount_stmt->execute($today_params);
$dashboard_total_withdrawals = abs($total_withdrawals_amount_stmt->fetchColumn());

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Resumo Geral</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0D1117;
            --bg-light: #161B22;
            --border-color: #30363D;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --accent-blue: #58A6FF;
            --accent-green: #3FB950;
            --accent-red: #E24C4C;
            --accent-yellow: #FFC107;
            --card-user-bg: #22364c;
            --card-finance-bg: #2a3a22;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            background-color: var(--bg-dark);
            color: var(--text-primary);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            padding: 2rem;
            min-height: 100vh;
        }

        .sidebar {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        .admin-profile {
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.5rem;
        }

        .admin-profile .icon {
            font-size: 4rem;
            color: var(--accent-blue);
        }

        .admin-profile h3 {
            margin: 0.5rem 0 0.2rem 0;
        }

        .admin-profile p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .sidebar-nav a:hover {
            background-color: #2a3038;
            color: var(--text-primary);
        }

        .sidebar-nav a.active {
            background-color: var(--accent-blue);
            color: #fff;
            font-weight: 600;
        }

        .logout-btn {
            margin-top: auto;
        }

        .logout-btn a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            border: 1px solid var(--text-secondary);
        }

        .logout-btn a:hover {
            background-color: #2a3038;
            color: var(--text-primary);
        }

        .main-content {
            overflow-y: auto;
        }

        h1,
        h2 {
            font-weight: 600;
        }

        .page-header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 150px;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            position: relative;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .summary-card.users-card {
            background-color: var(--card-user-bg);
        }

        .summary-card.finance-card {
            background-color: var(--card-finance-bg);
        }

        .summary-card.default-card {
            background-color: var(--bg-light);
        }

        .summary-card-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .summary-card-title i {
            font-size: 1.5rem;
            color: var(--accent-blue);
        }

        .summary-card.users-card .summary-card-title i {
            color: #87CEEB;
        }

        .summary-card.finance-card .summary-card-title i {
            color: #ADFF2F;
        }

        .summary-card.default-card .summary-card-title i {
            color: var(--accent-blue);
        }

        .summary-card-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .summary-card-value.money {
            color: var(--accent-green);
        }

        .summary-card-value.negative {
            color: var(--accent-red);
        }

        .summary-card-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: auto;
        }

        .summary-card-link {
            display: block;
            text-align: right;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .summary-card-link:hover {
            color: #4a90e2;
            text-decoration: underline;
        }

        .summary-card-link i {
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }
        /* ---------------------------------------------------- */
        /* RESPONSIVIDADE */
        /* ---------------------------------------------------- */

        /* Para telas pequenas (mobile, até 768px) */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr; /* Coloca a sidebar e o conteúdo em uma única coluna */
                gap: 1rem; /* Reduz o espaçamento geral */
                padding: 1rem; /* Diminui o padding da página */
            }

            .sidebar {
                padding: 1rem; /* Diminui o padding da barra lateral */
            }

            .admin-profile {
                padding-bottom: 1rem; /* Diminui o espaçamento na parte inferior do perfil */
            }

            .sidebar-nav {
                margin: 1rem 0; /* Ajusta o espaçamento da navegação */
            }

            .sidebar-nav a {
                padding: 0.65rem 0.8rem; /* Reduz o padding dos itens da navegação */
                font-size: 0.9rem; /* Diminui a fonte */
            }

            .logout-btn a {
                font-size: 0.9rem; /* Diminui a fonte do botão de logout */
            }

            .main-content {
                order: 1; /* Garante que o conteúdo principal apareça primeiro, se necessário */
            }

            .sidebar {
                order: 2; /* Move a sidebar para baixo em telas menores */
            }

            h1 {
                font-size: 1.5rem; /* Ajusta o tamanho do título principal */
            }

            .page-header {
                margin-bottom: 1rem; /* Reduz o espaçamento inferior do cabeçalho */
            }

            .summary-cards-grid {
                grid-template-columns: 1fr; /* Coloca os cards em uma única coluna */
                gap: 1rem; /* Reduz o espaçamento entre os cards */
            }

            .summary-card-value {
                font-size: 1.5rem; /* Ajusta o tamanho da fonte do valor */
            }
        }

        /* Para telas muito pequenas (smartphones, até 480px) */
        @media (max-width: 480px) {
            .dashboard-grid {
                padding: 0.5rem;
                gap: 0.5rem;
            }

            .sidebar {
                padding: 0.75rem;
            }

            .admin-profile h3 {
                font-size: 1.2rem;
            }

            .admin-profile p {
                font-size: 0.8rem;
            }

            .summary-card {
                padding: 1rem;
            }

            .summary-card-title {
                font-size: 1rem;
            }

            .summary-card-value {
                font-size: 1.4rem;
            }

            .summary-card-details {
                font-size: 0.8rem;
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
                <li><a href="index.php?page=aparencia" class="<?= ($page === 'aparencia') ? 'active' : '' ?>"><i class="bi bi-palette-fill"></i> Aparência</a></li>

            </ul>
            <div class="logout-btn">
                <a href="../logout.php"><i class="bi bi-box-arrow-right"></i> Sair da Conta</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Boas-vindas, <?= htmlspecialchars($admin_user) ?>!</h1>
                <p class="text-secondary">Visão geral rápida das atividades do sistema.</p>
            </div>

            <div class="summary-cards-grid">
                <div class="summary-card users-card">
                    <div class="summary-card-title">
                        <i class="bi bi-people"></i>
                        <span>Total de Usuários</span>
                    </div>
                    <p class="summary-card-value"><?= number_format($total_users, 0, ',', '.') ?></p>
                    <p class="summary-card-details">Usuários cadastrados no sistema (todos os tempos).</p>
                    <a href="index.php?page=usuarios" class="summary-card-link">Ver Todos os Usuários <i class="bi bi-arrow-right-circle"></i></a>
                </div>

                <div class="summary-card users-card">
                    <div class="summary-card-title">
                        <i class="bi bi-person-plus"></i>
                        <span>Novos Usuários</span>
                    </div>
                    <p class="summary-card-value"><?= number_format($dashboard_new_users, 0, ',', '.') ?></p>
                    <p class="summary-card-details">Novos usuários cadastrados hoje.</p>
                    <a href="index.php?page=relatorios&new_users_period=today" class="summary-card-link">Ver Relatórios de Usuários <i class="bi bi-arrow-right-circle"></i></a>
                </div>

                <div class="summary-card finance-card">
                    <div class="summary-card-title">
                        <i class="bi bi-cash-stack"></i>
                        <span>Depósitos Aprovados</span>
                    </div>
                    <p class="summary-card-value money">R$ <?= number_format($dashboard_total_deposits, 2, ',', '.') ?></p>
                    <p class="summary-card-details"><?= number_format($dashboard_approved_deposits_count, 0, ',', '.') ?> depósitos aprovados hoje.</p>
                    <a href="index.php?page=relatorios&deposits_detail_page=1&all_deposits_detail_period=today&deposit_detail_status_filter=approved" class="summary-card-link">Ver Detalhes Financeiros <i class="bi bi-arrow-right-circle"></i></a>
                </div>

                <div class="summary-card finance-card">
                    <div class="summary-card-title">
                        <i class="bi bi-cash-coin"></i>
                        <span>Saques Aprovados</span>
                    </div>
                    <p class="summary-card-value negative">R$ <?= number_format($dashboard_total_withdrawals, 2, ',', '.') ?></p>
                    <p class="summary-card-details">Total de saques aprovados hoje.</p>
                    <a href="index.php?page=financeiro&approved_withdrawals_period=today" class="summary-card-link">Gerenciar Saques <i class="bi bi-arrow-right-circle"></i></a>
                </div>

                <div class="summary-card finance-card">
                    <div class="summary-card-title">
                        <i class="bi bi-hourglass-split"></i>
                        <span>Depósitos Pendentes</span>
                    </div>
                    <p class="summary-card-value" style="color: var(--accent-yellow);"><?= number_format($dashboard_pending_deposits_count, 0, ',', '.') ?></p>
                    <p class="summary-card-details">Transações de depósito aguardando aprovação hoje.</p>
                    <a href="index.php?page=relatorios&deposits_detail_page=1&all_deposits_detail_period=today&deposit_detail_status_filter=pending" class="summary-card-link">Revisar Depósitos <i class="bi bi-arrow-right-circle"></i></a>
                </div>

                <div class="summary-card users-card">
                    <div class="summary-card-title">
                        <i class="bi bi-person-lines-fill"></i>
                        <span>Total de Afiliados</span>
                    </div>
                    <p class="summary-card-value"><?= number_format($total_affiliates, 0, ',', '.') ?></p>
                    <p class="summary-card-details">Usuários que indicaram outros usuários para a plataforma.</p>
                    <a href="index.php?page=relatorios" class="summary-card-link">Ver Relatórios de Afiliados <i class="bi bi-arrow-right-circle"></i></a>
                </div>

                <div class="summary-card default-card">
                    <div class="summary-card-title">
                        <i class="bi bi-bar-chart-line"></i>
                        <span>Relatórios Detalhados</span>
                    </div>
                    <p class="summary-card-value" style="font-size: 1.8rem;">Análises Completas</p>
                    <p class="summary-card-details">Acesse métricas avançadas e visualize tendências do sistema.</p>
                    <a href="index.php?page=relatorios" class="summary-card-link">Ir para Relatórios <i class="bi bi-arrow-right-circle"></i></a>
                </div>
            </div>
        </main>
    </div>
</body>

</html>