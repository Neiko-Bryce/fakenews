<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['text', 'url', 'image'])],
            'text' => ['required_if:mode,text', 'nullable', 'string', 'max:50000'],
            'url' => ['required_if:mode,url', 'nullable', 'url', 'max:2048'],
            'image' => ['required_if:mode,image', 'nullable', 'file', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required_if' => 'Paste some text to analyze.',
            'url.required_if' => 'Enter a URL to analyze.',
            'image.required_if' => 'Choose an image to analyze.',
        ];
    }
}
