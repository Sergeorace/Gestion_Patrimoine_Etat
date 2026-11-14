<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    use HasUuids;

    public $timestamps = true;
    protected $table = 'login_logs';

    protected $fillable = ['user_id', 'email_tente', 'ip', 'user_agent', 'succes', 'motif_echec', 'date'];

    protected $casts = ['succes' => 'boolean', 'date' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
