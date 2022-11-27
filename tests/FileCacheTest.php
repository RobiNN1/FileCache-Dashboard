<?php
/**
 * This file is part of phpCacheAdmin.
 *
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests;

use JsonException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
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
     * Call private method.
     *
     * @throws ReflectionException
     */
    protected static function callMethod(object $object, string $name, mixed ...$args): mixed {
        return (new ReflectionMethod($object, $name))->invokeArgs($object, $args);
    }

    /**
     * Set the value of private property.
     *
     * @throws ReflectionException
     */
    protected static function setValue(object $object, string $name, mixed $value): void {
        (new ReflectionProperty($object, $name))->setValue($object, $value);
    }

    /**
     * @throws RuntimeException|DashboardException|CacheException|ReflectionException
     */
    protected function setUp(): void {
        $this->template = new Template();
        $dashboard = new FileCacheDashboard($this->template);

        $this->path = __DIR__.'/file_cache';

        if (!is_dir($this->path) && false === @mkdir($this->path, 0777, true) && !is_dir($this->path)) {
            throw new RuntimeException(sprintf('Unable to create the "%s" directory.', $this->path));
        }

        $this->filecache = $dashboard->connect(['path' => $this->path]);

        self::setValue($dashboard, 'filecache', $this->filecache);
    }

    /**
     * @throws JsonException
     */
    public function testDeleteKey(): void {
        $key = 'pu-test-delete-key';

        $this->filecache->set($key, 'data');

        $_POST['delete'] = json_encode($key, JSON_THROW_ON_ERROR);

        $this->assertSame(
            $this->template->render('components/alert', ['message' => 'Key "'.$key.'" has been deleted.']),
            Helpers::deleteKey($this->template, fn (string $key): bool => $this->filecache->delete($key))
        );
        $this->assertFalse($this->filecache->exists($key));
    }

    /**
     * @throws JsonException
     */
    public function testDeleteKeys(): void {
        $key1 = 'pu-test-delete-key1';
        $key2 = 'pu-test-delete-key2';
        $key3 = 'pu-test-delete-key3';

        $this->filecache->set($key1, 'data1');
        $this->filecache->set($key2, 'data2');
        $this->filecache->set($key3, 'data3');

        $_POST['delete'] = json_encode([$key1, $key2, $key3], JSON_THROW_ON_ERROR);

        $this->assertSame(
            $this->template->render('components/alert', ['message' => 'Keys has been deleted.']),
            Helpers::deleteKey($this->template, fn (string $key): bool => $this->filecache->delete($key))
        );
        $this->assertFalse($this->filecache->exists($key1));
        $this->assertFalse($this->filecache->exists($key2));
        $this->assertFalse($this->filecache->exists($key3));
    }

    public function testSetGetKey(): void {
        $keys = [
            'string' => ['original' => 'phpCacheAdmin', 'expected' => 'phpCacheAdmin'],
            'int'    => ['original' => 23, 'expected' => '23'],
            'float'  => ['original' => 23.99, 'expected' => '23.99'],
            'bool'   => ['original' => true, 'expected' => '1'],
            'null'   => ['original' => null, 'expected' => ''],
            'array'  => [
                'original' => ['key1', 'key2'],
                'expected' => 'a:2:{i:0;s:4:"key1";i:1;s:4:"key2";}',
            ],
            'object' => [
                'original' => (object) ['key1', 'key2'],
                'expected' => 'O:8:"stdClass":2:{s:1:"0";s:4:"key1";s:1:"1";s:4:"key2";}',
            ],
        ];

        foreach ($keys as $key => $value) {
            $this->filecache->set('pu-test-'.$key, $value['original']);
        }

        $this->assertSame($keys['string']['expected'], Helpers::mixedToString($this->filecache->get('pu-test-string')));
        $this->assertSame($keys['int']['expected'], Helpers::mixedToString($this->filecache->get('pu-test-int')));
        $this->assertSame($keys['float']['expected'], Helpers::mixedToString($this->filecache->get('pu-test-float')));
        $this->assertSame($keys['bool']['expected'], Helpers::mixedToString($this->filecache->get('pu-test-bool')));
        $this->assertSame($keys['null']['expected'], Helpers::mixedToString($this->filecache->get('pu-test-null')));
        $this->assertSame($keys['array']['expected'], Helpers::mixedToString($this->filecache->get('pu-test-array')));
        $this->assertSame($keys['object']['expected'], Helpers::mixedToString($this->filecache->get('pu-test-object')));

        foreach ($keys as $key => $value) {
            $this->filecache->delete('pu-test-'.$key);
        }
    }

    /**
     * Recursively remove folder and all files/subdirectories.
     *
     * @param string $dir Path to the folder.
     */
    public function rrmdir(string $dir): void {
        if (is_dir($dir)) {
            $objects = (array) scandir($dir);

            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (filetype($dir.'/'.$object) === 'dir') {
                        $this->rrmdir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }

            rmdir($dir);
        }
    }

    protected function tearDown(): void {
        $this->rrmdir($this->path);
    }
}
