<?php

namespace Tests\Unit;

use App\Services\Analysis\KMeans;
use App\Services\Analysis\LinearRegression;
use PHPUnit\Framework\TestCase;

/**
 * Module 8: the k-means and regression math, checked against answers that can be worked out by hand.
 */
class AnalysisMathTest extends TestCase
{
    public function test_regression_recovers_an_exact_line(): void
    {
        // y = 2 + 3x
        $fit = LinearRegression::fit([[0], [1], [2], [3], [4]], [2, 5, 8, 11, 14]);

        $this->assertEqualsWithDelta(2.0, $fit['coefficients'][0], 1e-9);
        $this->assertEqualsWithDelta(3.0, $fit['coefficients'][1], 1e-9);
        $this->assertEqualsWithDelta(1.0, $fit['r2'], 1e-9);
        $this->assertEqualsWithDelta(23.0, LinearRegression::predict($fit['coefficients'], [7]), 1e-9);
    }

    public function test_regression_with_two_predictors_and_noise(): void
    {
        // y = 1 + 2a − b, with small noise -> coefficients close, R² below 1 but high.
        $x = [[0, 0], [1, 0], [0, 1], [1, 1], [2, 1], [2, 3], [3, 2], [4, 1]];
        $noise = [0.1, -0.1, 0.05, -0.05, 0.1, -0.1, 0.05, -0.05];
        $y = array_map(fn ($row, $e) => 1 + 2 * $row[0] - $row[1] + $e, $x, $noise);

        $fit = LinearRegression::fit($x, $y);

        $this->assertEqualsWithDelta(2.0, $fit['coefficients'][1], 0.1);
        $this->assertEqualsWithDelta(-1.0, $fit['coefficients'][2], 0.1);
        $this->assertGreaterThan(0.95, $fit['r2']);
        $this->assertLessThan(1.0, $fit['r2']);
    }

    public function test_regression_refuses_data_without_a_unique_answer(): void
    {
        $this->assertNull(LinearRegression::fit([[1], [1], [1]], [1, 2, 3])); // x never changes
        $this->assertNull(LinearRegression::fit([[1, 2]], [5]));               // fewer rows than unknowns
    }

    public function test_regression_r2_is_null_when_y_never_changes(): void
    {
        $fit = LinearRegression::fit([[0], [1], [2]], [4, 4, 4]);

        $this->assertNull($fit['r2']);
        $this->assertEqualsWithDelta(4.0, LinearRegression::predict($fit['coefficients'], [10]), 1e-9);
    }

    public function test_kmeans_finds_three_obvious_groups_ordered_high_to_low(): void
    {
        $points = [
            'a' => [100, 0.60], 'b' => [95, 0.58],   // high
            'c' => [50, 0.35], 'd' => [52, 0.33],    // medium
            'e' => [5, 0.10], 'f' => [8, 0.12],      // low
        ];

        $result = KMeans::cluster($points, 3);

        $this->assertSame(['a' => 0, 'b' => 0, 'c' => 1, 'd' => 1, 'e' => 2, 'f' => 2], $result['assignments']);
        $this->assertSame($result, KMeans::cluster($points, 3), 'Same input must give the same result.');
    }

    public function test_min_max_scaling(): void
    {
        $scaled = KMeans::minMaxScale(['x' => [10, 5], 'y' => [20, 5], 'z' => [30, 5]]);

        $this->assertEquals([0.0, 0.5], $scaled['x']);
        $this->assertEquals([0.5, 0.5], $scaled['y']);
        $this->assertEquals([1.0, 0.5], $scaled['z']);
    }
}
