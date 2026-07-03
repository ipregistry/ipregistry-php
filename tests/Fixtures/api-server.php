<?php

declare(strict_types=1);

/*
 * Copyright 2019 Ipregistry (https://ipregistry.co).
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * Router script for PHP's built-in server that fakes the Ipregistry API.
 * Used by offline behavior tests exercising the real cURL transport.
 */

function payloadFor(string $ip): array
{
    return [
        'ip' => $ip,
        'type' => str_contains($ip, ':') ? 'IPv6' : 'IPv4',
        'location' => [
            'country' => ['code' => 'US', 'name' => 'United States'],
            'city' => 'Mountain View',
        ],
        'connection' => ['asn' => 15169, 'organization' => 'Google LLC'],
        'security' => ['is_vpn' => false, 'is_threat' => false],
        'time_zone' => ['id' => 'America/Los_Angeles', 'offset' => -28800],
    ];
}

function looksLikeIp(string $candidate): bool
{
    return false !== filter_var($candidate, \FILTER_VALIDATE_IP);
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = rawurldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], \PHP_URL_PATH));

// Echo endpoint: reflects the received request for transport-level tests.
if ('/echo' === $path) {
    respond(200, [
        'method' => $method,
        'path' => $path,
        'query' => $_GET,
        'headers' => array_change_key_case(getallheaders(), \CASE_LOWER),
        'body' => file_get_contents('php://input'),
    ]);

    return;
}

// Arbitrary status endpoint: /status/503 responds with HTTP 503.
if (1 === preg_match('#^/status/(\d{3})$#', $path, $matches)) {
    header('X-Test-Header: present');
    respond((int) $matches[1], ['status' => (int) $matches[1]]);

    return;
}

// Slow endpoint, for timeout tests.
if ('/slow' === $path) {
    usleep(2_000_000);
    respond(200, ['slow' => true]);

    return;
}

// User-Agent parse endpoint.
if ('/user_agent' === $path && 'POST' === $method) {
    $userAgents = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($userAgents)) {
        respond(400, ['code' => 'BAD_REQUEST', 'message' => 'invalid body', 'resolution' => 'send a JSON array']);

        return;
    }
    $results = [];
    foreach ($userAgents as $userAgent) {
        $results[] = [
            'header' => $userAgent,
            'name' => 'Chrome',
            'type' => 'browser',
            'os' => ['name' => 'macOS', 'type' => 'desktop'],
        ];
    }
    respond(200, ['results' => $results]);

    return;
}

// Batch lookup endpoint.
if ('/' === $path && 'POST' === $method) {
    $ips = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($ips)) {
        respond(400, ['code' => 'BAD_REQUEST', 'message' => 'invalid body', 'resolution' => 'send a JSON array']);

        return;
    }
    $results = [];
    foreach ($ips as $ip) {
        $ip = (string) $ip;
        if (looksLikeIp($ip)) {
            $results[] = payloadFor($ip);
        } else {
            $results[] = [
                'code' => 'INVALID_IP_ADDRESS',
                'message' => 'Invalid IP address: '.$ip,
                'resolution' => 'Specify a valid IP address.',
            ];
        }
    }
    respond(200, ['results' => $results]);

    return;
}

// Origin lookup endpoint.
if ('/' === $path && 'GET' === $method) {
    $payload = payloadFor('93.184.216.34');
    $payload['user_agent'] = [
        'header' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'name' => 'IpregistryClient',
        'type' => 'library',
    ];
    respond(200, $payload);

    return;
}

// Single lookup endpoint.
if ('GET' === $method) {
    $ip = ltrim($path, '/');
    if (!looksLikeIp($ip)) {
        respond(400, [
            'code' => 'INVALID_IP_ADDRESS',
            'message' => 'Invalid IP address: '.$ip,
            'resolution' => 'Specify a valid IP address.',
        ]);

        return;
    }
    $payload = payloadFor($ip);
    if (($_GET['hostname'] ?? '') === 'true') {
        $payload['hostname'] = 'dns.google';
    }
    respond(200, $payload);

    return;
}

respond(404, ['code' => 'BAD_REQUEST', 'message' => 'not found', 'resolution' => 'check the request path']);
