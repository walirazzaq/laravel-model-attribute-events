<?php

namespace Walirazzaq\AttributeEvents\Livewire\Concerns;

use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;
use Walirazzaq\AttributeEvents\Attributes\ListenToModelAttr;

/**
 * @property-read Component $this
 */
trait HasModelAttributeListeners
{
    protected array $modelAttributeListeners = [];

    public function mountHasModelAttributeListeners()
    {
        /**
         * @var ListenToModelAttr $instance
         */
        $reflection = new ReflectionClass($this);
        $props = $reflection->getProperties();
        foreach ($props as $prop) {
            $attributes = $prop->getAttributes(ListenToModelAttr::class);
            foreach ($attributes as $attribute) {
                $listeners = $attribute->newInstance()->setModel($prop->getValue($this))->getListeners();
                $this->modelAttributeListeners = array_merge($listeners, $this->modelAttributeListeners);
            }
        }
    }

    protected function getEventsAndHandlers()
    {
        // if ($this::getName() == 'customer.avatar') {
        //     dd(array_merge(
        //         $this->modelAttributeListeners,
        //         $this->getListeners()
        //     ));
        // }
        return collect(
            array_merge(
                $this->modelAttributeListeners,
                $this->getListeners()
            )
        )
            ->mapWithKeys(function ($value, $key) {
                $key = is_numeric($key) ? $value : $key;

                return [$key => $value];
            })->toArray();
    }

    public function fireEvent($event, $params, $id)
    {
        $method = $this->getEventsAndHandlers()[$event] ?? '$refresh';

        $this->callMethod($method, $params, function ($returned) use ($event, $id) {
            Livewire::dispatch('action.returned', $this, $event, $returned, $id);
        });
    }
}
