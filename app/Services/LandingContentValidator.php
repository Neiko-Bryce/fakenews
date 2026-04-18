<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class LandingContentValidator
{
    /**
     * Ensure the payload matches the shape of config('landing').
     */
    public function validate(array $data): void
    {
        $template = config('landing');
        $this->assertMatches($data, $template, '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $template
     */
    private function assertMatches(array $data, array $template, string $path): void
    {
        foreach ($template as $key => $templateValue) {
            if (! array_key_exists($key, $data)) {
                throw ValidationException::withMessages([
                    'landing_json' => 'Missing key: '.$this->pathKey($path, $key),
                ]);
            }
            $this->assertValueMatches($data[$key], $templateValue, $this->pathKey($path, $key));
        }
        foreach ($data as $key => $_) {
            if (! array_key_exists($key, $template)) {
                throw ValidationException::withMessages([
                    'landing_json' => 'Unknown key: '.$this->pathKey($path, $key),
                ]);
            }
        }
    }

    private function assertValueMatches(mixed $value, mixed $templateValue, string $path): void
    {
        if (is_array($templateValue) && is_array($value)) {
            $tList = $this->isList($templateValue);
            $vList = $this->isList($value);
            if ($tList && $vList) {
                if ($this->isSegmentList($templateValue)) {
                    $this->validateSegmentList($value, $path);

                    return;
                }
                throw ValidationException::withMessages([
                    'landing_json' => 'Unsupported list type at '.$path,
                ]);
            }
            if (! $tList && ! $vList) {
                $this->assertMatches($value, $templateValue, $path);

                return;
            }
            throw ValidationException::withMessages([
                'landing_json' => 'Structure mismatch at '.$path,
            ]);
        }
        if (! is_array($templateValue) && ! is_array($value)) {
            if (gettype($value) !== gettype($templateValue)) {
                throw ValidationException::withMessages([
                    'landing_json' => 'Invalid type at '.$path,
                ]);
            }

            return;
        }
        throw ValidationException::withMessages([
            'landing_json' => 'Invalid structure at '.$path,
        ]);
    }

    /**
     * @param  array<int, mixed>  $array
     */
    private function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * @param  array<int, mixed>  $templateList
     */
    private function isSegmentList(array $templateList): bool
    {
        if ($templateList === []) {
            return true;
        }
        $first = $templateList[0];

        return is_array($first)
            && isset($first['type'], $first['value'])
            && in_array($first['type'], ['text', 'code', 'link'], true);
    }

    /**
     * @param  array<int, mixed>  $segmentList
     */
    private function validateSegmentList(array $segmentList, string $path): void
    {
        foreach ($segmentList as $i => $segment) {
            $p = $path.'['.$i.']';
            if (! is_array($segment)) {
                throw ValidationException::withMessages([
                    'landing_json' => 'Invalid segment at '.$p,
                ]);
            }
            $type = $segment['type'] ?? null;
            if (! in_array($type, ['text', 'code', 'link'], true)) {
                throw ValidationException::withMessages([
                    'landing_json' => 'Invalid segment type at '.$p,
                ]);
            }
            if (! isset($segment['value']) || ! is_string($segment['value'])) {
                throw ValidationException::withMessages([
                    'landing_json' => 'Invalid segment value at '.$p,
                ]);
            }
            if ($type === 'link') {
                if (empty($segment['href']) || ! is_string($segment['href'])) {
                    throw ValidationException::withMessages([
                        'landing_json' => 'Segment link requires href at '.$p,
                    ]);
                }
            }
        }
    }

    private function pathKey(string $path, string $key): string
    {
        return $path === '' ? $key : $path.'.'.$key;
    }
}
