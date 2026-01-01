<?php

/**
 * Amount Format Helper Examples
 *
 * This file demonstrates the comprehensive amount formatting functionality
 * with Indian currency conventions and extensive customization options.
 */

require_once __DIR__ . '/../src/helpers.php';

echo "=== AMOUNT FORMAT HELPER EXAMPLES ===\n\n";

echo "1. BASIC USAGE:\n";
echo "amount_format(1000) = " . amount_format(1000) . "\n";
echo "amount_format(1234567.89) = " . amount_format(1234567.89) . "\n";
echo "amount_format(1000.00) = " . amount_format(1000.00) . " (hides .00)\n";
echo "amount_format(1000.50) = " . amount_format(1000.50) . "\n\n";

echo "2. INDIAN NUMBERING SYSTEM:\n";
echo "amount_format(100000) = " . amount_format(100000) . " (1 Lakh)\n";
echo "amount_format(1000000) = " . amount_format(1000000) . " (10 Lakhs)\n";
echo "amount_format(10000000) = " . amount_format(10000000) . " (1 Crore)\n";
echo "amount_format(157500000) = " . amount_format(157500000) . " (15.75 Crores)\n\n";

echo "3. NEGATIVE AMOUNTS:\n";
echo "amount_format(-5000) = " . amount_format(-5000) . "\n";
echo "amount_format(-5000, ['negative_format' => 'brackets']) = " . amount_format(-5000, ['negative_format' => 'brackets']) . "\n\n";

echo "4. CUSTOM CURRENCY SYMBOLS:\n";
echo "amount_format(1000, ['symbol' => '']) = " . amount_format(1000, ['symbol' => '']) . " (no symbol)\n";
echo "amount_format(1000, ['symbol' => 'Rs.']) = " . amount_format(1000, ['symbol' => 'Rs.']) . "\n";
echo "amount_format(1000, ['symbol' => 'USD']) = " . amount_format(1000, ['symbol' => 'USD']) . "\n";
echo "amount_format(1000, ['symbol' => 'Rs.', 'symbol_space' => true]) = " . amount_format(1000, ['symbol' => 'Rs.', 'symbol_space' => true]) . "\n\n";

echo "5. SYMBOL POSITIONING:\n";
echo "amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after']) = " . amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after']) . "\n";
echo "amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after', 'symbol_space' => true]) = " . amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after', 'symbol_space' => true]) . "\n\n";

echo "6. DECIMAL CONFIGURATIONS:\n";
echo "amount_format(1000.00, ['hide_zero_decimals' => false]) = " . amount_format(1000.00, ['hide_zero_decimals' => false]) . "\n";
echo "amount_format(1234.56, ['decimals' => 0]) = " . amount_format(1234.56, ['decimals' => 0]) . " (rounded)\n";
echo "amount_format(1234.5678, ['decimals' => 3]) = " . amount_format(1234.5678, ['decimals' => 3]) . "\n\n";

echo "7. CUSTOM SEPARATORS:\n";
echo "amount_format(1234.56, ['decimal_separator' => ',', 'thousands_separator' => '.']) = " . amount_format(1234.56, ['decimal_separator' => ',', 'thousands_separator' => '.']) . "\n";
echo "amount_format(1000000, ['thousands_separator' => '_']) = " . amount_format(1000000, ['thousands_separator' => '_']) . "\n\n";

echo "8. EDGE CASES AND ERROR HANDLING:\n";
echo "amount_format(null) = " . amount_format(null) . "\n";
echo "amount_format('') = " . amount_format('') . "\n";
echo "amount_format('invalid') = " . amount_format('invalid') . "\n";
echo "amount_format('1000.50') = " . amount_format('1000.50') . " (string input)\n";
echo "amount_format('₹1,000') = " . amount_format('₹1,000') . " (formatted string input)\n\n";

echo "9. COMPLEX CONFIGURATION:\n";
$options = [
    'symbol' => 'USD',
    'decimals' => 3,
    'symbol_position' => 'after',
    'symbol_space' => true,
    'negative_format' => 'brackets',
    'hide_zero_decimals' => false
];
echo "Complex config positive: " . amount_format(1234.567, $options) . "\n";
echo "Complex config negative: " . amount_format(-1234.000, $options) . "\n\n";

echo "10. LARGE NUMBERS:\n";
echo "amount_format(999999999.99) = " . amount_format(999999999.99) . "\n";
echo "amount_format(1000000000) = " . amount_format(1000000000) . " (100 Crore)\n";
echo "amount_format(1000000000000) = " . amount_format(1000000000000) . " (1 Lakh Crore)\n\n";

echo "11. BLADE DIRECTIVE USAGE (in Blade templates):\n";
echo "<!-- Basic currency formatting -->\n";
echo "@currency(\$amount)                    // Equivalent to {{ amount_format(\$amount) }}\n";
echo "@rupee(\$amount)                       // Alias for @currency\n";
echo "@amount(\$amount)                      // Without currency symbol\n";
echo "@currencyWhole(\$amount)               // Without decimals\n";
echo "@currencyWithOptions(\$amount, \$opts)  // With custom options\n\n";

echo "12. PERFORMANCE TEST:\n";
$startTime = microtime(true);
$testAmounts = [];
for ($i = 0; $i < 1000; $i++) {
    $testAmounts[] = rand(0, 1000000) / 100; // Random amounts up to 10,000
}
foreach ($testAmounts as $amount) {
    amount_format($amount);
}
$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000, 1);
echo "Formatted 1000 random numbers in {$duration} milliseconds\n\n";

echo "=== END OF EXAMPLES ===\n";