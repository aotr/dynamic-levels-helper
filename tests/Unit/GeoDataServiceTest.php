<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Services\GeoDataService;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GeoDataServiceTest extends PackageTestCase
{
    protected GeoDataService $geoDataService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geoDataService = new GeoDataService();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_get_countries_returns_null_when_file_not_exists()
    {
        Storage::fake('local');

        $result = $this->geoDataService->getCountries();

        $this->assertNull($result);
    }

    public function test_get_countries_returns_data_when_file_exists()
    {
        Storage::fake('local');
        $jsonData = '{"countries": [{"id": 1, "name": "Country1"}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/countries.json', $jsonData);

        $result = $this->geoDataService->getCountries();

        $this->assertEquals($expected, $result);
    }

    public function test_get_countries_with_states()
    {
        Storage::fake('local');
        $jsonData = '{"countries": [{"id": 1, "name": "Country1", "states": []}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/countries+states.json', $jsonData);

        $result = $this->geoDataService->getCountriesWithStates();

        $this->assertEquals($expected, $result);
    }

    public function test_get_cities()
    {
        Storage::fake('local');
        $jsonData = '{"cities": [{"id": 1, "name": "City1"}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/cities.json', $jsonData);

        $result = $this->geoDataService->getCities();

        $this->assertEquals($expected, $result);
    }

    public function test_get_states()
    {
        Storage::fake('local');
        $jsonData = '{"states": [{"id": 1, "name": "State1"}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/states.json', $jsonData);

        $result = $this->geoDataService->getStates();

        $this->assertEquals($expected, $result);
    }

    public function test_get_regions()
    {
        Storage::fake('local');
        $jsonData = '{"regions": [{"id": 1, "name": "Region1"}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/regions.json', $jsonData);

        $result = $this->geoDataService->getRegions();

        $this->assertEquals($expected, $result);
    }

    public function test_get_subregions()
    {
        Storage::fake('local');
        $jsonData = '{"subregions": [{"id": 1, "name": "Subregion1"}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/subregions.json', $jsonData);

        $result = $this->geoDataService->getSubregions();

        $this->assertEquals($expected, $result);
    }

    public function test_get_countries_with_cities()
    {
        Storage::fake('local');
        $jsonData = '{"countries": [{"id": 1, "name": "Country1", "cities": []}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/countries+cities.json', $jsonData);

        $result = $this->geoDataService->getCountriesWithCities();

        $this->assertEquals($expected, $result);
    }

    public function test_get_countries_states_cities()
    {
        Storage::fake('local');
        $jsonData = '{"countries": [{"id": 1, "name": "Country1", "states": [], "cities": []}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/countries+states+cities.json', $jsonData);

        $result = $this->geoDataService->getCountriesStatesCities();

        $this->assertEquals($expected, $result);
    }

    public function test_get_states_with_cities()
    {
        Storage::fake('local');
        $jsonData = '{"states": [{"id": 1, "name": "State1", "cities": []}]}';
        $expected = json_decode($jsonData, true);

        Storage::disk('local')->put('remote/states+cities.json', $jsonData);

        $result = $this->geoDataService->getStatesWithCities();

        $this->assertEquals($expected, $result);
    }

    public function test_sync_skips_when_file_up_to_date()
    {
        Storage::fake('local');
        $jsonContent = '{"regions": []}';
        Storage::disk('local')->put('remote/regions.json', $jsonContent);

        // Mock HEAD request only - the sync method checks size via HEAD and skips if matches
        Http::fake([
            '*regions.json' => Http::response('', 200, ['Content-Length' => (string) strlen($jsonContent)]),
            '*' => Http::response('', 404),
        ]);

        $this->geoDataService->sync();

        // File should still have same content (not re-downloaded)
        $this->assertEquals($jsonContent, Storage::disk('local')->get('remote/regions.json'));
    }

    public function test_decompress_gzip_file_method()
    {
        // Test the decompression helper directly
        $jsonContent = '{"test": "data"}';
        $gzipContent = gzencode($jsonContent);

        $tempDir = sys_get_temp_dir();
        $gzPath = $tempDir . '/test_geo_' . uniqid() . '.json.gz';
        $destPath = $tempDir . '/test_geo_' . uniqid() . '.json';

        file_put_contents($gzPath, $gzipContent);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->geoDataService);
        $method = $reflection->getMethod('decompressGzipFile');
        $method->setAccessible(true);

        $result = $method->invoke($this->geoDataService, $gzPath, $destPath);

        $this->assertTrue($result);
        $this->assertFileExists($destPath);
        $this->assertEquals($jsonContent, file_get_contents($destPath));

        // Cleanup
        @unlink($gzPath);
        @unlink($destPath);
    }
}
