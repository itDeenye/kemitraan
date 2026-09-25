<?php

namespace App\Integrations\Stc;

use App\Contracts\Integrations\StcGateway;
use App\Exceptions\ProcessException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HttpStcGateway implements StcGateway
{
    /** @var array<string, string> */
    private const SORT_FIELDS = [
        'id' => 'ewallet_log_id',
        'datetime' => 'ewallet_log_datetime',
        'amount' => 'ewallet_log_value',
        'type' => 'ewallet_log_type',
        'category' => 'ewallet_log_category',
    ];

    public function balance(): array
    {
        return $this->get('stc-user');
    }

    public function mutations(array $params): array
    {
        return $this->get('list-mutasi', $this->listQuery($params));
    }

    public function topUps(array $params): array
    {
        Arr::set($params, 'filter.category', 'topup');

        return $this->get('list-mutasi', $this->listQuery($params));
    }

    public function topUpOptions(): array
    {
        return $this->get('topup-va');
    }

    public function expressShippingRates(array $params): array
    {
        $payload = $this->post('/api/shipping/price-express', $params);
        $rates = $payload['results'] ?? null;

        if (($payload['status'] ?? false) !== true || ! is_array($rates)) {
            throw new ProcessException('Tarif pengiriman belum tersedia. Silakan coba kembali.', 502);
        }

        $details = $payload['details'] ?? [];

        return [
            'details' => is_array($details) ? $details : [],
            'results' => array_values(array_filter($rates, 'is_array')),
        ];
    }

    public function instantShippingRates(array $params): array
    {
        $payload = $this->post('/api/shipping/price-instant', $params);
        $rates = $payload['result']
            ?? $payload['results']
            ?? data_get($payload, 'data.result');

        if (($payload['status'] ?? true) === false || ! is_array($rates)) {
            throw new ProcessException('Tarif pengiriman instan belum tersedia. Silakan coba kembali.', 502);
        }

        $payload['result'] = array_values(array_filter($rates, 'is_array'));

        return $payload;
    }

    public function expressPickupSchedules(): array
    {
        $payload = $this->post('/api/shipping/schedule-express');
        $schedules = $payload['schedules'] ?? null;

        if (($payload['status'] ?? false) !== true || ! is_array($schedules)) {
            throw new ProcessException('Jadwal pengambilan paket belum tersedia. Silakan coba kembali.', 502);
        }

        return array_values(array_filter($schedules, 'is_array'));
    }

    public function createExpressPickup(array $params): array
    {
        $payload = $this->post('/api/shipping/pickup-express', $params);

        if (($payload['status'] ?? true) === false || blank($payload['pickup_number'] ?? null)) {
            throw new ProcessException('Permintaan pengambilan paket belum berhasil. Silakan coba kembali.', 502);
        }

        $package = collect($payload['details'] ?? [])->first(
            fn (mixed $detail): bool => is_array($detail) && filled($detail['order_id'] ?? null)
        );

        return [
            'pickup_number' => (string) $payload['pickup_number'],
            'order_id' => is_array($package) ? (string) ($package['order_id'] ?? '') : '',
            'tracking_number' => is_array($package)
                ? ($package['awb'] ?? $package['waybill'] ?? null)
                : null,
        ];
    }

    public function createInstantPickup(array $params): array
    {
        $payload = $this->post('/api/shipping/pickup-instant', $params);

        if (($payload['status'] ?? false) !== true) {
            throw new ProcessException('Permintaan kurir instan belum berhasil. Silakan coba kembali.', 502);
        }

        $package = collect($payload['details'] ?? [])->first(
            fn (mixed $detail): bool => is_array($detail) && filled($detail['order_id'] ?? null)
        );
        if (! is_array($package)) {
            throw new ProcessException('Data pengambilan paket instan tidak dapat diproses. Silakan coba kembali.', 502);
        }

        return [
            'order_id' => (string) $package['order_id'],
            'tracking_number' => filled($package['awb'] ?? null)
                ? (string) $package['awb']
                : null,
            'status' => $package['status'] ?? null,
        ];
    }

    public function trackExpress(string $orderId): array
    {
        return $this->post('/api/shipping/tracking-express', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client()->get($this->endpoint($endpoint), $query)->throw();
        } catch (ConnectionException $exception) {
            report($exception);

            throw new ProcessException('Layanan pengiriman sedang tidak dapat dihubungi. Silakan coba kembali.', 503);
        } catch (RequestException $exception) {
            report($exception);

            $message = $exception->response->unauthorized() || $exception->response->forbidden()
                ? 'Layanan pengiriman belum dapat digunakan. Silakan hubungi administrator.'
                : 'Data layanan pengiriman belum dapat dimuat. Silakan coba kembali.';

            throw new ProcessException($message, 502);
        }

        $payload = $response->json();
        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            throw new ProcessException('Data dari layanan pengiriman tidak dapat diproses. Silakan coba kembali.', 502);
        }

        return $payload['data'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->client()->post($endpoint, $data)->throw();
        } catch (ConnectionException $exception) {
            report($exception);

            throw new ProcessException('Layanan pengiriman sedang tidak dapat dihubungi. Silakan coba kembali.', 503);
        } catch (RequestException $exception) {
            report($exception);

            $message = $exception->response->unauthorized() || $exception->response->forbidden()
                ? 'Layanan pengiriman belum dapat digunakan. Silakan hubungi administrator.'
                : 'Permintaan pengiriman belum berhasil. Periksa kembali data pengiriman atau coba kembali.';

            throw new ProcessException($message, 502);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new ProcessException('Data dari layanan pengiriman tidak dapat diproses. Silakan coba kembali.', 502);
        }

        return $this->unwrapShippingPayload($payload);
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.stc.base_url'), '/');
        $token = trim((string) config('services.stc.token'));

        if ($baseUrl === '' || $token === '') {
            throw new ProcessException('Layanan pengiriman belum siap digunakan. Silakan hubungi administrator.', 503);
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withToken($token)
            ->connectTimeout((int) config('services.stc.connect_timeout', 3))
            ->timeout((int) config('services.stc.timeout', 10))
            ->retry(
                [100, 500],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
            );
    }

    /** @param array<string, mixed> $payload */
    private function unwrapShippingPayload(array $payload): array
    {
        if (filled($payload['error'] ?? null)) {
            Log::warning('Permintaan pengiriman ditolak oleh penyedia layanan.', [
                'provider_error' => $payload['error'],
            ]);

            throw new ProcessException(
                'Permintaan pengiriman belum berhasil. Periksa kembali data pengiriman atau coba kembali.',
                502,
            );
        }

        $results = data_get($payload, 'data.results');

        return is_array($results) ? $results : $payload;
    }

    private function endpoint(string $endpoint): string
    {
        return "/api/saldo/{$endpoint}";
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listQuery(array $params): array
    {
        $requestedSort = (string) ($params['sort'] ?? '-datetime');
        $descending = Str::startsWith($requestedSort, '-');
        $sortAlias = Str::ltrim($requestedSort, '-');
        $filters = Arr::get($params, 'filter', []);
        $query = [
            'page' => (int) ($params['page'] ?? 1),
            'limit' => (int) ($params['limit'] ?? 10),
            'sort' => self::SORT_FIELDS[$sortAlias] ?? self::SORT_FIELDS['datetime'],
            'dir' => $descending ? 'desc' : 'asc',
        ];
        $providerFilters = [];

        if (is_array($filters) && isset($filters['type'])) {
            $providerFilters[] = [
                'type' => 'string',
                'field' => 'ewallet_log_type',
                'comparison' => '=',
                'value' => $filters['type'],
            ];
        }

        if (is_array($filters) && isset($filters['category'])) {
            $providerFilters[] = [
                'type' => 'string',
                'field' => 'ewallet_log_category',
                'comparison' => '=',
                'value' => $filters['category'],
            ];
        }

        if (isset($params['date_from']) || isset($params['date_to'])) {
            $providerFilters[] = [
                'type' => 'date',
                'comparison' => 'bet',
                'field' => 'ewallet_log_datetime',
                'value' => ($params['date_from'] ?? '1970-01-01').'::'.($params['date_to'] ?? '2999-12-31'),
            ];
        }

        if ($providerFilters !== []) {
            $query['filter'] = $providerFilters;
        }

        return $query;
    }
}
