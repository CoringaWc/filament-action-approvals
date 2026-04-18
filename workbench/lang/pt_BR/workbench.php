<?php

return [
    'resources' => [
        'purchase_orders' => [
            'model_label' => 'Pedido de Compra',
            'plural_model_label' => 'Pedidos de Compra',
            'navigation_label' => 'Pedidos de Compra',
            'navigation_group' => 'Compras',
            'fields' => [
                'requester' => 'Solicitante',
                'title' => 'Título',
                'description' => 'Descrição',
                'amount' => 'Valor',
            ],
            'columns' => [
                'title' => 'Título',
                'requester' => 'Solicitante',
                'amount' => 'Valor',
                'created_at' => 'Criado em',
            ],
        ],
        'expenses' => [
            'model_label' => 'Despesa',
            'plural_model_label' => 'Despesas',
            'navigation_label' => 'Despesas',
            'navigation_group' => 'Financeiro',
            'fields' => [
                'requester' => 'Solicitante',
                'title' => 'Título',
                'description' => 'Descrição',
                'category' => 'Categoria',
                'amount' => 'Valor',
            ],
            'columns' => [
                'title' => 'Título',
                'requester' => 'Solicitante',
                'category' => 'Categoria',
                'amount' => 'Valor',
                'status' => 'Status',
                'created_at' => 'Criada em',
            ],
            'categories' => [
                'travel' => 'Viagem',
                'supplies' => 'Suprimentos',
                'equipment' => 'Equipamentos',
                'training' => 'Treinamento',
            ],
            'statuses' => [
                'draft' => 'Rascunho',
                'approved' => 'Aprovada',
                'rejected' => 'Rejeitada',
            ],
        ],
        'invoices' => [
            'model_label' => 'Fatura',
            'plural_model_label' => 'Faturas',
            'navigation_label' => 'Faturas',
            'navigation_group' => 'Financeiro',
            'sections' => [
                'overview' => 'Visão Geral',
            ],
            'fields' => [
                'requester' => 'Solicitante',
                'number' => 'Número',
                'title' => 'Título',
                'description' => 'Descrição',
                'amount' => 'Valor',
                'status' => 'Status',
                'previous_status' => 'Status anterior',
                'sent_at' => 'Enviada em',
                'paid_at' => 'Paga em',
                'cancelled_at' => 'Cancelada em',
                'created_at' => 'Criada em',
            ],
            'columns' => [
                'number' => 'Número',
                'title' => 'Título',
                'requester' => 'Solicitante',
                'amount' => 'Valor',
                'status' => 'Status',
                'created_at' => 'Criada em',
            ],
            'states' => [
                'issuing' => 'Em emissão',
                'sent' => 'Enviada',
                'awaiting_payment' => 'Aguardando pagamento',
                'paid' => 'Paga',
                'cancelled' => 'Cancelada',
            ],
            'actions' => [
                'advance_to' => 'Avançar para :status',
                'advance_default' => 'Avançar status',
                'advance_modal_heading' => 'Avançar para :status?',
                'advance_modal_description' => 'Essa ação atualiza a máquina de estados da fatura e pode exigir aprovação.',
                'cancel' => 'Cancelar fatura',
                'cancel_modal_heading' => 'Cancelar esta fatura?',
                'cancel_modal_description' => 'Essa ação pode exigir aprovação e não deve ser usada em faturas já finalizadas.',
            ],
            'notifications' => [
                'transition_pending_title' => 'Transição enviada para aprovação',
                'transition_pending_body' => 'A mudança de status ficará pendente até a aprovação do fluxo configurado.',
                'transition_success_title' => 'Status atualizado',
                'transition_success_body' => 'A transição foi executada imediatamente.',
                'cancel_pending_title' => 'Cancelamento enviado para aprovação',
                'cancel_pending_body' => 'O cancelamento aguardará aprovação antes de ser executado.',
                'cancel_success_title' => 'Fatura cancelada',
                'cancel_success_body' => 'A fatura foi cancelada imediatamente.',
            ],
        ],
        'users' => [
            'model_label' => 'Usuário',
            'plural_model_label' => 'Usuários',
            'navigation_label' => 'Usuários',
            'navigation_group' => 'Controle de Acesso',
            'fields' => [
                'name' => 'Nome',
                'email' => 'E-mail',
                'password' => 'Senha',
                'roles' => 'Funções',
            ],
            'columns' => [
                'name' => 'Nome',
                'email' => 'E-mail',
                'roles' => 'Funções',
            ],
        ],
        'roles' => [
            'model_label' => 'Função',
            'plural_model_label' => 'Funções',
            'navigation_label' => 'Funções e Permissões',
            'navigation_group' => 'Controle de Acesso',
        ],
    ],
    'roles' => [
        'super_admin' => 'Super Administrador',
        'manager' => 'Gerente',
        'director' => 'Diretor',
        'requester' => 'Solicitante',
    ],
    'approval_actions' => [
        'purchase_orders' => [
            'submit' => 'Enviar pedido de compra',
            'cancel' => 'Cancelar pedido de compra',
        ],
        'expenses' => [
            'submit' => 'Enviar despesa',
            'reimburse' => 'Reembolsar despesa',
        ],
    ],
    'seeds' => [
        'users' => [
            'admin' => ['name' => 'Usuário Administrador'],
            'manager' => ['name' => 'Usuário Gerente'],
            'director' => ['name' => 'Usuário Diretor'],
            'requester' => ['name' => 'Usuário Solicitante'],
        ],
        'flows' => [
            'purchase_order' => [
                'name' => 'Aprovação de Pedido de Compra',
                'description' => 'Fluxo de duas etapas para pedidos de compra: gerente e depois diretor.',
                'manager_step' => 'Aprovação do Gerente',
                'director_step' => 'Aprovação do Diretor',
            ],
            'invoice_send' => [
                'name' => 'Aprovação para envio de fatura',
                'description' => 'Fluxo de uma etapa para aprovar o envio da fatura.',
                'manager_step' => 'Aprovação do Gerente',
            ],
            'expense_submit' => [
                'name' => 'Aprovação para envio de despesa',
                'description' => 'Fluxo de uma etapa para aprovar o envio de uma despesa para análise.',
                'manager_step' => 'Aprovação do Gerente',
            ],
            'expense_reimburse' => [
                'name' => 'Aprovação para reembolso de despesa',
                'description' => 'Fluxo de uma etapa para aprovar o reembolso de uma despesa.',
                'director_step' => 'Aprovação do Diretor',
            ],
        ],
        'invoices' => [
            'issuing' => [
                'title' => 'Fatura aguardando envio',
            ],
            'sent' => [
                'title' => 'Fatura enviada ao fornecedor',
            ],
            'awaiting_payment' => [
                'title' => 'Fatura aguardando pagamento',
            ],
        ],
    ],
];
