<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'filename', 'type', 'bank', 'path', 'status', 'error_message', 'transactions_count'])]
class RawImport extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (RawImport $rawImport) {
            $rawImport->transactions()->delete();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markDone(int $count): void
    {
        $this->update(['status' => 'done', 'transactions_count' => $count]);
    }

    public function markFailed(string $message): void
    {
        $this->update(['status' => 'failed', 'error_message' => $message]);
    }
}
