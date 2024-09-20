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
        $eventPayload = $event->getPayload();

        if ($eventPayload->path->isRelation()) {
            $eventPayload->query->on($eventPayload->path->to('deleted_at'), '=', null);
            return;
        }

        $eventPayload->query->where($eventPayload->path->to('deleted_at'), '=', null);
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

        $event->getPayload()->record->getModel()->makeEvent('deleted')->fillPayload(['record' => $event->getPayload()->record])->fire();
    }
}
