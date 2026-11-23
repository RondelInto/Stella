# SAMPLE DATA — NOT REAL COMPANY DATA

These files are **samples for development and testing only**.
Item names start with `SAMPLE` and codes start with `SMP-` so they are easy to spot and remove.
Unit costs are `0.00` on purpose: real costs must come from Stella's Cuisine.

**Never import these files into the real (production) database.**

## Files

| File | Purpose |
|---|---|
| `SAMPLE_inventory_items.csv` | Shows the import template with valid rows. |
| `SAMPLE_inventory_items_with_errors.csv` | One valid row + one deliberate mistake per row, to test that the importer rejects bad data. |
| `SAMPLE_pos_sales.csv` | POS sales in our import template (module 7). Products and prices are invented. |
| `SAMPLE_pos_sales_with_errors.csv` | One valid row + one deliberate mistake per row, to test that the sales import rejects the whole file. |

## Inventory item import template

Save the spreadsheet as **CSV UTF-8 (Comma delimited)** in Excel.
The first row must be exactly these column headers:

| Column | Required | Rules | Example |
|---|---|---|---|
| `item_code` | Yes | Unique; letters, numbers, `.` `_` `-`; no spaces | `KAK-001` |
| `name` | Yes | Max 255 characters | `Glutinous Rice Flour` |
| `category` | Yes | Must match an existing category name (e.g. Beverages, Cooked Food, Kakanin) | `Kakanin` |
| `item_type` | Yes | `ingredient`, `in_preparation`, or `finished_product` | `ingredient` |
| `unit_of_measure` | Yes | Max 30 characters | `kg` |
| `unit_cost` | Yes | Number, 0 or more, up to 2 decimals (no `₱` sign, no commas) | `55.50` |
| `reorder_level` | Yes | Number, 0 or more, up to 3 decimals | `10` |

Stock quantities are **not** part of this file. Opening stock is entered through the
first stock count at each location (team decision, Module 4 Q5).

## POS sales import template (module 7)

**This is OUR template, not the real POS export format.** The owner's POS export file is not
available yet (team decision PI1). When it arrives, only the column mapping in
`app/Services/PosSalesImporter.php` should need to change.

One row = one item sold on one receipt. Each file comes from **one** location's POS;
the location is picked on the upload form.

| Column | Required | Rules | Example |
|---|---|---|---|
| `receipt_no` | Yes | Max 100 characters | `R-000123` |
| `sale_datetime` | Yes | `YYYY-MM-DD HH:MM` (Excel's `MM/DD/YYYY HH:MM` also works); not in the future | `2026-11-03 14:30` |
| `product_code` | No | The POS product code; matched to the product's POS code first | `PAL-B` |
| `product_name` | Yes | Matched by name if the code does not match (case and extra spaces ignored) | `Pancit Palabok Bilao` |
| `quantity` | Yes | More than 0, up to 3 decimals (refunds/voids are not imported) | `2` |
| `unit_price` | Yes | Number, 0 or more, up to 2 decimals, no `₱` or commas | `350.00` |
| `discount` | No | Number, 0 or more; blank = 0 | `35.00` |
| `net_amount` | No | Blank = quantity × unit price − discount | `665.00` |

Rules:

- If any row has an error, **nothing** is imported (PI5).
- Products not in the system are imported as **unmatched** and linked later (PI4).
- A file whose dates overlap an earlier import for the same location is rejected (PI6).
- The original row is stored unchanged in `raw_data`.

### Expected results of the samples

- `SAMPLE_pos_sales.csv`: 10 rows, 7 receipts, ₱660.00 net, period Nov 20–21, 2026.
  Without matching products in the system, every row is unmatched. If you add products
  with POS codes `SMP-P01`, `SMP-P02`, `SMP-P03` first, only `SMP-P04` stays unmatched.
- `SAMPLE_pos_sales_with_errors.csv`: rejected. Row 3 has an impossible date (Nov 31), row 4 a negative
  quantity, row 5 a comma in the price, row 6 no receipt number, row 7 no product name,
  row 8 a discount larger than the line total.

## SAMPLE sales for trying Sales Analysis (module 8)

The forecast needs at least 28 days of sales, so the small CSV above is not enough to see it work.
For development and demos only, generate 60 days of **SAMPLE** sales:

```
php artisan db:seed --class=SampleSalesDataSeeder
```

- Adds 6 products named `SAMPLE …` (item codes `SMP-AN-01` to `SMP-AN-06`) with invented costs and prices.
- Adds 60 days of invented sales (up to yesterday) for the first two active branches, shown under
  POS Sales Imports as **"SAMPLE generated test data"**.
- Running it again adds nothing. It refuses to run when `APP_ENV=production`.
- These imports block real imports for the same dates and branches (overlap rule PI6). Delete them under
  POS Sales Imports before importing real files. They can only be deleted before an analysis uses them,
  so use a test database for demos, or reset the dev database with `php artisan migrate:fresh --seed`
  (**this erases all data in that database**).
