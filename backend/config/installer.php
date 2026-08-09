<?php

return [
    'app_name' => 'Qixer',
    'super_admin_role_id' => 1,
    'admin_model' => \App\Admin::class,
    'admin_table' => 'admins',
    'multi_tenant' => false,
    'author' => 'byteseed',
    'product_key' => '96944526b73bc278cbf76856ebf1cd01e0365214',
    'php_version' => '8.1',
    'extensions' => ['BCMath', 'Ctype', 'JSON', 'Mbstring', 'OpenSSL', 'PDO', 'pdo_mysql', 'Tokenizer', 'XML', 'cURL', 'fileinfo'],
    'website' => 'https://bytesed.com',
    'email' => 'support@bytesed.com',
    'env_example_path' => public_path('env-sample.txt'),
    'broadcast_driver' => 'log',
    'cache_driver' => 'file',
    'queue_connection' => 'sync',
    'mail_port' => '587',
    'mail_encryption' => 'tls',
    'model_has_roles' => true,
    'bundle_pack' => true,
    'bundle_pack_key' => '73bcc5edc65c962024c5d20fc2c010cab344e8c4',
];