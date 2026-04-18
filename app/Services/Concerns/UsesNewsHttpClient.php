<?php

namespace App\Services\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

trait UsesNewsHttpClient
{
    /**
     * Laravel HTTP client with optional CA bundle (fixes SSL error 60 on Windows PHP).
     *
     * @param  int  $retryTimes  Number of retries (0 = none).
     */
    protected function httpClient(int $timeout = 120, int $retryTimes = 2): PendingRequest
    {
        $request = Http::timeout($timeout);
        if ($retryTimes > 0) {
            $request = $request->retry($retryTimes, 500, null, false);
        }

        $bundle = config('services.ssl_ca_bundle');
        if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
            return $request->withOptions(['verify' => $bundle]);
        }

        return $request;
    }

    protected function tryFetchUrlText(string $url): ?string
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $res = $this->httpClient(15, 0)
                ->withHeaders([
                    'User-Agent' => 'FakeNewsCheck/1.0 (educational; +'.config('app.url').')',
                ])
                ->get($url);

            if (! $res->successful()) {
                return null;
            }

            $html = $res->body();
            if (strlen($html) > 500_000) {
                $html = substr($html, 0, 500_000);
            }

            $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
            $plain = trim($plain);

            return Str::limit($plain, 15_000, '…');
        } catch (Throwable) {
            return null;
        }
    }
}
