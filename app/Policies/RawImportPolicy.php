<?php

namespace App\Policies;

use App\Models\RawImport;
use App\Models\User;

class RawImportPolicy
{
    public function delete(User $user, RawImport $rawImport): bool
    {
        return $user->id === $rawImport->user_id;
    }
}
