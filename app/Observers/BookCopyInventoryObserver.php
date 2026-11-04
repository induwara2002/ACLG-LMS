<?php

namespace App\Observers;

use App\Models\BookCopy;
use App\Services\BookInventoryService;

class BookCopyInventoryObserver
{
    public function __construct(private BookInventoryService $inventory)
    {
    }

    public function created(BookCopy $copy): void
    {
        $this->inventory->copyCreated($copy->getAttributes());
    }

    public function updated(BookCopy $copy): void
    {
        $this->inventory->copyUpdated($copy->getRawOriginal(), $copy->getAttributes());
    }

    public function deleted(BookCopy $copy): void
    {
        $this->inventory->copyDeleted($copy->getAttributes());
    }
}
