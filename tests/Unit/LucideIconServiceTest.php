<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Tests\Unit;

use Aotr\DynamicLevelHelper\Services\LucideIconService;
use Aotr\DynamicLevelHelper\Tests\PackageTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Tests for LucideIconService.
 */
class LucideIconServiceTest extends PackageTestCase
{
    protected LucideIconService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use fake storage for tests
        Storage::fake('local');
        
        $this->service = new LucideIconService();
    }

    /** @test */
    public function test_normalize_name_converts_to_kebab_case(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizeName');
        $method->setAccessible(true);

        $this->assertEquals('check', $method->invoke($this->service, 'check'));
        $this->assertEquals('alert-circle', $method->invoke($this->service, 'alertCircle'));
        $this->assertEquals('shopping-cart', $method->invoke($this->service, 'ShoppingCart'));
        $this->assertEquals('arrow-left', $method->invoke($this->service, '  arrow-left  '));
    }

    /** @test */
    public function test_get_icon_path_returns_correct_path(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getIconPath');
        $method->setAccessible(true);

        $path = $method->invoke($this->service, 'check');
        $this->assertStringEndsWith('check.svg', $path);
        $this->assertStringContainsString('lucide/icons', $path);
    }

    /** @test */
    public function test_exists_returns_false_when_icon_not_cached(): void
    {
        $this->assertFalse($this->service->exists('check'));
    }

    /** @test */
    public function test_exists_returns_true_when_icon_cached(): void
    {
        // Manually create a cached icon
        Storage::disk('local')->put('lucide/icons/check.svg', '<svg></svg>');

        $this->assertTrue($this->service->exists('check'));
    }

    /** @test */
    public function test_get_cached_icons_returns_empty_array_initially(): void
    {
        $cached = $this->service->getCachedIcons();
        $this->assertIsArray($cached);
        $this->assertEmpty($cached);
    }

    /** @test */
    public function test_get_cached_icons_returns_cached_icon_names(): void
    {
        Storage::disk('local')->put('lucide/icons/check.svg', '<svg></svg>');
        Storage::disk('local')->put('lucide/icons/alert-circle.svg', '<svg></svg>');

        $cached = $this->service->getCachedIcons();
        
        $this->assertCount(2, $cached);
        $this->assertContains('check', $cached);
        $this->assertContains('alert-circle', $cached);
    }

    /** @test */
    public function test_delete_removes_cached_icon(): void
    {
        Storage::disk('local')->put('lucide/icons/check.svg', '<svg></svg>');
        $this->assertTrue($this->service->exists('check'));

        $this->service->delete('check');
        
        $this->assertFalse($this->service->exists('check'));
    }

    /** @test */
    public function test_clear_cache_removes_all_icons(): void
    {
        Storage::disk('local')->put('lucide/icons/check.svg', '<svg></svg>');
        Storage::disk('local')->put('lucide/icons/alert-circle.svg', '<svg></svg>');

        $this->assertCount(2, $this->service->getCachedIcons());

        $this->service->clearCache();
        
        $this->assertCount(0, $this->service->getCachedIcons());
    }

    /** @test */
    public function test_apply_attributes_adds_size_as_width_and_height(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('applyAttributes');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $svg, ['size' => '24']);
        
        $this->assertStringContainsString('width="24"', $result);
        $this->assertStringContainsString('height="24"', $result);
    }

    /** @test */
    public function test_apply_attributes_handles_color_alias(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('applyAttributes');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $svg, ['color' => '#ff0000']);
        
        $this->assertStringContainsString('stroke="#ff0000"', $result);
    }

    /** @test */
    public function test_apply_attributes_handles_camel_case_attributes(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('applyAttributes');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $svg, [
            'strokeWidth' => '3',
            'strokeLinecap' => 'butt',
            'strokeLinejoin' => 'miter',
        ]);
        
        $this->assertStringContainsString('stroke-width="3"', $result);
        $this->assertStringContainsString('stroke-linecap="butt"', $result);
        $this->assertStringContainsString('stroke-linejoin="miter"', $result);
    }

    /** @test */
    public function test_apply_attributes_merges_classes(): void
    {
        $svg = '<svg class="lucide" xmlns="http://www.w3.org/2000/svg"></svg>';
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('applyAttributes');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $svg, ['class' => 'w-6 h-6']);
        
        $this->assertStringContainsString('class="lucide w-6 h-6"', $result);
    }

    /** @test */
    public function test_cache_downloads_and_stores_icon(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        
        Http::fake([
            '*check.svg' => Http::response($svgContent, 200),
        ]);

        $result = $this->service->cache('check');
        
        $this->assertTrue($result);
        $this->assertTrue($this->service->exists('check'));
    }

    /** @test */
    public function test_cache_throws_on_404(): void
    {
        Http::fake([
            '*nonexistent.svg' => Http::response('Not Found', 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to fetch Lucide icon 'nonexistent': HTTP 404");

        $this->service->cache('nonexistent');
    }

    /** @test */
    public function test_cache_throws_on_invalid_svg(): void
    {
        Http::fake([
            '*invalid.svg' => Http::response('This is not SVG content', 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid SVG content");

        $this->service->cache('invalid');
    }

    /** @test */
    public function test_cache_skips_if_already_cached_without_force(): void
    {
        Storage::disk('local')->put('lucide/icons/check.svg', '<svg>original</svg>');
        
        Http::fake(); // No HTTP calls should be made

        $result = $this->service->cache('check', false);
        
        $this->assertTrue($result);
        $this->assertEquals('<svg>original</svg>', Storage::disk('local')->get('lucide/icons/check.svg'));
    }

    /** @test */
    public function test_cache_re_downloads_with_force(): void
    {
        Storage::disk('local')->put('lucide/icons/check.svg', '<svg>original</svg>');
        
        $newSvg = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5"/></svg>';
        Http::fake([
            '*check.svg' => Http::response($newSvg, 200),
        ]);

        $result = $this->service->cache('check', true);
        
        $this->assertTrue($result);
        $this->assertEquals($newSvg, Storage::disk('local')->get('lucide/icons/check.svg'));
    }

    /** @test */
    public function test_cache_many_returns_results_array(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><path/></svg>';
        
        Http::fake([
            '*check.svg' => Http::response($svgContent, 200),
            '*download.svg' => Http::response($svgContent, 200),
            '*nonexistent.svg' => Http::response('Not Found', 404),
        ]);

        $results = $this->service->cacheMany(['check', 'download', 'nonexistent']);
        
        $this->assertTrue($results['check']);
        $this->assertTrue($results['download']);
        $this->assertFalse($results['nonexistent']);
    }

    /** @test */
    public function test_get_icon_returns_cached_svg_with_attributes(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>';
        Storage::disk('local')->put('lucide/icons/check.svg', $svgContent);

        $result = $this->service->getIcon('check', ['class' => 'w-6 h-6']);
        
        $this->assertStringContainsString('class="w-6 h-6"', $result);
        $this->assertStringContainsString('<path', $result);
    }

    /** @test */
    public function test_get_icon_fetches_and_caches_missing_icon(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>';
        
        Http::fake([
            '*check.svg' => Http::response($svgContent, 200),
        ]);

        $this->assertFalse($this->service->exists('check'));
        
        $result = $this->service->getIcon('check');
        
        $this->assertTrue($this->service->exists('check'));
        $this->assertStringContainsString('<svg', $result);
    }
}
