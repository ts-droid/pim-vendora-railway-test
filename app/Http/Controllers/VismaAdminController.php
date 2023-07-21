<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VismaAdminController extends Controller
{
    const DB_HOST = '91.201.62.100';
    const DB_PORT = '3306';
    const DB_NAME = 'dashsector_vendora';
    const DB_USER = 'dashsector_vendora';
    const DB_PASS = 'dEDsmHazAjorA2cVNs';

    public RemoteDatabaseController $database;

    function __construct()
    {
        $this->database = new RemoteDatabaseController(
            self::DB_HOST,
            self::DB_PORT,
            self::DB_NAME,
            self::DB_USER,
            self::DB_PASS
        );
    }

    public function fetchAll(): void
    {
        $this->fetchCustomers();
        $this->fetchSuppliers();
        $this->fetchCustomerInvoices();
    }

    public function fetchCustomerInvoices(): void
    {
        $documents = $this->database->fetchAll(
            'SELECT *
            FROM OOF
            WHERE DOKTYP = \'F\'
                AND MAKUL = 0'
        );

        if (!$documents) {
            return;
        }

        $invoiceController = new CustomerInvoiceController();

        foreach ($documents as $document) {
            $invoiceData = [
                'invoice_number' => (string) $document->DOKNR,
                'date' => (string) $document->DATUM1,
                'status' => '',
                'customer_number' => (string) $document->KUNDNR,
                'credit_terms' => '',
                'currency' => strtolower($document->VALUTAKOD),
                'amount' => 0,
                'lines' => []
            ];

            // Require invoice number to import
            if (!$invoiceData['invoice_number']) {
                continue;
            }

            // Do not overwrite existing invoices
            $response = $invoiceController->get(new Request([
                'invoice_number' => $invoiceData['invoice_number']
            ]));
            $existingInvoices = ApiResponseController::getDataFromResponse($response);

            if ($existingInvoices) {
                continue;
            }

            // Fetch document lines
            $lines = $this->database->fetchAll(
                'SELECT *
                FROM ARTRAD
                WHERE DOKNR = \'' . $document->DOKNR . '\'
                    AND ARTNR != \'\'
                    AND ARTNR != \' \'
                    AND ARTNR IS NOT NULL
                    AND ARTRAD.MAKUL = 0
                ORDER BY REV ASC'
            );

            if (!$lines) {
                continue;
            }

            $lineIndex = 0;
            foreach ($lines as $line) {
                $invoiceData['lines'][] = [
                    'line_key' => (string) $lineIndex++,
                    'article_number' => (string) $line->ARTNR,
                    'description' => (string) $line->TXT,
                    'order_number' => '',
                    'shipment_number' => '',
                    'line_type' => '',
                    'quantity' => (int) $line->ANTAL1,
                    'unit_price' => (float) $line->PRIS_ST_I,
                    'amount' => (float) $line->BELOPP_I,
                    'cost' => floatval($line->BELOPP_I) - floatval($line->TBBEL),
                    'sales_person_id' => '',
                ];

                $invoiceData['amount'] += (float) $line->BELOPP_I;
            }

            $invoiceController->store(new Request($invoiceData));
        }
    }

    public function fetchSuppliers(): void
    {
        $suppliers = $this->database->fetchAll('SELECT * FROM LEV');

        if (!$suppliers) {
            return;
        }

        $supplierController = new SupplierController();

        foreach ($suppliers as $supplier) {
            $supplierData = [
                'external_id' => $supplier->REV,
                'number' => $supplier->LEVNR,
                'vat_number' => '',
                'org_number' => '',
                'name' => $supplier->NAMN,
                'class_description' => '',
                'credit_terms_description' => '',
                'currency' => strtolower($supplier->VALUTAKOD),
                'language' => strtolower($supplier->SPRAAKKOD),
            ];

            // Require name to import
            if (!$supplierData['name']) {
                continue;
            }

            // Never overwrite existing customers
            $response = $supplierController->get(new Request([
                'name' => $supplierData['name']
            ]));
            $existingSuppliers = ApiResponseController::getDataFromResponse($response);

            if ($existingSuppliers) {
                continue;
            }

            $supplierController->store(new Request($supplierData));
        }
    }

    public function fetchCustomers(): void
    {
        $customers = $this->database->fetchAll('SELECT * FROM KUND');

        if (!$customers) {
            return;
        }

        $customerController = new CustomerController();

        foreach ($customers as $customer) {
            $customerData = [
                'external_id' => (string) $customer->REV,
                'customer_number' => (string) $customer->KUNDNR,
                'vat_number' => (string) $customer->MOMSREGNR,
                'org_number' => (string) $customer->ORGNR,
                'name' => (string) $customer->NAMN,
            ];

            // Require VAT number to import
            if (!$customerData['vat_number']) {
                continue;
            }


            // Never overwrite existing customers
            $response = $customerController->get(new Request([
                'vat_number' => $customerData['vat_number']
            ]));
            $existingCustomers = ApiResponseController::getDataFromResponse($response);

            if ($existingCustomers) {
                continue;
            }

            // Create customer
            $customerController->store(new Request($customerData));
        }
    }
}
