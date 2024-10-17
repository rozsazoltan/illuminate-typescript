<?php

namespace Rose\IlluminateTypeScript\Generators;

use Rose\IlluminateTypeScript\Contracts\Generator;
use Illuminate\Support\Collection;
use ReflectionClass;

abstract class AbstractGenerator implements Generator
{
    protected ReflectionClass $reflection;

    private array $defaultSettings = [];

    protected Collection $settings;

    public function __construct(
        array $userSettings = [],
    )
    {
        $this->settings = collect($this->defaultSettings)->mergeRecursive($userSettings);
    }

    public function generate(ReflectionClass $reflection): ?string
    {
        $this->reflection = $reflection;
        $this->boot();

        if (empty(trim($definition = $this->getDefinition()))) {
            return "  export interface {$this->tsClassName()} {}" . PHP_EOL;
        }

        return <<<TS
          export interface {$this->tsClassName()} {
            $definition
          }

        TS;
    }

    protected function boot(): void
    {
        //
    }

    protected function tsClassName(): string
    {
        return str_replace('\\', '.', $this->reflection->getShortName());
    }
}
