<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'billing.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allows($user, 'billing.view', $invoice);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'billing.manage');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->allows($user, 'billing.manage', $invoice);
    }

    public function refund(User $user, Invoice $invoice): bool
    {
        return $this->allows($user, 'billing.refund', $invoice);
    }
}
