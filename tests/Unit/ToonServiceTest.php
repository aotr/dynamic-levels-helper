<?php

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Services\ToonService;
use MischaSigtermans\ToonLaravel\ToonApi;
use PHPUnit\Framework\TestCase;
use Mockery;

class ToonServiceTest extends TestCase
{
    protected $mockToonApi;
    protected $toonService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockToonApi = Mockery::mock(ToonApi::class);
        $this->toonService = new ToonService($this->mockToonApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_thermostat_info()
    {
        $expectedData = [
            'currentTemp' => 21.5,
            'targetTemp' => 22.0,
            'programState' => 1
        ];

        $this->mockToonApi
            ->shouldReceive('getThermostatInfo')
            ->once()
            ->andReturn($expectedData);

        $result = $this->toonService->getThermostatInfo();

        $this->assertEquals($expectedData, $result);
    }

    /** @test */
    public function it_can_set_temperature()
    {
        $temperature = 23.5;
        $expectedResponse = ['success' => true];

        $this->mockToonApi
            ->shouldReceive('setTemperature')
            ->once()
            ->with($temperature)
            ->andReturn($expectedResponse);

        $result = $this->toonService->setTemperature($temperature);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_energy_usage()
    {
        $expectedData = [
            'avgValue' => 1500,
            'dailyUsage' => 25.5,
            'weeklyUsage' => 180.2
        ];

        $this->mockToonApi
            ->shouldReceive('getEnergyUsage')
            ->once()
            ->andReturn($expectedData);

        $result = $this->toonService->getEnergyUsage();

        $this->assertEquals($expectedData, $result);
    }

    /** @test */
    public function it_can_get_energy_usage_by_period()
    {
        $period = 'month';
        $expectedData = [
            'period' => 'month',
            'usage' => 750.5
        ];

        $this->mockToonApi
            ->shouldReceive('getEnergyUsageByPeriod')
            ->once()
            ->with($period)
            ->andReturn($expectedData);

        $result = $this->toonService->getEnergyUsageByPeriod($period);

        $this->assertEquals($expectedData, $result);
    }

    /** @test */
    public function it_can_get_device_info()
    {
        $expectedData = [
            'displayCommonName' => 'Toon',
            'deviceUuid' => 'abc123'
        ];

        $this->mockToonApi
            ->shouldReceive('getDeviceInfo')
            ->once()
            ->andReturn($expectedData);

        $result = $this->toonService->getDeviceInfo();

        $this->assertEquals($expectedData, $result);
    }

    /** @test */
    public function it_can_check_availability()
    {
        $this->mockToonApi
            ->shouldReceive('getDeviceInfo')
            ->once()
            ->andReturn(['status' => 'ok']);

        $result = $this->toonService->isAvailable();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_not_available()
    {
        $this->mockToonApi
            ->shouldReceive('getDeviceInfo')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $result = $this->toonService->isAvailable();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_get_dashboard_data()
    {
        $thermostatData = ['currentTemp' => 21.5];
        $energyData = ['usage' => 1500];
        $gasData = ['usage' => 50.2];
        $deviceData = ['deviceUuid' => 'abc123'];
        $programData = ['currentProgram' => 1];

        $this->mockToonApi->shouldReceive('getThermostatInfo')->once()->andReturn($thermostatData);
        $this->mockToonApi->shouldReceive('getEnergyUsage')->once()->andReturn($energyData);
        $this->mockToonApi->shouldReceive('getGasUsage')->once()->andReturn($gasData);
        $this->mockToonApi->shouldReceive('getDeviceInfo')->once()->andReturn($deviceData);
        $this->mockToonApi->shouldReceive('getCurrentProgram')->once()->andReturn($programData);

        $result = $this->toonService->getDashboardData();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('thermostat', $result);
        $this->assertArrayHasKey('energy_usage', $result);
        $this->assertArrayHasKey('gas_usage', $result);
        $this->assertArrayHasKey('device_info', $result);
        $this->assertArrayHasKey('current_program', $result);
    }

    /** @test */
    public function it_can_set_smart_plug_state()
    {
        $deviceId = 'plug123';
        $state = true;
        $expectedResponse = ['success' => true];

        $this->mockToonApi
            ->shouldReceive('setSmartPlugState')
            ->once()
            ->with($deviceId, $state)
            ->andReturn($expectedResponse);

        $result = $this->toonService->setSmartPlugState($deviceId, $state);

        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_returns_collection_for_devices_status()
    {
        $devicesData = [
            ['id' => 'device1', 'status' => 'on'],
            ['id' => 'device2', 'status' => 'off']
        ];

        $this->mockToonApi
            ->shouldReceive('getDeviceStatus')
            ->once()
            ->andReturn($devicesData);

        $result = $this->toonService->getAllDevicesStatus();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertEquals(2, $result->count());
    }
}