<?php

namespace App\Http\Controllers\Api\V1\Callback;

use App\Exceptions\ProcessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Callback\SupplyChainSaleApprovalRequest;
use App\Models\IntegrationCallbackLog;
use App\Services\Integration\SupplyChainSyncService;
use Illuminate\Http\JsonResponse;
use Throwable;

class SupplyChainSaleApprovalController extends Controller
{
    public function __construct(private readonly SupplyChainSyncService $syncService) {}

    public function __invoke(SupplyChainSaleApprovalRequest $request): JsonResponse
    {
        $this->ensureValidToken($request);
        $payload = $request->validated();
        $audit = IntegrationCallbackLog::query()->create([
            'integration_callback_log_provider' => 'supply_chain',
            'integration_callback_log_type' => 'sale',
            'integration_callback_log_event' => 'approved',
            'integration_callback_log_headers_json' => $this->safeHeaders($request),
            'integration_callback_log_payload_json' => $payload,
            'integration_callback_log_status' => 'processing',
            'integration_callback_log_created_datetime' => now(),
        ]);

        try {
            $result = $this->syncService->receiveSaleApproval($payload, $request->fullUrl());
            $response = [
                'success' => true,
                'message' => 'Faktur, surat jalan, dan batch penjualan berhasil disinkronkan.',
                'data' => $result,
            ];
            $audit->update([
                'integration_callback_log_response_json' => $response,
                'integration_callback_log_status' => 'completed',
                'integration_callback_log_http_code' => 200,
                'integration_callback_log_processed_datetime' => now(),
            ]);

            return response()->json($response);
        } catch (Throwable $exception) {
            $httpCode = $exception instanceof ProcessException ? $exception->httpStatus() : 500;
            $audit->update([
                'integration_callback_log_response_json' => [
                    'message' => $exception instanceof ProcessException
                        ? $exception->getMessage()
                        : 'Callback approval Supply Chain gagal diproses.',
                    'error_code' => 'process_error',
                ],
                'integration_callback_log_status' => 'failed',
                'integration_callback_log_http_code' => $httpCode,
                'integration_callback_log_processed_datetime' => now(),
            ]);

            throw $exception;
        }
    }

    private function ensureValidToken(SupplyChainSaleApprovalRequest $request): void
    {
        $expected = trim((string) config('services.supply_chain.callback_token', ''));
        if ($expected === '') {
            if (app()->environment('production')) {
                throw new ProcessException('Token callback Supply Chain belum dikonfigurasi.', 503);
            }

            return;
        }

        $actual = (string) ($request->header('X-Supply-Chain-Callback-Token') ?: $request->bearerToken());
        if ($actual === '' || ! hash_equals($expected, $actual)) {
            throw new ProcessException('Callback approval Supply Chain tidak dapat diverifikasi.', 401);
        }
    }

    /** @return array<string, array<int, string|null>> */
    private function safeHeaders(SupplyChainSaleApprovalRequest $request): array
    {
        return collect($request->headers->all())
            ->except(['authorization', 'cookie', 'x-supply-chain-callback-token'])
            ->all();
    }
}
