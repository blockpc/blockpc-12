<?php

declare(strict_types=1);

return [
    'logout' => 'Cerrar Sesión',
    'dashboard' => [
        'titles' => [
            'link' => 'Escritorio',
        ],
    ],
    'profile' => [
        'titles' => [
            'link' => 'Perfil Usuario',
        ],
        'form' => [
            'firstname' => 'Nombre',
            'lastname' => 'Apellido',
            'email' => 'Correo',
            'photo' => 'Imagen',
            'current_password' => 'Clave Actual',
            'password' => 'Nueva Clave',
            'password_confirmation' => 'Confirmar Clave',
        ],
        'buttons' => [
            'update' => 'Actualizar Perfil',
            'update-password' => 'Actualizar Clave',
            'delete' => 'Eliminar',
            'delete-account' => 'Eliminar Cuenta',
        ],
    ],
    'users' => [
        'titles' => [
            'table' => 'Tabla de Usuarios',
            'empty' => 'Sin usuarios encontrados',
            'create' => 'Crear Usuario',
            'edit' => 'Editar Usuario',
            'delete' => 'Eliminar Usuario',
            'restore' => 'Restaurar Usuario',
            'link' => 'Usuarios',
            'user' => 'Usuario',
            'profile' => 'Perfil Usuario',
            'add_role' => 'Agregar Cargo',
            'generate_password' => 'Generar Clave',
            'change_password' => 'Cambiar Clave',
            'active' => 'Activar Usuario',
        ],
        'table' => [
            'name' => 'Nombre',
            'email' => 'Correo',
            'firstname' => 'Nombre',
            'lastname' => 'Apellido',
            'role' => 'Cargo',
            'actions' => 'Acciones',
        ],
        'attributes' => [
            'form' => [
                'name' => 'Alias',
                'email' => 'Correo',
                'firstname' => 'Nombre',
                'lastname' => 'Apellido',
                'password' => 'Clave',
                'confirmed_password' => 'Confirmar Clave',
                'role_id' => 'Cargos',
                'photo' => 'Imagen',
            ],
        ],
    ],
    'roles' => [
        'titles' => [
            'table' => 'Tabla de Cargos',
            'empty' => 'Sin cargos encontrados',
            'create' => 'Crear Cargo',
            'edit' => 'Editar Cargo',
            'delete' => 'Eliminar Cargo',
            'restore' => 'Restaurar Cargo',
            'link' => 'Cargos',
        ],
        'table' => [
            'name' => 'Nombre',
            'description' => 'Descripción',
            'permissions' => 'Permisos',
            'actions' => 'Acciones',
        ],
        'attributes' => [
            'form' => [
                'name' => 'Nombre',
                'display_name' => 'Nombre a Mostrar',
                'description' => 'Descripción',
                'permissions' => 'Permisos',
            ],
        ],
    ],
    'permissions' => [
        'titles' => [
            'table' => 'Tabla de Permisos',
            'edit' => 'Editar Permiso',
            'link' => 'Permisos',
            'keys' => 'Grupo',
        ],
        'table' => [
            'name' => 'Nombre',
            'display_name' => 'Nombre a Mostrar',
            'description' => 'Descripción',
            'actions' => 'Acciones',
        ],
        'edit' => [
            'form' => [
                'display_name' => 'Nombre a Mostrar',
                'description' => 'Descripción',
            ],
        ],
        'keys' => [
            'sudo' => 'Super Usuario',
            'users' => 'Usuarios',
            'roles' => 'Cargos',
            'permissions' => 'Permisos',
            'jobs' => 'Control de Tareas',
            'settings' => 'Configuración',
        ],
    ],
    'register' => [
        'name' => 'Alias',
        'email' => 'Correo',
        'firstname' => 'Nombre',
        'lastname' => 'Apellido',
        'password' => 'Clave',
        'password_confirmation' => 'Clave',
        'password-confirmation' => 'Confirmar Clave',
    ],
];
