# FileCache-Dashboard

FileCache dashboard (from [`robinn/cache`](https://github.com/RobiNN1/Cache))
for [phpCacheAdmin](https://github.com/RobiNN1/phpCacheAdmin).

![Visitor Badge](https://visitor-badge.laobi.icu/badge?page_id=RobiNN1.FileCache-Dashboard)

![FileCache](.github/img/filecache.png)

## Installation

```
composer require robinn/filecache-dashboard
```

In phpCacheAdmin's `config.php` add class to the `dashboards` list and add `filecache` config

```php
'dashboards' => [
    ...
    RobiNN\FileCache\FileCacheDashboard::class,
],
'filecache'  => [
    [
        'name' => 'Project Name', // Optional
        'path' => __DIR__.'/path/to/cache/data',
    ],
],
```

For this to work, phpCacheAdmin should be in the same directory as
the project or have access to folders outside of website root.

## Requirements

- PHP >= 8.1
- phpCacheAdmin >= 1.3.2

## Testing

PHPUnit

```
composer test
```

PHPStan

```
composer phpstan
```
