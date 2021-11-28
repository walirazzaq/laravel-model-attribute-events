<?php

namespace Walirazzaq\AttributeEvents\Models\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Walirazzaq\AttributeEvents\Events\BroadcastableModelAttributeEventOccurred;

/**
 * @property \Illuminate\Database\Eloquent\Model $this
 */
trait BroadcastsAttributeEvents
{
    protected static $defaultAttributeBroadcastChannels = [];


    public static function bootBroadcastsAttributeEvents()
    {
        static::updated(function (Model $model) {
            (Closure::bind(
                function () {
                    foreach ($this->observableAttributes as $attribute) {
                        $this->broadcastAttributeEvent($attribute);
                    }
                },
                $model
            ))();
        });
    }
    public static function setDefaultAttributeBroadcastChannels($channels)
    {
        static::$defaultAttributeBroadcastChannels = Arr::wrap($channels);
    }

    public function broadcastAttributeEvent(string $attribute, $channels = null)
    {
        return $this->broadcastIfBroadcastChannelsExistForAttributeEvent(
            $this->newBroadcastableModelAttributeEvent($attribute),
            $attribute,
            $channels
        );
    }

    /**
     * Broadcast the given event instance if channels are configured for the model event.
     *
     * @param  mixed  $instance
     * @param  string  $event
     * @param  mixed  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast|null
     */
    protected function broadcastIfBroadcastChannelsExistForAttributeEvent($instance, $attribute, $channels = null)
    {
        if (!static::$isBroadcasting) {
            return;
        }

        if (!empty($this->broadcastAttributeOn($attribute)) || !empty($channels)) {
            return broadcast($instance->onChannels(Arr::wrap($channels)));
        }
    }

    public function newBroadcastableModelAttributeEvent($attribute)
    {
        return tap($this->newBroadcastableAttributeEvent($attribute), function ($event) use ($attribute) {
            $event->connection = property_exists($this, 'broadcastAttributeConnection')
                ? $this->broadcastConnection[$attribute] ?? $this->broadcastAttributeConnection($attribute)
                : $this->broadcastAttributeConnection($attribute);

            $event->queue = property_exists($this, 'broadcastAttributeQueue')
                ? $this->broadcastAttributeQueue[$attribute] ?? $this->broadcastAttributeQueue($attribute)
                : $this->broadcastAttributeQueue($attribute);

            $event->afterCommit = property_exists($this, 'broadcastAttributeAfterCommit')
                ? $this->broadcastAttributeAfterCommit[$attribute] ?? $this->broadcastAttributeAfterCommit($attribute)
                : $this->broadcastAttributeAfterCommit($attribute);
        });
    }

    /**
     * Create a new broadcastable model attribute event for the model.
     *
     * @param  string  $attribute
     * @return BroadcastableModelAttributeEventOccurred
     */
    public function newBroadcastableAttributeEvent($attribute)
    {
        if ($this->dontBroadcastAttributeToCurrentUser($attribute)) {
            return (new BroadcastableModelAttributeEventOccurred($this, $attribute))->dontBroadcastToCurrentUser();
        } else {
            return (new BroadcastableModelAttributeEventOccurred($this, $attribute));
        }
    }

    /**
     * Get the channels that model attribute events should broadcast on.
     *
     * @param  string  $event
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastAttributeOn($attribute)
    {
        return static::$defaultAttributeBroadcastChannels ?: [];
    }
    /**
     * Get the queue connection that should be used to broadcast model events.
     * @param string|null $attribute
     * @return string|null
     */
    public function broadcastAttributeConnection($attribute = null)
    {
        //
    }

    /**
     * Get the queue that should be used to broadcast model events.
     * @param string|null $attribute
     * @return string|null
     */
    public function broadcastAttributeQueue($attribute = null)
    {
        //
    }

    public function dontBroadcastAttributeToCurrentUser($attribute)
    {
        return true;
    }

    /**
     * Determine if the model event broadcast queued job should be dispatched after all transactions are committed.
     * @param string|null $attribute
     * @return bool
     */
    public function broadcastAttributeAfterCommit($attribute = null)
    {
        return false;
    }
}
