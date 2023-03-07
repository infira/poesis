<?php

namespace Infira\Poesis\support;

class DateTime extends \DateTime
{
    /**
     * format current time as $format, defaults to d.m.Y
     *
     * @param  string  $format
     * @return string
     */
    public function toDMY(string $format = 'd.m.Y'): string
    {
        return $this->format($format);
    }

    /**
     * format as  H:i:s
     *
     * @return string - date H:i:s
     */
    public function toNiceTime(): string
    {
        return $this->format('H:i:s');
    }

    /**
     * format as d.m.Y H:i:s
     *
     * @return string
     */
    public function toDateTime(): string
    {
        return $this->format("d.m.Y H:i:s");
    }

    /**
     * format as Y-m-d
     *
     * @return string
     */
    public function toSqlDate(): string
    {
        return $this->format('Y-m-d');
    }

    /**
     * format as Y-m-d H:i:s
     *
     * @return string
     */
    public function toSqlDateTime(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    /**
     * Is current time in the past
     *
     * @return bool
     */
    public function inPast(): bool
    {
        return ($this->getTimestamp() < time());
    }

    /**
     * Is current time in the past
     *
     * @return bool
     */
    public function inFuture(): bool
    {
        return ($this->getTimestamp() > time());
    }

    /**
     * Is current time present
     *
     * @return bool
     */
    public function isPresent(): bool
    {
        return ($this->getTimestamp() == time());
    }
}