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
