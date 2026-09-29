<?php

namespace App\Http\Controllers\Api\V1\Callback;

use App\Exceptions\ProcessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Callback\StcShippingCallbackRequest;
use App\Models\IntegrationCallbackLog;
use App\Services\Shipping\StcShippingCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class StcShippingController extends Controller
{
    public function __construct(private readonly StcShippingCallbackService $callbackService) {}

    public function __invoke(StcShippingCallbackRequest $request): JsonResponse
    {
        $this->ensureValidToken($request);
        $payload = $request->validated();
        $audit = IntegrationCallbackLog::query()->create([
            'integration_callback_log_provider' => 'stc',
            'integration_callback_log_type' => 'shipping',
            'integration_callback_log_event' => $payload['method'],
            'integration_callback_log_headers_json' => $this->safeHeaders($request),
            'integration_callback_log_payload_json' => $payload,
            'integration_callback_log_status' => 'processing',
            'integration_callback_log_created_datetime' => now(),
        ]);

        try {
            $result = $this->callbackService->handle($payload);
            if ($result['processed'] === 0) {
                throw new ProcessException(
                    (string) data_get(
                        $result,
                        'errors.0.message',
                        'Pemberitahuan pengiriman belum berhasil diproses.',
                    ),
                    422,
                );
            }

            $partial = $result['failed'] > 0;
            $message = $partial
                ? 'Pemberitahuan pengiriman diproses sebagian.'
                : 'Pemberitahuan pengiriman berhasil diproses.';
            $response = [
                'success' => true,
                'message' => $message,
                'data' => $result,
            ];
            $audit->update([
                'integration_callback_log_response_json' => $response,
                'integration_callback_log_status' => $partial ? 'partial' : 'completed',
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
                        : 'Pemberitahuan pengiriman gagal diproses.',
                    'error_code' => 'process_error',
                ],
                'integration_callback_log_status' => 'failed',
                'integration_callback_log_http_code' => $httpCode,
                'integration_callback_log_processed_datetime' => now(),
            ]);

            throw $exception;
        }
    }

    private function ensureValidToken(StcShippingCallbackRequest $request): void
    {
        if (app()->environment(['local', 'development', 'testing']) && Auth::guard('admin_api')->check()) {
            return;
        }

        $expected = (string) config('services.stc.callback_token', '');
        if ($expected === '') {
            return;
        }

        $actual = (string) ($request->header('X-STC-Callback-Token') ?: $request->bearerToken());
        if ($actual === '' || ! hash_equals($expected, $actual)) {
            throw new ProcessException('Pemberitahuan pengiriman tidak dapat diverifikasi.', 401);
        }
    }

    /** @return array<string, array<int, string|null>> */
    private function safeHeaders(StcShippingCallbackRequest $request): array
    {
        return collect($request->headers->all())
            ->except(['authorization', 'cookie', 'x-stc-callback-token'])
            ->all();
    }
}
