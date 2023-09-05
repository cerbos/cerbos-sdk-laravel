<?php

// Copyright 2021-2023 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

namespace Cerbos\Sdk\Laravel\Provider;

use Cerbos\Sdk\Builder\CerbosClientBuilder;
use Cerbos\Sdk\CerbosClient;
use Illuminate\Support\ServiceProvider;

class CerbosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CerbosClient::class, function () {
            return CerbosClientBuilder::newInstance(env('CERBOS_HOST') .":". env('CERBOS_PORT'))
                ->withPlaintext(true)
                ->build();
        });
    }

    public function provides(): array
    {
        return [CerbosClient::class];
    }
}
