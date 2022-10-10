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

use RobiNN\Pca\Format;
use RobiNN\Pca\Http;
use RobiNN\Pca\Paginator;
use RobiNN\Pca\Value;

trait FileCacheTrait {
    /**
     * Delete key.
     *
     * @param FileCache $filecache
     *
     * @return string
     */
    private function deleteKey(FileCache $filecache): string {
        $keys = explode(',', Http::get('delete'));

        if (count($keys) === 1 && $filecache->delete($keys[0])) {
            $message = sprintf('Key "%s" has been deleted.', $keys[0]);
        } elseif (count($keys) > 1) {
            foreach ($keys as $key) {
                $filecache->delete($key);
            }
            $message = 'Keys has been deleted.';
        } else {
            $message = 'No keys are selected.';
        }

        return $this->template->render('components/alert', ['message' => $message]);
    }

    /**
     * Get all keys with data.
     *
     * @param FileCache $filecache
     *
     * @return array<int, array<string, string|int>>
     */
    private function getAllKeys(FileCache $filecache): array {
        $keys = [];

        $handle = opendir($filecache->getPath());

        if ($handle) {
            while (false !== ($file = readdir($handle))) {
                if ($file !== '.' && $file !== '..') {
                    $key = str_replace('.cache', '', $file);

                    $keys[] = [
                        'key' => $key,
                        'ttl' => $filecache->ttl($key) === 0 ? -1 : $filecache->ttl($key),
                    ];
                }
            }

            closedir($handle);
        }

        return array_values($keys);
    }

    /**
     * Main dashboard content.
     *
     * @param FileCache $filecache
     *
     * @return string
     */
    private function mainDashboard(FileCache $filecache): string {
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
     * View key value.
     *
     * @param FileCache $filecache
     *
     * @return string
     */
    private function viewKey(FileCache $filecache): string {
        $key = Http::get('key');

        if (!is_file($filecache->getPath().'/'.$key.'.cache')) {
            Http::redirect();
        }

        if (isset($_GET['delete'])) {
            $filecache->delete($key);
            Http::redirect();
        }

        $value = $filecache->getKey($key);

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
