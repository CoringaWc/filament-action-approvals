<?php

return [

    // Geral
    'approval' => 'Aprovação',
    'approvals' => 'Aprovações',

    // Navegação
    'navigation_group' => 'Aprovações',
    'flow_resource_label' => 'Fluxo de Aprovação',
    'flow_resource_plural' => 'Fluxos de Aprovação',

    // Status
    'status' => [
        'pending' => 'Pendente',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado',
    ],

    // Tipos de etapa
    'step_type' => [
        'single' => 'Aprovador Único',
        'sequential' => 'Sequencial',
        'parallel' => 'Paralelo',
    ],

    // Tipos de ação
    'action_type' => [
        'submitted' => 'Submetido',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'commented' => 'Comentado',
        'delegated' => 'Delegado',
        'escalated' => 'Escalado',
        'returned' => 'Devolvido',
    ],

    // Status da instância de etapa
    'step_status' => [
        'pending' => 'Pendente',
        'waiting' => 'Aguardando',
        'approved' => 'Aprovado',
        'rejected' => 'Rejeitado',
        'skipped' => 'Ignorado',
    ],

    // Ações de escalação
    'escalation' => [
        'notify' => 'Enviar Lembrete',
        'auto_approve' => 'Aprovar Automaticamente',
        'reassign' => 'Reatribuir',
        'reject' => 'Rejeitar Automaticamente',
    ],

    // Labels dos resolvers
    'resolvers' => [
        'user' => 'Usuários Específicos',
        'role' => 'Usuários por Função',
        'callback' => 'Callback Personalizado',
    ],

    // Formulário do flow
    'flow' => [
        'flow_details' => 'Detalhes do Fluxo',
        'name' => 'Nome',
        'description' => 'Descrição',
        'applies_to' => 'Aplica-se a',
        'any_model' => 'Qualquer Modelo',
        'applies_to_helper' => 'Deixe em branco para aplicar a qualquer modelo',
        'is_active' => 'Ativo',
        'approval_steps' => 'Etapas de Aprovação',
        'step_name' => 'Nome da Etapa',
        'type' => 'Tipo',
        'approver_type' => 'Tipo de Aprovador',
        'required_approvals' => 'Aprovações Necessárias',
        'required_approvals_hint' => 'Requer :required de :total aprovadores selecionados',
        'required_approvals_helper' => 'Quantos aprovadores devem aprovar para esta etapa passar',
        'sla_hours' => 'Prazo para resposta (horas)',
        'sla_helper' => 'Deixe em branco para não definir prazo automático',
        'escalation_action' => 'O que fazer quando o prazo vencer',
        'add_step' => 'Adicionar Etapa',
        'action_key' => 'Ação',
        'any_action' => 'Qualquer ação',
        'action_key_helper' => 'Opcional. Se preenchido, o fluxo valerá apenas para esta ação do modelo. Se ficar vazio, o fluxo poderá ser usado em qualquer ação desse modelo.',
        'select_model_first' => 'Selecione um modelo para listar as ações disponíveis.',
    ],

    // Tabela do flow
    'flow_table' => [
        'name' => 'Nome',
        'model' => 'Modelo',
        'any' => 'Qualquer',
        'steps' => 'Etapas',
        'is_active' => 'Ativo',
        'created_at' => 'Criado em',
        'action_key' => 'Ação',
    ],

    // Labels de campos comuns
    'fields' => [
        'status' => 'Status',
        'type' => 'Tipo',
        'comment' => 'Comentário',
        'submitted_at' => 'Submetido em',
        'completed_at' => 'Concluído em',
    ],

    // Ações
    'actions' => [
        'submit' => 'Submeter para Aprovação',
        'approve' => 'Aprovar',
        'reject' => 'Rejeitar',
        'comment' => 'Comentar',
        'delegate' => 'Delegar',

        'approval_flow' => 'Fluxo de Aprovação',
        'approval_action' => 'Ação a aprovar',
        'approval_action_helper' => 'Informe qual cenário está sendo submetido. O sistema tentará usar um fluxo específico para essa ação e, se não existir, usará o fluxo genérico do modelo.',
        'comment_optional' => 'Comentário (opcional)',
        'rejection_reason' => 'Motivo da rejeição',
        'delegate_to' => 'Delegar para',
        'reason' => 'Motivo',

        'approve_heading' => 'Aprovar este registro?',
        'reject_heading' => 'Rejeitar este registro?',

        // Mensagens de sucesso
        'submitted_success' => 'Submetido para aprovação',
        'approved_success' => 'Aprovado',
        'rejected_success' => 'Rejeitado',
        'comment_success' => 'Comentário adicionado',
        'delegated_success' => 'Delegado com sucesso',
    ],

    // Notificações
    'notifications' => [
        'requested_title' => 'Aprovação Solicitada: :step',
        'requested_body' => ':model #:id requer sua aprovação.',
        'approved_title' => 'Aprovação Concluída',
        'approved_body' => ':model #:id foi aprovado.',
        'rejected_title' => 'Aprovação Rejeitada',
        'rejected_body' => ':model #:id foi rejeitado.',
        'escalated_title' => 'Prazo de aprovação vencido',
        'escalated_body' => ':model #:id ultrapassou o prazo definido.',
        'sla_warning_title' => 'Prazo de aprovação próximo do vencimento',
        'sla_warning_body' => ':model #:id precisa ser aprovado até :deadline.',
    ],

    // Widgets
    'widgets' => [
        'pending_heading' => 'Minhas Aprovações Pendentes',
        'step' => 'Etapa',
        'record' => 'Registro',
        'since' => 'Desde',
        'due' => 'Prazo',
        'no_sla' => 'Sem prazo definido',
        'pending_approvals' => 'Aprovações Pendentes',
        'approved_30d' => 'Aprovadas (30d)',
        'rejected_30d' => 'Rejeitadas (30d)',
        'overdue_steps' => 'Etapas com prazo vencido',
    ],

    // Relation manager
    'relation_manager' => [
        'title' => 'Aprovações',
        'flow' => 'Fluxo',
        'submitted_by' => 'Submetido por',
        'in_progress' => 'Em Andamento',
        'approval_details' => 'Detalhes da Aprovação',
        'steps' => 'Etapas',
        'audit_trail' => 'Histórico de Auditoria',
        'approvers' => 'Aprovadores',
        'received_required' => 'Recebidas / Necessárias',
        'by' => 'Por',
        'system' => 'Sistema',
        'date' => 'Data',
        'close' => 'Fechar',
        'approval_heading' => 'Aprovação: :flow',
        'not_available' => 'N/D',
    ],

    // Seção infolist
    'infolist' => [
        'approval_status' => 'Status da Aprovação',
        'status' => 'Status',
        'flow' => 'Fluxo',
        'submitted_by' => 'Submetido por',
        'submitted' => 'Submetido',
        'completed' => 'Concluído',
        'not_submitted' => 'Não Submetido',
        'in_progress' => 'Em Andamento',
        'current_step' => 'Etapa Atual',
        'step' => 'Etapa',
        'pending_approvers' => 'Aprovadores Pendentes',
        'progress' => 'Progresso',
        'approvals_count' => ':received / :required aprovações',
        'sla_deadline' => 'Prazo limite',
        'no_sla' => 'Sem prazo definido',
        'recent_activity' => 'Atividade Recente',
        'by' => 'Por',
        'system' => 'Sistema',
        'date' => 'Data',
        'no_approval' => 'Sem Aprovação',
        'not_available' => 'N/D',
    ],

    // Coluna de status
    'column' => [
        'label' => 'Aprovação',
        'no_approval' => 'Sem Aprovação',
    ],

    // Config do resolver
    'resolver_config' => [
        'users' => 'Usuários',
        'role' => 'Função',
        'resolver' => 'Resolver',
    ],

    'flow_hints' => [
        'name' => 'Nome interno do fluxo. Use um título claro para identificar este processo na listagem e no histórico.',
        'description' => 'Descrição opcional para contextualizar quando este fluxo deve ser usado.',
        'applies_to' => 'Define para qual tipo de registro este fluxo ficará disponível. Em branco, ele poderá ser usado em qualquer modelo compatível.',
        'action_key' => 'Use este campo para limitar o fluxo a uma ação específica do modelo, quando houver mais de um cenário de aprovação.',
        'is_active' => 'Desative para impedir novas submissões sem perder a configuração já cadastrada.',
        'steps' => 'Cadastre as etapas na ordem em que a aprovação deve acontecer. Cada item define quem aprova, em qual formato e com qual prazo.',
        'step_name' => 'Nome exibido para os usuários e no histórico da aprovação desta etapa.',
        'type' => 'Escolhe se a etapa terá um único aprovador, uma sequência de aprovação ou aprovações paralelas.',
        'approver_type' => 'Define a regra usada para descobrir os aprovadores desta etapa.',
        'required_approvals' => 'Em etapas paralelas, informa quantas aprovações são necessárias para concluir a etapa.',
        'sla_hours' => 'Prazo máximo, em horas, para a etapa receber resposta. Deixe vazio para não aplicar SLA.',
        'escalation_action' => 'Ação automática executada quando o prazo desta etapa vencer.',
        'resolver_users' => 'Selecione os usuários específicos que poderão aprovar esta etapa.',
        'resolver_role' => 'Todos os usuários com esta função serão considerados aprovadores desta etapa.',
        'resolver_callback' => 'Escolha o callback registrado que calcula os aprovadores dinamicamente em tempo de execução.',
    ],

    'select' => [
        'search_prompt' => 'Digite para pesquisar',
        'no_options' => 'Nenhuma opção disponível',
        'no_search_results' => 'Nenhum resultado encontrado',
        'loading' => 'Carregando opções...',
    ],

    // Comando SLA
    'sla' => [
        'auto_approved' => 'Aprovado automaticamente porque o prazo venceu',
        'auto_rejected' => 'Rejeitado automaticamente porque o prazo venceu',
    ],

];
