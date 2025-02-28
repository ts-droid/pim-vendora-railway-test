<?php

namespace App\Http\Controllers;

use App\Jobs\FetchCustomerInvoicePage;
use App\Models\Article;
use App\Models\CreditNote;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\InventoryReceipt;
use App\Models\PurchaseOrder;
use App\Models\SalesPerson;
use App\Models\Supplier;
use App\Services\ApiLogger;
use App\Services\CreditNoteService;
use App\Services\VismaNet\VismaNetCustomerInvoiceService;
use App\Services\VismaNet\VismaNetSalesOrderService;
use App\Services\WMS\StockItemService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class VismaNetController extends Controller
{
    const API_URL = 'https://integration.visma.net';

    const SLEEP_TIME = 1;
    const PAGE_SIZE = 500;

    const APP_SCOPE = [
        'openid',
        'email',
        'profile',
        'tenants',
        'offline_access',
        'vismanet_erp_interactive_api:create',
        'vismanet_erp_interactive_api:delete',
        'vismanet_erp_interactive_api:read',
        'vismanet_erp_interactive_api:update'
    ];

    const MAX_TRIES = 3; // Maximum number of tries to for callAPI() if the request fails

    // Number of calls made to the API
    private int $callCount = 0;

    // Visma.net app credentials
    private string $clientID = '';
    private string $clientSecret = '';

    // Callback URL
    private string $callbackURL = '';

    /**
     * @throws \Exception
     */
    function __construct()
    {
        $this->clientID = env('VISMA_CLIENT_ID', '');
        $this->clientSecret = env('VISMA_CLIENT_SECRET', '');

        $this->callbackURL = route('visma.callback');

        if (!$this->clientID || !$this->clientSecret) {
            throw new \Exception('Visma.net API credentials not set.');
        }
    }

    /**
     * Fetches all data from Visma.net.
     *
     * @return void
     */
    public function fetchAll(): void
    {
        $this->fetchCustomers();

        $this->fetchSalesPersons();

        $this->fetchSuppliers();

        $this->fetchArticles('', true); // Always fetch all articles to also fetch stock

        $customerInvoiceService = new VismaNetCustomerInvoiceService();
        $customerInvoiceService->fetchCustomerInvoices();

        $this->fetchPurchaseOrders();

        $this->fetchInventoryReceipts();

        $this->fetchCurrencyRates();

        $salesOrderService = new VismaNetSalesOrderService();
        $salesOrderService->fetchSalesOrders();

        StatusIndicatorController::ping('Visma.net sync', 86400);
    }

    /**
     * Fetches currency rate history from Visma.net and stores it locally
     *
     * @return void
     */
    public function fetchCurrencyRates(): void
    {
        $rows = $this->getPagedResult('/v2/currencyrate');

        if (!$rows) {
            return;
        }

        $currencyRateController = new CurrencyRateController();

        foreach ($rows as $data) {
            $currencyRateData = [
                'external_id' => (string) ($data['id'] ?? ''),
                'from_currency' => (string) ($data['fromCurrencyId'] ?? ''),
                'to_currency' => (string) ($data['toCurrencyId'] ?? ''),
                'type' => (string) ($data['rateType'] ?? ''),
                'rate' => (float) ($data['rate'] ?? 0),
                'date' => date('Y-m-d', strtotime($data['effectiveDate'] ?? '')),
                'mult_div' => (string) ($data['multDiv'] ?? ''),
                'rate_reciprocal' => (float) ($data['rateReciprocal'] ?? 0),
            ];

            $response = $currencyRateController->get(new Request([
                'external_id' => $currencyRateData['external_id']
            ]));
            $existingCurrencyRate = ApiResponseController::getDataFromResponse($response);

            if (!$existingCurrencyRate) {
                // Create new currency rate
                $currencyRateController->store(new Request($currencyRateData));
            }
            else {
                // Update existing currency rate
                $existingCurrencyRate = CurrencyRate::find($existingCurrencyRate[0]['id']);
                $currencyRateController->update(new Request($currencyRateData), $existingCurrencyRate);
            }
        }
    }

    /**
     * Fetches inventory receipts from Visma.net updated after the given date.
     * If no date is given, the last updated date is fetched from the database.
     *
     * @param string $updatedAfter
     * @return void
     */
    public function fetchInventoryReceipts(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_inventory_receipts_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-1 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $receipts = $this->getPagedResult('/v1/inventoryReceipt', $params);

        if ($receipts) {
            $receiptController = new InventoryReceiptController();

            foreach ($receipts as $receipt) {
                $fetchedData = true;

                $receiptData = [
                    'receipt_number' => (string) ($receipt['referenceNumber'] ?? ''),
                    'date' => date('Y-m-d', strtotime($order['date'] ?? '')),
                    'status' => (string) ($receipt['status'] ?? ''),
                    'total_cost' => (string) ($receipt['totalCost'] ?? ''),
                    'total_quantity' => (string) ($receipt['totalQuantity'] ?? ''),
                    'lines' => []
                ];

                foreach (($receipt['receiptLines'] ?? []) as $line) {
                    $articleNumber = (string) ($line['inventoryItem']['number'] ?? '');

                    $receiptData['lines'][] = [
                        'line_key' => (string) ($line['lineNumber'] ?? ''),
                        'article_number' => $articleNumber,
                        'description' => (string) ($line['description'] ?? ''),
                        'unit_cost' => (float) ($line['unitCost'] ?? ''),
                        'quantity' => (int) ($line['quantity'] ?? ''),
                        'total_cost' => (float) ($line['extCost'] ?? ''),
                    ];

                    trigger_stock_sync($articleNumber);
                }

                $response = $receiptController->get(new Request([
                    'receipt_number' => $receiptData['receipt_number']
                ]));
                $existingReceipt = ApiResponseController::getDataFromResponse($response);

                if (!$existingReceipt) {
                    // Create new order
                    $receiptController->store(new Request($receiptData));
                }
                else {
                    // Update existing order
                    $existingReceipt = InventoryReceipt::find($existingReceipt[0]['id']);
                    $receiptController->update(new Request($receiptData), $existingReceipt);
                }
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_inventory_receipts_fetch' => $fetchTime]);
        }
    }

    public function fetchPurchaseReceipts(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_purchase_receipts_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-1 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $receipts = $this->getPagedResult('/v2/PurchaseReceipt', $params);

        $purchaseOrderNumbers = [];

        if ($receipts) {
            foreach ($receipts as $receipt) {
                if (!($receipt['lines'] ?? false)) {
                    continue;
                }

                foreach ($receipt['lines'] as $line) {
                    $purchaseOrderNumbers[] = $line['poOrderNbr'];
                }
            }
        }

        $purchaseOrderNumbers = array_unique($purchaseOrderNumbers);
        $purchaseOrderNumbers = array_filter($purchaseOrderNumbers);

        ConfigController::setConfigs(['vismanet_last_purchase_receipts_fetch' => $fetchTime]);

        if (count($purchaseOrderNumbers) == 0) {
            return;
        }

        foreach ($purchaseOrderNumbers as $purchaseOrderNumber) {
            $this->fetchPurchaseOrders('', $purchaseOrderNumber);
        }
    }

    /**
     * Fetches purchase orders from Visma.net updated after the given date.
     * If no date is given, the last updated date is fetched from the database.
     *
     * @param string $updatedAfter
     * @return void
     */
    public function fetchPurchaseOrders(string $updatedAfter = '', string $orderNumber = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        if ($orderNumber) {
            // Fetch a specific order
            $order = $this->callAPI('GET', '/v1/purchaseorder/' . $orderNumber);

            if (empty($order['orderNbr'])) {
                log_data('Could not find order in visma.net with number ' . $orderNumber . '. Failed to fetch update. Response from Visma.net: ' . json_encode($order));
                return;
            }

            $orders = [$order];
        }
        else {
            // Fetch a collection of orders
            $params = [];

            $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_purchase_orders_fetch');

            if ($updatedAfter) {
                $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-1 minutes', strtotime($updatedAfter)));
                $params['lastModifiedDateTimeCondition'] = '>';
            }

            $orders = $this->getPagedResult('/v1/purchaseorder', $params);
        }

        if ($orders) {
            $orderController = new PurchaseOrderController();

            foreach ($orders as $order) {
                $fetchedData = true;

                if ($order['hold'] ?? false) {
                    continue;
                }

                $promisedOn = $order['promisedOn'] ?? '';
                $promisedDate = $promisedOn ? (date('Y-m-d', strtotime($promisedOn))) : '';

                $orderData = [
                    'order_number' => (string) ($order['orderNbr'] ?? ''),
                    'status' => (string) ($order['status'] ?? ''),
                    'date' => date('Y-m-d', strtotime($order['date'] ?? '')),
                    'promised_date' => $promisedDate,
                    'supplier_id' => (string) ($order['supplier']['internalId'] ?? ''),
                    'supplier_number' => (string) ($order['supplier']['number'] ?? ''),
                    'supplier_name' => (string) ($order['supplier']['name'] ?? ''),
                    'currency' => (string) ($order['currency'] ?? ''),
                    'currency_rate' => (float) ($order['exchangeRate'] ?? 0),
                    'amount' => (float) ($order['orderTotal'] ?? 0),
                    'is_draft' => 0,
                    'lines' => []
                ];

                foreach (($order['lines'] ?? []) as $line) {
                    $orderLinePromisedDate = $line['promised'] ?? '';
                    $orderLinePromisedDate = $orderLinePromisedDate ? (date('Y-m-d', strtotime($orderLinePromisedDate))) : '';

                    $articleNumber = (string) ($line['inventory']['number'] ?? '');

                    $orderData['lines'][] = [
                        'line_key' => (string) ($line['lineNbr'] ?? ''),
                        'article_number' => $articleNumber,
                        'description' => (string) ($line['lineDescription'] ?? ''),
                        'quantity' => (int) ($line['orderQty'] ?? 0),
                        'quantity_received' => (int) ($line['qtyOnReceipts'] ?? 0),
                        'unit_cost' => (float) ($line['unitCost'] ?? 0),
                        'amount' => (float) ($line['amount'] ?? 0),
                        'promised_date' => $orderLinePromisedDate,
                        'is_completed' => (int) ($line['completed'] ?? 0),
                        'is_canceled' => (int) ($line['canceled'] ?? 0),
                    ];

                    trigger_stock_sync($articleNumber);
                }

                $response = $orderController->get(new Request([
                    'order_number' => $orderData['order_number']
                ]));
                $existingOrder = ApiResponseController::getDataFromResponse($response);

                if (!$existingOrder) {
                    // Create new order
                    $orderController->store(new Request($orderData));
                }
                else {
                    // Update existing order
                    $existingOrder = PurchaseOrder::find($existingOrder[0]['id']);
                    $orderController->update(new Request($orderData), $existingOrder);
                }
            }
        }

        if (!$orderNumber && $fetchedData) {
            ConfigController::setConfigs(['vismanet_last_purchase_orders_fetch' => $fetchTime]);
        }
    }

    public function fetchCustomerCreditNotes(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_customer_credit_note_fetch');

        $params = [];
        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $creditNotes = $this->getPagedResult('/v1/customerCreditNote', $params);

        if ($creditNotes) {
            $creditNoteService = new CreditNoteService();

            $fetchedData = count($creditNotes) > 0;

            foreach ($creditNotes as $creditNote) {
                if (!is_array($creditNote)) {
                    continue;
                }

                if ($creditNote['hold'] ?? false) {
                    continue;
                }

                $creditNoteData = [
                    'credit_number' => (string) ($creditNote['referenceNumber'] ?? ''),
                    'date' => date('Y-m-d', strtotime($creditNote['documentDate'] ?? '')),
                    'status' => (string) ($creditNote['status'] ?? ''),
                    'customer_number' => (string) ($creditNote['customer']['number'] ?? ''),
                    'currency' => (string) ($creditNote['currencyId'] ?? ''),
                    'amount' => (float) ($creditNote['amount'] ?? 0),
                    'lines' => [],
                ];

                foreach (($creditNote['lines'] ?? []) as $line) {
                    $creditNoteData['lines'][] = [
                        'line_key' => (string) ($line['lineNumber'] ?? ''),
                        'article_number' => (string) ($line['inventoryNumber'] ?? ''),
                        'description' => (string) ($line['description'] ?? ''),
                        'order_number' => (string) ($line['soOrderNbr'] ?? ''),
                        'shipment_number' => (string) ($line['soShipmentNbr'] ?? ''),
                        'quantity' => (int) ($line['quantity'] ?? 0),
                        'unit_price' => (float) ($line['unitPrice'] ?? 0),
                        'amount' => (float) ($line['amount'] ?? 0),
                        'cost' => (float) ($line['cost'] ?? 0),
                    ];
                }

                $existingCreditNote = CreditNote::where('credit_number', $creditNoteData['credit_number'])->first();

                if ($existingCreditNote) {
                    // Update existing credit note
                    $creditNoteService->updateCreditNote($existingCreditNote, $creditNoteData);
                }
                else {
                    // Create new credit note
                    $creditNoteService->storeCreditNote($creditNoteData);
                }
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_customer_credit_note_fetch' => $fetchTime]);
        }
    }

    /**
     * Fetches articles from Visma.net updated after the given date.
     * If no date is given, the last updated date is fetched from the database.
     *
     * @param string $updatedAfter
     * @return void
     */
    public function fetchArticles(string $updatedAfter = '', bool $forceUpdate = false): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_article_fetch');

        if ($forceUpdate) {
            $updatedAfter = '';
        }

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $articles = $this->getPagedResult('/v1/inventory', $params);

        if ($articles) {
            foreach ($articles as $article) {
                $fetchedData = true;

                $updateData = [
                    'external_id' => (string) ($article['inventoryId'] ?? ''),
                    'article_number' => (string) ($article['inventoryNumber'] ?? ''),
                    'cost_price_avg' => (float) ($article['costPriceStatistics']['averageCost'] ?? 0),
                    'stock' => 0,
                    'stock_warehouse' => 0,
                    'stock_on_hand' => 0,
                    'stock_available_for_shipment' => 0,
                ];

                // Fetch stock
                $warehouseDetails = $article['warehouseDetails'] ?? [];
                foreach ($warehouseDetails as $warehouse) {
                    $updateData['stock'] += (int) ($warehouse['available'] ?? 0);
                    $updateData['stock_warehouse'] += (int) ($warehouse['warehouse'] ?? 0);
                    $updateData['stock_on_hand'] += (int) ($warehouse['quantityOnHand'] ?? 0);
                    $updateData['stock_available_for_shipment'] += (int) ($warehouse['availableForShipment'] ?? 0);
                }

                // Require article number to fetch
                if (!$updateData['article_number']) {
                    continue;
                }

                $existingArticle = Article::where('article_number', $updateData['article_number'])->first();
                if (!$existingArticle) {
                    continue;
                }

                // Fetch detailed stock
                if (should_sync_stock($updateData['article_number'])) {
                    $updateData['stock_manageable'] = 0;

                    $detailedStock = $this->callAPI('GET', '/v1/inventorysummary/' . $updateData['article_number']);
                    foreach ($detailedStock as $stock) {
                        $stockLocationName = trim($stock['location']['name'] ?? '');

                        if ($stockLocationName === '1') {
                            $updateData['stock_manageable'] += $stock['onHand'];
                            break;
                        }
                    }

                    clear_stock_sync($updateData['article_number']);
                }

                // Update existing article
                $hasUpdate = false;
                foreach ($updateData as $key => $value) {
                    if ($value != $existingArticle->{$key}) {
                        $hasUpdate = true;
                        break;
                    }
                }

                if (!$hasUpdate) {
                    continue;
                }

                DB::table('articles')->where('id', $existingArticle->id)
                    ->update($updateData);

                // Log stock change
                $stockLogController = new StockLogController();
                $stockLogController->logStock($existingArticle->article_number, $updateData['stock']);
            }
        }

        if ($fetchedData) {
            $stockItemService = new StockItemService();
            $stockItemService->updateAllStockMovements();

            ConfigController::setConfigs(['vismanet_last_article_fetch' => $fetchTime]);
        }
    }

    /**
     * Fetches suppliers from Visma.net updated after the given date.
     * If no date is given, the last updated date is fetched from the database.
     *
     * @param string $updatedAfter
     * @return void
     */
    public function fetchSuppliers(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_supplier_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $suppliers = $this->getPagedResult('/v1/supplier', $params);

        if ($suppliers) {
            $supplierController = new SupplierController();

            foreach ($suppliers as $supplier) {
                $fetchedData = true;

                $supplierEmail = $supplier['supplierContact']['email'] ?? '';
                if (!$supplierEmail) {
                    $supplierEmail = $supplier['mainContact']['email'] ?? '';
                }

                $supplierData = [
                    'external_id' => (string) ($supplier['internalId'] ?? ''),
                    'number' => (string) ($supplier['number'] ?? ''),
                    'vat_number' => (string) ($supplier['vatRegistrationId'] ?? ''),
                    'org_number' => (string) ($supplier['corporateId'] ?? ''),
                    'name' => (string) ($supplier['name'] ?? ''),
                    'class_description' => (string) ($supplier['supplierClass']['description'] ?? ''),
                    'credit_terms_description' => (string) ($supplier['creditTerms']['description'] ?? ''),
                    'currency' => (string) ($supplier['currencyId'] ?? ''),
                    'language' => (string) ($supplier['documentLanguage'] ?? ''),
                    'email' => (string) $supplierEmail,
                ];

                // Require supplier number to fetch
                if (!$supplierData['number']) {
                    continue;
                }

                $response = $supplierController->get(new Request([
                    'number' => $supplierData['number']
                ]));
                $existingSuppliers = ApiResponseController::getDataFromResponse($response);

                if (!$existingSuppliers) {
                    // Create new supplier
                    $supplierController->store(new Request($supplierData));
                }
                else {
                    // Update existing supplier
                    $existingSupplier = Supplier::find($existingSuppliers[0]['id']);
                    $supplierController->update(new Request($supplierData), $existingSupplier);
                }
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_supplier_fetch' => $fetchTime]);
        }
    }

    /**
     * Fetches sales persons from Visma.net updated after the given date.
     * If no date is given, the last updated date is fetched from the database.
     *
     * @param string $updatedAfter
     * @return void
     */
    public function fetchSalesPersons(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_sales_persons_fetch');

        $customerController = new CustomerController();
        $salesPersonController = new SalesPersonController();

        $response = $customerController->get(new Request());

        $customers = ApiResponseController::getDataFromResponse($response);

        foreach ($customers as $customer) {
            if ($updatedAfter && strtotime($updatedAfter) > strtotime($customer['updated_at'])) {
                continue;
            }

            $salesPersons = $this->getPagedResult('/v1/customer/' . $customer['customer_number'] . '/salespersons');

            if (!$salesPersons) {
                continue;
            }

            foreach ($salesPersons as $salesPerson) {
                $fetchedData = true;

                $salesPersonData = [
                    'external_id' => (string) ($salesPerson['salePersonID'] ?? ''),
                    'name' => (string) ($salesPerson['name'] ?? ''),
                ];

                $response = $salesPersonController->get(new Request([
                    'external_id' => $salesPersonData['external_id']
                ]));
                $existingSalesPersons = ApiResponseController::getDataFromResponse($response);

                if (!$existingSalesPersons) {
                    // Create new sales person
                    $salesPersonController->store(new Request($salesPersonData));
                }
                else {
                    // Update existing sales person
                    $existingSalesPerson = SalesPerson::find($existingSalesPersons[0]['id']);
                    $salesPersonController->update(new Request($salesPersonData), $existingSalesPerson);
                }

                // Connect to the customer if this is the default sales person
                if (($salesPerson['isDefault'] ?? false) || count($salesPersons) === 1) {
                    $customerData = [
                        'sales_person_id' => $salesPersonData['external_id']
                    ];

                    $existingCustomer = Customer::find($customer['id']);
                    $customerController->update(new Request($customerData), $existingCustomer);
                }
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_sales_persons_fetch' => $fetchTime]);
        }
    }

    /**
     * Fetches customers from Visma.net updated after the given date.
     * If no date is given, the last updated date is fetched from the database.
     *
     * @param string $updatedAfter
     * @return void
     */
    public function fetchCustomers(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_customer_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $customers = $this->getPagedResult('/v1/customer', $params);

        if ($customers) {
            $customerController = new CustomerController();

            foreach ($customers as $customer) {
                $fetchedData = true;

                $customerData = [
                    'external_id' => (string) ($customer['internalId'] ?? ''),
                    'customer_number' => (string) ($customer['number'] ?? ''),
                    'vat_number' => (string) ($customer['vatRegistrationId'] ?? ''),
                    'org_number' => (string) ($customer['corporateId'] ?? ''),
                    'name' => (string) ($customer['name'] ?? ''),
                    'country' => (string) ($customer['mainAddress']['country']['id'] ?? ''),
                    'credit_limit' => (float) ($customer['creditLimit'] ?? 0),
                    'credit_terms' => (int) ($customer['creditTerms']['description'] ?? 0)
                ];

                // Require vat number to fetch
                if (!$customerData['vat_number']) {
                    continue;
                }

                $response = $customerController->get(new Request([
                    'vat_number' => $customerData['vat_number']
                ]));
                $existingCustomers = ApiResponseController::getDataFromResponse($response);

                if (!$existingCustomers) {
                    // Create new customer
                    $customerController->store(new Request($customerData));
                }
                else {
                    // Update existing customer
                    $existingCustomer = Customer::find($existingCustomers[0]['id']);
                    $customerController->update(new Request($customerData), $existingCustomer);
                }
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_customer_fetch' => $fetchTime]);
        }
    }

    /**
     * Returns the visma.net shipment data
     * @param string $shipmentNumber
     * @return array
     */
    public function getShipment(string $shipmentNumber): array
    {
        return $this->callAPI('GET', '/v1/shipment/' . $shipmentNumber);
    }

    /**
     * Returns the visma.net customer data
     * @param string $customerNumber
     * @return array|mixed
     */
    public function getCustomer(string $customerNumber)
    {
        return $this->callAPI('GET', '/v1/customer/' . $customerNumber);
    }

    /**
     * Returns the visma.net article data
     * @param string $articleNumber
     * @return array|mixed
     */
    public function getInventoryItem(string $articleNumber)
    {
        return $this->callAPI('GET', '/v1/inventory/' . $articleNumber);
    }

    /**
     * Returns the visma.net sales order data
     * @param string $orderType
     * @param string $orderNumber
     * @return array|mixed
     */
    public function getSalesOrder(string $orderType, string $orderNumber)
    {
        $endpoint = '/v1/salesorder/' . $orderNumber;
        if ($orderType) {
            $endpoint = '/v1/salesorder/' . $orderType . '/' . $orderNumber;
        }

        return $this->callAPI('GET', $endpoint);
    }

    /**
     * Handles the oauth2 callback.
     *
     * @param Request $request
     * @return array
     */
    public function authCallback(Request $request): array
    {
        $authCode = $request->code ?? null;
        if (!$authCode) {
            return array(false, 'No auth code provided in the request.');
        }

        list($success, $data) = $this->generateAccessToken($authCode, true);

        if (!$success) {
            return array(false, $data);
        }

        return array(true, '');
    }

    /**
     * Returns the URL to redirect the user to for authentication.
     *
     * @return string
     */
    public function getAuthURL()
    {
        return 'https://connect.visma.com/connect/authorize?' . http_build_query([
                'client_id' => $this->clientID,
                'scope' => implode(' ', self::APP_SCOPE),
                'response_type' => 'code',
                'response_mode' => 'form_post',
                'redirect_uri' => $this->callbackURL,
            ]);
    }

    /**
     * Returns true if the app is authenticated.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $data = $this->getPagedResult('/v1/organization');

        $orgName = $data[0]['name'] ?? '';

        if (!$orgName) {
            return false;
        }

        return true;
    }

    /**
     * Returns paged result from the API.
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    private function getPagedResult(string $endpoint, array $params = []): array
    {
        $params['pageSize'] = self::PAGE_SIZE;

        if (!isset($params['pageNumber'])) {
            $params['pageNumber'] = 1;
        }

        // Convert boolean values to string
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            }
        }

        $rows = $this->callAPI('GET', ($endpoint . '?' . http_build_query($params)));

        if ($rows && count($rows) === self::PAGE_SIZE) {
            $params['pageNumber']++;
            $rows = array_merge($rows, $this->getPagedResult($endpoint, $params));
        }

        return $rows;
    }

    /**
     * Makes a call to the API and returns the result.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param string $accessToken
     * @param int $tries
     * @return array|mixed
     */
    public function callAPI(string $method, string $endpoint, array $params = [], string $accessToken = '', int $tries = 0)
    {
        if ($this->callCount > 0) {
            sleep(self::SLEEP_TIME);
        }

        $headers = [
            'Authorization' => 'Bearer ' . ($accessToken ?: $this->getAccessToken()),
        ];

        if ($params) {
            $headers['Content-Type'] = 'application/json';
        }

        if (substr($endpoint, 0, '4') === 'http') {
            $url = $endpoint;
        }
        else {
            $url = self::API_URL . '/API/controller/api' . $endpoint;
        }

        try {
            switch (strtoupper($method)) {
                case 'POST':
                    $response = HTTP::withHeaders($headers)
                        ->connectTimeout(600)
                        ->timeout(600)
                        ->post($url, $params);
                    break;

                case 'GET':
                default:
                    $response = HTTP::withHeaders($headers)
                        ->connectTimeout(600)
                        ->timeout(600)
                        ->get($url);
                    break;
            }
        }
        catch (Exception $e) {
            if ($tries < self::MAX_TRIES) {
                $tries++;
                return $this->callAPI($method, $endpoint, $params, $accessToken, $tries);
            }

            return [];
        }

        $this->callCount++;

        return $response->json() ?: [];
    }

    /**
     * Returns the saved access token, generates new one if expired.
     *
     * @return string
     */
    private function getAccessToken(): string
    {
        $accessToken = ConfigController::getConfig('vismanet_access_token');
        $refreshToken = ConfigController::getConfig('vismanet_refresh_token');
        $expiresAt = ConfigController::getConfig('vismanet_token_expires_at');

        if ($expiresAt < (time() - 60)) {
            list($success, $accessToken) = $this->generateAccessToken($refreshToken);

            if (!$success) {
                return '';
            }
        }

        return $accessToken;
    }

    /**
     * Generates and returns an access token.
     *
     * @param string $code
     * @param bool $isAuthCode
     * @return array
     */
    private function generateAccessToken(string $code, bool $isAuthCode = false): array
    {
        if ($isAuthCode) {
            $params = [
                'client_id' => $this->clientID,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackURL,
            ];
        }
        else {
            $params = [
                'client_id' => $this->clientID,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $code
            ];
        }

        $response = Http::asForm()->post('https://connect.visma.com/connect/token', $params)
            ->json();

        $accessToken = $response['access_token'] ?? '';
        $refreshToken = $response['refresh_token'] ?? '';
        $expiresIn = $response['expires_in'] ?? 0;
        $expiresAt = time() + $expiresIn;

        ConfigController::setConfigs([
            'vismanet_access_token' => $accessToken,
            'vismanet_refresh_token' => $refreshToken,
            'vismanet_token_expires_at' => $expiresAt,
        ]);

        if (!$accessToken) {
            return array(false, json_encode($response));
        }

        return array(true, $accessToken);
    }
}
