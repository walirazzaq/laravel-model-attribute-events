<?php

namespace Walirazzaq\AttributeEvents\Support;

use Closure;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BroadcastingAuthorizer
{
    protected PusherBroadcaster $broadcaster;
    protected static $channels = [];
    protected $listeners = [];

    public function __construct(
        public Model $model,
        public array $attributes
    ) {
        $this->broadcaster = app(Broadcaster::class);
    }

    public static function make(Model $model, array $attributes): static
    {
        return new static($model, $attributes);
    }

    protected function handle()
    {
        foreach ($this->attributes as  $attribute) {
            $event = $this->model->newBroadcastableAttributeEvent($attribute);
            $on = $this->stringify($event->broadcastOn());
            $as = trim($event->broadcastAs());
            if (blank($on) || ! $as) {
                continue;
            }
            if ($toUse = $this->getFromCachedIfAvailable($on)) {
                $this->configureChannel($toUse, $as);

                continue;
            }
            if ($toUse = $this->getPublicChannels($on)) {
                $this->configureChannel($toUse, $as);

                continue;
            }
            if (! $user = auth()->user()) {
                continue;
            }
            foreach ($on as  $channel) {
                $request = (new Request())->setUserResolver(fn () => $user);
                $authorizer = Closure::bind(function ($request, $channel) {
                    /**
                     * @var PusherBroadcaster $this
                     */
                    foreach ($this->channels as $pattern => $callback) {
                        if (! $this->channelNameMatchesPattern($channel, $pattern)) {
                            continue;
                        }

                        $parameters = $this->extractAuthParameters($pattern, $channel, $callback);

                        $handler = $this->normalizeChannelHandlerToCallable($callback);

                        return $handler($this->retrieveUser($request, $channel), ...$parameters);
                    }
                }, $this->broadcaster, $this->broadcaster);
                if ($authorizer($request, $this->broadcaster->normalizeChannelName($channel))) {
                    $this->configureChannel([$channel], $as);
                    array_push(static::$channels, $channel);

                    break;
                }
            }
        }
    }

    protected function configureChannel($channels, $event)
    {
        array_push(
            $this->listeners,
            $this->formattedChannel(Arr::first($channels)) . ',' . $this->formattedEvent($event)
        );
    }

    protected function formattedChannel($channel)
    {
        $privateEncrypted = 'private-encrypted-';
        $private = 'private-';
        $presence = 'presence-';
        if (Str::startsWith($channel, $privateEncrypted)) {
            return "echo-encryptedPrivate:" . Str::replaceFirst($privateEncrypted, "", $channel);
        }
        if (Str::startsWith($channel, $private)) {
            return "echo-private:" . Str::replaceFirst($private, "", $channel);
        }
        if (Str::startsWith($channel, $presence)) {
            return "echo-presence:" . Str::replaceFirst($presence, "", $channel);
        }

        return "echo:" . $channel;
    }

    protected function formattedEvent($event)
    {
        return '.' . $event;
    }

    protected function getFromCachedIfAvailable($channels)
    {
        return array_intersect(static::$channels, $channels);
    }

    protected function getPublicChannels($channels)
    {
        return collect($channels)->filter(fn ($channel) => Str::startsWith($channel, ['private-', 'presence-']) == false)->all();
    }

    protected function stringify($channels)
    {
        return collect($channels)->map(fn ($channel) => (string)$channel)->all();
    }

    public function getListeners(): array
    {
        $this->handle();

        return $this->listeners;
    }
}
