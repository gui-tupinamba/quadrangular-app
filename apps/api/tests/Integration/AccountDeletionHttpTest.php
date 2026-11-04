<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Auth\SessionService;
use App\Enum\AuthClientType;
use App\Security\JwtService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountDeletionHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?Connection $db = null;
    private array $users = [];
    private array $ministries = [];
    private array $posts = [];
    private array $events = [];
    private array $schedules = [];
    private array $references = [];
    private bool $failureTriggerInstalled = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_DATABASE_TESTS') !== '1') {
            self::markTestSkipped('Requires isolated PostgreSQL.');
        }
        $this->client = self::createClient();
        $this->db = DriverManager::getConnection(self::getContainer()->get(EntityManagerInterface::class)->getConnection()->getParams());
        self::assertStringEndsWith('_test', (string) $this->db->fetchOne('SELECT current_database()'));
    }

    protected function tearDown(): void
    {
        if ($this->failureTriggerInstalled && $this->db !== null) {
            $this->db->executeStatement('DROP TRIGGER IF EXISTS test_account_deletion_failure ON users');
            $this->db->executeStatement('DROP FUNCTION IF EXISTS test_account_deletion_failure()');
            $this->failureTriggerInstalled = false;
        }
        if ($this->db !== null) {
            $ids = array_values(array_unique($this->users));
            $types = [ArrayParameterType::INTEGER];
            $requestIds = [];
            foreach ($this->references as $reference) {
                $id = $this->db->fetchOne('SELECT id FROM account_deletion_requests WHERE public_id = ?', [$reference]);
                if ($id !== false) { $requestIds[] = (int) $id; }
            }
            if ($requestIds !== []) {
                $this->db->executeStatement("DELETE FROM audit_logs WHERE entity_type = 'account_deletion_requests' AND entity_id IN (?)", [$requestIds], $types);
                $this->db->executeStatement('DELETE FROM account_deletion_requests WHERE id IN (?)', [$requestIds], $types);
            }
            if ($ids !== []) {
            $this->db->executeStatement('DELETE FROM audit_logs WHERE actor_id IN (?) OR (entity_type = \'users\' AND entity_id IN (?))', [$ids, $ids], [$types[0], $types[0]]);
            $this->db->executeStatement('DELETE FROM comments WHERE user_id IN (?) OR post_id IN (?)', [$ids, [...$this->posts, 0]], [$types[0], $types[0]]);
            if ($this->posts !== []) { $this->db->executeStatement('DELETE FROM posts WHERE id IN (?)', [$this->posts], $types); }
            if ($this->events !== []) { $this->db->executeStatement('DELETE FROM events WHERE id IN (?)', [$this->events], $types); }
            if ($this->schedules !== []) { $this->db->executeStatement('DELETE FROM ministry_schedules WHERE id IN (?)', [$this->schedules], $types); }
            $this->db->executeStatement('DELETE FROM notifications WHERE created_by_id IN (?) OR target_user_id IN (?)', [$ids, $ids], [$types[0], $types[0]]);
            $this->db->executeStatement('DELETE FROM notification_recipients WHERE user_id IN (?)', [$ids], $types);
            $this->db->executeStatement('DELETE FROM push_devices WHERE user_id IN (?)', [$ids], $types);
            $this->db->executeStatement('DELETE FROM notification_preferences WHERE user_id IN (?)', [$ids], $types);
            $this->db->executeStatement('UPDATE refresh_tokens SET replaced_by_id = NULL WHERE session_id IN (SELECT id FROM auth_sessions WHERE user_id IN (?))', [$ids], $types);
            $this->db->executeStatement('DELETE FROM refresh_tokens WHERE session_id IN (SELECT id FROM auth_sessions WHERE user_id IN (?))', [$ids], $types);
            $this->db->executeStatement('DELETE FROM auth_sessions WHERE user_id IN (?)', [$ids], $types);
            $this->db->executeStatement('DELETE FROM user_ministries WHERE user_id IN (?)', [$ids], $types);
            if ($this->ministries !== []) { $this->db->executeStatement('DELETE FROM ministries WHERE id IN (?)', [$this->ministries], $types); }
            $this->db->executeStatement('DELETE FROM users WHERE id IN (?)', [$ids], $types);
            }
        }
        $this->db?->close();
        parent::tearDown();
    }

    public function testPublicRequestIsNeutralForExistingAndUnknownEmailAndDoesNotAssociateAccount(): void
    {
        $existing = $this->account('MEMBER');
        $responses = [];
        foreach ([$existing['email'], 'unknown-'.bin2hex(random_bytes(5)).'@example.test'] as $email) {
            $this->send('POST', '/api/account-deletion-requests', null, [
                'name' => 'Pessoa solicitante', 'email' => $email, 'confirm' => true,
            ], '198.51.100.20');
            self::assertResponseStatusCodeSame(202);
            $responses[] = $this->body();
            $this->references[] = $responses[array_key_last($responses)]['reference'];
        }
        self::assertSame($responses[0]['message'], $responses[1]['message']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $responses[0]['reference']);
        self::assertSame('PENDING', $this->db->fetchOne('SELECT status FROM account_deletion_requests WHERE public_id = ?', [$responses[0]['reference']]));
        self::assertNull($this->db->fetchOne('SELECT user_id FROM account_deletion_requests WHERE public_id = ?', [$responses[0]['reference']]));

        $before = (int) $this->db->fetchOne('SELECT count(*) FROM account_deletion_requests');
        $this->send('POST', '/api/account-deletion-requests', null, [
            'name' => 'Bot', 'email' => 'bot-'.bin2hex(random_bytes(4)).'@example.test', 'confirm' => true, 'website' => 'spam',
        ], '198.51.100.21');
        self::assertResponseStatusCodeSame(202);
        self::assertSame($before, (int) $this->db->fetchOne('SELECT count(*) FROM account_deletion_requests'));
    }

    public function testPublicRequestsAreRateLimitedByEmail(): void
    {
        $email = 'limit-'.bin2hex(random_bytes(5)).'@example.test';
        for ($index = 0; $index < 3; $index++) {
            $this->send('POST', '/api/account-deletion-requests', null, ['name' => 'Solicitante', 'email' => $email, 'confirm' => true], '198.51.100.22');
            self::assertResponseStatusCodeSame(202);
            $this->references[] = $this->body()['reference'];
        }
        $this->send('POST', '/api/account-deletion-requests', null, ['name' => 'Solicitante', 'email' => $email, 'confirm' => true], '198.51.100.22');
        self::assertResponseStatusCodeSame(429);
    }

    public function testPublicIpLimitUsesTheTrustedCloudflareVisitorIp(): void
    {
        $visitorIp = '198.51.100.'.random_int(50, 199);
        $otherVisitorIp = '198.51.100.'.random_int(200, 249);
        for ($index = 0; $index < 20; $index++) {
            $this->send('POST', '/api/account-deletion-requests', null, [
                'name' => 'Solicitante', 'email' => 'visitor-'.$index.'-'.bin2hex(random_bytes(4)).'@example.test', 'confirm' => true,
            ], '127.0.0.1', $visitorIp);
            self::assertResponseStatusCodeSame(202);
            $this->references[] = $this->body()['reference'];
        }

        $this->send('POST', '/api/account-deletion-requests', null, [
            'name' => 'Solicitante', 'email' => 'visitor-blocked-'.bin2hex(random_bytes(4)).'@example.test', 'confirm' => true,
        ], '127.0.0.1', $visitorIp);
        self::assertResponseStatusCodeSame(429);

        $this->send('POST', '/api/account-deletion-requests', null, [
            'name' => 'Solicitante', 'email' => 'other-visitor-'.bin2hex(random_bytes(4)).'@example.test', 'confirm' => true,
        ], '127.0.0.1', $otherVisitorIp);
        self::assertResponseStatusCodeSame(202);
        $this->references[] = $this->body()['reference'];

        $untrustedPeerIp = '198.51.'.random_int(1, 254).'.250';
        for ($index = 0; $index < 20; $index++) {
            $this->send('POST', '/api/account-deletion-requests', null, [
                'name' => 'Solicitante', 'email' => 'spoof-'.$index.'-'.bin2hex(random_bytes(4)).'@example.test', 'confirm' => true,
            ], $untrustedPeerIp, '203.0.113.'.random_int(1, 254));
            self::assertResponseStatusCodeSame(202);
            $this->references[] = $this->body()['reference'];
        }
        $this->send('POST', '/api/account-deletion-requests', null, [
            'name' => 'Solicitante', 'email' => 'spoof-blocked-'.bin2hex(random_bytes(4)).'@example.test', 'confirm' => true,
        ], $untrustedPeerIp, '203.0.113.'.random_int(1, 254));
        self::assertResponseStatusCodeSame(429);
    }

    public function testAuthenticatedRequestIsAssociatedAndUsesSessionVerification(): void
    {
        $member = $this->account('MEMBER');
        $this->send('POST', '/api/profile/account-deletion-request', $member, ['confirm' => true]);
        self::assertResponseStatusCodeSame(202);
        $reference = $this->body()['reference'];
        $this->references[] = $reference;
        $row = $this->db->fetchAssociative('SELECT user_id, submitted_name, submitted_email, status, verification_method FROM account_deletion_requests WHERE public_id = ?', [$reference]);
        self::assertSame($member['id'], (int) $row['user_id']);
        self::assertSame($member['email'], $row['submitted_email']);
        self::assertSame('PENDING', $row['status']);
        self::assertSame('AUTHENTICATED_SESSION', $row['verification_method']);
        $this->send('POST', '/api/profile/account-deletion-request', $member, ['confirm' => true]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testRejectionKeepsReasonAndProcessingHistoryButClearsSubmittedPersonalData(): void
    {
        $admin = $this->account('ADMIN');
        $member = $this->account('MEMBER');
        $reference = $this->publicRequest($member['name'], $member['email']);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/reject", $admin, ['reason' => 'Não foi possível validar a identidade.']);
        self::assertResponseIsSuccessful();
        $this->references[] = $reference;
        $row = $this->db->fetchAssociative('SELECT submitted_name, submitted_email, additional_information, status, rejection_reason, processed_at FROM account_deletion_requests WHERE public_id = ?', [$reference]);
        self::assertNull($row['submitted_name']);
        self::assertNull($row['submitted_email']);
        self::assertNull($row['additional_information']);
        self::assertSame('REJECTED', $row['status']);
        self::assertSame('Não foi possível validar a identidade.', $row['rejection_reason']);
        self::assertNotNull($row['processed_at']);
    }

    public function testOnlyAdministrativeRolesCanReviewAndInvalidTransitionsAreRejected(): void
    {
        $admin = $this->account('ADMIN');
        $member = $this->account('MEMBER');
        $reference = $this->publicRequest($member['name'], $member['email']);
        $this->send('GET', '/api/admin/account-deletion-requests', null);
        self::assertResponseStatusCodeSame(401);
        $this->send('GET', '/api/admin/account-deletion-requests', $member);
        self::assertResponseStatusCodeSame(403);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/approve", $admin, []);
        self::assertResponseStatusCodeSame(409);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/review", $admin, []);
        self::assertResponseIsSuccessful();
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/approve", $admin, []);
        self::assertResponseStatusCodeSame(409);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/verify-identity", $admin, []);
        self::assertResponseIsSuccessful();
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/approve", $admin, []);
        self::assertResponseIsSuccessful();
        self::assertSame('MANUAL_CONTACT', $this->db->fetchOne('SELECT verification_method FROM account_deletion_requests WHERE public_id = ?', [$reference]));
        $this->references[] = $reference;
    }

    public function testPastorCannotReadOrProcessARequestForAnAdministrator(): void
    {
        $admin = $this->account('ADMIN');
        $pastor = $this->account('PASTOR');
        $protectedAdmin = $this->account('ADMIN');
        $reference = $this->publicRequest($protectedAdmin['name'], $protectedAdmin['email']);
        $this->send('GET', '/api/admin/account-deletion-requests', $pastor);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->body()['pagination']['total']);
        $this->send('GET', "/api/admin/account-deletion-requests/$reference", $pastor);
        self::assertResponseStatusCodeSame(403);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/review", $pastor, []);
        self::assertResponseStatusCodeSame(403);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/review", $admin, []);
        self::assertResponseIsSuccessful();
        $this->references[] = $reference;
    }

    public function testCompletedDeletionIsAtomicAndRetainsAuthoredContentAnonymously(): void
    {
        $admin = $this->account('ADMIN');
        $target = $this->account('MEMBER');
        $ministry = $this->ministry();
        $this->db->executeStatement("INSERT INTO user_ministries (user_id,ministry_id,is_leader,status,joined_at,created_at,updated_at) VALUES (?, ?, FALSE, 'ACTIVE', clock_timestamp(), clock_timestamp(), clock_timestamp())", [$target['id'], $ministry]);
        $post = (int) $this->db->fetchOne("INSERT INTO posts (author_id,title,content,visibility,comments_enabled,status,published_at,created_at,updated_at,ministry_id) VALUES (?, 'Post preservado','Conteúdo comunitário','PUBLIC',TRUE,'PUBLISHED',clock_timestamp(),clock_timestamp(),clock_timestamp(),NULL) RETURNING id", [$target['id']]);
        $this->posts[] = $post;
        $this->db->executeStatement("INSERT INTO comments (post_id,user_id,content,status,created_at,updated_at) VALUES (?,?,'Comentário removido','VISIBLE',clock_timestamp(),clock_timestamp())", [$post, $target['id']]);
        $event = (int) $this->db->fetchOne("INSERT INTO events (created_by,title,description,location,address,starts_at,ends_at,visibility,status,created_at,updated_at,ministry_id) VALUES (?, 'Evento preservado',NULL,NULL,NULL,clock_timestamp(),NULL,'PUBLIC','PUBLISHED',clock_timestamp(),clock_timestamp(),NULL) RETURNING id", [$target['id']]);
        $this->events[] = $event;
        $schedule = (int) $this->db->fetchOne("INSERT INTO ministry_schedules (created_by,title,description,starts_at,ends_at,visibility,status,created_at,updated_at,ministry_id) VALUES (?, 'Atividade preservada',NULL,clock_timestamp(),NULL,'PUBLIC','PUBLISHED',clock_timestamp(),clock_timestamp(),NULL) RETURNING id", [$target['id']]);
        $this->schedules[] = $schedule;
        $this->db->executeStatement('INSERT INTO notification_preferences (user_id,push_enabled,church_push_enabled,ministry_push_enabled,updated_at) VALUES (?,TRUE,TRUE,TRUE,clock_timestamp())', [$target['id']]);
        $this->db->insert('push_devices', ['user_id' => $target['id'], 'session_id' => $target['session'], 'expo_push_token' => 'ExpoPushToken['.bin2hex(random_bytes(8)).']', 'platform' => 'ANDROID', 'status' => 'ACTIVE', 'last_seen_at' => $this->now(), 'created_at' => $this->now(), 'updated_at' => $this->now()]);
        $notification = (int) $this->db->fetchOne("INSERT INTO notifications (created_by_id,scope,ministry_id,target_user_id,title,body,route,idempotency_key,payload_hash,created_at) VALUES (?,'USER',NULL,?,'Pessoal','Aviso direcionado',NULL,?,?,clock_timestamp()) RETURNING id", [$admin['id'], $target['id'], bin2hex(random_bytes(12)), hash('sha256', 'private')]);
        $this->db->insert('notification_recipients', ['notification_id' => $notification, 'user_id' => $target['id'], 'read_at' => null, 'created_at' => $this->now()]);
        $this->db->insert('audit_logs', ['actor_id' => $target['id'], 'action' => 'post.created', 'entity_type' => 'posts', 'entity_id' => $post, 'metadata' => '{"title":"private"}', 'request_id' => bin2hex(random_bytes(16)), 'created_at' => $this->now()]);

        $reference = $this->publicRequest($target['name'], $target['email']);
        $this->processPublicRequest($admin, $reference);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/complete", $admin, []);
        self::assertResponseIsSuccessful();
        $this->references[] = $reference;

        $row = $this->db->fetchAssociative('SELECT name,email,email_normalized,phone,birth_date,role,status FROM users WHERE id = ?', [$target['id']]);
        self::assertSame('Conta excluída', $row['name']);
        self::assertStringEndsWith('@account.invalid', $row['email']);
        self::assertNull($row['phone']);
        self::assertNull($row['birth_date']);
        self::assertSame('MEMBER', $row['role']);
        self::assertSame('INACTIVE', $row['status']);
        self::assertSame(1, (int) $this->db->fetchOne('SELECT count(*) FROM posts WHERE id = ? AND author_id = ?', [$post, $target['id']]));
        self::assertSame('Conta excluída', $this->db->fetchOne('SELECT u.name FROM posts p JOIN users u ON u.id = p.author_id WHERE p.id = ?', [$post]));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT count(*) FROM events WHERE id = ? AND created_by = ?', [$event, $target['id']]));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT count(*) FROM ministry_schedules WHERE id = ? AND created_by = ?', [$schedule, $target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM comments WHERE user_id = ?', [$target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM auth_sessions WHERE user_id = ?', [$target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM refresh_tokens WHERE session_id = ?', [$target['session']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM user_ministries WHERE user_id = ?', [$target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM push_devices WHERE user_id = ?', [$target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM notification_preferences WHERE user_id = ?', [$target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM notifications WHERE target_user_id = ?', [$target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM notification_recipients WHERE user_id = ?', [$target['id']]));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT count(*) FROM audit_logs WHERE actor_id = ? AND entity_type <> \'account_deletion_requests\'', [$target['id']]));
        self::assertNull($this->db->fetchOne('SELECT submitted_email FROM account_deletion_requests WHERE public_id = ?', [$reference]));
        self::assertSame('COMPLETED', $this->db->fetchOne('SELECT status FROM account_deletion_requests WHERE public_id = ?', [$reference]));
    }

    public function testLastActiveAdministratorCannotBeDeleted(): void
    {
        $admin = $this->account('ADMIN');
        $reference = $this->publicRequest($admin['name'], $admin['email']);
        $this->processPublicRequest($admin, $reference);
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/complete", $admin, []);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('ACTIVE', $this->db->fetchOne('SELECT status FROM users WHERE id = ?', [$admin['id']]));
        self::assertSame('APPROVED', $this->db->fetchOne('SELECT status FROM account_deletion_requests WHERE public_id = ?', [$reference]));
        $this->references[] = $reference;
    }

    public function testDatabaseFailureRollsBackAllDeletionChanges(): void
    {
        $admin = $this->account('ADMIN');
        $target = $this->account('MEMBER');
        $post = (int) $this->db->fetchOne("INSERT INTO posts (author_id,title,content,visibility,comments_enabled,status,published_at,created_at,updated_at) VALUES (?, 'Rollback post','Content','PUBLIC',TRUE,'PUBLISHED',clock_timestamp(),clock_timestamp(),clock_timestamp()) RETURNING id", [$target['id']]);
        $this->posts[] = $post;
        $this->db->executeStatement("INSERT INTO comments (post_id,user_id,content,status,created_at,updated_at) VALUES (?,?,'Keep on rollback','VISIBLE',clock_timestamp(),clock_timestamp())", [$post, $target['id']]);
        $reference = $this->publicRequest($target['name'], $target['email']);
        $this->processPublicRequest($admin, $reference);
        $this->db->executeStatement(str_replace('__TARGET__', (string) $target['id'], <<<'SQL'
            CREATE FUNCTION test_account_deletion_failure() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.id = __TARGET__ THEN RAISE EXCEPTION 'forced deletion rollback'; END IF;
                RETURN NEW;
            END $$
            SQL));
        $this->db->executeStatement('CREATE TRIGGER test_account_deletion_failure BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION test_account_deletion_failure()');
        $this->failureTriggerInstalled = true;
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/complete", $admin, []);
        self::assertResponseStatusCodeSame(500);
        $this->db->executeStatement('DROP TRIGGER IF EXISTS test_account_deletion_failure ON users');
        $this->db->executeStatement('DROP FUNCTION IF EXISTS test_account_deletion_failure()');
        $this->failureTriggerInstalled = false;
        $this->references[] = $reference;
        self::assertSame('ACTIVE', $this->db->fetchOne('SELECT status FROM users WHERE id = ?', [$target['id']]));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT count(*) FROM comments WHERE user_id = ?', [$target['id']]));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT count(*) FROM auth_sessions WHERE user_id = ?', [$target['id']]));
        self::assertSame('APPROVED', $this->db->fetchOne('SELECT status FROM account_deletion_requests WHERE public_id = ?', [$reference]));
    }

    private function processPublicRequest(array $admin, string $reference): void
    {
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/review", $admin, []);
        self::assertResponseIsSuccessful();
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/verify-identity", $admin, []);
        self::assertResponseIsSuccessful();
        $this->send('POST', "/api/admin/account-deletion-requests/$reference/approve", $admin, []);
        self::assertResponseIsSuccessful();
    }

    private function publicRequest(string $name, string $email): string
    {
        $this->send('POST', '/api/account-deletion-requests', null, ['name' => $name, 'email' => $email, 'confirm' => true], '198.51.100.'.random_int(1, 254));
        self::assertResponseStatusCodeSame(202);
        $reference = $this->body()['reference'];
        $this->references[] = $reference;
        return $reference;
    }

    private function account(string $role): array
    {
        $email = 'delete-'.bin2hex(random_bytes(8)).'@example.test';
        $password = 'test-only-'.bin2hex(random_bytes(8));
        $id = (int) $this->db->fetchOne("INSERT INTO users (name,email,email_normalized,password_hash,role,status,created_at,updated_at) VALUES ('Deletion fixture',?,?,?,?,'ACTIVE',clock_timestamp(),clock_timestamp()) RETURNING id", [$email, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), $role]);
        $this->users[] = $id;
        $session = self::getContainer()->get(SessionService::class)->login($email, $password, AuthClientType::MOBILE);
        return ['id' => $id, 'name' => 'Deletion fixture', 'email' => $email, 'session' => $session->sessionId, 'token' => self::getContainer()->get(JwtService::class)->issue((string) $id, $session->sessionId)];
    }

    private function ministry(): int
    {
        $id = (int) $this->db->fetchOne("INSERT INTO ministries (name,slug,status,created_at,updated_at) VALUES ('Deletion fixture',?,'ACTIVE',clock_timestamp(),clock_timestamp()) RETURNING id", ['delete-'.bin2hex(random_bytes(8))]);
        $this->ministries[] = $id;
        return $id;
    }

    private function send(string $method, string $path, ?array $actor, ?array $body = null, ?string $ip = null, ?string $forwardedIp = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip ?? ('198.51.100.'.random_int(1, 254))];
        if ($forwardedIp !== null) { $server['HTTP_CF_CONNECTING_IP'] = $forwardedIp; }
        if ($actor !== null) { $server['HTTP_AUTHORIZATION'] = 'Bearer '.$actor['token']; }
        $this->client->request($method, 'https://localhost'.$path, server: $server, content: $body === null ? null : json_encode((object) $body, JSON_THROW_ON_ERROR));
    }

    private function body(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function now(): string
    {
        return (string) $this->db->fetchOne('SELECT clock_timestamp()');
    }
}
