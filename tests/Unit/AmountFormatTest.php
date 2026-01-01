<?php

declare(strict_types=1);

use Aotr\DynamicLevelHelper\Services\ToonService;
use Illuminate\Support\Collection;

beforeEach(function () {
    // Load the helpers file to ensure functions are available
    require_once __DIR__ . '/../../src/helpers.php';
});

describe('Amount Format Helper', function () {
    it('formats basic amounts correctly', function () {
        expect(amount_format(1000))->toBe('₹1,000')
            ->and(amount_format(1000.00))->toBe('₹1,000') // Should hide .00
            ->and(amount_format(1000.50))->toBe('₹1,000.50')
            ->and(amount_format(0))->toBe('₹0')
            ->and(amount_format(null))->toBe('₹0');
    });

    it('formats Indian currency with proper numbering', function () {
        expect(amount_format(100000))->toBe('₹1,00,000')    // 1 lakh
            ->and(amount_format(1000000))->toBe('₹10,00,000')  // 10 lakhs
            ->and(amount_format(10000000))->toBe('₹1,00,00,000') // 1 crore
            ->and(amount_format(157500000))->toBe('₹15,75,00,000'); // 15.75 crores
    });

    it('handles negative amounts', function () {
        expect(amount_format(-1000))->toBe('-₹1,000')
            ->and(amount_format(-1000, ['negative_format' => 'brackets']))->toBe('(₹1,000)');
    });

    it('supports custom currency symbols', function () {
        expect(amount_format(1000, ['symbol' => 'Rs.']))->toBe('Rs.1,000')
            ->and(amount_format(1000, ['symbol' => 'USD']))->toBe('USD1,000')
            ->and(amount_format(1000, ['symbol' => '']))->toBe('1,000'); // No symbol
    });

    it('supports symbol positioning and spacing', function () {
        expect(amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after']))->toBe('1,000INR')
            ->and(amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after', 'symbol_space' => true]))->toBe('1,000 INR')
            ->and(amount_format(1000, ['symbol' => 'Rs.', 'symbol_space' => true]))->toBe('Rs. 1,000');
    });

    it('handles decimal configurations', function () {
        expect(amount_format(1234.56, ['decimals' => 0]))->toBe('₹1,235') // Rounded
            ->and(amount_format(1000.00, ['hide_zero_decimals' => false]))->toBe('₹1,000.00')
            ->and(amount_format(1234.5678, ['decimals' => 3]))->toBe('₹1,234.568');
    });

    it('handles custom separators', function () {
        expect(amount_format(1234.56, ['decimal_separator' => ',', 'thousands_separator' => '.']))->toBe('₹1.234,56')
            ->and(amount_format(1000000, ['thousands_separator' => '_']))->toBe('₹10_00_000');
    });

    it('handles edge cases and invalid inputs gracefully', function () {
        expect(amount_format('invalid'))->toBe('₹0')
            ->and(amount_format(''))->toBe('₹0')
            ->and(amount_format('1000.50'))->toBe('₹1,000.50') // String number
            ->and(amount_format('₹1,000'))->toBe('₹1,000'); // Already formatted string
    });

    it('formats large amounts correctly', function () {
        expect(amount_format(999999999.99))->toBe('₹99,99,99,999.99')
            ->and(amount_format(1000000000))->toBe('₹1,00,00,00,000'); // 100 crore
    });

    it('handles complex configurations', function () {
        $options = [
            'symbol' => 'USD',
            'decimals' => 3,
            'symbol_position' => 'after',
            'symbol_space' => true,
            'negative_format' => 'brackets',
            'hide_zero_decimals' => false
        ];

        expect(amount_format(1234.567, $options))->toBe('1,234.567 USD')
            ->and(amount_format(-1234.000, $options))->toBe('(1,234.000 USD)');
    });
});

describe('Format Indian Currency Custom Helper', function () {
    it('formats numbers with Indian numbering system', function () {
        $config = ['decimals' => 2, 'thousands_separator' => ','];
        
        expect(formatIndianCurrencyCustom(1000, $config))->toBe('1,000')
            ->and(formatIndianCurrencyCustom(100000, $config))->toBe('1,00,000')
            ->and(formatIndianCurrencyCustom(1000000, $config))->toBe('10,00,000');
    });

    it('handles decimal configurations', function () {
        $config = ['decimals' => 0];
        expect(formatIndianCurrencyCustom(1234.56, $config))->toBe('1,235');

        $config = ['decimals' => 3, 'hide_zero_decimals' => false];
        expect(formatIndianCurrencyCustom(1000.000, $config))->toBe('1,000.000');
    });

    it('handles edge cases', function () {
        expect(formatIndianCurrencyCustom(0))->toBe('0')
            ->and(formatIndianCurrencyCustom('invalid'))->toBe('0');
    });
});

describe('Apply Indian Numbering Helper', function () {
    it('applies correct comma placement for Indian numbering', function () {
        expect(applyIndianNumbering('1000'))->toBe('1,000')
            ->and(applyIndianNumbering('10000'))->toBe('10,000')
            ->and(applyIndianNumbering('100000'))->toBe('1,00,000')
            ->and(applyIndianNumbering('1000000'))->toBe('10,00,000')
            ->and(applyIndianNumbering('10000000'))->toBe('1,00,00,000');
    });

    it('handles small numbers', function () {
        expect(applyIndianNumbering('0'))->toBe('0')
            ->and(applyIndianNumbering('123'))->toBe('123')
            ->and(applyIndianNumbering('999'))->toBe('999');
    });

    it('handles custom separators', function () {
        expect(applyIndianNumbering('100000', '_'))->toBe('1_00_000')
            ->and(applyIndianNumbering('1000000', '.'))->toBe('10.00.000');
    });

    it('handles edge cases safely', function () {
        expect(applyIndianNumbering(''))->toBe('0')
            ->and(applyIndianNumbering('invalid'))->toBe('0');
    });
});

// Integration tests with various data types
describe('Amount Format Integration Tests', function () {
    it('handles different numeric data types', function () {
        expect(amount_format(1000))->toBe('₹1,000')      // int
            ->and(amount_format(1000.0))->toBe('₹1,000')    // float
            ->and(amount_format('1000'))->toBe('₹1,000')    // string
            ->and(amount_format('1000.50'))->toBe('₹1,000.50'); // string with decimals
    });

    it('is exception-free for all invalid inputs', function () {
        $invalidInputs = [null, '', 'abc', [], new stdClass(), true, false];
        
        foreach ($invalidInputs as $input) {
            $result = amount_format($input);
            expect($result)->toBeString();
            // Should not throw any exceptions
        }
    });

    it('produces consistent output for equivalent values', function () {
        expect(amount_format(1000.00))->toBe(amount_format('1000.00'))
            ->and(amount_format(1000))->toBe(amount_format(1000.0));
    });
});