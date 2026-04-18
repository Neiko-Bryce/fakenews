<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'mode',
        'has_image',
        'image_bytes',
        'image_mime',
        'image_client_name',
        'text_length',
        'has_url',
        'status',
        'http_status',
        'error_message',
        'analysis_result',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_image' => 'boolean',
            'has_url' => 'boolean',
            'http_status' => 'integer',
            'image_bytes' => 'integer',
            'text_length' => 'integer',
            'analysis_result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
