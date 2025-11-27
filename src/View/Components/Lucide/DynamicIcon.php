<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\View\Components\Lucide;

use Aotr\DynamicLevelHelper\Services\LucideIconService;
use Closure;
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
     * Create a new component instance.
     */
    public function __construct(
        public string $name,
        public ?string $size = null,
        public ?string $stroke = null,
        public ?string $color = null,
        public ?string $strokeWidth = null,
        public ?string $strokeLinecap = null,
        public ?string $strokeLinejoin = null,
        public ?string $fill = null
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return function (array $data) {
            $attributes = $this->buildAttributes($data['attributes'] ?? null);

            try {
                /** @var LucideIconService $service */
                $service = app(LucideIconService::class);
                return $service->getIcon($this->name, $attributes);
            } catch (\Throwable $e) {
                // Log the error and return an empty string or placeholder
                report($e);
                return '<!-- Lucide icon "' . e($this->name) . '" could not be loaded -->';
            }
        };
    }

    /**
     * Build the attributes array from component properties.
     *
     * @param \Illuminate\View\ComponentAttributeBag|null $attributeBag
     */
    protected function buildAttributes($attributeBag = null): array
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
        if ($attributeBag !== null) {
            $attributes = array_merge($attributes, $attributeBag->getAttributes());
        }

        return $attributes;
    }
}
