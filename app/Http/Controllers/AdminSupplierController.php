<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Brand;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Mock of the Vendora admin supplier detail page (matches the General tab
 * shown in the screenshot shared by the user).
 *
 * Like AdminArticleController, this exists only to demo how the Railway
 * test instance could render the supplier admin view. The real editable
 * form lives in adm.vendora.se.
 */
class AdminSupplierController extends Controller
{
    public function show(string $supplierNumber, Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $supplier = Supplier::where('number', $supplierNumber)->first();
        abort_if(!$supplier, 404);

        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $tab = $request->input('tab', 'general');
        $allowedTabs = ['general', 'contacts', 'logistics', 'purchasing'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'general';
        }

        // Link supplier → brand when suppliers.brand_name matches a
        // brands.name row. Data connection already exists in the dump.
        $brand = $supplier->brand_name
            ? Brand::where('name', $supplier->brand_name)->first()
            : null;

        return View::make('admin.supplier', [
            'supplier' => $supplier,
            'apiKey' => $apiKey,
            'activeTab' => $tab,
            'brand' => $brand,
        ]);
    }
}
