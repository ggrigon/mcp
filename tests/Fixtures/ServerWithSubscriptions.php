<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Subscription;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ServerWithSubscriptions extends Server
{
    public array $tools = [
        SayHiTool::class,
    ];

    protected function subscriptions(Subscription $subscription): iterable
    {
        yield JsonRpcResponse::notification('notifications/tools/list_changed', []);
        yield JsonRpcResponse::notification('notifications/prompts/list_changed', []);
        yield JsonRpcResponse::notification('notifications/resources/updated', ['uri' => 'file://b']);
        yield JsonRpcResponse::notification('notifications/resources/updated', ['uri' => 'file://a']);
    }
}
