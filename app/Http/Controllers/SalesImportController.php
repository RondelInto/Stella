<?php

namespace App\Http\Controllers;

use App\Models\ImportedPosSale;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesImportBatch;
use App\Services\PosSalesImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module 7: import of sales EXPORTED from each location's POS (Management only; 'can:import-pos-sales').
 * Nothing here talks to the POS itself.
 */
class SalesImportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['location' => ['nullable', 'integer', 'exists:locations,id']]);

        $batches = SalesImportBatch::with(['location', 'importedBy'])
            ->withCount(['sales as unmatched_count' => fn ($q) => $q->whereNull('product_id')])
            ->withSum('sales', 'net_amount')
            ->when($filters['location'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('sales-imports.index', [
            'batches' => $batches,
            'locations' => Location::orderBy('type')->orderBy('name')->get(),
            'filters' => $filters,
            'unmatchedTotal' => ImportedPosSale::whereNull('product_id')->count(),
        ]);
    }

    public function create(): View
    {
        return view('sales-imports.create', [
            'locations' => Location::orderBy('type')->orderBy('name')->get(),
            'headers' => PosSalesImporter::HEADERS,
            'maxRows' => PosSalesImporter::MAX_ROWS,
        ]);
    }

    public function store(Request $request, PosSalesImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            // 'txt' is allowed because Windows/Excel often reports CSV files as text/plain.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.mimes' => 'Please upload a CSV file (in Excel: Save As > CSV UTF-8).',
            'file.max' => 'The file must not be larger than 5 MB. Split it into smaller files.',
        ], ['location_id' => 'location']);

        $file = $request->file('file');
        $result = $importer->import(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            Location::findOrFail($data['location_id']),
            $request->user(),
        );

        if ($result['errors']) {
            return back()->withInput()
                ->with('import_errors', $result['errors'])
                ->with('error', count($result['errors']).' problem(s) found. Nothing was imported. Fix the file and upload it again.');
        }

        $message = number_format($result['batch']->total_records).' sales row(s) were imported.';
        if ($result['unmatched']) {
            $message .= ' '.number_format($result['unmatched']).' row(s) did not match a product. Link them under "Unmatched Products".';
        }

        return redirect()->route('sales-imports.show', $result['batch'])->with('success', $message);
    }

    public function show(SalesImportBatch $batch): View
    {
        $batch->load(['location', 'importedBy']);

        $totals = $batch->sales()->selectRaw(
            'COUNT(*) as lines_count, COUNT(DISTINCT pos_receipt_no) as receipts, SUM(quantity_sold) as quantity, '
            .'SUM(net_amount) as net, SUM(CASE WHEN product_id IS NULL THEN 1 ELSE 0 END) as unmatched'
        )->first();

        $products = $batch->sales()
            ->leftJoin('products', 'products.id', '=', 'imported_pos_sales.product_id')
            ->selectRaw('imported_pos_sales.product_id, products.pos_product_name as product_name, '
                .'MIN(imported_pos_sales.pos_product_name) as file_name, '
                .'SUM(imported_pos_sales.quantity_sold) as quantity, SUM(imported_pos_sales.net_amount) as net, '
                .'COUNT(DISTINCT imported_pos_sales.pos_receipt_no) as receipts')
            ->groupBy('imported_pos_sales.product_id', 'products.pos_product_name',
                DB::raw('CASE WHEN imported_pos_sales.product_id IS NULL THEN imported_pos_sales.pos_product_name END'))
            ->orderByDesc('net')
            ->get();

        return view('sales-imports.show', compact('batch', 'totals', 'products'));
    }

    /** Decision PI7: delete a whole import, but only while no analysis run has used it. */
    public function destroy(SalesImportBatch $batch): RedirectResponse
    {
        if (! $batch->canBeDeleted()) {
            return back()->with('error', 'This import was already used in a sales analysis, so it cannot be deleted.');
        }

        DB::transaction(function () use ($batch) {
            $batch->sales()->delete();
            $batch->delete();
        });

        return redirect()->route('sales-imports.index')
            ->with('success', "Import #{$batch->id} ({$batch->file_name}) and its sales rows were deleted.");
    }

    /** Decision PI4: POS product names that matched no product, grouped across all imports. */
    public function unmatched(): View
    {
        $groups = ImportedPosSale::whereNull('product_id')
            ->selectRaw('pos_product_code, pos_product_name, COUNT(*) as lines_count, SUM(quantity_sold) as quantity, '
                .'MIN(sale_datetime) as first_sale, MAX(sale_datetime) as last_sale, '
                .'COUNT(DISTINCT sales_import_batch_id) as batches')
            ->groupBy('pos_product_code', 'pos_product_name')
            ->orderBy('pos_product_name')
            ->paginate(50);

        return view('sales-imports.unmatched', [
            'groups' => $groups,
            'products' => Product::orderBy('pos_product_name')->get(['id', 'pos_product_name', 'pos_product_code', 'status']),
        ]);
    }

    /** Link every unmatched row with this POS code + name to a product. */
    public function link(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pos_product_name' => ['required', 'string', 'max:255'],
            'pos_product_code' => ['nullable', 'string', 'max:50'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
        ], [], ['product_id' => 'product']);

        $updated = ImportedPosSale::whereNull('product_id')
            ->where('pos_product_name', $data['pos_product_name'])
            ->when(
                $data['pos_product_code'] ?? null,
                fn ($q, $code) => $q->where('pos_product_code', $code),
                fn ($q) => $q->whereNull('pos_product_code'),
            )
            ->update(['product_id' => $data['product_id']]);

        $product = Product::find($data['product_id']);

        return back()->with('success', "{$updated} sales row(s) of \"{$data['pos_product_name']}\" were linked to {$product->pos_product_name}.");
    }

    /** Empty template with the correct headers. */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, PosSalesImporter::HEADERS);
            fclose($out);
        }, 'pos_sales_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
