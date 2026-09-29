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
use Illuminate\Support\Str;

class HttpSupplyChainGateway implements SupplyChainGateway
{
    private const API_PREFIX = '/api/v1/penjualan-supply-chain';

    public function createCustomer(array $payload): array
    {
        $data = $this->authenticatedPost('/tambah-customer', $payload);

        if (blank($data['cust_no'] ?? null)) {
            throw new ProcessException('Nomor customer tidak ditemukan pada respons Supply Chain.', 502);
        }

        return $data;
    }

    public function saveSale(array $payload): array
    {
        $data = $this->authenticatedPost('/simpan', $payload);

        if (blank($data['jual_no'] ?? null)) {
            throw new ProcessException('Nomor penjualan tidak ditemukan pada respons Supply Chain.', 502);
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
                $existingCustomer = $this->existingCustomerData(
                    $endpoint,
                    $exception->response->json(),
                );
                if ($existingCustomer !== null) {
                    return $existingCustomer;
                }

                throw $this->requestFailure($exception);
            }

            Cache::forget($cacheKey);

            try {
                return $this->post($endpoint, $payload, $this->token());
            } catch (RequestException $retryException) {
                $existingCustomer = $this->existingCustomerData(
                    $endpoint,
                    $retryException->response->json(),
                );
                if ($existingCustomer !== null) {
                    return $existingCustomer;
                }

                throw $this->requestFailure($retryException);
            }
        } catch (ConnectionException $exception) {
            report($exception);

            throw new ProcessException('Layanan Supply Chain sedang tidak dapat dihubungi.', 503);
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
                    throw new ProcessException('Kredensial Supply Chain belum dikonfigurasi.', 503);
                }

                try {
                    $response = $this->client()->post(self::API_PREFIX.'/login', [
                        'username' => $username,
                        'password' => $password,
                    ])->throw();
                } catch (ConnectionException $exception) {
                    report($exception);

                    throw new ProcessException('Layanan Supply Chain sedang tidak dapat dihubungi.', 503);
                } catch (RequestException $exception) {
                    throw $this->requestFailure($exception);
                }

                $payload = $response->json();
                $token = is_array($payload) ? data_get($payload, 'data.api_token') : null;
                if (($payload['success'] ?? false) !== true || blank($token)) {
                    throw new ProcessException('Autentikasi Supply Chain tidak berhasil.', 502);
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

        $existingCustomer = $this->existingCustomerData($endpoint, $body);
        if ($existingCustomer !== null) {
            return $existingCustomer;
        }

        if (! is_array($body) || ($body['success'] ?? false) !== true) {
            Log::warning('Permintaan Supply Chain ditolak.', [
                'endpoint' => $endpoint,
                'message' => is_array($body) ? ($body['message'] ?? null) : null,
            ]);

            throw new ProcessException(
                is_array($body) && filled($body['message'] ?? null)
                    ? (string) $body['message']
                    : 'Respons Supply Chain tidak dapat diproses.',
                502,
            );
        }

        $data = $body['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function existingCustomerData(string $endpoint, mixed $payload): ?array
    {
        if ($endpoint !== '/tambah-customer' || ! is_array($payload)) {
            return null;
        }

        $message = Str::lower((string) ($payload['message'] ?? ''));
        if (! Str::contains($message, ['sudah terdaftar', 'data kembar'])) {
            return null;
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        $customerNumber = trim((string) ($data['cust_no'] ?? $data['Cust_no'] ?? ''));
        if ($customerNumber === '') {
            return null;
        }

        $data['cust_no'] = $customerNumber;

        return $data;
    }

    private function client(?string $token = null): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.supply_chain.base_url'), '/');
        if ($baseUrl === '') {
            throw new ProcessException('Alamat layanan Supply Chain belum dikonfigurasi.', 503);
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
            'message' => 'Respons Supply Chain bukan JSON.',
        ];
        $message = $response->unauthorized() || $response->forbidden()
            ? 'Autentikasi Supply Chain ditolak.'
            : (string) ($payload['message'] ?? 'Permintaan ke Supply Chain belum berhasil.');

        return new SupplyChainRequestException(
            $message,
            $response->status(),
            $payload,
        );
    }
}
