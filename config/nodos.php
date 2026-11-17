<?php
declare(strict_types=1);

return [
    'n1' => ['host' => '127.0.0.1', 'port' => 3307, 'user' => 'academia_app', 'password' => getenv('ACADEMIA_DB_PASSWORD_n1') ?: ''],
    'n2' => ['host' => '127.0.0.1', 'port' => 3308, 'user' => 'academia_app', 'password' => getenv('ACADEMIA_DB_PASSWORD_n2') ?: ''],
];