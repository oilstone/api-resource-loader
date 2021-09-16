<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Oilstone\ApiResourceLoader\Listeners;

use Carbon\Carbon;
use Stitch\Events\Event;
use Stitch\Events\Listener;

class HandleSoftDeletes extends Listener
{
    /**
     * @param Event $event
     * @return void
     */
    public function fetching(Event $event): void
    {
        if ($event->getPayload()->path->count() > 0) {
            return; // Don't apply the scope to relations
        }

        $event->getPayload()->query->where('deleted_at', '=', null);
    }

    /**
     * @param Event $event
     * @return void
     */
    public function deleting(Event $event): void
    {
        $event->preventDefault();

        $event->getPayload()->record
            ->setAttribute('deleted_at', Carbon::now()->toDateTimeString())
            ->save();
    }
}