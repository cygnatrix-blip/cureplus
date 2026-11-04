<?php

namespace App\Services\Portal;

use App\Models\Consent;
use App\Models\Patient;
use App\Models\PatientAccount;
use Illuminate\Support\Facades\DB;

/**
 * Carries out a deletion request. The login and personal profile go; clinical and
 * billing records stay with each organization, which must keep them by law. Whether
 * the retention periods are right is a question for legal counsel, not for this code.
 */
class AccountEraser
{
    public function erase(PatientAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            Consent::query()->where('patient_account_id', $account->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            Patient::withoutTenancy()->where('patient_account_id', $account->id)->update(['patient_account_id' => null]);

            $account->forceFill([
                'email' => 'erased-'.$account->id.'@erased.invalid',
                'name' => null,
                'mobile' => null,
                'status' => 'erased',
            ])->save();
            $account->delete();
        });
    }
}
