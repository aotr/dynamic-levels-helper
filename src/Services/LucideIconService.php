<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service for fetching, caching, and rendering Lucide SVG icons.
 */
class LucideIconService
{
    /**
     * The storage disk to use.
     */
    protected string $disk;

    /**
     * The storage path for icons.
     */
    protected string $storagePath;

    /**
     * The remote source URL template.
     */
    protected string $remoteSource;

    /**
     * Default SVG attributes.
     */
    protected array $defaultAttributes;

    /**
     * Create a new LucideIconService instance.
     */
    public function __construct()
    {
        $this->disk = config('lucide.icon_storage_disk', 'local');
        $this->storagePath = config('lucide.icon_storage_path', 'lucide/icons');
        $this->remoteSource = config('lucide.remote_source', 'https://unpkg.com/lucide-static@latest/icons/{icon}.svg');
        $this->defaultAttributes = config('lucide.default_attributes', [
            'stroke' => 'currentColor',
            'stroke-width' => '2',
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
            'fill' => 'none',
        ]);
    }

    /**
     * Get an icon's SVG content with applied attributes.
     *
     * @param string $name The icon name (kebab-case)
     * @param array $attributes Additional SVG attributes to apply
     * @return string The rendered SVG string
     * @throws RuntimeException If icon cannot be fetched
     */
    public function getIcon(string $name, array $attributes = []): string
    {
        $name = $this->normalizeName($name);
        $svg = $this->loadIcon($name);

        return $this->applyAttributes($svg, $attributes);
    }

    /**
     * Check if an icon exists in the cache.
     *
     * @param string $name The icon name
     * @return bool
     */
    public function exists(string $name): bool
    {
        $name = $this->normalizeName($name);
        return Storage::disk($this->disk)->exists($this->getIconPath($name));
    }

    /**
     * Cache an icon from the remote source.
     *
     * @param string $name The icon name
     * @param bool $force Force re-download even if cached
     * @return bool True if successfully cached
     * @throws RuntimeException If download fails
     */
    public function cache(string $name, bool $force = false): bool
    {
        $name = $this->normalizeName($name);
        $path = $this->getIconPath($name);

        if (!$force && Storage::disk($this->disk)->exists($path)) {
            return true;
        }

        $svg = $this->fetchFromRemote($name);
        
        return Storage::disk($this->disk)->put($path, $svg);
    }

    /**
     * Cache multiple icons.
     *
     * @param array $names Array of icon names
     * @param bool $force Force re-download
     * @return array Results with icon names as keys and success status as values
     */
    public function cacheMany(array $names, bool $force = false): array
    {
        $results = [];

        foreach ($names as $name) {
            try {
                $results[$name] = $this->cache($name, $force);
            } catch (RuntimeException $e) {
                Log::warning("Failed to cache Lucide icon: {$name}", ['error' => $e->getMessage()]);
                $results[$name] = false;
            }
        }

        return $results;
    }

    /**
     * Get all cached icon names.
     *
     * @return array
     */
    public function getCachedIcons(): array
    {
        $files = Storage::disk($this->disk)->files($this->storagePath);
        
        return array_map(function ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
    }

    /**
     * Clear all cached icons.
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        $files = Storage::disk($this->disk)->files($this->storagePath);
        
        foreach ($files as $file) {
            Storage::disk($this->disk)->delete($file);
        }

        return true;
    }

    /**
     * Delete a specific cached icon.
     *
     * @param string $name The icon name
     * @return bool
     */
    public function delete(string $name): bool
    {
        $name = $this->normalizeName($name);
        $path = $this->getIconPath($name);

        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Load an icon from cache or fetch from remote.
     *
     * @param string $name The normalized icon name
     * @return string The raw SVG content
     * @throws RuntimeException If icon cannot be loaded
     */
    protected function loadIcon(string $name): string
    {
        $path = $this->getIconPath($name);

        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->get($path);
        }

        // Try to fetch and cache the icon
        $svg = $this->fetchFromRemote($name);
        Storage::disk($this->disk)->put($path, $svg);

        return $svg;
    }

    /**
     * Fetch an icon from the remote source.
     *
     * @param string $name The icon name
     * @return string The SVG content
     * @throws RuntimeException If fetch fails
     */
    protected function fetchFromRemote(string $name): string
    {
        $url = str_replace('{icon}', $name, $this->remoteSource);

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                throw new RuntimeException(
                    "Failed to fetch Lucide icon '{$name}': HTTP {$response->status()}"
                );
            }

            $content = $response->body();

            // Validate it's actually SVG content
            if (!str_contains($content, '<svg') || !str_contains($content, '</svg>')) {
                throw new RuntimeException(
                    "Invalid SVG content received for Lucide icon '{$name}'"
                );
            }

            return $content;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Failed to fetch Lucide icon '{$name}': {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Apply attributes to an SVG string.
     *
     * @param string $svg The raw SVG content
     * @param array $attributes Attributes to apply
     * @return string The modified SVG
     */
    protected function applyAttributes(string $svg, array $attributes): string
    {
        // Merge with default attributes, user attributes take precedence
        $mergedAttributes = array_merge($this->defaultAttributes, $attributes);

        // Handle special attributes
        if (isset($mergedAttributes['size'])) {
            $size = $mergedAttributes['size'];
            $mergedAttributes['width'] = $size;
            $mergedAttributes['height'] = $size;
            unset($mergedAttributes['size']);
        }

        // Handle color -> stroke alias
        if (isset($mergedAttributes['color'])) {
            $mergedAttributes['stroke'] = $mergedAttributes['color'];
            unset($mergedAttributes['color']);
        }

        // Handle camelCase to kebab-case conversions
        $attributeAliases = [
            'strokeWidth' => 'stroke-width',
            'strokeLinecap' => 'stroke-linecap',
            'strokeLinejoin' => 'stroke-linejoin',
        ];

        foreach ($attributeAliases as $camel => $kebab) {
            if (isset($mergedAttributes[$camel])) {
                $mergedAttributes[$kebab] = $mergedAttributes[$camel];
                unset($mergedAttributes[$camel]);
            }
        }

        // Handle class merging
        $existingClass = '';
        if (preg_match('/class=["\']([^"\']*)["\']/', $svg, $matches)) {
            $existingClass = $matches[1];
        }

        if (isset($mergedAttributes['class'])) {
            $newClass = trim($existingClass . ' ' . $mergedAttributes['class']);
            $mergedAttributes['class'] = $newClass;
        } elseif ($existingClass) {
            $mergedAttributes['class'] = $existingClass;
        }

        // Build attribute string
        $attrString = '';
        foreach ($mergedAttributes as $key => $value) {
            if ($value !== null && $value !== '') {
                $attrString .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') 
                    . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        // Replace or insert attributes in the SVG tag
        $svg = preg_replace(
            '/<svg([^>]*)>/i',
            '<svg' . $attrString . '>',
            $svg,
            1
        );

        return $svg;
    }

    /**
     * Normalize the icon name to kebab-case.
     *
     * @param string $name The icon name
     * @return string The normalized name
     */
    protected function normalizeName(string $name): string
    {
        return Str::kebab(trim($name));
    }

    /**
     * Get the storage path for an icon.
     *
     * @param string $name The icon name
     * @return string The full storage path
     */
    protected function getIconPath(string $name): string
    {
        return rtrim($this->storagePath, '/') . '/' . $name . '.svg';
    }
}
