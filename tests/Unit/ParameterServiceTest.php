<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Services\ParameterService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ParameterServiceTest extends TestCase
{
    public function test_process_with_array()
    {
        $data = ['param1' => 'value1', 'param2' => 'value2', 'param3' => 'value3'];
        $result = ParameterService::process($data);

        $this->assertEquals('value1^^value2^^value3', $result);
    }

    public function test_process_with_sequence()
    {
        $data = ['param1' => 'value1', 'param2' => 'value2', 'param3' => 'value3'];
        $sequence = ['param2', 'param1'];
        $result = ParameterService::process($data, $sequence);

        $this->assertEquals('value2^^value1', $result);
    }

    public function test_process_with_custom_delimiter()
    {
        $data = ['param1' => 'value1', 'param2' => 'value2'];
        $result = ParameterService::process($data, null, '|');

        $this->assertEquals('value1|value2', $result);
    }

    public function test_process_simple_with_array()
    {
        $data = ['param1' => 'value1', 'param2' => 'value2'];
        $result = ParameterService::processSimple($data);

        $this->assertEquals('value1^^value2', $result);
    }

    public function test_process_simple_with_sequence()
    {
        $data = ['param1' => 'value1', 'param2' => 'value2', 'param3' => 'value3'];
        $sequence = ['param3', 'param1'];
        $result = ParameterService::processSimple($data, $sequence);

        $this->assertEquals('value3^^value1', $result);
    }

    public function test_process_simple_with_request()
    {
        $request = new Request(['param1' => 'value1', 'param2' => 'value2']);
        $result = ParameterService::processSimple($request);

        $this->assertEquals('value1^^value2', $result);
    }

    public function test_quick_method_alias()
    {
        $data = ['param1' => 'value1', 'param2' => 'value2'];
        $result = ParameterService::quick($data);

        $this->assertEquals('value1^^value2', $result);
    }

    public function test_from_values()
    {
        $result = ParameterService::fromValues('value1', 'value2', 123, 'value4');

        $this->assertEquals('value1^^value2^^123^^value4', $result);
    }

    public function test_from_values_with_non_scalar()
    {
        $result = ParameterService::fromValues('value1', ['array'], 'value3');

        $this->assertEquals('value1^^^^value3', $result);
    }

    public function test_split_parameter_string()
    {
        $paramString = 'value1^^value2^^value3';
        $result = ParameterService::split($paramString);

        $this->assertEquals(['value1', 'value2', 'value3'], $result);
    }

    public function test_split_with_custom_delimiter()
    {
        $paramString = 'value1|value2|value3';
        $result = ParameterService::split($paramString, '|');

        $this->assertEquals(['value1', 'value2', 'value3'], $result);
    }

    public function test_split_empty_string()
    {
        $result = ParameterService::split('');

        $this->assertEquals([], $result);
    }

    public function test_validate_required_success()
    {
        $data = ['param1' => 'value1', 'param2' => 'value2', 'param3' => 'value3'];
        $required = ['param1', 'param2'];

        $result = ParameterService::validateRequired($data, $required);

        $this->assertTrue($result);
    }

    public function test_validate_required_failure()
    {
        $data = ['param1' => 'value1', 'param2' => '', 'param3' => 'value3'];
        $required = ['param1', 'param2'];

        $result = ParameterService::validateRequired($data, $required);

        $this->assertFalse($result);
    }

    public function test_validate_required_with_zero_values()
    {
        $data = ['param1' => 0, 'param2' => '0', 'param3' => 'value3'];
        $required = ['param1', 'param2'];

        $result = ParameterService::validateRequired($data, $required);

        $this->assertTrue($result);
    }

    public function test_get_missing_required()
    {
        $data = ['param1' => 'value1', 'param2' => '', 'param3' => 'value3', 'param4' => null];
        $required = ['param1', 'param2', 'param3', 'param4', 'param5'];

        $missing = ParameterService::getMissingRequired($data, $required);

        $this->assertEquals(['param2', 'param4', 'param5'], $missing);
    }

    public function test_process_simple_invalid_input()
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line - Testing invalid input intentionally
        ParameterService::processSimple('invalid');
    }

    public function test_nested_array_access()
    {
        $data = [
            'user' => [
                'id' => 123,
                'name' => 'John Doe'
            ],
            'settings' => [
                'theme' => 'dark'
            ]
        ];

        $sequence = ['user.id', 'user.name', 'settings.theme'];
        $result = ParameterService::process($data, $sequence);

        $this->assertEquals('123^^John Doe^^dark', $result);
    }

    public function test_handles_non_scalar_values()
    {
        $data = [
            'param1' => 'value1',
            'param2' => ['nested', 'array'],
            'param3' => 'value3',
            'param4' => (object) ['property' => 'value']
        ];

        $result = ParameterService::processSimple($data);

        $this->assertEquals('value1^^^^value3^^', $result);
    }

    public function test_empty_input_handling()
    {
        $this->assertEquals('', ParameterService::process([]));
        $this->assertEquals('', ParameterService::processSimple([]));
        $this->assertEquals('', ParameterService::fromValues());
    }
}
