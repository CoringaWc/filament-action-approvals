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
        ],
    ],
];
