<?php

declare(strict_types=1);

namespace App\Controller;

use App\AccountDeletion\AccountDeletionAdministration;
use App\AccountDeletion\PublicAccountDeletionService;
use App\Http\ApiInput;
use App\Security\CurrentActor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountDeletionController
{
    public function __construct(
        private CurrentActor $actor,
        private PublicAccountDeletionService $publicRequests,
        private AccountDeletionAdministration $administration,
    ) {
    }

    #[Route('/api/account-deletion-requests', name: 'api_account_deletion_request', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $this->noQuery($request);
        $data = ApiInput::jsonObject($request, ['name', 'email', 'confirm', 'additional_information', 'website'], 8192);
        $result = $this->publicRequests->submit(
            $data['name'] ?? null,
            $data['email'] ?? null,
            $data['confirm'] ?? null,
            $data['additional_information'] ?? null,
            $this->clientIp($request),
            $data['website'] ?? null,
        );
        return new JsonResponse([
            'message' => 'Recebemos sua solicitação. A equipe fará a validação manual da identidade. O envio não significa que a conta já foi excluída.',
            'reference' => $result['reference'],
        ], Response::HTTP_ACCEPTED, ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/profile/account-deletion-request', name: 'api_profile_account_deletion_request', methods: ['POST'])]
    public function submitAuthenticated(Request $request): JsonResponse
    {
        $this->noQuery($request);
        $data = ApiInput::jsonObject($request, ['confirm']);
        $result = $this->publicRequests->submitAuthenticated($this->actor->get(), $data['confirm'] ?? null);
        return new JsonResponse([
            'message' => 'Recebemos sua solicitação. A equipe fará a análise antes de concluir a exclusão.',
            'reference' => $result['reference'],
        ], Response::HTTP_ACCEPTED, ['Cache-Control' => 'no-store, private']);
    }

    #[Route('/api/admin/account-deletion-requests', name: 'api_admin_account_deletion_requests', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $p = ApiInput::pagination($request, ['status']);
        $status = $request->query->get('status');
        if ($status !== null && !in_array($status, ['PENDING', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED'], true)) {
            throw new UnprocessableEntityHttpException();
        }
        return new JsonResponse($this->administration->list($this->actor->get(), $p['page'], $p['limit'], $status), headers: ['Cache-Control' => 'no-store, private']);
    }

    #[Route('/api/admin/account-deletion-requests/{reference}', name: 'api_admin_account_deletion_request_detail', requirements: ['reference' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function detail(string $reference): JsonResponse
    {
        return new JsonResponse($this->administration->detail($this->actor->get(), $reference), headers: ['Cache-Control' => 'no-store, private']);
    }

    #[Route('/api/admin/account-deletion-requests/{reference}/review', name: 'api_admin_account_deletion_request_review', requirements: ['reference' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function review(string $reference, Request $request): JsonResponse
    {
        $this->empty($request);
        return new JsonResponse($this->administration->review($this->actor->get(), $reference), headers: ['Cache-Control' => 'no-store, private']);
    }

    #[Route('/api/admin/account-deletion-requests/{reference}/verify-identity', name: 'api_admin_account_deletion_request_verify', requirements: ['reference' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function verifyIdentity(string $reference, Request $request): JsonResponse
    {
        $this->empty($request);
        return new JsonResponse($this->administration->verifyIdentity($this->actor->get(), $reference), headers: ['Cache-Control' => 'no-store, private']);
    }

    #[Route('/api/admin/account-deletion-requests/{reference}/approve', name: 'api_admin_account_deletion_request_approve', requirements: ['reference' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function approve(string $reference, Request $request): JsonResponse
    {
        $this->empty($request);
        return new JsonResponse($this->administration->approve($this->actor->get(), $reference), headers: ['Cache-Control' => 'no-store, private']);
    }

    #[Route('/api/admin/account-deletion-requests/{reference}/reject', name: 'api_admin_account_deletion_request_reject', requirements: ['reference' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function reject(string $reference, Request $request): JsonResponse
    {
        $data = ApiInput::jsonObject($request, ['reason']);
        return new JsonResponse($this->administration->reject($this->actor->get(), $reference, $data['reason'] ?? null), headers: ['Cache-Control' => 'no-store, private']);
    }

    #[Route('/api/admin/account-deletion-requests/{reference}/complete', name: 'api_admin_account_deletion_request_complete', requirements: ['reference' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function complete(string $reference, Request $request): JsonResponse
    {
        $this->empty($request);
        return new JsonResponse($this->administration->complete($this->actor->get(), $reference), headers: ['Cache-Control' => 'no-store, private']);
    }

    private function empty(Request $request): void
    {
        $this->noQuery($request);
        if ($request->getContent() !== '' && ApiInput::jsonObject($request, []) !== []) {
            throw new UnprocessableEntityHttpException();
        }
    }

    private function noQuery(Request $request): void
    {
        if ($request->query->count() !== 0) {
            throw new UnprocessableEntityHttpException();
        }
    }

    private function clientIp(Request $request): string
    {
        // Never trust a client-supplied forwarding header. Cloudflare replaces
        // CF-Connecting-IP at its edge; accept it only from a configured proxy.
        if ($request->isFromTrustedProxy()) {
            $cloudflareIp = $request->headers->get('CF-Connecting-IP');
            if (is_string($cloudflareIp) && filter_var($cloudflareIp, FILTER_VALIDATE_IP) !== false) {
                return $cloudflareIp;
            }
        }

        return $request->getClientIp() ?? 'unknown';
    }
}
