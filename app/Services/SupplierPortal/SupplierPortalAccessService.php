<?php

namespace App\Services\SupplierPortal;

use App\Models\Supplier;

class SupplierPortalAccessService
{
    public function validateAccessKey($accessKey)
    {
        return Supplier::where('access_key', $accessKey)->exists();
    }

    public function getActiveSupplier(): ?Supplier
    {
        $supplier = Supplier::where('access_key', request()->cookie('supplier_access_key'))->first();

        return $supplier ?: null;
    }
}
