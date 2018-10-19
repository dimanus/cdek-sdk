<?php
/**
 * This code is licensed under the MIT License.
 *
 * Copyright (c) 2018 Appwilio (http://appwilio.com), greabock (https://github.com/greabock), JhaoDa (https://github.com/jhaoda)
 * Copyright (c) 2018 Alexey Kopytko <alexey@kopytko.com> and contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

declare(strict_types=1);

namespace Tests\CdekSDK;

use CdekSDK\CdekClient;
use CdekSDK\LaravelCdekServiceProvider;
use Doctrine\Common\Annotations\AnnotationRegistry;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Foundation\Application as ApplicationInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CdekSDK\LaravelCdekServiceProvider
 */
class LaravelCdekServiceProviderTest extends TestCase
{
    /**
     * @var ApplicationInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $app;

    /**
     * @var LaravelCdekServiceProvider
     */
    private $provider;

    // PHPStan workarounds (can't grok introspection yet)
    private $account;
    private $password;

    /** @var ClientInterface */
    private $http;

    protected function setUp()
    {
        parent::setUp();

        $this->app = $this->createMock(ApplicationInterface::class);
        $this->provider = new LaravelCdekServiceProvider($this->app);
    }

    public function testBoot()
    {
        $this->provider->boot();

        $this->assertEquals('class_exists', \Closure::bind(function () {
            return end(AnnotationRegistry::$loaders);
        }, null, AnnotationRegistry::class)());
    }

    private function applicationWithConfig(array $config): \Illuminate\Foundation\Application
    {
        $app = new class() extends \Illuminate\Foundation\Application {
            private $config;

            public function __construct($basePath = null)
            {
                if (false) {
                    // PHPStan workaround
                    parent::__construct($basePath);
                }
            }

            public function setConfig($config)
            {
                $this->config = $config;
            }

            public function offsetGet($key)
            {
                TestCase::assertSame('config', $key);

                return $this->config;
            }
        };

        $app->setConfig($config);

        return $app;
    }

    private function runOnClient(CdekClient $client, callable $callback)
    {
        return \Closure::bind($callback, $client, CdekClient::class)();
    }

    public function testRegister()
    {
        $savedCallback = null;

        $this->app->expects($this->once())
            ->method('singleton')
            ->with(CdekClient::class)->will($this->returnCallback(function ($className, $callback) use (&$savedCallback) {
                $savedCallback = $callback;
            }));

        $this->provider->register();

        $this->assertNotNull($savedCallback);

        return $savedCallback;
    }

    /**
     * @depends testRegister
     */
    public function testWithMinimalConfig(callable $savedCallback)
    {
        $client = $savedCallback($this->applicationWithConfig([
            'services.cdek' => [
                'account'  => 'foo',
                'password' => 'bar',
            ],
        ]));

        $this->assertInstanceOf(CdekClient::class, $client);

        $this->assertEquals('foo', $this->runOnClient($client, function () {
            return $this->account;
        }));

        $this->assertEquals('bar', $this->runOnClient($client, function () {
            return $this->password;
        }));
    }

    /**
     * @depends testRegister
     */
    public function testWithCustomBaseUrl(callable $savedCallback)
    {
        $client = $savedCallback($this->applicationWithConfig([
            'services.cdek' => [
                'account'        => 'bang',
                'password'       => 'boom',
                'guzzle_options' => [
                    'base_uri' => 'https://www.example.com',
                ],
            ],
        ]));

        $this->assertInstanceOf(CdekClient::class, $client);

        $this->assertEquals('bang', $this->runOnClient($client, function () {
            return $this->account;
        }));

        $this->assertEquals('boom', $this->runOnClient($client, function () {
            return $this->password;
        }));

        $this->assertEquals('https://www.example.com', $this->runOnClient($client, function () {
            return $this->http->getConfig()['base_uri'];
        }));

        $this->assertEquals(CdekClient::DEFAULT_TIMEOUT, $this->runOnClient($client, function () {
            return $this->http->getConfig()['timeout'];
        }));
    }

    /**
     * @depends testRegister
     */
    public function testWithCustomTimeout(callable $savedCallback)
    {
        $client = $savedCallback($this->applicationWithConfig([
            'services.cdek' => [
                'account'        => 'bang',
                'password'       => 'boom',
                'guzzle_options' => [
                    'timeout' => 100000,
                ],
            ],
        ]));

        $this->assertInstanceOf(CdekClient::class, $client);

        $this->assertEquals(100000, $this->runOnClient($client, function () {
            return $this->http->getConfig()['timeout'];
        }));
    }

    public function testProvides()
    {
        $this->assertSame([CdekClient::class], $this->provider->provides());
    }
}
