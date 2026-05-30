<?php

// Copyright 2021-2025 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

namespace Cerbos\Sdk\Laravel\Provider;

use Cerbos\Sdk\Builder\CerbosClientBuilder;
use Cerbos\Sdk\CerbosClient;
use Cerbos\Sdk\Laravel\CerbosManager;
use Cerbos\Sdk\Laravel\QueryPlan\LaravelQueryPlanAdapter;
use Cerbos\Sdk\Laravel\QueryPlan\QueryPlanBuilderMacros;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Cerbos services, configuration, facades, and query builder macros.
 */
class CerbosServiceProvider extends ServiceProvider
{
    /**
     * Publish configuration and register Laravel query plan macros.
     */
    public function boot(): void
    {
        $this->publishes([
                __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cerbos.php' => $this->app->basePath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cerbos.php',
        ]);
        QueryPlanBuilderMacros::register($this->app->make(LaravelQueryPlanAdapter::class));
    }

    /**
     * Bind the Cerbos client and Laravel authorization manager into the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ .  DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config'. DIRECTORY_SEPARATOR . 'cerbos.php', 'cerbos');
        $this->app->singleton(LaravelQueryPlanAdapter::class);
        $this->app->singleton(CerbosManager::class, function (): CerbosManager {
            return new CerbosManager(
                $this->app->make(CerbosClient::class),
                queryPlanAdapter: $this->app->make(LaravelQueryPlanAdapter::class),
                authResolver: fn () => Auth::user(),
            );
        });
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

    /**
     * Return the services provided by this service provider.
     *
     * @return list<class-string>
     */
    public function provides(): array
    {
        return [CerbosClient::class, CerbosManager::class, LaravelQueryPlanAdapter::class];
    }
}
