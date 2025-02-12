<?php
/**
 * This file is part of the FileCache-Dashboard.
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 */

declare(strict_types=1);

namespace RobiNN\FileCache;

use RobiNN\Cache\Cache;
use RobiNN\Cache\CacheException;
use RobiNN\Cache\Storages\FileStorage;
use RobiNN\Pca\Config;
use RobiNN\Pca\Dashboards\DashboardException;
use RobiNN\Pca\Dashboards\DashboardInterface;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Template;

class FileCacheDashboard implements DashboardInterface {
    use FileCacheTrait;

    final public const VERSION = '2.0.0';

    /**
     * @var array<int, array<string, int|string>>
     */
    private array $projects;

    private int $current_project;

    private FileStorage $filecache;

    /**
     * @var array<int, string>
     */
    private array $all_keys = [];

    public function __construct(private readonly Template $template) {
        $this->template->addPath('filecache', __DIR__.'/../templates');

        $this->projects = Config::get('filecache', []);

        $project = Http::get('server', 0);
        $this->current_project = array_key_exists($project, $this->projects) ? $project : 0;
    }

    public static function check(): bool {
        return class_exists(Cache::class);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function dashboardInfo(): array {
        return [
            'key'   => 'file',
            'title' => 'FileCache',
            'icon'  => __DIR__.'/../assets/filecache.svg',
        ];
    }

    /**
     * Get project cache data.
     *
     * @param array<string, int|string> $project
     *
     * @throws DashboardException|CacheException
     */
    public function connect(array $project): FileStorage {
        $filecache = new FileStorage($project);

        if (!$filecache->isConnected()) {
            throw new DashboardException(sprintf('Directory "%s" does not exists.', $project['path']));
        }

        return $filecache;
    }

    public function ajax(): string {
        $projects = $this->projects;

        try {
            $this->filecache = $this->connect($projects[$this->current_project]);

            if (isset($_GET['deleteall']) && $this->filecache->flush()) {
                return Helpers::alert($this->template, 'Cache has been cleaned.', 'success');
            }

            if (isset($_GET['delete'])) {
                return Helpers::deleteKey($this->template, fn (string $key): bool => $this->filecache->delete($key));
            }
        } catch (DashboardException|CacheException $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function dashboard(): string {
        if ($this->projects === []) {
            return 'No projects';
        }

        $projects = [];

        foreach ($this->projects as $id => $project) {
            if (!isset($project['name'])) {
                $projects[$id]['name'] = 'Project '.$id;
            }
        }

        $this->template->addGlobal('servers', Helpers::serverSelector($this->template, $projects, $this->current_project));

        try {
            $this->filecache = $this->connect($this->projects[$this->current_project]);
            $this->all_keys = $this->filecache->keys();

            $this->template->addGlobal('side', $this->panels());

            if (isset($_GET['view'], $_GET['key'])) {
                return $this->viewKey();
            }

            return $this->mainDashboard();
        } catch (DashboardException|CacheException $e) {
            return $e->getMessage();
        }
    }
}
