# Laravel 5 Lightweight Task Scheduler

An Lightweight Task Scheduler Package for Laravel 5. This package allows you to dispatch all scheduled jobs and run at the time.

## Installation

Require the package

```php
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

### Usage

### Dispath the Jobs and run it

```php
php artisan scheduler:dispatch
```

You can use in Cron
```php
* * * * * php artisan scheduler:dispatch >> /dev/null 2>&1
```

### View the scheduler config

```php
php artisan scheduler:info config
```

### View the scheduler run result stats

```php
php artisan scheduler:info stats
```

### Clean the scheduler cache

```php
php artisan scheduler:clean
```




