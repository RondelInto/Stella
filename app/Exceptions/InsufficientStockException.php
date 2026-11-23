<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a stock movement would make the quantity on hand negative
 * (team decision: negative stock is blocked).
 */
class InsufficientStockException extends RuntimeException
{
    public static function for(string $itemName, string $locationName, string $available, string $requested, string $unit): self
    {
        return new self("Not enough stock of {$itemName} at {$locationName}. Available: {$available} {$unit}, requested: {$requested} {$unit}.");
    }
}
