<?php

namespace App\Services;

/** A manually chosen protocol number turned out to be already used when saving. */
class ProtocolNumberTaken extends \RuntimeException
{
    public function __construct(public readonly int $number, public readonly int $etos)
    {
        parent::__construct("Protocol number $number/$etos already exists");
    }
}
