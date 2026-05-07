<?php

/**
 * Medicine Helper Functions
 * Shared logic for medicine inventory status.
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
