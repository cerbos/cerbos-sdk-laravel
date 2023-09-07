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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cerbos.php' => $this->app->basePath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . ('cerbos.php'),
            ], 'cerbos-config');
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/cerbos.php', 'cerbos');
    }

    public function register(): void
    {
        $this->app->singleton(CerbosClient::class, function () {
            $caCert = File::get($this->app['config']['cerbos.caCertPath']);
            $tlsCert = File::get($this->app['config']['cerbos.tlsCertPath']);
            $tlsKey = File::get($this->app['config']['cerbos.tlsKeyPath']);

            $cb = CerbosClientBuilder::newInstance($this->app['config']['cerbos.host'] .':'. $this->app['config']['cerbos.port'])
                ->withPlaintext($this->app['config']['cerbos.plaintext']);
            if ($caCert) {
                $cb = $cb->withCaCertificate($caCert);
            }
            if ($tlsCert) {
                $cb = $cb->withTlsCertificate($tlsCert);
            }
            if ($tlsKey) {
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
