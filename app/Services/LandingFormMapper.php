<?php

namespace App\Services;

/**
 * Maps between resolved landing data and the small admin form (hero, footer, branding only).
 * Other copy stays on defaults from config/landing.php or prior DB merges.
 */
class LandingFormMapper
{
    /**
     * Fields exposed in the admin UI (subset of the full landing array).
     *
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    public function formFromResolved(array $resolved): array
    {
        return [
            'meta' => $resolved['meta'],
            'header' => $resolved['header'],
            'hero' => $resolved['hero'],
            'footer' => $resolved['footer'],
        ];
    }

    /**
     * Merge admin form values into the current resolved landing (keeps nav, input labels, API key copy, etc.).
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $partial
     * @return array<string, mixed>
     */
    public function mergeIntoCurrent(array $current, array $partial): array
    {
        // Do not mutate the resolved landing array from the service (shares structure with config merge).
        $current = unserialize(serialize($current));

        return $this->mergeDeep($current, $this->coerceFormNullsToEmptyStrings($partial));
    }

    /**
     * Laravel may send null for cleared fields; ConvertEmptyStringsToNull also yields null.
     * Stored landing JSON must use strings (never null) so LandingContentValidator matches config.
     *
     * @param  array<string, mixed>  $partial
     * @return array<string, mixed>
     */
    private function coerceFormNullsToEmptyStrings(array $partial): array
    {
        foreach (['meta', 'header', 'hero', 'footer'] as $section) {
            if (! isset($partial[$section]) || ! is_array($partial[$section])) {
                continue;
            }
            foreach ($partial[$section] as $key => $value) {
                if ($value === null) {
                    $partial[$section][$key] = '';
                }
            }
        }

        return $partial;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $replace
     * @return array<string, mixed>
     */
    private function mergeDeep(array $base, array $replace): array
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
