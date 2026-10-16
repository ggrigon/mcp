<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Methods;

use Generator;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Subscription;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class Listen implements Method
{
    /**
     * @return Generator<int, JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator
    {
        $meta = ['_meta' => [MetaKey::SUBSCRIPTION_ID->value => $request->id]];

        if ($context->subscriptions === null) {
            yield JsonRpcResponse::notification('notifications/subscriptions/acknowledged', [
                ...$meta,
                'notifications' => (object) [],
            ]);

            yield JsonRpcResponse::result($request->id, $meta);

            return;
        }

        $requested = $request->params['notifications'] ?? [];

        $subscription = new Subscription($request->id, is_array($requested) ? $requested : []);
        $honored = $subscription->honored();

        yield JsonRpcResponse::notification('notifications/subscriptions/acknowledged', [
            ...$meta,
            'notifications' => $honored === [] ? (object) [] : $honored,
        ]);

        foreach (($context->subscriptions)($subscription) as $message) {
            if (! $message instanceof JsonRpcResponse || ! $subscription->accepts($message)) {
                continue;
            }

            $params = (array) ($message->content['params'] ?? []);

            yield JsonRpcResponse::notification($message->content['method'], [...$params, ...$meta]);
        }

        yield JsonRpcResponse::result($request->id, $meta);
    }
}
