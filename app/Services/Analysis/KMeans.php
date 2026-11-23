<?php

namespace App\Services\Analysis;

/**
 * K-means clustering (decision AN4), written out in plain PHP so it can be explained step by step.
 *
 *  1. Scale every measure to 0–1 (min-max), so a measure with big numbers (e.g. 500 receipts)
 *     does not outweigh one with small numbers (e.g. a 0.35 margin).
 *  2. Pick k starting centres. To get the SAME result every run, the points are sorted by their
 *     total score and the centres are taken evenly from that order (no random start).
 *  3. Assign each point to its nearest centre (straight-line / Euclidean distance).
 *  4. Move each centre to the average of its points.
 *  5. Repeat 3–4 until no point changes group (or 100 rounds).
 */
class KMeans
{
    public const MAX_ITERATIONS = 100;

    /**
     * @param  array<int|string, array<int, float>>  $points  key => [measure1, measure2, ...] (raw values)
     * @return array{assignments: array<int|string, int>, centroids: array<int, array<int, float>>, iterations: int, scaled: array<int|string, array<int, float>>}
     *         assignments: key => group index (0 = highest average score, ordered high to low)
     */
    public static function cluster(array $points, int $k): array
    {
        $scaled = self::minMaxScale($points);
        $keys = array_keys($scaled);
        $k = min($k, count($keys));

        // Step 2: deterministic start, spread evenly over points sorted by total score.
        $sorted = $keys;
        usort($sorted, fn ($a, $b) => array_sum($scaled[$a]) <=> array_sum($scaled[$b]) ?: strcmp((string) $a, (string) $b));
        $centroids = [];
        for ($c = 0; $c < $k; $c++) {
            $centroids[$c] = $scaled[$sorted[(int) floor(($c + 0.5) * count($sorted) / $k)]];
        }

        $assignments = [];
        $iterations = 0;

        while ($iterations < self::MAX_ITERATIONS) {
            $iterations++;

            // Step 3: nearest centre.
            $changed = false;
            foreach ($scaled as $key => $point) {
                $best = 0;
                $bestDistance = INF;
                foreach ($centroids as $c => $centroid) {
                    $distance = self::distance($point, $centroid);
                    if ($distance < $bestDistance) {
                        $best = $c;
                        $bestDistance = $distance;
                    }
                }
                if (($assignments[$key] ?? null) !== $best) {
                    $assignments[$key] = $best;
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }

            // Step 4: move each centre to the average of its points (an empty group keeps its centre).
            foreach ($centroids as $c => $centroid) {
                $members = array_keys($assignments, $c, true);
                if ($members) {
                    $dimensions = count($centroid);
                    $centroids[$c] = array_map(
                        fn ($d) => array_sum(array_map(fn ($m) => $scaled[$m][$d], $members)) / count($members),
                        range(0, $dimensions - 1),
                    );
                }
            }
        }

        // Order groups from highest to lowest average score, so group 0 is always the "best" one.
        $order = array_keys($centroids);
        usort($order, fn ($a, $b) => array_sum($centroids[$b]) <=> array_sum($centroids[$a]));
        $rank = array_flip($order);

        return [
            'assignments' => array_map(fn ($c) => $rank[$c], $assignments),
            'centroids' => array_map(fn ($c) => $centroids[$c], $order),
            'iterations' => $iterations,
            'scaled' => $scaled,
        ];
    }

    /** Step 1: scale each measure to 0–1. A measure where every value is equal becomes 0.5. */
    public static function minMaxScale(array $points): array
    {
        if (! $points) {
            return [];
        }

        $dimensions = count(reset($points));
        $scaled = $points;

        for ($d = 0; $d < $dimensions; $d++) {
            $values = array_column($points, $d);
            $min = min($values);
            $max = max($values);

            foreach ($points as $key => $point) {
                $scaled[$key][$d] = $max == $min ? 0.5 : ($point[$d] - $min) / ($max - $min);
            }
        }

        return $scaled;
    }

    private static function distance(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $d => $value) {
            $sum += ($value - $b[$d]) ** 2;
        }

        return sqrt($sum);
    }
}
