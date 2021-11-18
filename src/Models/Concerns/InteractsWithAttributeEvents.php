<?php

namespace Walirazzaq\LaravelModelAttributeEvents\Models\Concerns;


/**
 * @property \Illuminate\Database\Eloquent\Model $this
 */
trait InteractsWithAttributeEvents
{
    use HasAttributeEvents, BroadcastsAttributeEvents;
}
