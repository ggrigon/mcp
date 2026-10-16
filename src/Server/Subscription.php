<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Transport\JsonRpcResponse;

class Subscription
{
    /**
     * @var array<string, string>
     */
    protected const LIST_CHANGED = [
        'toolsListChanged' => 'notifications/tools/list_changed',
        'promptsListChanged' => 'notifications/prompts/list_changed',
        'resourcesListChanged' => 'notifications/resources/list_changed',
    ];

    /**
     * @param  array<string, mixed>  $requested
     */
    public function __construct(
        public readonly int|string $id,
        protected array $requested,
    ) {
        //
    }

    /**
     * The subset of the requested filter that this server honors.
     *
     * @return array<string, mixed>
     */
    public function honored(): array
    {
        $honored = [];

        foreach (array_keys(self::LIST_CHANGED) as $key) {
            if (($this->requested[$key] ?? false) === true) {
                $honored[$key] = true;
            }
        }

        $uris = $this->resourceSubscriptions();

        if ($uris !== []) {
            $honored['resourceSubscriptions'] = $uris;
        }

        return $honored;
    }

    /**
     * @return array<int, string>
     */
    public function resourceSubscriptions(): array
    {
        $uris = $this->requested['resourceSubscriptions'] ?? [];

        return is_array($uris)
            ? array_values(array_filter($uris, is_string(...)))
            : [];
    }

    /**
     * Whether a message yielded by the server falls inside the honored filter.
     */
    public function accepts(JsonRpcResponse $message): bool
    {
        $method = $message->content['method'] ?? null;

        if (! is_string($method)) {
            return false;
        }

        foreach (self::LIST_CHANGED as $key => $listChangedMethod) {
            if ($method === $listChangedMethod) {
                return ($this->requested[$key] ?? false) === true;
            }
        }

        if ($method === 'notifications/resources/updated') {
            $params = (array) ($message->content['params'] ?? []);

            return in_array($params['uri'] ?? null, $this->resourceSubscriptions(), true);
        }

        return false;
    }
}
