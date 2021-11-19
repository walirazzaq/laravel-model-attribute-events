<?php

namespace Walirazzaq\AttributeEvents\Livewire\Concerns;

use ReflectionClass;
use ReflectionMethod;
use Walirazzaq\AttributeEvents\Attributes\SubscribeTo;

/**
 * @property \Livewire\Component $this
 */
trait RegisterSubscribers
{
    public function bootedRegisterSubscribers()
    {
        /**
         * @var SubscribeTo $instance
         */
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(SubscribeTo::class);
        foreach ($attributes as $attribute) {
            $attribute->newInstance()->subscribe($this);
        }
        $properties = $reflection->getProperties();
        foreach ($properties as $reflectionProperty) {
            $attributes = $reflectionProperty->getAttributes(SubscribeTo::class);
            foreach ($attributes as $attribute) {
                $attribute->newInstance()->forProperty($reflectionProperty->getValue($this))->subscribe($this);
            }
        }
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $reflectionMethod) {
            $attributes = $reflectionMethod->getAttributes(SubscribeTo::class);
            foreach ($attributes as $attribute) {
                $attribute->newInstance()->forMethod($reflectionMethod->getName())->subscribe($this);
            }
        }
    }
}
