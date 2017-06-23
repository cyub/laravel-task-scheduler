# Laravel 5 Lightweight Task Scheduler

An Lightweight Task Scheduler Package for Laravel 5. This package allows you to dispatch all scheduled jobs and run at the time.

## Installation

Require the package

```shell
composer require "cyub/laravel-task-scheduler"
```

After adding the package, add the ServiceProvider to the providers array in `config/app.php`

```php
'providers' => [
    ...
    Tink\Scheduler\SchedulerServiceProvider::class,
    ...
];

```

Then, publish the scheduler config and migration the database of scheduler

```php
php artisan scheduler:install
```


## Configuration

After Install the package, You will find the Configuration in `config\scheduler.php`

```php
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
```

### Usage

### Dispatch job and run

```shell
php artisan scheduler:dispatch
```

You can use in Cron
```shell
* * * * * php /your-application-path/artisan scheduler:dispatch >> /dev/null 2>&1
```

### View the scheduler config

```shell
php artisan scheduler:info config
```

![scheduler config](http://static.cyub.me/images/201706/scheduler-info-config.jpg)

### View the scheduler run result stats

```shell
php artisan scheduler:info stats
```

![scheduler config](http://static.cyub.me/images/201706/scheduler-info-stats.jpg)

### Clean the scheduler cache

```shell
php artisan scheduler:clean
```




