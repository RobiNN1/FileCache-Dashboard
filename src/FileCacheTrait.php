<?php
/**
 * This file is part of the FileCache-Dashboard.
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 */

declare(strict_types=1);

namespace RobiNN\FileCache;

use RobiNN\Cache\Cache;
use RobiNN\Pca\Format;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Paginator;
use RobiNN\Pca\Value;

trait FileCacheTrait {
    /**
     * @param array<int, string> $all_keys
     */
    private function panels(array $all_keys): string {
        $project = $this->projects[$this->current_project];

        $panels = [
            [
                'title' => 'FileCache <span>v'.Cache::VERSION.'</span>',
                'data'  => [
                    'Path'  => is_dir((string) $project['path']) ? realpath((string) $project['path']) : $project['path'],
                    'Files' => count($all_keys),
                ],
            ],
        ];

        return $this->template->render('partials/info', ['panels' => $panels, 'thead' => false]);
    }

    private function viewKey(): string {
        $key = Http::get('key', '');

        if (!$this->filecache->exists($key)) {
            Http::redirect();
        }

        if (isset($_GET['delete'])) {
            $this->filecache->delete($key);
            Http::redirect();
        }

        $value = Helpers::mixedToString($this->filecache->get($key));

        [$formatted_value, $encode_fn, $is_formatted] = Value::format($value);

        $ttl = $this->filecache->ttl($key) === 0 ? -1 : $this->filecache->ttl($key);

        return $this->template->render('partials/view_key', [
            'key'        => $key,
            'value'      => $formatted_value,
            'ttl'        => Format::seconds($ttl),
            'size'       => Format::bytes(strlen($value)),
            'encode_fn'  => $encode_fn,
            'formatted'  => $is_formatted,
            'delete_url' => Http::queryString(['view'], ['delete' => 'key', 'key' => $key]),
        ]);
    }

    /**
     * @param array<int, string> $all_keys
     *
     * @return array<int, array<string, string|int>>
     */
    private function getAllKeys(array $all_keys): array {
        static $keys = [];
        $search = Http::get('s', '');

        $this->template->addGlobal('search_value', $search);

        foreach ($all_keys as $key) {
            if (count($all_keys) < 1000) {
                $ttl = $this->filecache->ttl($key);
                $ttl = $ttl === 0 ? 'Doesn\'t expire' : $ttl;
            }

            if (stripos($key, $search) !== false) {
                $keys[] = [
                    'key'   => $key,
                    'items' => [
                        'link_title' => $key,
                        'ttl'        => $ttl ?? 'No info',
                    ],
                ];
            }
        }

        return $keys;
    }

    private function mainDashboard(): string {
        $all_keys = $this->filecache->keys();
        $keys = $this->getAllKeys($all_keys);

        $paginator = new Paginator($this->template, $keys);

        $projects = [];

        foreach ($this->projects as $id => $project) {
            if (!isset($project['name'])) {
                $projects[$id]['name'] = 'Project '.$id;
            }
        }

        return $this->template->render('@filecache/filecache', [
            'select'      => Helpers::serverSelector($this->template, $projects, $this->current_project),
            'panels'      => $this->panels($all_keys),
            'keys'        => $paginator->getPaginated(),
            'all_keys'    => count($all_keys),
            'new_key_url' => Http::queryString([], ['form' => 'new']),
            'paginator'   => $paginator->render(),
            'view_key'    => Http::queryString([], ['view' => 'key', 'key' => '__key__']),
        ]);
    }
}
