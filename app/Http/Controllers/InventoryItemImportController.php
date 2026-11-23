<?php

namespace App\Http\Controllers;

use App\Services\InventoryItemImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import of the inventory item list (Management only; 'can:manage-inventory-items').
 */
class InventoryItemImportController extends Controller
{
    public function create(): View
    {
        return view('inventory.items.import', ['headers' => InventoryItemImporter::HEADERS]);
    }

    public function store(Request $request, InventoryItemImporter $importer): RedirectResponse
    {
        $request->validate([
            // 'txt' is allowed because Windows/Excel often reports CSV files as text/plain.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.mimes' => 'Please upload a CSV file (in Excel: Save As > CSV UTF-8).',
        ]);

        $result = $importer->import($request->file('file')->getRealPath());

        if ($result['errors']) {
            return back()
                ->with('import_errors', $result['errors'])
                ->with('error', count($result['errors']).' row(s) have errors. Nothing was imported. Fix the file and upload it again.');
        }

        return redirect()->route('inventory-items.index')
            ->with('success', "{$result['imported']} item(s) were imported.");
    }

    /** Download an empty CSV template with the correct headers. */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens it as UTF-8 (supports ñ, e.g. "Pequeño")
            fputcsv($out, InventoryItemImporter::HEADERS);
            fclose($out);
        }, 'inventory_items_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
