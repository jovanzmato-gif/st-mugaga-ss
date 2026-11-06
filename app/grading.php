<?php
// Returns the grade letter for a mark out of 100
function grade_for(float $marks): string
{
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    if ($marks >= 40) return 'E';
    return 'F';
}

// Returns the short comment that goes next to each grade on the report card
function remark_for(string $grade): string
{
    $remarks = [
        'A' => 'Excellent',
        'B' => 'Very good',
        'C' => 'Good',
        'D' => 'Fair',
        'E' => 'Weak',
        'F' => 'Fail',
    ];
    return $remarks[$grade];
}