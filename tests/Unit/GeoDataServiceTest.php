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

    public function test_sync_downloads_json_file_when_available()
    {
        Storage::fake('local');
        Http::fake([
            '*countries.json' => Http::sequence()
                ->push('', 200, ['Content-Length' => '50'])  // HEAD request
                ->push('{"countries": []}', 200),  // GET request
            '*' => Http::response('', 404),  // All other requests return 404
        ]);

        $this->geoDataService->sync();

        Storage::disk('local')->assertExists('remote/countries.json');
    }

    public function test_sync_tries_gzip_when_json_returns_404()
    {
        Storage::fake('local');
        $jsonContent = '{"cities": [{"id": 1, "name": "TestCity"}]}';
        $gzipContent = gzencode($jsonContent);

        // Use callback-based fake for more precise control
        Http::fake(function ($request) use ($gzipContent) {
            $url = $request->url();

            // Cities JSON returns 404
            if (str_contains($url, 'cities.json') && !str_contains($url, '.gz')) {
                return Http::response('', 404);
            }

            // Cities gzip is available
            if (str_contains($url, 'cities.json.gz')) {
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200, ['Content-Length' => (string) strlen($gzipContent)]);
                }
                return Http::response($gzipContent, 200);
            }

            // All other files return 404
            return Http::response('', 404);
        });

        $this->geoDataService->sync();

        Storage::disk('local')->assertExists('remote/cities.json');
        $storedContent = Storage::disk('local')->get('remote/cities.json');
        $this->assertEquals($jsonContent, $storedContent);
    }

    public function test_sync_skips_when_file_up_to_date()
    {
        Storage::fake('local');
        $jsonContent = '{"regions": []}';
        Storage::disk('local')->put('remote/regions.json', $jsonContent);

        Http::fake([
            '*regions.json' => Http::response('', 200, ['Content-Length' => (string) strlen($jsonContent)]),
            '*' => Http::response('', 404),
        ]);

        $this->geoDataService->sync();

        // File should still have same content (not re-downloaded)
        $this->assertEquals($jsonContent, Storage::disk('local')->get('remote/regions.json'));
    }
}
