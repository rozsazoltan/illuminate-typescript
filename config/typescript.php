<?php

use Rose\IlluminateTypeScript\Generators\ModelGenerator;
use Illuminate\Database\Eloquent\Model;

return [
    'generators' => [
        Model::class => [
            'name' => ModelGenerator::class,
            'settings' => [
                'shouldDBType' => true,
                'shouldStrictEnum' => true,
            ],
        ],
    ],

    'paths' => [
        //
    ],

    'customRules' => [
        // \App\Rules\MyCustomRule::class => 'string',
        // \App\Rules\MyOtherCustomRule::class => ['string', 'number'],
    ],

    'output' => resource_path('js/models.d.ts'),

    'autoloadDev' => false,
];
