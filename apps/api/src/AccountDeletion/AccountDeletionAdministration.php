<?php

declare(strict_types=1);

namespace App\AccountDeletion;

use App\Security\AuthenticatedActor;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Doctrine\DBAL\Connection;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class AccountDeletionAdministration
{
    public function __construct(
        private Connection $db,
        #[Autowire('%env(APP_SECRET)%')]
        private string $secret,
    ) {
    }

    public function list(AuthenticatedActor $actor, int $page, int $limit, ?string $status): array
    {
        $role = $this->assertManager($actor);
        $conditions = [];
        $params = [];
        if ($status !== null) { $conditions[] = 'r.status = ?'; $params[] = $status; }
        if ($role === 'PASTOR') {
            $conditions[] = "NOT EXISTS (SELECT 1 FROM users protected WHERE protected.role = 'ADMIN' AND (protected.id = r.user_id OR protected.email_normalized = lower(r.submitted_email)))";
        }
        $where = $conditions === [] ? '' : ' WHERE '.implode(' AND ', $conditions);
        $total = (int) $this->db->fetchOne('SELECT count(*) FROM account_deletion_requests r'.$where, $params);
        $rows = $this->db->fetchAllAssociative(
            'SELECT r.public_id, r.submitted_name, r.submitted_email, r.status, r.requested_at, r.identity_verified_at, r.processed_at, r.rejection_reason FROM account_deletion_requests r'.$where.' ORDER BY r.requested_at DESC, r.id DESC LIMIT ? OFFSET ?',
            [...$params, $limit, ($page - 1) * $limit],
        );

        return ['items' => array_map($this->view(...), $rows), 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]];
    }

    public function detail(AuthenticatedActor $actor, string $reference): array
    {
        $role = $this->assertManager($actor);
        $row = $this->find($reference);
        $this->assertRequestAccessible($role, $row);
        $actors = $this->db->fetchAssociative(
            'SELECT verified.name AS verified_by_name, processed.name AS processed_by_name FROM account_deletion_requests r LEFT JOIN users verified ON verified.id = r.identity_verified_by_id LEFT JOIN users processed ON processed.id = r.processed_by_id WHERE r.id = ?',
            [(int) $row['id']],
        );
        $history = $this->db->fetchAllAssociative(
            'SELECT action, actor_id, created_at FROM audit_logs WHERE entity_type = ? AND entity_id = ? ORDER BY created_at, id',
            ['account_deletion_requests', (int) $row['id']],
        );
        return ['request' => [...$this->view($row, true), 'identity_verified_by_name' => $actors['verified_by_name'] ?? null, 'processed_by_name' => $actors['processed_by_name'] ?? null, 'history' => array_map(static fn (array $item): array => [
            'action' => $item['action'], 'actor_id' => $item['actor_id'] === null ? null : (int) $item['actor_id'], 'created_at' => $item['created_at'],
        ], $history)]];
    }

    public function review(AuthenticatedActor $actor, string $reference): array
    {
        return $this->transition($actor, $reference, ['PENDING'], 'UNDER_REVIEW', 'account_deletion.reviewed');
    }

    /** Records the administrator's out-of-system identity check without retaining its evidence. */
    public function verifyIdentity(AuthenticatedActor $actor, string $reference): array
    {
        return $this->mutate($actor, $reference, function (array $row, string $now) use ($actor): array {
            if ($row['status'] !== 'UNDER_REVIEW' || $row['identity_verified_at'] !== null) {
                throw new ConflictHttpException();
            }
            $email = $row['submitted_email'];
            if (!is_string($email)) {
                throw new ConflictHttpException();
            }
            $userId = $this->db->fetchOne('SELECT id FROM users WHERE email_normalized = ? FOR UPDATE', [strtolower($email)]);
            if ($userId === false) {
                throw new ConflictHttpException();
            }
            if ($this->db->fetchOne("SELECT 1 FROM account_deletion_requests WHERE user_id = ? AND id <> ? AND status IN ('PENDING','UNDER_REVIEW','APPROVED')", [(int) $userId, $row['id']]) !== false) {
                throw new ConflictHttpException();
            }
            $this->db->executeStatement(
                "UPDATE account_deletion_requests SET user_id = ?, identity_verified_at = ?, verification_method = 'MANUAL_CONTACT', identity_verified_by_id = ? WHERE id = ?",
                [(int) $userId, $now, $actor->userId, $row['id']],
            );
            $this->audit($actor->userId, (int) $row['id'], 'account_deletion.identity_verified', $now);
            return ['status' => 'UNDER_REVIEW', 'identity_verified_at' => $now];
        });
    }

    public function approve(AuthenticatedActor $actor, string $reference): array
    {
        return $this->mutate($actor, $reference, function (array $row, string $now) use ($actor): array {
            if ($row['status'] !== 'UNDER_REVIEW' || $row['identity_verified_at'] === null || $row['user_id'] === null) {
                throw new ConflictHttpException();
            }
            $this->db->executeStatement(
                "UPDATE account_deletion_requests SET status = 'APPROVED', processed_at = ?, processed_by_id = ? WHERE id = ?",
                [$now, $actor->userId, $row['id']],
            );
            $this->audit($actor->userId, (int) $row['id'], 'account_deletion.approved', $now);
            return ['status' => 'APPROVED', 'processed_at' => $now];
        });
    }

    public function reject(AuthenticatedActor $actor, string $reference, mixed $reason): array
    {
        if (!is_string($reason) || trim($reason) === '' || mb_strlen(trim($reason)) > 1000) {
            throw new UnprocessableEntityHttpException();
        }
        $reason = trim($reason);
        return $this->mutate($actor, $reference, function (array $row, string $now) use ($actor, $reason): array {
            if (!in_array($row['status'], ['PENDING', 'UNDER_REVIEW', 'APPROVED'], true)) {
                throw new ConflictHttpException();
            }
            $this->db->executeStatement(
                "UPDATE account_deletion_requests SET status = 'REJECTED', processed_at = ?, processed_by_id = ?, rejection_reason = ?, submitted_name = NULL, submitted_email = NULL, additional_information = NULL, user_id = NULL WHERE id = ?",
                [$now, $actor->userId, $reason, $row['id']],
            );
            $this->audit($actor->userId, (int) $row['id'], 'account_deletion.rejected', $now);
            return ['status' => 'REJECTED', 'processed_at' => $now];
        });
    }

    public function complete(AuthenticatedActor $actor, string $reference): array
    {
        if ($this->db->isTransactionActive()) {
            throw new LogicException('Account deletion must own the outermost transaction.');
        }

        return $this->db->transactional(function () use ($actor, $reference): array {
            $this->db->executeQuery('SELECT pg_advisory_xact_lock(841920041)');
            $request = $this->find($reference, true);
            $userId = $request['user_id'] === null ? null : (int) $request['user_id'];
            $actorRow = $this->lockActor($actor);
            $this->assertRequestAccessible($actorRow['role'], $request);
            if ($request['status'] !== 'APPROVED' || $userId === null) {
                throw new ConflictHttpException();
            }
            $target = $this->db->fetchAssociative('SELECT id, role, status FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if ($target === false) {
                throw new ConflictHttpException();
            }
            if ($target['role'] === 'ADMIN') {
                if ($actorRow['role'] !== 'ADMIN') {
                    throw new AccessDeniedHttpException();
                }
                if ($target['status'] === 'ACTIVE'
                    && (int) $this->db->fetchOne("SELECT count(*) FROM users WHERE role = 'ADMIN' AND status = 'ACTIVE'") <= 1) {
                    throw new ConflictHttpException();
                }
            }
            $this->eraseAccount($userId, (string) $request['public_id']);

            $now = $this->now();
            $this->db->executeStatement(
                "UPDATE account_deletion_requests SET status = 'COMPLETED', processed_at = ?, processed_by_id = ?, submitted_name = NULL, submitted_email = NULL, additional_information = NULL, user_id = NULL WHERE id = ?",
                [$now, $actor->userId, $request['id']],
            );
            $this->audit($actor->userId, (int) $request['id'], 'account_deletion.completed', $now);
            return ['reference' => $request['public_id'], 'status' => 'COMPLETED', 'processed_at' => $now];
        });
    }

    private function transition(AuthenticatedActor $actor, string $reference, array $from, string $to, string $action): array
    {
        return $this->mutate($actor, $reference, function (array $row, string $now) use ($actor, $from, $to, $action): array {
            if (!in_array($row['status'], $from, true)) {
                throw new ConflictHttpException();
            }
            $this->db->executeStatement('UPDATE account_deletion_requests SET status = ? WHERE id = ?', [$to, $row['id']]);
            $this->audit($actor->userId, (int) $row['id'], $action, $now);
            return ['status' => $to];
        });
    }

    private function mutate(AuthenticatedActor $actor, string $reference, callable $operation): array
    {
        if ($this->db->isTransactionActive()) {
            throw new LogicException('Account deletion request changes must own the outermost transaction.');
        }
        return $this->db->transactional(function () use ($actor, $reference, $operation): array {
            $this->db->executeQuery('SELECT pg_advisory_xact_lock(841920041)');
            $actorRow = $this->lockActor($actor);
            if (!in_array($actorRow['role'], ['ADMIN', 'PASTOR'], true)) {
                throw new AccessDeniedHttpException();
            }
            $row = $this->find($reference, true);
            $this->assertRequestAccessible($actorRow['role'], $row);
            return $operation($row, $this->now());
        });
    }

    private function assertManager(AuthenticatedActor $actor): string
    {
        $row = $this->db->fetchAssociative('SELECT role, status FROM users WHERE id = ?', [$actor->userId]);
        if ($row === false || $row['status'] !== 'ACTIVE') {
            throw new UnauthorizedHttpException('Bearer');
        }
        if (!in_array($row['role'], ['ADMIN', 'PASTOR'], true)) {
            throw new AccessDeniedHttpException();
        }
        $session = $this->db->fetchAssociative('SELECT revoked_at, expires_at FROM auth_sessions WHERE id = ? AND user_id = ?', [$actor->sessionId, $actor->userId]);
        if ($session === false || $session['revoked_at'] !== null || new DateTimeImmutable($session['expires_at']) <= new DateTimeImmutable($this->now())) {
            throw new UnauthorizedHttpException('Bearer');
        }
        return $row['role'];
    }

    private function lockActor(AuthenticatedActor $actor): array
    {
        $row = $this->db->fetchAssociative('SELECT role, status FROM users WHERE id = ? FOR UPDATE', [$actor->userId]);
        if ($row === false || $row['status'] !== 'ACTIVE') {
            throw new UnauthorizedHttpException('Bearer');
        }
        if (!in_array($row['role'], ['ADMIN', 'PASTOR'], true)) {
            throw new AccessDeniedHttpException();
        }
        $session = $this->db->fetchAssociative('SELECT revoked_at, expires_at FROM auth_sessions WHERE id = ? AND user_id = ? FOR UPDATE', [$actor->sessionId, $actor->userId]);
        if ($session === false || $session['revoked_at'] !== null || new DateTimeImmutable($session['expires_at']) <= new DateTimeImmutable($this->now())) {
            throw new UnauthorizedHttpException('Bearer');
        }
        return $row;
    }

    private function find(string $reference, bool $lock = false): array
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $reference) !== 1) {
            throw new NotFoundHttpException();
        }
        $row = $this->db->fetchAssociative('SELECT * FROM account_deletion_requests WHERE public_id = ?'.($lock ? ' FOR UPDATE' : ''), [$reference]);
        if ($row === false) {
            throw new NotFoundHttpException();
        }
        return $row;
    }

    private function assertRequestAccessible(string $role, array $request): void
    {
        if ($role !== 'PASTOR') {
            return;
        }
        $isAdminRequest = $this->db->fetchOne(
            "SELECT 1 FROM users WHERE role = 'ADMIN' AND (id = ? OR email_normalized = lower(?)) LIMIT 1",
            [$request['user_id'], $request['submitted_email']],
        );
        if ($isAdminRequest !== false) {
            throw new AccessDeniedHttpException();
        }
    }

    private function view(array $row, bool $withAdditional = false): array
    {
        $result = [
            'reference' => $row['public_id'], 'name' => $row['submitted_name'], 'email' => $row['submitted_email'],
            'status' => $row['status'], 'requested_at' => $row['requested_at'],
            'identity_verified_at' => $row['identity_verified_at'], 'processed_at' => $row['processed_at'],
            'rejection_reason' => $row['rejection_reason'],
        ];
        if ($withAdditional) {
            $result['additional_information'] = $row['additional_information'];
            $result['identity_verified_by_id'] = $row['identity_verified_by_id'] === null ? null : (int) $row['identity_verified_by_id'];
            $result['processed_by_id'] = $row['processed_by_id'] === null ? null : (int) $row['processed_by_id'];
        }
        return $result;
    }

    private function eraseAccount(int $userId, string $reference): void
    {
        $now = $this->now();
        $oldEmail = (string) $this->db->fetchOne('SELECT email_normalized FROM users WHERE id = ?', [$userId]);
        // Remove private records before anonymizing the account that anchors retained content.
        $this->db->executeStatement('DELETE FROM comments WHERE user_id = ?', [$userId]);
        $this->db->executeStatement('DELETE FROM notifications WHERE target_user_id = ? OR created_by_id = ?', [$userId, $userId]);
        $this->db->executeStatement('DELETE FROM notification_recipients WHERE user_id = ?', [$userId]);
        $this->db->executeStatement('DELETE FROM push_devices WHERE user_id = ?', [$userId]);
        $this->db->executeStatement('DELETE FROM notification_preferences WHERE user_id = ?', [$userId]);
        $this->db->executeStatement('DELETE FROM user_ministries WHERE user_id = ?', [$userId]);
        $this->db->delete('auth_login_limits', ['id' => hash_hmac('sha256', 'account:'.$oldEmail, $this->secret)]);
        $this->db->delete('account_deletion_request_limits', ['id' => hash_hmac('sha256', 'email:'.$oldEmail, $this->secret)]);
        $this->db->executeStatement("UPDATE audit_logs SET actor_id = NULL, metadata = '{}'::jsonb WHERE actor_id = ?", [$userId]);
        $this->db->executeStatement("DELETE FROM audit_logs WHERE entity_type = 'users' AND entity_id = ?", [$userId]);
        $this->db->executeStatement('UPDATE refresh_tokens SET replaced_by_id = NULL WHERE session_id IN (SELECT id FROM auth_sessions WHERE user_id = ?)', [$userId]);
        $this->db->executeStatement('DELETE FROM refresh_tokens WHERE session_id IN (SELECT id FROM auth_sessions WHERE user_id = ?)', [$userId]);
        $this->db->executeStatement('DELETE FROM auth_sessions WHERE user_id = ?', [$userId]);

        $this->db->executeStatement(
            "UPDATE users SET name = 'Conta excluída', email = ?, email_normalized = ?, password_hash = ?, phone = NULL, birth_date = NULL, role = 'MEMBER', status = 'INACTIVE', updated_at = ? WHERE id = ?",
            ['deleted-'.$reference.'@account.invalid', 'deleted-'.$reference.'@account.invalid', password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $now, $userId],
        );
    }

    private function audit(int $actorId, int $requestId, string $action, string $now): void
    {
        $this->db->insert('audit_logs', [
            'actor_id' => $actorId, 'action' => $action, 'entity_type' => 'account_deletion_requests',
            'entity_id' => $requestId, 'metadata' => '{}', 'request_id' => bin2hex(random_bytes(16)), 'created_at' => $now,
        ]);
    }

    private function now(): string
    {
        return (string) $this->db->fetchOne("SELECT date_trunc('second', clock_timestamp())");
    }
}
