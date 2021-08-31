<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Oilstone\ApiResourceLoader\Listeners;

use Carbon\Carbon;
use Stitch\Events\Event;
use Stitch\Events\Listener;

class HandleTimestamps extends Listener
{
    /**
     * @param Event $event
     * @return void
     */
    public function inserting(Event $event): void
    {
        $event->getPayload()->record
            ->setAttribute('created_at', Carbon::now()->toDateTimeString())
            ->setAttribute('updated_at', Carbon::now()->toDateTimeString());
    }

    /**
     * @param Event $event
     * @return void
     */
    public function updating(Event $event): void
    {
        $event->getPayload()->record
            ->setAttribute('updated_at', Carbon::now()->toDateTimeString());
    }
}
