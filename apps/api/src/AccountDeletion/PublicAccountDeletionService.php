<?php

declare(strict_types=1);

namespace App\AccountDeletion;

use App\Security\AuthenticatedActor;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class PublicAccountDeletionService
{
    public function __construct(
        private Connection $db,
        #[Autowire('%env(APP_SECRET)%')]
        private string $secret,
    ) {
    }

    /** @return array{reference: string} */
    public function submit(mixed $name, mixed $email, mixed $confirmed, mixed $additional, string $ip, mixed $honeypot): array
    {
        $this->consumeLimit('ip:'.$ip, 20);
        $this->db->executeStatement("DELETE FROM account_deletion_request_limits WHERE window_started_at < clock_timestamp() - interval '1 day'");
        if (!is_string($name) || !is_string($email) || $confirmed !== true
            || ($additional !== null && !is_string($additional))
            || ($honeypot !== null && !is_string($honeypot))) {
            throw new UnprocessableEntityHttpException();
        }

        $name = trim($name);
        $email = strtolower(trim($email));
        $additional = $additional === null ? null : trim($additional);
        if ($name === '' || mb_strlen($name) > 120 || str_contains($name, "\0")
            || strlen($email) > 180 || preg_match('/[^\x00-\x7f]/', $email) === 1
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ($additional !== null && (mb_strlen($additional) > 2000 || str_contains($additional, "\0")))) {
            throw new UnprocessableEntityHttpException();
        }

        $this->consumeLimit('email:'.$email, 3);

        // A honeypot response has the same status and shape as an accepted request.
        // It never creates a database row or exposes whether an account exists.
        if (is_string($honeypot) && trim($honeypot) !== '') {
            return ['reference' => bin2hex(random_bytes(16))];
        }

        $reference = bin2hex(random_bytes(16));
        $this->db->transactional(function () use ($reference, $name, $email, $additional): void {
            $now = (string) $this->db->fetchOne("SELECT date_trunc('second', clock_timestamp())");
            $requestId = $this->db->fetchOne(<<<'SQL'
                INSERT INTO account_deletion_requests
                    (public_id, submitted_name, submitted_email, additional_information, status, requested_at)
                VALUES (?, ?, ?, ?, 'PENDING', ?)
                RETURNING id
                SQL, [$reference, $name, $email, ($additional ?? '') === '' ? null : $additional, $now]);
            $this->db->insert('audit_logs', [
                'actor_id' => null, 'action' => 'account_deletion.requested',
                'entity_type' => 'account_deletion_requests', 'entity_id' => (int) $requestId,
                'metadata' => '{}', 'request_id' => bin2hex(random_bytes(16)), 'created_at' => $now,
            ]);
        });
        return ['reference' => $reference];
    }

    /** @return array{reference: string} */
    public function submitAuthenticated(AuthenticatedActor $actor, mixed $confirmed): array
    {
        if ($confirmed !== true) {
            throw new UnprocessableEntityHttpException();
        }
        if ($this->db->isTransactionActive()) {
            throw new LogicException('Account deletion request must own the outermost transaction.');
        }

        return $this->db->transactional(function () use ($actor): array {
            $this->db->executeQuery('SELECT pg_advisory_xact_lock(841920041)');
            $user = $this->db->fetchAssociative('SELECT name, email, status FROM users WHERE id = ? FOR UPDATE', [$actor->userId]);
            if ($user === false || $user['status'] !== 'ACTIVE') {
                throw new UnauthorizedHttpException('Bearer');
            }
            $session = $this->db->fetchAssociative('SELECT revoked_at, expires_at FROM auth_sessions WHERE id = ? AND user_id = ? FOR UPDATE', [$actor->sessionId, $actor->userId]);
            $now = (string) $this->db->fetchOne("SELECT date_trunc('second', clock_timestamp())");
            if ($session === false || $session['revoked_at'] !== null || new DateTimeImmutable($session['expires_at']) <= new DateTimeImmutable($now)) {
                throw new UnauthorizedHttpException('Bearer');
            }
            if ($this->db->fetchOne("SELECT 1 FROM account_deletion_requests WHERE user_id = ? AND status IN ('PENDING','UNDER_REVIEW','APPROVED')", [$actor->userId]) !== false) {
                throw new ConflictHttpException();
            }
            $reference = bin2hex(random_bytes(16));
            $id = $this->db->fetchOne(<<<'SQL'
                INSERT INTO account_deletion_requests
                    (public_id, user_id, submitted_name, submitted_email, status, requested_at,
                     identity_verified_at, verification_method, identity_verified_by_id)
                VALUES (?, ?, ?, ?, 'PENDING', ?, ?, 'AUTHENTICATED_SESSION', ?)
                RETURNING id
                SQL, [$reference, $actor->userId, $user['name'], $user['email'], $now, $now, $actor->userId]);
            $this->db->insert('audit_logs', [
                'actor_id' => $actor->userId, 'action' => 'account_deletion.requested',
                'entity_type' => 'account_deletion_requests', 'entity_id' => (int) $id,
                'metadata' => '{}', 'request_id' => bin2hex(random_bytes(16)), 'created_at' => $now,
            ]);
            return ['reference' => $reference];
        });
    }

    private function consumeLimit(string $value, int $limit): void
    {
        $id = hash_hmac('sha256', $value, $this->secret);
        $row = $this->db->fetchAssociative(<<<'SQL'
            INSERT INTO account_deletion_request_limits (id, attempts, window_started_at)
            VALUES (?, 1, to_timestamp(floor(extract(epoch FROM clock_timestamp()) / 900) * 900))
            ON CONFLICT (id) DO UPDATE SET
                attempts = CASE
                    WHEN account_deletion_request_limits.window_started_at < EXCLUDED.window_started_at THEN 1
                    ELSE LEAST(account_deletion_request_limits.attempts::bigint + 1, 2147483647)::integer
                END,
                window_started_at = GREATEST(account_deletion_request_limits.window_started_at, EXCLUDED.window_started_at)
            RETURNING attempts,
                GREATEST(1, ceil(extract(epoch FROM window_started_at + interval '15 minutes' - clock_timestamp())))::integer AS retry_after
            SQL, [$id]);
        if ($row !== false && (int) $row['attempts'] > $limit) {
            throw new TooManyRequestsHttpException((int) $row['retry_after']);
        }
    }
}
