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

use RobiNN\Cache\CacheException;
use RobiNN\Pca\Dashboards\DashboardException;
use RobiNN\Pca\Format;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Paginator;
use RobiNN\Pca\Value;

trait FileCacheTrait {
    /**
     * @return array<int, mixed>
     */
    private function panels(): array {
        $panels = [];

        foreach ($this->projects as $id => $project) {
            try {
                $files = count($this->connect($project)->keys());
            } catch (DashboardException|CacheException) {
                $files = 'An error occurred while retrieving files.';
            }

            $panels[] = [
                'title'            => $project['name'] ?? 'Project '.$id,
                'server_selection' => true,
                'current_server'   => $this->current_project,
                'data'             => [
                    'Path'  => realpath($project['path']),
                    'Files' => $files,
                ],
            ];
        }

        return $panels;
    }

    private function viewKey(): string {
        $key = Http::get('key');

        if (!$this->filecache->exists($key)) {
            Http::redirect();
        }

        if (isset($_GET['delete'])) {
            $this->filecache->delete($key);
            Http::redirect();
        }

        $value = Helpers::mixedToString($this->filecache->get($key));

        [$value, $encode_fn, $is_formatted] = Value::format($value);

        $ttl = $this->filecache->ttl($key) === 0 ? -1 : $this->filecache->ttl($key);

        return $this->template->render('partials/view_key', [
            'key'        => $key,
            'value'      => $value,
            'ttl'        => Format::seconds($ttl),
            'size'       => Format::bytes(strlen((string) $value)),
            'encode_fn'  => $encode_fn,
            'formatted'  => $is_formatted,
            'delete_url' => Http::queryString(['view'], ['delete' => 'key', 'key' => $key]),
        ]);
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function getAllKeys(): array {
        static $keys = [];

        foreach ($this->filecache->keys() as $key) {
            $ttl = $this->filecache->ttl($key);

            $keys[] = [
                'key'   => $key,
                'items' => [
                    'title' => [
                        'title' => $key,
                        'link'  => Http::queryString([], ['view' => 'key', 'key' => $key]),
                    ],
                    'ttl'   => $ttl === 0 ? 'Doesn\'t expire' : $ttl,
                ],
            ];
        }

        return $keys;
    }

    private function mainDashboard(): string {
        $keys = $this->getAllKeys();

        $paginator = new Paginator($this->template, $keys);

        return $this->template->render('@filecache/filecache', [
            'keys'        => $paginator->getPaginated(),
            'all_keys'    => count($keys),
            'new_key_url' => Http::queryString([], ['form' => 'new']),
            'paginator'   => $paginator->render(),
        ]);
    }
}
