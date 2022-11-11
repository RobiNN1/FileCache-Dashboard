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

use JsonException;
use RobiNN\Cache\Storages\FileStorage;
use RobiNN\Pca\Format;
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
        try {
            $keys = json_decode(Http::post('delete'), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $keys = [];
        }

        if (is_array($keys) && count($keys)) {
            foreach ($keys as $key) {
                $filecache->delete($key);
            }
            $message = 'Keys has been deleted.';
        } elseif (is_string($keys) && $filecache->delete($keys)) {
            $message = sprintf('Key "%s" has been deleted.', $keys);
        } else {
            $message = 'No keys are selected.';
        }

        return $this->template->render('components/alert', ['message' => $message]);
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
                    'title' => ['title' => $key, 'link' => true,],
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
            'view_url'    => Http::queryString([], ['view' => 'key', 'key' => '']),
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
