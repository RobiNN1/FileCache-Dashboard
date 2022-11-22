<?php
/**
 * This file is part of FileCache-Dashboard.
 *
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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

    /**
     * @const string FileCache dashbord version.
     */
    final public const VERSION = '1.1.0';

    /**
     * @var array<int, array<string, int|string>>
     */
    private array $projects;

    private int $current_project;

    public function __construct(private readonly Template $template) {
        $this->template->addPath('filecache', __DIR__.'/../templates');

        $this->projects = Config::get('filecache', []);

        $project = Http::get('server', 'int');
        $this->current_project = array_key_exists($project, $this->projects) ? $project : 0;
    }

    public static function check(): bool {
        return class_exists(Cache::class);
    }

    /**
     * @return array<string, string>
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
        $return = '';
        $projects = $this->projects;

        try {
            $filecache = $this->connect($projects[$this->current_project]);

            if (isset($_GET['deleteall']) && $filecache->flush()) {
                $return = $this->template->render('components/alert', [
                    'message' => 'Cache has been cleaned.',
                ]);
            }

            if (isset($_GET['delete'])) {
                $return = Helpers::deleteKey($this->template, static fn (string $key): bool => $filecache->delete($key));
            }
        } catch (DashboardException|CacheException $e) {
            $return = $e->getMessage();
        }

        return $return;
    }

    public function infoPanels(): string {
        // Hide panels on view-key page.
        if (isset($_GET['view'], $_GET['key'])) {
            return '';
        }

        return $this->template->render('partials/info', [
            'title'             => 'FileCache',
            'extension_version' => Cache::VERSION,
            'info'              => ['panels' => $this->panels()],
        ]);
    }

    public function dashboard(): string {
        if (count($this->projects) === 0) {
            return 'No projects';
        }

        try {
            $filecache = $this->connect($this->projects[$this->current_project]);
            if (isset($_GET['view'], $_GET['key'])) {
                $return = $this->viewKey($filecache);
            } else {
                $return = $this->mainDashboard($filecache);
            }
        } catch (DashboardException|CacheException $e) {
            return $e->getMessage();
        }

        return $return;
    }
}
