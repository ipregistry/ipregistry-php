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
 * Batch IP lookup. Each entry may independently succeed or fail. Run with:
 *
 *     IPREGISTRY_API_KEY=YOUR_API_KEY php examples/batch.php
 */

require __DIR__.'/../vendor/autoload.php';

use Ipregistry\Exception\IpregistryException;
use Ipregistry\IpregistryClient;

$apiKey = getenv('IPREGISTRY_API_KEY');
if (false === $apiKey || '' === $apiKey) {
    fwrite(\STDERR, "Set the IPREGISTRY_API_KEY environment variable.\n");
    exit(1);
}

$client = new IpregistryClient($apiKey);

try {
    $list = $client->lookupBatch(['73.2.2.2', '8.8.8.8', '2001:67c:2e8:22::c100:68b']);
} catch (IpregistryException $e) {
    fwrite(\STDERR, $e->getMessage()."\n");
    exit(1);
}

foreach ($list as $result) {
    if (null === $result->info) {
        // Handle a per-entry error (e.g. an invalid IP address).
        echo 'entry failed: ', $result->error?->getMessage(), \PHP_EOL;
        continue;
    }
    echo $result->info->ip, ' -> ', $result->info->location->country->name, \PHP_EOL;
}
