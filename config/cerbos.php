<?php

// Copyright 2021-2023 Zenauth Ltd.
// SPDX-License-Identifier: Apache-2.0

return [
    'host'        => env('CERBOS_HOST', '127.0.0.1'),
    'port'        => env('CERBOS_PORT', '3593'),
    'plaintext'   => env('CERBOS_PLAINTEXT', true),
    'caCertPath'  => env('CERBOS_CA_CERT_PATH', ''),
    'tlsCertPath' => env('CERBOS_TLS_CERT_PATH', ''),
    'tlsKeyPath'  => env('CERBOS_TLS_KEY_PATH', '')
];