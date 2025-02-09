<?php
/**
 * This file is part of the FileCache-Dashboard.
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 */

declare(strict_types=1);

namespace RobiNN\FileCache\Tests;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RobiNN\Cache\CacheException;
use RobiNN\Cache\Storages\FileStorage;
use RobiNN\FileCache\FileCacheDashboard;
use RobiNN\Pca\Dashboards\DashboardException;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Template;
use RuntimeException;

final class FileCacheTest extends TestCase {
    private Template $template;

    private FileStorage $filecache;

    private string $path;

    /**
     * @throws RuntimeException|DashboardException|CacheException
     */
    protected function setUp(): void {
        $this->template = new Template();
        $dashboard = new FileCacheDashboard($this->template);

        $this->path = __DIR__.'/file_cache';

        if (!is_dir($this->path) && @mkdir($this->path, 0777, true) === false && !is_dir($this->path)) {
            throw new RuntimeException(sprintf('Unable to create the "%s" directory.', $this->path));
        }

        $this->filecache = $dashboard->connect(['path' => $this->path]);
    }

    protected function tearDown(): void {
        $this->rrmdir($this->path);
    }

    /**
     * @param array<int, string>|string $keys
     */
    private function deleteKeys(array|string $keys): void {
        $delete_key = fn (string $key): bool => $this->filecache->delete($key);

        try {
            $_POST['delete'] = json_encode($keys, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            //
        }

        $this->assertSame(
            Helpers::alert($this->template, (is_array($keys) ? 'Keys' : 'Key "'.$keys.'"').' has been deleted.', 'success'),
            Helpers::deleteKey($this->template, $delete_key)
        );
    }

    public function testDeleteKey(): void {
        $key = 'pu-test-delete-key';

        $this->filecache->set($key, 'data');
        $this->deleteKeys($key);
        $this->assertFalse($this->filecache->exists($key));
    }

    public function testDeleteKeys(): void {
        $key1 = 'pu-test-delete-key1';
        $key2 = 'pu-test-delete-key2';
        $key3 = 'pu-test-delete-key3';

        $this->filecache->set($key1, 'data1');
        $this->filecache->set($key2, 'data2');
        $this->filecache->set($key3, 'data3');

        $this->deleteKeys([$key1, $key2, $key3]);

        $this->assertFalse($this->filecache->exists($key1));
        $this->assertFalse($this->filecache->exists($key2));
        $this->assertFalse($this->filecache->exists($key3));
    }

    /**
     * @return array<int, mixed>
     */
    public static function keysProvider(): array {
        return [
            ['string', 'phpCacheAdmin', 'phpCacheAdmin'],
            ['int', 23, '23'],
            ['float', 23.99, '23.99'],
            ['bool', true, '1'],
            ['null', null, ''],
            ['array', ['key1', 'key2'], 'a:2:{i:0;s:4:"key1";i:1;s:4:"key2";}'],
            ['object', (object) ['key1', 'key2'], 'O:8:"stdClass":2:{s:1:"0";s:4:"key1";s:1:"1";s:4:"key2";}'],
        ];
    }

    #[DataProvider('keysProvider')]
    public function testSetGetKey(string $type, mixed $original, mixed $expected): void {
        $this->filecache->set('pu-test-'.$type, $original);
        $this->assertSame($expected, Helpers::mixedToString($this->filecache->get('pu-test-'.$type)));
        $this->filecache->delete('pu-test-'.$type);
    }

    /**
     * Recursively remove folder and all files/subdirectories.
     *
     * @param string $dir Path to the directory.
     */
    public function rrmdir(string $dir): void {
        if (is_dir($dir)) {
            $directory_contents = array_diff((array) scandir($dir), ['.', '..']);

            foreach ($directory_contents as $content) {
                $content_path = $dir.DIRECTORY_SEPARATOR.$content;

                is_dir($content_path) ? $this->rrmdir($content_path) : unlink($content_path);
            }

            rmdir($dir);
        }
    }
}
