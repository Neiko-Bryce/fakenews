<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingCopyFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the fields shown in the admin “Landing page copy” form.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        $line = ['nullable', 'string', 'max:2000'];
        $long = ['nullable', 'string', 'max:5000'];

        return [
            'landing' => ['required', 'array'],

            'landing.meta.document_title' => ['nullable', 'string', 'max:120'],

            'landing.header.brand' => ['nullable', 'string', 'max:255'],
            'landing.header.tagline' => ['nullable', 'string', 'max:500'],

            'landing.hero.badge' => ['nullable', 'string', 'max:500'],
            'landing.hero.headline' => ['nullable', 'string', 'max:500'],
            'landing.hero.intro_before_provider' => $line,
            'landing.hero.intro_after_provider' => $line,
            'landing.hero.intro_driver_env' => ['nullable', 'string', 'max:120'],
            'landing.hero.intro_after_code' => $line,

            'landing.footer.text' => $long,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'landing.meta.document_title' => 'browser tab title',
            'landing.header.brand' => 'site name',
            'landing.header.tagline' => 'header tagline',
            'landing.hero.badge' => 'hero badge',
            'landing.hero.headline' => 'hero headline',
            'landing.hero.intro_before_provider' => 'intro (before provider name)',
            'landing.hero.intro_after_provider' => 'intro (after provider name)',
            'landing.hero.intro_driver_env' => 'env var label',
            'landing.hero.intro_after_code' => 'intro (after env var)',
            'landing.footer.text' => 'footer disclaimer',
        ];
    }
}
