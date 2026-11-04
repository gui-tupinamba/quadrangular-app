<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** HMAC identifiers only; submitted email and IP addresses are never stored here. */
#[ORM\Entity]
#[ORM\Table(name: 'account_deletion_request_limits')]
#[ORM\Index(name: 'idx_account_deletion_limits_window', columns: ['window_started_at'])]
class AccountDeletionRequestLimit
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int $attempts;

    #[ORM\Column(name: 'window_started_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $windowStartedAt;

    private function __construct()
    {
    }
}
