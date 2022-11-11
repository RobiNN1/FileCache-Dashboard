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

use RobiNN\Cache\Storages\FileStorage;
use RobiNN\Pca\Format;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Paginator;
use RobiNN\Pca\Value;

trait FileCacheTrait {
    /**
     * Delete key.
     *
     * @param FileStorage $filecache
     *
     * @return string
     */
    private function deleteKey(FileStorage $filecache): string {
        return Helpers::deleteKey($this->template, static function (string $key) use ($filecache): bool {
            return $filecache->delete($key);
        });
    }

    /**
     * Get all keys with data.
     *
     * @param FileStorage $filecache
     *
     * @return array<int, array<string, string|int>>
     */
    private function getAllKeys(FileStorage $filecache): array {
        static $keys = [];

        foreach ($filecache->keys() as $key) {
            $ttl = $filecache->ttl($key);

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

    /**
     * Main dashboard content.
     *
     * @param FileStorage $filecache
     *
     * @return string
     */
    private function mainDashboard(FileStorage $filecache): string {
        $keys = $this->getAllKeys($filecache);

        $paginator = new Paginator($this->template, $keys);

        return $this->template->render('@filecache/filecache', [
            'keys'        => $paginator->getPaginated(),
            'all_keys'    => count($keys),
            'new_key_url' => Http::queryString([], ['form' => 'new']),
            'paginator'   => $paginator->render(),
        ]);
    }

    /**
     * Get key and convert any value to a string.
     *
     * @param FileStorage $filecache
     * @param string      $key
     *
     * @return string
     */
    private function getKey(FileStorage $filecache, string $key): string {
        $data = $filecache->get($key);

        if (is_array($data) || is_object($data)) {
            $data = serialize($data);
        }

        return (string) $data;
    }

    /**
     * View key value.
     *
     * @param FileStorage $filecache
     *
     * @return string
     */
    private function viewKey(FileStorage $filecache): string {
        $key = Http::get('key');

        if (!$filecache->exists($key)) {
            Http::redirect();
        }

        if (isset($_GET['delete'])) {
            $filecache->delete($key);
            Http::redirect();
        }

        $value = $this->getKey($filecache, $key);

        [$value, $encode_fn, $is_formatted] = Value::format($value);

        $ttl = $filecache->ttl($key) === 0 ? -1 : $filecache->ttl($key);

        return $this->template->render('partials/view_key', [
            'key'        => $key,
            'value'      => $value,
            'ttl'        => Format::seconds($ttl),
            'size'       => Format::bytes(strlen($value)),
            'encode_fn'  => $encode_fn,
            'formatted'  => $is_formatted,
            'delete_url' => Http::queryString(['view'], ['delete' => 'key', 'key' => $key]),
        ]);
    }
}
