<?php
/**
 * This file is part of the FileCache-Dashboard.
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 */

declare(strict_types=1);

namespace RobiNN\FileCache;

use RobiNN\Cache\Cache;
use RobiNN\Pca\Csrf;
use RobiNN\Pca\Format;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Paginator;
use RobiNN\Pca\Value;

trait FileCacheTrait {
    /**
     * @param array<string, string> $all_keys
     */
    private function panels(array $all_keys): string {
        $panels = [
            [
                'title' => 'FileCache v'.Cache::VERSION,
                'data'  => [
                    'Path'  => $this->filecache->getPath(),
                    'Files' => count($all_keys),
                ],
            ],
        ];

        return Helpers::panels($panels);
    }

    /**
     * @param array<string, string> $all_keys
     */
    private function viewKey(array $all_keys): string {
        $name = Http::get('key', '');

        if (!$this->filecache->exists($name)) {
            Http::redirect();
        }

        if (isset($_POST['delete'])) {
            if (!Csrf::validateToken(Http::post('csrf_token', ''))) {
                Helpers::alert('Invalid CSRF token.', 'error');
            } else {
                $this->filecache->delete($name);
                Http::redirect();
            }
        }

        $value = Helpers::mixedToString($this->filecache->get($name));

        [$formatted_value, $encode_fn, $is_formatted] = Value::format($value);

        $ttl = $this->filecache->ttl($name);
        $ttl = $ttl === 0 ? -1 : $ttl;

        return $this->template->render('partials/view_key', [
            'key'       => $all_keys[$name] ?? $name,
            'value'     => $formatted_value,
            'ttl'       => Format::seconds($ttl),
            'size'      => Format::bytes(strlen($value)),
            'encode_fn' => $encode_fn,
            'formatted' => $is_formatted,
        ]);
    }

    /**
     * @param array<string, string> $all_keys Map of [file_name => original_key], actions use
     *                                        file names so that they work even without a secret.
     *
     * @return array<int, array<string, array<string, int|string>|string>>
     */
    private function getAllKeys(array $all_keys): array {
        $keys = [];
        $search = Http::get('s', '');

        $this->template->addGlobal('search_value', $search);

        // Getting a TTL requires reading the whole cache file, skip it for large caches.
        $show_ttl = count($all_keys) < 1000;

        foreach ($all_keys as $name => $key) {
            if (stripos($key, $search) === false) {
                continue;
            }

            $ttl = 'No info';

            if ($show_ttl) {
                $ttl = $this->filecache->ttl($name);
                $ttl = $ttl === 0 ? 'Doesn\'t expire' : $ttl;
            }

            $keys[] = [
                'key'  => $name,
                'info' => [
                    'link_title' => $key,
                    'ttl'        => $ttl,
                ],
            ];
        }

        return $keys;
    }

    /**
     * @param array<string, string> $all_keys
     */
    private function mainDashboard(array $all_keys): string {
        $keys = $this->getAllKeys($all_keys);

        $paginator = new Paginator($keys);

        return $this->template->render('@filecache/filecache', [
            'keys'      => $paginator->getPaginated(),
            'all_keys'  => count($all_keys),
            'paginator' => $paginator->render(),
            'view_key'  => Http::queryString([], ['view' => 'key', 'key' => '__key__']),
        ]);
    }
}
