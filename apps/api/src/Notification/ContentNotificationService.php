<?php

declare(strict_types=1);

namespace App\Notification;

use App\Security\AuthenticatedActor;

final readonly class ContentNotificationService
{
    public function __construct(
        private NotificationService $notifications,
    ) {
    }

    public function postPublished(
        AuthenticatedActor $actor,
        array $post,
        string $now,
    ): void {
        $this->published(
            actor: $actor,
            type: 'post',
            content: $post,
            notificationTitle: 'Nova publicação',
            notificationBody: (string) $post['title'],
            route: '/posts/' . (int) $post['id'],
            now: $now,
        );
    }

    public function eventPublished(
        AuthenticatedActor $actor,
        array $event,
        string $now,
    ): void {
        $this->published(
            actor: $actor,
            type: 'event',
            content: $event,
            notificationTitle: 'Novo evento',
            notificationBody: (string) $event['title'],
            route: '/events/' . (int) $event['id'],
            now: $now,
        );
    }

    public function schedulePublished(
        AuthenticatedActor $actor,
        array $schedule,
        string $now,
    ): void {
        $this->published(
            actor: $actor,
            type: 'schedule',
            content: $schedule,
            notificationTitle: 'Nova atividade na agenda',
            notificationBody: (string) $schedule['title'],
            route: '/agenda',
            now: $now,
        );
    }

    private function published(
        AuthenticatedActor $actor,
        string $type,
        array $content,
        string $notificationTitle,
        string $notificationBody,
        string $route,
        string $now,
    ): void {
        $id = (int) $content['id'];

        $ministryId =
            $content['ministry_id'] === null
                ? null
                : (int) $content['ministry_id'];

        $scope =
            $content['visibility'] === 'MINISTRY_MEMBERS'
                ? 'MINISTRY'
                : 'CHURCH';

        if ($scope === 'MINISTRY' && $ministryId === null) {
            throw new \LogicException(
                'Conteúdo privado precisa estar ligado a um ministério.',
            );
        }

        $key = sprintf(
            'auto_%s_%d_%s',
            $type,
            $id,
            substr(hash('sha256', $now), 0, 16),
        );

        $this->notifications->create(
            $actor,
            [
                'scope' => $scope,
                'ministry_id' =>
                    $scope === 'MINISTRY'
                        ? $ministryId
                        : null,
                'target_user_id' => null,
                'title' => $notificationTitle,
                'body' => $notificationBody,
                'route' => $route,
                'idempotency_key' => $key,
            ],
        );
    }
}
