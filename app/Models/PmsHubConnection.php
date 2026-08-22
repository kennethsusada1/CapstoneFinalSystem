<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PmsHubConnection extends Model
{
    protected $table = 'pms_hub_connection';

    protected $fillable = [
        'status',
        'pms_base_url',
        'pms_callback_token',
        'requested_at',
        'accepted_at',
    ];

    protected $hidden = [
        'pms_callback_token',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'accepted_at'  => 'datetime',
    ];

    // Status constants
    const STATUS_DISCONNECTED = 'disconnected';
    const STATUS_PENDING      = 'pending';
    const STATUS_CONNECTED    = 'connected';
    const STATUS_REJECTED     = 'rejected';

    /**
     * Get the singleton row. Creates it if it doesn't exist yet.
     */
    public static function instance(): static
    {
        return static::firstOrCreate([], [
            'status' => self::STATUS_DISCONNECTED,
        ]);
    }
}
