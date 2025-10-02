<?php

return [
    'public_path' => APP_PUBLIC_PATH,
    'public_dir' => APP_PUBLIC_DIR,
    'overwrite_on_upload' => false,
    'timezone' => 'UTC', // https://www.php.net/manual/en/timezones.php
    'download_inline' => ['pdf'], // download inline in the browser, array of extensions, use * for all
    'lockout_attempts' => 5, // max failed login attempts before ip lockout
    'lockout_timeout' => 15, // ip lockout timeout in seconds

    'frontend_config' => [
        'app_name' => 'FileGator',
        'app_version' => APP_VERSION,
        'language' => 'english',
        'logo' => 'https://filegator.io/filegator_logo.svg',
        'upload_max_size' => 100 * 1024 * 1024, // 100MB
        'upload_chunk_size' => 1 * 1024 * 1024, // 1MB
        'upload_simultaneous' => 3,
        'default_archive_name' => 'archive.zip',
        'editable' => ['.txt', '.css', '.js', '.ts', '.html', '.php', '.json', '.md'],
        'date_format' => 'YY/MM/DD hh:mm:ss', // see: https://momentjs.com/docs/#/displaying/format/
        'guest_redirection' => '', // useful for external auth adapters
        'search_simultaneous' => 5,
        'filter_entries' => [],
        'pagination' =>[100, 200, 500, 1000, ''],
    ],

    'services' => [
        'Filegator\Services\Logger\LoggerInterface' => [
            'handler' => '\Filegator\Services\Logger\Adapters\MonoLogger',
            'config' => [
                'monolog_handlers' => [
                    function () {
                        return new \Monolog\Handler\StreamHandler(
                            __DIR__.'/private/logs/app.log',
                            \Monolog\Logger::DEBUG
                        );
                    },
                ],
            ],
        ],
        'Filegator\Services\Session\SessionStorageInterface' => [
            'handler' => '\Filegator\Services\Session\Adapters\SessionStorage',
            'config' => [
                'handler' => function () {
                    $save_path = null; // use default system path
                    //$save_path = __DIR__.'/private/sessions';
                    $handler = new \Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler($save_path);

                    return new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage([
                            "cookie_samesite" => "Lax",
                            "cookie_secure" => null,
                            "cookie_httponly" => true,
                        ], $handler);
                },
            ],
        ],
        'Filegator\Services\Cors\Cors' => [
            'handler' => '\Filegator\Services\Cors\Cors',
            'config' => [
                'enabled' => APP_ENV == 'production' ? false : true,
            ],
        ],
        'Filegator\Services\Tmpfs\TmpfsInterface' => [
            'handler' => '\Filegator\Services\Tmpfs\Adapters\Tmpfs',
            'config' => [
                'path' => __DIR__.'/private/tmp/',
                'gc_probability_perc' => 10,
                'gc_older_than' => 60 * 60 * 24 * 2, // 2 days
            ],
        ],
        'Filegator\Services\Security\Security' => [
            'handler' => '\Filegator\Services\Security\Security',
            'config' => [
                'csrf_protection' => true,
                'csrf_key' => "123456", // randomize this
                'ip_allowlist' => [],
                'ip_denylist' => [],
                'allow_insecure_overlays' => false,
            ],
        ],
        'Filegator\Services\View\ViewInterface' => [
            'handler' => '\Filegator\Services\View\Adapters\Vuejs',
            'config' => [
                // 'add_to_head' => 'eeeee',
                'add_to_head' => '',
                'add_to_body' => '',
            ],
        ],
        // 'Filegator\Services\Storage\Filesystem' => [
        //     'handler' => '\Filegator\Services\Storage\Filesystem',
        //     'config' => [
        //         'separator' => '/',
        //         'config' => [],
        //         'adapter' => function () {
        //             return new \League\Flysystem\Adapter\Local(
        //                 __DIR__.'/repository'
        //             );
        //         },
        //     ],
        // ],

        'Filegator\Services\Storage\Filesystem' => [
            'handler' => '\Filegator\Services\Storage\Filesystem',
            'config' => [
                'separator' => '/',
                'config' => [],
                'adapter' => function () {
                    $client = new \Aws\S3\S3Client([
                        'endpoint' => 'http://seaweed:8333',
                        'credentials' => [
                            'key' => 'seaweed',
                            'secret' => 'LY3NDXX6VDAEE82A57FK99QXHDHQREPS',
                        ],
                        'region' => 'local',
                        'version' => 'latest',
                        'use_path_style_endpoint' => true,

                    ]);

                    return new \League\Flysystem\AwsS3v3\AwsS3Adapter($client, 'bucket-1');
                },
            ],
        ],

        'Filegator\Services\Archiver\ArchiverInterface' => [
            'handler' => '\Filegator\Services\Archiver\Adapters\ZipArchiver',
            'config' => [],
        ],
        // 'Filegator\Services\Auth\AuthInterface' => [
        //     'handler' => '\Filegator\Services\Auth\Adapters\JsonFile',
        //     'config' => [
        //         'file' => __DIR__.'/private/users.json',
        //     ],
        // ],
        'Filegator\Services\Auth\AuthInterface' => [
            'handler' => '\Filegator\Services\Auth\Adapters\LDAP',
            'config' => [
                    'private_repos' => false,
                    'ldap_server'=>'ldap://ldap.example.org',
                    'ldap_bindDN'=>'cn=admin,dc=example,dc=org',
                    'ldap_bindPass'=>'admin',
                    'ldap_baseDN'=>'dc=example,dc=org',
                    'ldap_filter'=>'(uid=*)', //ex: 'ldap_filter'=>'(&(uid=*)(memberOf=cn=administrators,cn=groups,dc=ldap,dc=example,dc=com))',
                    'ldap_attributes' => ["uid","cn","dn"],
                    'login_group' => ['seaweed'],
                    'ldap_userFieldMapping'=> [
                        'username' =>'uid',
                        'username_AddDomain' =>'@example.org',
                        'username_RemoveDomains' =>['@department1.example.org', '@department2.example.org'],
                        'name' =>'cn',
                        'userDN' =>'dn',
                        'default_permissions' => 'read|write|upload|download|batchdownload|zip',
                        'admin_usernames' =>['user1', 'user2'],
                    ],
            ],
        ],
        'Filegator\Services\Router\Router' => [
            'handler' => '\Filegator\Services\Router\Router',
            'config' => [
                'query_param' => 'r',
                'routes_file' => __DIR__.'/backend/Controllers/routes.php',
            ],
        ],
    ],
];
