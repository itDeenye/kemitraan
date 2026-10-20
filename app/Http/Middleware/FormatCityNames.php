<?php

namespace App\Http\Middleware;

use App\Services\Reference\CityNameFormatter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class FormatCityNames
{
    public function __construct(private readonly CityNameFormatter $formatter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $response instanceof JsonResponse || ! $response->isSuccessful()) {
            return $response;
        }

        $payload = $response->getData();
        if ($payload instanceof stdClass && property_exists($payload, 'data')) {
            $payload->data = $this->formatter->format($payload->data, $request->is('api/v1/references/cities'));
            $response->setData($payload);
        }

        return $response;
    }
}
