<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'role', 'content'])]
class Message extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
