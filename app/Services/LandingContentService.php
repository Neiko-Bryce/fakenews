<?php

namespace App\Services;

use App\Models\LandingSetting;

class LandingContentService
{
    public function __construct(
        protected LandingContentValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        // Never merge into config('landing') in place — that mutates the cached config array.
        $defaults = $this->cloneArray(config('landing'));
        $stored = LandingSetting::query()->first()?->content;
        if ($stored === null) {
            return $defaults;
        }

        return $this->mergeDeep($defaults, $stored);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(array $payload): void
    {
        $this->validator->validate($payload);
        // Payload is already the full merged tree from mergeIntoCurrent(resolve(), …); persist as-is.
        $row = LandingSetting::query()->first();
        if ($row) {
            $row->update(['content' => $payload]);
        } else {
            LandingSetting::query()->create(['content' => $payload]);
        }
    }

    /**
     * Deep clone so mergeDeep never mutates config() or shared references.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function cloneArray(array $array): array
    {
        return unserialize(serialize($array));
    }

    public function reset(): void
    {
        LandingSetting::query()->delete();
    }

    public function hasStoredContent(): bool
    {
        return LandingSetting::query()->exists();
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $replace
     * @return array<string, mixed>
     */
    protected function mergeDeep(array $base, array $replace): array
    {
        foreach ($replace as $key => $value) {
            if (! array_key_exists($key, $base)) {
                $base[$key] = $value;

                continue;
            }
            $existing = $base[$key];
            if (is_array($value) && is_array($existing)) {
                if (array_is_list($value) || array_is_list($existing)) {
                    $base[$key] = $value;

                    continue;
                }
                $base[$key] = $this->mergeDeep($existing, $value);

                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }
}
