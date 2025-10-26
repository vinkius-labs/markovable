<?php

namespace VinkiusLabs\Markovable\Handlers;

use VinkiusLabs\Markovable\Models\RecommendationAction;

class ActionSuggestionHandler
{
    private ?NotificationHandler $notificationHandler;

    public function __construct(?NotificationHandler $notificationHandler = null)
    {
        $this->notificationHandler = $notificationHandler;
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $context
     * @return array<int, RecommendationAction>
     */
    public function handle(array $actions, array $context = []): array
    {
        $recommendations = [];

        foreach ($actions as $action) {
            $payload = array_merge($action, ['context' => $context]);
            $recommendations[] = RecommendationAction::fromArray($payload);

            if ($this->notificationHandler) {
                $this->notificationHandler->notify($payload);
            }
        }

        return $recommendations;
    }
}
