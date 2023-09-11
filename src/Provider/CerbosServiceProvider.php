<?php

// Copyright 2021-2023 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

namespace Cerbos\Sdk\Laravel\Provider;

use Cerbos\Sdk\Builder\CerbosClientBuilder;
use Cerbos\Sdk\CerbosClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class CerbosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cerbos.php' => $this->app->basePath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cerbos.php',
        ]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ .  DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config'. DIRECTORY_SEPARATOR . 'cerbos.php', 'cerbos');
        $this->app->singleton(CerbosClient::class, function () {
            $caCert = '';
            $tlsCert = '';
            $tlsKey = '';
            if (isset($this->app['config']['cerbos.caCertPath']) && $this->app['config']['cerbos.caCertPath'] != '') {
                $caCert = File::get($this->app['config']['cerbos.caCertPath']);
            }

            if (isset($this->app['config']['cerbos.tlsCertPath']) && $this->app['config']['cerbos.tlsCertPath'] != '') {
                $tlsCert = File::get($this->app['config']['cerbos.tlsCertPath']);            }

            if (isset($this->app['config']['cerbos.tlsKeyPath']) && $this->app['config']['cerbos.tlsKeyPath'] != '') {
                $tlsKey = File::get($this->app['config']['cerbos.tlsKeyPath']);
            }

            $cb = CerbosClientBuilder::newInstance($this->app['config']['cerbos.host'] .':'. $this->app['config']['cerbos.port'])
                ->withPlaintext($this->app['config']['cerbos.plaintext']);
            if ($caCert != '') {
                $cb = $cb->withCaCertificate($caCert);
            }
            if ($tlsCert != '') {
                $cb = $cb->withTlsCertificate($tlsCert);
            }
            if ($tlsKey != '') {
                $cb = $cb->withTlsKey($tlsKey);
            }

            return $cb->build();
        });
    }

    public function provides(): array
    {
        return [CerbosClient::class];
    }
}
