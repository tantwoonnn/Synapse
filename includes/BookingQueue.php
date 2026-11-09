<?php
/** FIFO queue for tutor requests. Rows are enqueued in created_at order. */
declare(strict_types=1);

class BookingQueue
{
    private array $items = [];
    private int $head = 0;
    private int $tail = 0;

    public function enqueue(array $request): void
    {
        $this->items[$this->tail++] = $request;
    }

    public function dequeue(): ?array
    {
        if ($this->isEmpty()) return null;
        $request = $this->items[$this->head];
        unset($this->items[$this->head++]);
        return $request;
    }

    public function isEmpty(): bool
    {
        return $this->head === $this->tail;
    }

    public function toArray(): array
    {
        return array_values($this->items);
    }
}
