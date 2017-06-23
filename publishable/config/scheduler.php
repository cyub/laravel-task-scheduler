<?php

return [
    'enable' => true,
    'schedule_table_name' => 'cron_schedule',
    'schedule_generate_every' => 1,
    'schedule_ahead_for' => 50,
    'schedule_lifetime' => 15,
    'history_cleanup_every' => 10,
    'history_success_lifetime' => 600,
    'history_failure_lifetime' => 600,

    'schedules' => [
        'RegisterRedpacket.activate' => [
            'schedule'  => [
                'cron_expr'   => '*/1 * * * *',
            ],
            'run'   => [
                'class' => App\Cron\RegisterRedpacket::class,
                'function'  => 'activate',
                'params'    => ['isSendSmsNotice' => true]
            ],
            'description' => 'activate register redpacket'
        ]
    ]
];