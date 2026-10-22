<?php

namespace App\Integrations\SupplyChain;

use App\Contracts\Integrations\SupplyChainGateway;
use App\Exceptions\ProcessException;
use App\Exceptions\SupplyChainRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpSupplyChainGateway implements SupplyChainGateway
{
    private const API_PREFIX = '/api/v1/penjualan-supply-chain';

    public function createCustomer(array $payload): array
    {
        $data = $this->authenticatedPost('/tambah-customer', $payload);

        if (blank($data['cust_no'] ?? null)) {
            throw new ProcessException($this->internalErrorMessage(null, 'Nomor customer tidak ditemukan pada respons Aplikasi Internal.'), 502);
        }

        return $data;
    }

    public function saveSale(array $payload): array
    {
        $data = $this->authenticatedPost('/simpan', $payload);

        if (blank($data['jual_no'] ?? null)) {
            throw new ProcessException($this->internalErrorMessage(null, 'Nomor penjualan tidak ditemukan pada respons Aplikasi Internal.'), 502);
        }

        return $data;
    }

    public function postSale(string $saleNumber): array
    {
        return $this->authenticatedPost('/posting', ['jual_no' => $saleNumber]);
    }

    public function approveSale(string $saleNumber): array
    {
        return $this->authenticatedPost('/approve', ['jual_no' => $saleNumber]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function authenticatedPost(string $endpoint, array $payload): array
    {
        $cacheKey = $this->tokenCacheKey();
        $token = $this->token();

        try {
            return $this->post($endpoint, $payload, $token);
        } catch (RequestException $exception) {
            if (! $exception->response->unauthorized()) {
                throw $this->requestFailure($exception);
            }

            Cache::forget($cacheKey);

            try {
                return $this->post($endpoint, $payload, $this->token());
            } catch (RequestException $retryException) {
                throw $this->requestFailure($retryException);
            }
        } catch (ConnectionException $exception) {
            report($exception);

            throw new ProcessException('Layanan Aplikasi Internal sedang tidak dapat dihubungi.', 503);
        }
    }

    private function token(): string
    {
        $cacheKey = $this->tokenCacheKey();

        return Cache::remember(
            $cacheKey,
            max(60, (int) config('services.supply_chain.token_ttl_seconds', 3300)),
            function (): string {
                $username = trim((string) config('services.supply_chain.username'));
                $password = (string) config('services.supply_chain.password');

                if ($username === '' || $password === '') {
                    throw new ProcessException('Kredensial Aplikasi Internal belum dikonfigurasi.', 503);
                }

                try {
                    $response = $this->client()->post(self::API_PREFIX.'/login', [
                        'username' => $username,
                        'password' => $password,
                    ])->throw();
                } catch (ConnectionException $exception) {
                    report($exception);

                    throw new ProcessException('Layanan Aplikasi Internal sedang tidak dapat dihubungi.', 503);
                } catch (RequestException $exception) {
                    throw $this->requestFailure($exception);
                }

                $payload = $response->json();
                $token = is_array($payload) ? data_get($payload, 'data.api_token') : null;
                if (($payload['success'] ?? false) !== true || blank($token)) {
                    throw new ProcessException($this->internalErrorMessage($payload, 'Autentikasi Aplikasi Internal tidak berhasil.'), 502);
                }

                return (string) $token;
            },
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload, string $token): array
    {
        $response = $this->client($token)
            ->post(self::API_PREFIX.$endpoint, $payload);
        if ($response->failed()) {
            $response->throw();
        }
        $body = $response->json();

        if (! is_array($body) || ($body['success'] ?? false) !== true) {
            Log::warning('Permintaan Aplikasi Internal ditolak.', [
                'endpoint' => $endpoint,
                'message' => is_array($body) ? ($body['message'] ?? null) : null,
            ]);

            throw new ProcessException(
                $this->internalErrorMessage($body, 'Respons Aplikasi Internal tidak dapat diproses.'),
                502,
            );
        }

        $data = $body['data'] ?? [];

        if ($endpoint === '/tambah-customer') {
            $customerNumber = is_array($data) ? ($data['cust_no'] ?? $data['Cust_no'] ?? null) : null;
            if ((! is_string($customerNumber) && ! is_int($customerNumber)) || trim((string) $customerNumber) === '') {
                throw new ProcessException($this->internalErrorMessage($body, 'Nomor customer tidak ditemukan pada respons Aplikasi Internal.'), 502);
            }
            $data['cust_no'] = trim((string) $customerNumber);
        }

        return [
            ...(is_array($data) ? $data : []),
            'message' => $this->responseMessage($body, ''),
        ];
    }

    private function client(?string $token = null): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.supply_chain.base_url'), '/');
        if ($baseUrl === '') {
            throw new ProcessException('Alamat layanan Aplikasi Internal belum dikonfigurasi.', 503);
        }

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.supply_chain.connect_timeout', 3))
            ->timeout((int) config('services.supply_chain.timeout', 30));

        return filled($token) ? $request->withToken($token) : $request;
    }

    private function tokenCacheKey(): string
    {
        return 'supply-chain:token:'.sha1(
            (string) config('services.supply_chain.base_url').'|'.
            (string) config('services.supply_chain.username'),
        );
    }

    private function requestFailure(RequestException $exception): ProcessException
    {
        report($exception);
        $response = $exception->response;
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [
            'message' => 'Respons Aplikasi Internal bukan JSON.',
        ];
        $fallbackMessage = $response->unauthorized() || $response->forbidden()
            ? 'Autentikasi Aplikasi Internal ditolak.'
            : 'Permintaan ke Aplikasi Internal belum berhasil.';

        return new SupplyChainRequestException(
            $this->internalErrorMessage($payload, $fallbackMessage),
            $response->status(),
            $payload,
        );
    }

    private function responseMessage(mixed $payload, string $fallback): string
    {
        $message = is_array($payload) ? ($payload['message'] ?? null) : null;

        return is_string($message) && filled($message) ? $message : $fallback;
    }

    private function internalErrorMessage(mixed $payload, string $fallback): string
    {
        return 'Error pada Aplikasi Internal: '.$this->responseMessage($payload, $fallback);
    }
}
