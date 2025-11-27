<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\View\Components\Lucide;

use Aotr\DynamicLevelHelper\Services\LucideIconService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Dynamic Lucide Icon Blade Component.
 *
 * Usage:
 *   <x-lucide-icon name="check" class="w-6 h-6 text-success" />
 *   <x-lucide-icon name="alert-circle" size="24" stroke="#333" />
 */
class DynamicIcon extends Component
{
    /**
     * The icon name (kebab-case).
     */
    public string $name;

    /**
     * Optional size (sets width and height).
     */
    public ?string $size;

    /**
     * Optional stroke color.
     */
    public ?string $stroke;

    /**
     * Optional color (alias for stroke).
     */
    public ?string $color;

    /**
     * Optional stroke width.
     */
    public ?string $strokeWidth;

    /**
     * Optional stroke linecap.
     */
    public ?string $strokeLinecap;

    /**
     * Optional stroke linejoin.
     */
    public ?string $strokeLinejoin;

    /**
     * Optional fill color.
     */
    public ?string $fill;

    /**
     * Create a new component instance.
     */
    public function __construct(
        string $name,
        ?string $size = null,
        ?string $stroke = null,
        ?string $color = null,
        ?string $strokeWidth = null,
        ?string $strokeLinecap = null,
        ?string $strokeLinejoin = null,
        ?string $fill = null
    ) {
        $this->name = $name;
        $this->size = $size;
        $this->stroke = $stroke;
        $this->color = $color;
        $this->strokeWidth = $strokeWidth;
        $this->strokeLinecap = $strokeLinecap;
        $this->strokeLinejoin = $strokeLinejoin;
        $this->fill = $fill;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|string
    {
        $attributes = $this->buildAttributes();

        try {
            /** @var LucideIconService $service */
            $service = app(LucideIconService::class);
            return $service->getIcon($this->name, $attributes);
        } catch (\Throwable $e) {
            // Log the error and return an empty string or placeholder
            report($e);
            return '<!-- Lucide icon "' . e($this->name) . '" could not be loaded -->';
        }
    }

    /**
     * Build the attributes array from component properties.
     */
    protected function buildAttributes(): array
    {
        $attributes = [];

        if ($this->size !== null) {
            $attributes['size'] = $this->size;
        }

        if ($this->stroke !== null) {
            $attributes['stroke'] = $this->stroke;
        }

        if ($this->color !== null) {
            $attributes['color'] = $this->color;
        }

        if ($this->strokeWidth !== null) {
            $attributes['stroke-width'] = $this->strokeWidth;
        }

        if ($this->strokeLinecap !== null) {
            $attributes['stroke-linecap'] = $this->strokeLinecap;
        }

        if ($this->strokeLinejoin !== null) {
            $attributes['stroke-linejoin'] = $this->strokeLinejoin;
        }

        if ($this->fill !== null) {
            $attributes['fill'] = $this->fill;
        }

        // Merge with any additional attributes passed to the component
        return array_merge($attributes, $this->attributes->getAttributes());
    }
}
