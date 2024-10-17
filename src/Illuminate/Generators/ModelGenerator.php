<?php

namespace Rose\IlluminateTypeScript\Generators;

use Rose\IlluminateTypeScript\Definitions\TypeScriptProperty;
use Rose\IlluminateTypeScript\Definitions\TypeScriptType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class ModelGenerator extends AbstractGenerator
{
    protected Model $model;
    protected Collection $columns;

    private array $defaultSettings = [
        "shouldDBType" => true,
        "shouldStrictEnum" => true,
    ];

    public function getDefinition(): ?string
    {
        return collect([
            $this->getProperties(),
            $this->getRelations(),
            $this->getManyRelations(),
            $this->getAccessors(),
        ])
            ->filter(fn (string $part) => !empty($part))
            ->join(PHP_EOL . '    ');
    }

    /**
     * @throws \ReflectionException
     */
    protected function boot(): void
    {
        $this->model = $this->reflection->newInstance();

        $this->columns = collect(Schema::connection($this->model->getConnectionName())->getColumns($this->model->getTable()));
    }

    protected function getProperties(): string
    {
        return $this->columns->map(function ($column) {
            $types = [];
            if ($this->settings->get('shouldDBType')) {
                array_push($types, '/*' . $column['type'] . '*/');
            }

            $propertyType = $this->getPropertyType($column['type']);
            if (is_array($propertyType)) {
                $types = array_merge($types, $propertyType);
            } else {
                array_push($types, $propertyType);
            }

            return (string) new TypeScriptProperty(
                name: $column['name'],
                types: $types,
                nullable: $column['nullable'],
            );
        })
        ->join(PHP_EOL . '    ');
    }

    protected function getAccessors(): string
    {
        $relationsToSkip =  $this->getRelationMethods()
            ->map(function (ReflectionMethod $method) {
                return Str::snake($method->getName());
            });

        return $this->getMethods()
            ->filter(fn (ReflectionMethod $method) => Str::startsWith($method->getName(), 'get'))
            ->filter(fn (ReflectionMethod $method) => Str::endsWith($method->getName(), 'Attribute'))
            ->mapWithKeys(function (ReflectionMethod $method) {
                $property = (string) Str::of($method->getName())
                    ->between('get', 'Attribute')
                    ->snake();

                return [$property => $method];
            })
            ->reject(function (ReflectionMethod $method, string $property) {
                return $this->columns->contains(fn ($column) => $column['name'] == $property);
            })
            ->reject(function (ReflectionMethod $method, string $property) use ($relationsToSkip) {
                return $relationsToSkip->contains($property);
            })
            ->map(function (ReflectionMethod $method, string $property) {
                return (string) new TypeScriptProperty(
                    name: $property,
                    types: TypeScriptType::fromMethod($method),
                    optional: true,
                    readonly: true
                );
            })
            ->join(PHP_EOL . '    ');
    }

    protected function getRelations(): string
    {
        return $this->getRelationMethods()
            ->map(function (ReflectionMethod $method) {
                return (string) new TypeScriptProperty(
                    name: Str::snake($method->getName()),
                    types: $this->getRelationType($method),
                    optional: true,
                    nullable: true
                );
            })
            ->join(PHP_EOL . '    ');
    }

    protected function getManyRelations(): string
    {
        return $this->getRelationMethods()
            ->filter(fn (ReflectionMethod $method) => $this->isManyRelation($method))
            ->map(function (ReflectionMethod $method) {
                return (string) new TypeScriptProperty(
                    name: Str::snake($method->getName()) . '_count',
                    types: TypeScriptType::NUMBER,
                    optional: true,
                    nullable: true
                );
            })
            ->join(PHP_EOL . '    ');
    }

    protected function getRelationMethods(): Collection
    {
        return $this->getMethods()
            ->filter(function (ReflectionMethod $method) {
                try {
                    return $method->invoke($this->model) instanceof Relation;
                } catch (Throwable) {
                    return false;
                }
            })
            ->filter(function (ReflectionMethod $method) {
                return collect($this->reflection->getTraits())
                    ->filter(function (ReflectionClass $trait) use ($method) {
                        return $trait->hasMethod($method->name);
                    })
                    ->isEmpty();
            });
    }

    protected function getMethods(): Collection
    {
        return collect($this->reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $method) => $method->isStatic())
            ->reject(fn (ReflectionMethod $method) => $method->getNumberOfParameters());
    }

    protected function getPropertyType(string $type): string|array
    {
        $typeName = $this->getTypeName($type);

        return match ($typeName) {
            'array' => [TypeScriptType::array(), TypeScriptType::ANY],
            'bigint', 'integer', 'int' => TypeScriptType::NUMBER,
            'tinyint', 'smallint', 'mediumint' => TypeScriptType::NUMBER,
            'decimal', 'double', 'float', 'numeric' => TypeScriptType::NUMBER,
            'boolean' => TypeScriptType::BOOLEAN,
            'date', 'datetime', 'timestamp', 'time', 'year' => TypeScriptType::STRING,
            'json' => [TypeScriptType::array(), TypeScriptType::ANY],
            'varchar', 'char', 'string', 'text', 'mediumtext', 'longtext', 'tinytext' => TypeScriptType::STRING,
            'blob', 'mediumblob', 'longblob', 'tinyblob' => TypeScriptType::ANY,
            'enum' => $this->settings->get('shouldStrictEnum') ? $this->getEnum($type) : TypeScriptType::STRING,
            default => TypeScriptType::ANY,
        };
    }

    protected function getTypeName(string $type): string
    {
        $type = trim($type);

        $type = preg_replace('/\s*\(.*?\)\s*/', ' ', $type);
        $type = preg_replace('/\s+/', ' ', $type);

        $typeParts = explode(' ', $type);

        return $typeParts[0];
    }

    protected function getEnum(string $enumDefinition): array
    {
        $enumDefinition = Str::replaceFirst('enum(', '', $enumDefinition);
        $enumDefinition = Str::replaceLast(')', '', $enumDefinition);

        return array_map('trim', explode(',', $enumDefinition));
    }

    protected function getRelationType(ReflectionMethod $method): string
    {
        $relationReturn = $method->invoke($this->model);
        $related = str_replace('\\', '.', get_class($relationReturn->getRelated()));

        if ($this->isManyRelation($method)) {
            return TypeScriptType::array($related);
        }

        if ($this->isOneRelattion($method)) {
            return $related;
        }

        return TypeScriptType::ANY;
    }

    protected function isManyRelation(ReflectionMethod $method): bool
    {
        $relationType = get_class($method->invoke($this->model));

        return in_array(
            $relationType,
            [
                HasMany::class,
                BelongsToMany::class,
                HasManyThrough::class,
                MorphMany::class,
                MorphToMany::class,
            ]
        );
    }

    protected function isOneRelattion(ReflectionMethod $method): bool
    {
        $relationType = get_class($method->invoke($this->model));

        return in_array(
            $relationType,
            [
                HasOne::class,
                BelongsTo::class,
                MorphOne::class,
                HasOneThrough::class,
            ]
        );
    }
}
