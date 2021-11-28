<?php

namespace Walirazzaq\AttributeEvents\Attributes;

use Attribute;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Livewire\Component;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class SubscribeTo
{
    public ?string $event = null;
    public ?Dispatcher $dispatcher = null;
    public function __construct(
        string|array $target,
        public ?string $method = null,
        public bool $emit = true,
    ) {
        $this->dispatcher = app('events');
        if (is_string($target)) {
            $this->event = $target;
            return;
        }
        if (Arr::isAssoc($target)) {
            throw new Exception("invalid attribute argument" . static::class);
        }
        if (blank($target) || count($target) > 2) {
            throw new Exception("invalid attribute argument" . static::class);
        }
        $this->event = $target[1];
        $this->getDispatcherAndEventForModel($target[0]);
    }

    protected function getDispatcherAndEventForModel($object)
    {
        throw_if(!$object || is_a($object, Model::class, true) == false);
        $event = $this->event;

        if (is_object($object)) {
            if (method_exists($object, 'getDistinctObservableAttributeName')) {
                $event = $object->getDistinctObservableAttributeName($event);
            }
            $this->event = "eloquent.{$event}: " . get_class($object);
        } else {
            if (method_exists($object, 'getObserverableAttributeName')) {
                $event = $object::getObserverableAttributeName($event);
            }
            $this->event = "eloquent.{$event}: {$object}";
        }
        $this->dispatcher = $object::getEventDispatcher();
    }

    public function forProperty($object): self
    {
        if ($object instanceof Model) {
            $this->getDispatcherAndEventForModel($object);
        }
        return $this;
    }

    public function forMethod(string $name): self
    {
        $this->method = $name;
        return $this;
    }

    public function subscribe(Component $component)
    {
        if (!$this->event && !$this->dispatcher) return;
        if ($this->method) {
            $this->dispatcher->listen($this->event, [$component, $this->method]);
        }
        if ($this->emit) {
            $this->dispatcher->listen($this->event, fn ($payload) => $component->emit($this->event, $this->toLivewireEmit($payload)));
        }
    }
    protected function toLivewireEmit($payload)
    {
        if (is_object($payload) == false) return $payload;

        if (method_exists($payload, 'toLivewireEmit')) return $payload->toLivewireEmit();

        if ($payload instanceof Model) return $payload->getKey();

        return $payload;
    }
}
