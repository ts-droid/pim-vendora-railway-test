<?php

namespace App\Services\SupplierPortal;

use App\Models\Supplier;

class SupplierPortalAccessService
{
    public static function validateAccessKey($accessKey)
    {
        return Supplier::where('access_key', $accessKey)->exists();
    }

    public static function getActiveSupplier(): ?Supplier
    {
        $supplier = Supplier::where('access_key', request()->cookie('supplier_access_key'))->first();

        return $supplier ?: null;
    }
}
