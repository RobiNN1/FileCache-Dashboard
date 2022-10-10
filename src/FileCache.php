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

/**
 * This is a modified FileStorage class.
 *
 * @link https://github.com/RobiNN1/Cache/blob/master/src/Storages/FileStorage.php
 */
class FileCache {
    /**
     * @const string FileCache version.
     */
    public const VERSION = '2.4.0';

    private string $path;

    public function __construct(string $path) {
        $this->path = $path;
    }

    /**
     * Get a cache path.
     *
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * Check connection.
     *
     * @return bool
     */
    public function isConnected(): bool {
        return is_writable($this->path);
    }

    /**
     * Check if the data is cached.
     *
     * @param string $key
     *
     * @return bool
     */
    public function exists(string $key): bool {
        return is_file($this->getFileName($key));
    }

    /**
     * Save data to cache.
     *
     * @param string $key
     * @param mixed  $data
     * @param int    $seconds
     *
     * @return void
     */
    public function set(string $key, $data, int $seconds = 0): void {
        $file = $this->getFileName($key);

        try {
            $json = json_encode([
                'time'   => time(),
                'expire' => $seconds,
                'data'   => serialize($data),
            ], JSON_THROW_ON_ERROR);

            if (@file_put_contents($file, $json, LOCK_EX) === strlen((string) $json)) {
                @chmod($file, 0777);
            }
        } catch (JsonException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Get TTL.
     *
     * @param string $key
     *
     * @return int
     */
    public function ttl(string $key): int {
        try {
            $data = json_decode((string) file_get_contents($this->getFileName($key)), true, 512, JSON_THROW_ON_ERROR);

            return $data['expire'] === 0 ? -1 : (($data['time'] + $data['expire']) - time());
        } catch (JsonException $e) {
            return 0;
        }
    }

    /**
     * Get data by key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key) {
        $file = $this->getFileName($key);

        if (is_file($file)) {
            try {
                $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

                if ($this->isExpired($data)) {
                    $this->delete($key);
                }

                return unserialize($data['data'], ['allowed_classes' => false]);
            } catch (JsonException $e) {
                return $e->getMessage();
            }
        }

        return null;
    }

    /**
     * Get key and convert any value to a string.
     *
     * @param string $key
     *
     * @return string
     */
    public function getKey(string $key): string {
        $data = $this->get($key);

        if (is_array($data) || is_object($data)) {
            $data = serialize($data);
        }

        return (string) $data;
    }

    /**
     * Delete data by key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool {
        $file = $this->getFileName($key);

        if (is_file($file)) {
            return @unlink($file);
        }

        return false;
    }

    /**
     * Delete all data from cache.
     *
     * @return bool
     */
    public function flush(): bool {
        $handle = opendir($this->path);

        if ($handle) {
            while (false !== ($file = readdir($handle))) {
                if ($file !== '.' && $file !== '..' && unlink($this->path.'/'.$file) === false) {
                    return false;
                }
            }

            closedir($handle);

            return true;
        }

        return false;
    }

    /**
     * Get file name.
     *
     * @param string $key
     *
     * @return string
     */
    private function getFileName(string $key): string {
        return realpath($this->path).'/'.$key.'.cache';
    }

    /**
     * Check if the item is expired or not.
     *
     * @param array<string, mixed> $data
     *
     * @return bool
     */
    private function isExpired(array $data): bool {
        if (!isset($data['time']) && !isset($data['expire'])) {
            return false;
        }

        $expired = false;

        if ((int) $data['expire'] !== 0) {
            $expired = (time() - (int) $data['time']) > (int) $data['expire'];
        }

        return $expired;
    }
}
