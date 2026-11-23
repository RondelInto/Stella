<?php

namespace App\Policies;

use App\Models\SopRecord;
use App\Models\User;

/**
 * Who may view a specific SOP. Management sees all; staff only what SopRecord::visibleTo() allows.
 * Laravel finds this policy automatically (SopRecord -> SopRecordPolicy).
 */
class SopRecordPolicy
{
    public function view(User $user, SopRecord $sop): bool
    {
        if ($user->can('manage-sops')) {
            return true;
        }

        return $user->can('view-sops')
            && SopRecord::whereKey($sop->id)->visibleTo($user)->exists();
    }
}
