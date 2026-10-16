<?php

declare(strict_types=1);

use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\ServerWithSubscriptions;

function listenOn(string $server, array $notifications): array
{
    $transport = new ArrayTransport;
    $instance = new $server($transport);

    $instance->start();

    ($transport->handler)((string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'subscriptions/listen',
        'params' => [
            '_meta' => protocolMeta(),
            'notifications' => $notifications,
        ],
    ]));

    return array_map(fn (string $sent): array => json_decode($sent, true), $transport->sent);
}

function advertisedToolsListChanged(string $server): bool
{
    $transport = new ArrayTransport;
    (new $server($transport))->start();

    ($transport->handler)((string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'server/discover',
        'params' => ['_meta' => protocolMeta()],
    ]));

    return json_decode($transport->sent[0], true)['result']['capabilities']['tools']['listChanged'];
}

it('keeps advertising listChanged false when the server does not override the hook', function (): void {
    expect(advertisedToolsListChanged(ExampleServer::class))->toBeFalse();
});

it('advertises listChanged true when the server overrides the hook', function (): void {
    expect(advertisedToolsListChanged(ServerWithSubscriptions::class))->toBeTrue();
});

it('streams only the notifications the client asked for, stamped with the subscription id', function (): void {
    $messages = listenOn(ServerWithSubscriptions::class, [
        'toolsListChanged' => true,
        'resourceSubscriptions' => ['file://a'],
    ]);

    expect(array_column($messages, 'method'))->toBe([
        'notifications/subscriptions/acknowledged',
        'notifications/tools/list_changed',
        'notifications/resources/updated',
        null,
    ]);

    expect($messages[0]['params']['notifications'])->toBe([
        'toolsListChanged' => true,
        'resourceSubscriptions' => ['file://a'],
    ]);

    expect($messages[2]['params']['uri'])->toBe('file://a');

    foreach ($messages as $message) {
        $meta = $message['params']['_meta'] ?? $message['result']['_meta'];
        expect($meta['io.modelcontextprotocol/subscriptionId'])->toBe(9);
    }

    expect(end($messages)['id'])->toBe(9);
});

it('acknowledges only the filters the server honors', function (): void {
    $messages = listenOn(ServerWithSubscriptions::class, ['toolsListChanged' => false, 'promptsListChanged' => true]);

    expect($messages[0]['params']['notifications'])->toBe(['promptsListChanged' => true]);
});

it('keeps the default immediate close when the hook is not overridden', function (): void {
    $messages = listenOn(ExampleServer::class, ['toolsListChanged' => true]);

    expect($messages)->toHaveCount(2);
    expect($messages[0]['params']['notifications'])->toBe([]);
});
