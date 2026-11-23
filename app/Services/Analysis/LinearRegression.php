<?php

namespace App\Services\Analysis;

/**
 * Ordinary least squares (multiple) linear regression, in plain PHP (decision AN5).
 *
 *   y = b0 + b1·x1 + b2·x2 + ... + bn·xn
 *
 * The coefficients b are found by solving the "normal equations"  (XᵀX)·b = Xᵀy
 * with Gaussian elimination. R² (0 to 1) tells how much of the ups and downs in y the model explains.
 */
class LinearRegression
{
    /**
     * @param  array<int, array<int, float>>  $x  one row of predictors per observation (no intercept column)
     * @param  array<int, float>  $y
     * @return array{coefficients: array<int, float>, r2: ?float}|null  coefficients[0] = intercept.
     *         NULL when the data cannot give a unique answer (e.g. too few or repeated rows).
     */
    public static function fit(array $x, array $y): ?array
    {
        $n = count($y);
        if ($n === 0 || $n !== count($x)) {
            return null;
        }

        $rows = array_map(fn ($row) => array_merge([1.0], array_values($row)), array_values($x));
        $y = array_values($y);
        $p = count($rows[0]);

        if ($n < $p) {
            return null;
        }

        // Build XᵀX (p×p) and Xᵀy (p).
        $xtx = array_fill(0, $p, array_fill(0, $p, 0.0));
        $xty = array_fill(0, $p, 0.0);
        foreach ($rows as $i => $row) {
            for ($a = 0; $a < $p; $a++) {
                $xty[$a] += $row[$a] * $y[$i];
                for ($b = 0; $b < $p; $b++) {
                    $xtx[$a][$b] += $row[$a] * $row[$b];
                }
            }
        }

        $coefficients = self::solve($xtx, $xty);
        if ($coefficients === null) {
            return null;
        }

        // R² = 1 − (errors left over ÷ total variation). Undefined when y never changes.
        $mean = array_sum($y) / $n;
        $ssTotal = 0.0;
        $ssResidual = 0.0;
        foreach ($rows as $i => $row) {
            $ssTotal += ($y[$i] - $mean) ** 2;
            $ssResidual += ($y[$i] - self::dot($coefficients, $row)) ** 2;
        }

        return [
            'coefficients' => $coefficients,
            'r2' => $ssTotal > 1e-12 ? max(0.0, 1 - $ssResidual / $ssTotal) : null,
        ];
    }

    /** Predict y for one row of predictors (no intercept column). */
    public static function predict(array $coefficients, array $x): float
    {
        return self::dot($coefficients, array_merge([1.0], array_values($x)));
    }

    /** Gaussian elimination with partial pivoting. NULL if the system has no unique solution. */
    private static function solve(array $a, array $b): ?array
    {
        $n = count($b);

        for ($col = 0; $col < $n; $col++) {
            $pivot = $col;
            for ($row = $col + 1; $row < $n; $row++) {
                if (abs($a[$row][$col]) > abs($a[$pivot][$col])) {
                    $pivot = $row;
                }
            }
            if (abs($a[$pivot][$col]) < 1e-9) {
                return null;
            }

            [$a[$col], $a[$pivot]] = [$a[$pivot], $a[$col]];
            [$b[$col], $b[$pivot]] = [$b[$pivot], $b[$col]];

            for ($row = $col + 1; $row < $n; $row++) {
                $factor = $a[$row][$col] / $a[$col][$col];
                for ($k = $col; $k < $n; $k++) {
                    $a[$row][$k] -= $factor * $a[$col][$k];
                }
                $b[$row] -= $factor * $b[$col];
            }
        }

        $x = array_fill(0, $n, 0.0);
        for ($row = $n - 1; $row >= 0; $row--) {
            $sum = $b[$row];
            for ($k = $row + 1; $k < $n; $k++) {
                $sum -= $a[$row][$k] * $x[$k];
            }
            $x[$row] = $sum / $a[$row][$row];
        }

        return $x;
    }

    private static function dot(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $value) {
            $sum += $value * $b[$i];
        }

        return $sum;
    }
}
