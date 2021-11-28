<?php

namespace Walirazzaq\AttributeEvents\Models\Concerns;

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
                    $normalObservables = $observables->map(fn ($name) => $this::getObserverableAttributeName($name));
                    $distinctObservables = $observables->map(fn ($name) => $this->getDistinctObservableAttributeName($name));
                    $observables = $normalObservables->merge($distinctObservables)->filter()->unique();
                    $this->setObservableEvents($observables->all());
                    $observables->each(fn ($event) => $this->fireModelEvent($event, false));
                },
                $model
            ))();
        });
    }

    public static function onAttributeUpdated(string $attribute, Closure $callback)
    {
        static::registerModelEvent(
            static::getObserverableAttributeName($attribute),
            $callback
        );
    }

    public function dispatchModelAttributeEvent(string $attribute, bool $broadcast = true, $channels = null)
    {
        foreach ([
            $this->getDistinctObservableAttributeName($attribute),
            static::getObserverableAttributeName($attribute)
        ] as $event) {
            $this->fireModelEvent($event, false);
        }
        if ($broadcast) {
            $this->broadcastAttributeEvent($attribute, $channels);
        }
    }

    public function getDistinctObservableAttributeName($attribute)
    {
        if (in_array($attribute, $this->getObservableEvents())) {
            return $attribute;
        }
        if ($this->getKey()) {
            $attribute = $attribute . $this->getKey();
        }
        return static::getObserverableAttributeName($attribute);
    }

    public static function getObserverableAttributeName($attribute)
    {
        $instance = new static;
        if (in_array($attribute, $instance->getObservableEvents())) {
            return $attribute;
        }
        return 'updated' . ucfirst(Str::camel($attribute));
    }
}
