<?php

namespace Walirazzaq\LaravelModelAttributeEvents\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class BroadcastableModelAttributeEventOccurred implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * The model instance corresponding to the event.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * The attribute name which got updated (name, role_id etc).
     *
     * @var string
     */
    protected $attribute;

    /**
     * The channels that the event should be broadcast on.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * The queue connection that should be used to queue the broadcast job.
     *
     * @var string
     */
    public $connection;

    /**
     * The queue that should be used to queue the broadcast job.
     *
     * @var string
     */
    public $queue;

    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $attribute
     * @return void
     */
    public function __construct($model, $attribute)
    {
        $this->model = $model;
        $this->attribute = $attribute;
    }

    /**
     * The channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        $channels = empty($this->channels)
            ? ($this->model->broadcastAttributeOn($this->attribute) ?: [])
            : $this->channels;

        return collect($channels)->map(function ($channel) {
            return $channel instanceof Model ? new PrivateChannel($channel) : $channel;
        })->all();
    }

    /**
     * The name the event should broadcast as.
     *
     * @return string
     */
    public function broadcastAs()
    {
        $default = $this->defaultBroadcastAs();

        return method_exists($this->model, 'broadcastAttributeAs')
            ? ($this->model->broadcastAttributeAs($this->attribute) ?: $default)
            : $default;
    }

    public function defaultBroadcastAs()
    {
        return class_basename($this->model) . 'Updated' . ucfirst(Str::camel($this->attribute)) . $this->model->getKey();
    }

    /**
     * Get the data that should be sent with the broadcasted event.
     *
     * @return array|null
     */
    public function broadcastWith()
    {
        $payload = method_exists($this->model, 'broadcastAttributeWith')
            ? $this->model->broadcastAttributeWith($this->attribute)
            : null;
        if (is_null($payload) == false) {
            return $payload;
        }
        return [
            $this->model->getKeyName() => $this->model->getKey(),
            $this->attribute => rescue(fn () => $this->model->getAttribute($this->attribute))
        ];
    }

    /**
     * Manually specify the channels the event should broadcast on.
     *
     * @param  array  $channels
     * @return $this
     */
    public function onChannels(array $channels)
    {
        $this->channels = $channels;

        return $this;
    }

    /**
     * Determine if the event should be broadcast synchronously.
     *
     * @return bool
     */
    public function shouldBroadcastNow()
    {
        return method_exists($this->model, 'shouldBroadcastAttributeNow')
            ? $this->model->shouldBroadcastAttributeNow($this->attribute)
            : false;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function attribute()
    {
        return $this->attribute;
    }
}
