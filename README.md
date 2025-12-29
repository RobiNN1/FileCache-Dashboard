# FileCache-Dashboard

FileCache ([`robinn/cache`](https://github.com/RobiNN1/Cache)) dashboard
for [phpCacheAdmin](https://github.com/RobiNN1/phpCacheAdmin).

<p align="center"><img alt="FileCache" src=".github/img/preview.png" width="500px"></p>

![Visitor Badge](https://visitor-badge.laobi.icu/badge?page_id=RobiNN1.FileCache-Dashboard)

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
the project or have access to folders outside the website root.

## Requirements

- PHP >= 8.2
- phpCacheAdmin >= 2.0.0
