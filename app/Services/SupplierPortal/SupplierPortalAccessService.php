<?php

namespace App\Services\SupplierPortal;

use App\Models\Supplier;

class SupplierPortalAccessService
{
    public static function validateAccessKey($accessKey)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service static method.', $__serviceLogContext);

        return Supplier::where('access_key', $accessKey)->exists();
    }

    public static function getActiveSupplier(): ?Supplier
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service static method.', $__serviceLogContext);

        $supplier = Supplier::where('access_key', request()->input('supplier_access_key'))->first();

        return $supplier ?: null;
    }
}
