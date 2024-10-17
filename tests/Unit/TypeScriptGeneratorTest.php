<?php

use Rose\IlluminateTypeScript\Foundation\TypeScriptGenerator;

it('convert models to typescript interfaces', function () {
    $output = @tempnam(__DIR__ . '/tmp', 'models.d.ts');

    $generator = new TypeScriptGenerator(
        generators: config('typescript.generators'),
        output: $output,
        autoloadDev: true
    );

    $generator->execute();

    expect($output)->toBeFile();

    $result = file_get_contents($output);

    expect(substr_count($result, 'interface'))->toEqual(3);
    expect(str_contains($result, 'sub_category?: Rose.IlluminateTypeScript.Tests.Models.Category | null;'))->toBeTrue();
    expect(str_contains($result, 'products_count?: number | null;'))->toBeTrue();

    unlink($output);
});
