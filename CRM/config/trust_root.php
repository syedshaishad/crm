<?php

/*
 * Pinned trust anchors for this product.
 *
 * These are CODE, not .env. Moving a value here deliberately requires a code
 * change (and a signed release), which is the whole point: a buyer who can
 * edit .env or storage/app must not be able to become the licence issuer.
 *
 * Certificate keys below are the Licensify public signing keys shipped with
 * v1.0.0. Rotation happens by shipping a new app release with a new pin — the
 * remote keyring is never trusted on its own.
 */
return [
    'product' => 'SSCCRM1000V1',

    // Only certificates issued by these hosts are accepted (claim "iss").
    'issuers' => ['license.shaishad.com'],

    'certificates' => [
        'keys' => [
            'key_2026_11_ybRR' => 'FxNx0Q5FsQ/uS6uiGdIG/88CwyBoI0ZsTxeg38Bj9Y0=',
            'key_2026_10_TSxF' => 'hEOQRNfRmtTPI+/PCKgIrYLswQGSF013txGre5s6HVM=',
            'key_2026_10_c3u7' => '7/xQabdXa/g+4pkKq0Wsz2qSOjiZ4lrWLFxImUpxKqo=',
        ],
        // A cached certificate never grants more than 7 days offline, whatever
        // the server (or a stale certificate) claims.
        'max_offline_seconds' => 7 * 86400,
        // Refuse certificates whose "dom" / "inst" claims name another install.
        'require_binding' => true,
    ],

    'updates' => [
        // Ed25519 public key for signed update packages. The private key stays
        // with the seller (deploy/keys/update-signing.key, never shipped).
        'keys' => [
            'rel_2026_1' => 'bhE5wBfVqlbTFE/qecFAEL0WjTqJb56Sb2BWYkOp+a4=',
        ],
    ],
];
