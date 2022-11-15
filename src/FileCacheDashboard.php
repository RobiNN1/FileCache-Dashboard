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
    final public const VERSION = '1.0.2';

    private int $current_project = 0;

    public function __construct(
        private readonly Template $template
    ) {
        $this->template->addPath('filecache', __DIR__.'/../templates');

        if (is_array(Config::get('filecache'))) { // fix for tests
            $project = Http::get('server', 'int');
            $this->current_project = array_key_exists($project, Config::get('filecache')) ? $project : 0;
        }
    }

    public static function check(): bool {
        return class_exists(Cache::class);
    }

    /**
     * Get dashboard info.
     *
     * @return array<string, string>
     */
    public function getDashboardInfo(): array {
        return [
            'key'   => 'file',
            'title' => 'FileCache',
            'icon'  => Helpers::svg(__DIR__.'/../assets/filecache.svg'),
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
        $projects = Config::get('filecache');

        try {
            $filecache = $this->connect($projects[$this->current_project]);

            if (isset($_GET['deleteall']) && $filecache->flush()) {
                $return = $this->template->render('components/alert', [
                    'message' => 'Cache has been cleaned.',
                ]);
            }

            if (isset($_GET['delete'])) {
                $return = $this->deleteKey($filecache);
            }
        } catch (DashboardException|CacheException $e) {
            $return = $e->getMessage();
        }

        return $return;
    }

    /**
     * Data for info panels.
     *
     * @return array<string, mixed>
     */
    public function info(): array {
        $info = [];

        foreach (Config::get('filecache') as $id => $project) {
            try {
                $files = count($this->connect($project)->keys());
            } catch (DashboardException|CacheException) {
                $files = 'An error occurred while retrieving files.';
            }

            $info['panels'][] = [
                'title'            => $project['name'] ?? 'Project '.$id,
                'server_selection' => true,
                'current_server'   => $this->current_project,
                'data'             => [
                    'Path'  => realpath($project['path']),
                    'Files' => $files,
                ],
            ];
        }

        return $info;
    }

    public function showPanels(): string {
        if (isset($_GET['moreinfo']) || isset($_GET['form']) || isset($_GET['view'], $_GET['key'])) {
            return '';
        }

        return $this->template->render('partials/info', [
            'title'             => 'FileCache',
            'extension_version' => Cache::VERSION,
            'info'              => $this->info(),
        ]);
    }

    public function dashboard(): string {
        $projects = Config::get('filecache');

        try {
            $filecache = $this->connect($projects[$this->current_project]);
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
