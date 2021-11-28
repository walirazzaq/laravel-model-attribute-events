<?php

namespace Walirazzaq\AttributeEvents\Models\Concerns;

/**
 * @property \Illuminate\Database\Eloquent\Model $this
 */
trait InteractsWithAttributeEvents
{
    use HasAttributeEvents;
    use BroadcastsAttributeEvents;
}
