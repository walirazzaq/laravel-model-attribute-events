<?php

namespace Walirazzaq\AttributeEvents\Attributes;

use Attribute;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Walirazzaq\AttributeEvents\Support\BroadcastingAuthorizer;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_PROPERTY)]
class ListenToModelAttr
{
    public ?string $event = null;
    public array $attributes = [];
    public ?Model $model = null;
    public ?Dispatcher $dispatcher = null;
    public function __construct(
        string|array $attribute,
        public string $method = '$refresh',
        public bool $echo = true,
        public bool $native = true,
    ) {
        $this->attributes = array_unique(
            array_filter(
                Arr::flatten(
                    Arr::wrap($attribute)
                )
            )
        );
    }

    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function nativeListeners(): array
    {
        if (!$this->native || !auth()->user()) return [];
        $listeners = [];
        foreach ($this->attributes  as $attribute) {
            $event = $attribute;
            if (method_exists($this->model, 'getDistinctObservableAttributeName')) {
                $event = $this->model->getDistinctObservableAttributeName($attribute);
            }
            $listeners["eloquent.{$event}: " . get_class($this->model)] = $this->method;
        }
        return $listeners;
    }

    public function echoListeners(): array
    {
        if (!$this->echo) return [];

        $listeners = BroadcastingAuthorizer::make($this->model, $this->attributes)->getListeners();
        $withMethod = [];
        foreach ($listeners as $listener) {
            $withMethod[$listener] = $this->method;
        }
        return $withMethod;
    }

    public function getListeners(): array
    {
        return array_merge($this->nativeListeners(), $this->echoListeners());
    }

}
