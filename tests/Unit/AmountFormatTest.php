<?php

require_once __DIR__ . '/../../src/helpers.php';

describe('Amount Format Helper', function () {
    it('formats basic amounts correctly', function () {
        expect(amount_format(1000))->toBe('₹1,000');
        expect(amount_format(1234567.89))->toBe('₹12,34,567.89');
        expect(amount_format(1000.00))->toBe('₹1,000'); // Hides .00
        expect(amount_format(1000.50))->toBe('₹1,000.50');
    });

    it('formats Indian currency with proper numbering', function () {
        expect(amount_format(100000))->toBe('₹1,00,000'); // 1 Lakh
        expect(amount_format(1000000))->toBe('₹10,00,000'); // 10 Lakhs
        expect(amount_format(10000000))->toBe('₹1,00,00,000'); // 1 Crore
        expect(amount_format(157500000))->toBe('₹15,75,00,000'); // 15.75 Crores
    });

    it('handles negative amounts', function () {
        expect(amount_format(-5000))->toBe('-₹5,000');
        expect(amount_format(-5000, ['negative_format' => 'brackets']))->toBe('(₹5,000)');
        expect(amount_format(-1234567.89))->toBe('-₹12,34,567.89');
    });

    it('supports custom currency symbols', function () {
        expect(amount_format(1000, ['symbol' => '']))->toBe('1,000');
        expect(amount_format(1000, ['symbol' => 'Rs.']))->toBe('Rs.1,000');
        expect(amount_format(1000, ['symbol' => 'USD']))->toBe('USD1,000');
        expect(amount_format(1000, ['symbol' => 'Rs.', 'symbol_space' => true]))->toBe('Rs. 1,000');
    });

    it('supports symbol positioning and spacing', function () {
        expect(amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after']))->toBe('1,000INR');
        expect(amount_format(1000, ['symbol' => 'INR', 'symbol_position' => 'after', 'symbol_space' => true]))->toBe('1,000 INR');
        expect(amount_format(1000, ['symbol' => '$', 'symbol_position' => 'before']))->toBe('$1,000');
    });

    it('handles decimal configurations', function () {
        expect(amount_format(1000.00, ['hide_zero_decimals' => false]))->toBe('₹1,000.00');
        expect(amount_format(1234.56, ['decimals' => 0]))->toBe('₹1,235'); // Rounded
        expect(amount_format(1234.5678, ['decimals' => 3]))->toBe('₹1,234.568');
        expect(amount_format(1234.5678, ['decimals' => 1]))->toBe('₹1,234.6');
    });

    it('handles custom separators', function () {
        expect(amount_format(1234.56, ['decimal_separator' => ',', 'thousands_separator' => '.']))->toBe('₹1.234,56');
        expect(amount_format(1000000, ['thousands_separator' => '_']))->toBe('₹10_00_000');
        expect(amount_format(1234.56, ['decimal_separator' => '|', 'thousands_separator' => '-']))->toBe('₹1-234|56');
    });

    it('handles edge cases and invalid inputs gracefully', function () {
        expect(amount_format(null))->toBe('₹0');
        expect(amount_format(''))->toBe('₹0');
        expect(amount_format('invalid'))->toBe('₹0');
        expect(amount_format('1000.50'))->toBe('₹1,000.50'); // String input
        expect(amount_format('₹1,000'))->toBe('₹1,000'); // Formatted string input
        expect(amount_format([]))->toBe('₹0');
        expect(amount_format(new stdClass()))->toBe('₹0');
    });

    it('formats large amounts correctly', function () {
        expect(amount_format(999999999.99))->toBe('₹99,99,99,999.99');
        expect(amount_format(1000000000))->toBe('₹1,00,00,00,000'); // 100 Crore
        expect(amount_format(1000000000000))->toBe('₹10,00,00,00,00,000'); // 1 Lakh Crore
        expect(amount_format(PHP_INT_MAX))->toBe(amount_format(PHP_INT_MAX)); // Large numbers
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

        expect(amount_format(1234.567, $options))->toBe('1,234.567 USD');
        expect(amount_format(-1234.000, $options))->toBe('(1,234.000 USD)');
    });
});

describe('Format Indian Currency Custom Helper', function () {
    it('formats numbers with Indian numbering system', function () {
        expect(formatIndianCurrencyCustom(100000))->toBe('1,00,000');
        expect(formatIndianCurrencyCustom(1000000))->toBe('10,00,000');
        expect(formatIndianCurrencyCustom(10000000))->toBe('1,00,00,000');
    });

    it('handles decimal configurations', function () {
        expect(formatIndianCurrencyCustom(1234.56, ['decimals' => 2]))->toBe('1,234.56');
        expect(formatIndianCurrencyCustom(1234.56, ['decimals' => 0]))->toBe('1,235');
        expect(formatIndianCurrencyCustom(1234.00, ['hide_zero_decimals' => false]))->toBe('1,234.00');
    });

    it('handles edge cases', function () {
        expect(formatIndianCurrencyCustom(0))->toBe('0');
        expect(formatIndianCurrencyCustom(0.0))->toBe('0');
        expect(formatIndianCurrencyCustom(0.1))->toBe('0.10'); // Default 2 decimals
    });
});

describe('Apply Indian Numbering Helper', function () {
    it('applies correct comma placement for Indian numbering', function () {
        expect(applyIndianNumbering('100000'))->toBe('1,00,000');
        expect(applyIndianNumbering('1000000'))->toBe('10,00,000');
        expect(applyIndianNumbering('10000000'))->toBe('1,00,00,000');
        expect(applyIndianNumbering('123456789'))->toBe('12,34,56,789');
    });

    it('handles small numbers', function () {
        expect(applyIndianNumbering('100'))->toBe('100');
        expect(applyIndianNumbering('1000'))->toBe('1,000');
        expect(applyIndianNumbering('10000'))->toBe('10,000');
        expect(applyIndianNumbering('100000'))->toBe('1,00,000');
    });

    it('handles custom separators', function () {
        expect(applyIndianNumbering('100000', '.'))->toBe('1.00.000');
        expect(applyIndianNumbering('1000000', '_'))->toBe('10_00_000');
        expect(applyIndianNumbering('123456789', '-'))->toBe('12-34-56-789');
    });

    it('handles edge cases safely', function () {
        expect(applyIndianNumbering(''))->toBe('0');
        expect(applyIndianNumbering('0'))->toBe('0');
        expect(applyIndianNumbering('1'))->toBe('1');
        expect(applyIndianNumbering('123'))->toBe('123');
    });
});

describe('Amount Format Integration Tests', function () {
    it('handles different numeric data types', function () {
        expect(amount_format(1000))->toBe('₹1,000');
        expect(amount_format(1000.0))->toBe('₹1,000');
        expect(amount_format('1000'))->toBe('₹1,000');
        expect(amount_format('1000.50'))->toBe('₹1,000.50');
    });

    it('is exception-free for all invalid inputs', function () {
        // These should all return safe defaults without throwing exceptions
        expect(amount_format(null))->toBe('₹0');
        expect(amount_format(''))->toBe('₹0');
        expect(amount_format('invalid'))->toBe('₹0');
        expect(amount_format('₹1,000'))->toBe('₹1,000');
        expect(amount_format([]))->toBe('₹0');
        expect(amount_format(new stdClass()))->toBe('₹0');
        expect(amount_format(true))->toBe('₹0');
        expect(amount_format(false))->toBe('₹0');
    });

    it('produces consistent output for equivalent values', function () {
        $expected = '₹1,000';
        expect(amount_format(1000))->toBe($expected);
        expect(amount_format(1000.0))->toBe($expected);
        expect(amount_format('1000'))->toBe($expected);
        expect(amount_format('1000.00'))->toBe($expected);
    });
});