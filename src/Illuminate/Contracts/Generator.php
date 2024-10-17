<?php

namespace Rose\IlluminateTypeScript\Contracts;

use ReflectionClass;

interface Generator
{
    public function __construct(array $userSettings = []);

    public function generate(ReflectionClass $reflection): ?string;

    public function getDefinition(): ?string;
}
