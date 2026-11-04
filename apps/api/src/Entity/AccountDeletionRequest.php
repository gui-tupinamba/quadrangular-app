<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccountDeletionStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'account_deletion_requests')]
#[ORM\UniqueConstraint(name: 'uniq_account_deletion_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_account_deletion_status_requested', columns: ['status', 'requested_at', 'id'])]
#[ORM\Index(name: 'idx_account_deletion_user', columns: ['user_id'])]
class AccountDeletionRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'public_id', length: 32)]
    private string $publicId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'submitted_name', length: 120, nullable: true)]
    private ?string $submittedName = null;

    #[ORM\Column(name: 'submitted_email', length: 180, nullable: true)]
    private ?string $submittedEmail = null;

    #[ORM\Column(name: 'additional_information', type: Types::TEXT, nullable: true)]
    private ?string $additionalInformation = null;

    #[ORM\Column(length: 20, enumType: AccountDeletionStatus::class)]
    private AccountDeletionStatus $status;

    #[ORM\Column(name: 'requested_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'identity_verified_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $identityVerifiedAt = null;

    #[ORM\Column(name: 'verification_method', length: 24, nullable: true)]
    private ?string $verificationMethod = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'identity_verified_by_id', nullable: true, onDelete: 'RESTRICT')]
    private ?User $identityVerifiedBy = null;

    #[ORM\Column(name: 'processed_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'processed_by_id', nullable: true, onDelete: 'RESTRICT')]
    private ?User $processedBy = null;

    #[ORM\Column(name: 'rejection_reason', length: 1000, nullable: true)]
    private ?string $rejectionReason = null;

    private function __construct()
    {
    }
}
