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
    private function panels(): string {
        $project = $this->projects[$this->current_project];

        $panels = [
            [
                'title' => 'FileCache v'.Cache::VERSION,
                'data'  => [
                    'Path'  => is_dir((string) $project['path']) ? realpath((string) $project['path']) : $project['path'],
                    'Files' => count($this->all_keys),
                ],
            ],
        ];

        return $this->template->render('partials/info', ['panels' => $panels, 'left' => true]);
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
     * @return array<int, array<string, string|int>>
     */
    private function getAllKeys(): array {
        static $keys = [];
        $search = Http::get('s', '');

        $this->template->addGlobal('search_value', $search);

        foreach ($this->all_keys as $key) {
            if (count($this->all_keys) < 1000) {
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
        $keys = $this->getAllKeys();

        $paginator = new Paginator($this->template, $keys);

        return $this->template->render('@filecache/filecache', [
            'keys'        => $paginator->getPaginated(),
            'all_keys'    => count($this->all_keys),
            'new_key_url' => Http::queryString([], ['form' => 'new']),
            'paginator'   => $paginator->render(),
            'view_key'    => Http::queryString([], ['view' => 'key', 'key' => '__key__']),
        ]);
    }
}
