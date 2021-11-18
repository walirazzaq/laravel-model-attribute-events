<?php

namespace Walirazzaq\LaravelModelAttributeEvents\Models\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @property \Illuminate\Database\Eloquent\Model $this
 */
trait HasAttributeEvents
{

    protected $observableAttributes = [];
    protected function getMappedAttributeEventNames(): array
    {
        return property_exists($this, 'mapAttributeEventName') ? array_filter(Arr::wrap($this->mapAttributeEventName)) : [];
    }

    public static function bootHasAttributeEvents()
    {
        static::updated(function (Model $model) {
            (Closure::bind(
                function () {
                    $mappable = collect($this->getMappedAttributeEventNames());
                    $dirty = collect(array_keys($this->getDirty()));
                    $observables = $dirty->map(
                        fn ($attr) => $mappable->map(
                            fn ($from, $to) => in_array($attr, Arr::wrap($from)) ? $to : $attr
                        )->first(default: $attr)
                    )->merge($dirty)
                        ->filter()
                        ->unique();
                    $this->observableAttributes =  $observables->all();
                    $observables = $observables->map(fn ($name) => 'updated' . ucfirst(Str::camel($name)));
                    $this->setObservableEvents($observables->all());
                    $observables->each(fn ($event) => $this->fireModelEvent($event, false));
                },
                $model
            ))();
        });
    }

    public static function onAttributeUpdated(string $attribute, Closure $callback)
    {
        $event = 'updated' . ucfirst(Str::camel($attribute));
        static::registerModelEvent($event, $callback);
    }
}
