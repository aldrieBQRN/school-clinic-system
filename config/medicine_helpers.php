<?php

/**
 * Medicine Helper Functions
 * Shared logic for medicine inventory status.
 */

/**
 * Determine the inventory status label for a medicine.
 *
 * @param int $quantity Current quantity in stock.
 * @return string One of: In Stock, Low Stock, Critical, or Out of Stock.
 */
function getMedicineStatus($quantity)
{
    $status = 'In Stock';
    if ($quantity <= 0) {
        $status = 'Out of Stock';
    } elseif ($quantity <= 5) {
        $status = 'Critical';
    } elseif ($quantity <= 15) {
        $status = 'Low Stock';
    }
    return $status;
}
