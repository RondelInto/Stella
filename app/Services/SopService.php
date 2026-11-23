<?php

namespace App\Services;

use App\Models\SopRecord;
use App\Models\SopVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves SOPs and keeps their version history (team decision S2).
 *
 * Rule: when an ACTIVE SOP's title, description, procedure or effective date changes,
 * the previous version is copied to sop_versions and version_no goes up by 1.
 * Drafts are edited in place (they were never shown to staff, so there is nothing to preserve).
 */
class SopService
{
    /** Fields that, when changed on an active SOP, create a new version. */
    private const VERSIONED_FIELDS = ['title', 'description', 'content', 'effective_date'];

    public function create(User $user, array $data, array $roleIds): SopRecord
    {
        return DB::transaction(function () use ($user, $data, $roleIds) {
            $sop = SopRecord::create($data + [
                'version_no' => 1,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $sop->roles()->sync($roleIds);

            return $sop;
        });
    }

    /** @return bool true when a new version was created */
    public function update(SopRecord $sop, User $user, array $data, array $roleIds): bool
    {
        return DB::transaction(function () use ($sop, $user, $data, $roleIds) {
            $locked = SopRecord::lockForUpdate()->findOrFail($sop->id);
            $wasActive = $locked->isActive();

            $locked->fill($data);
            $newVersion = $wasActive && $locked->isDirty(self::VERSIONED_FIELDS);

            if ($newVersion) {
                // Save the version being replaced, exactly as it was.
                SopVersion::create([
                    'sop_record_id' => $locked->id,
                    'version_no' => $locked->getOriginal('version_no'),
                    'title' => $locked->getOriginal('title'),
                    'description' => $locked->getOriginal('description'),
                    'content' => $locked->getOriginal('content'),
                    'effective_date' => $locked->getOriginal('effective_date'),
                    'changed_by' => $locked->getOriginal('updated_by') ?? $locked->created_by,
                ]);

                $locked->version_no = $locked->getOriginal('version_no') + 1;
            }

            $locked->updated_by = $user->id;
            $locked->save();
            $locked->roles()->sync($roleIds);

            return $newVersion;
        });
    }

    public function changeStatus(SopRecord $sop, User $user, string $status): void
    {
        $sop->update(['status' => $status, 'updated_by' => $user->id]);
    }
}
