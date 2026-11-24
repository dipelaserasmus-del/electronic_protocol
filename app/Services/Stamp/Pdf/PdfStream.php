<?php

namespace App\Services\Stamp\Pdf;

/** R4e: a stream: its dictionary and its data as stored (still encoded) */
final class PdfStream
{
    public function __construct(public array $dict, public string $data)
    {
    }
}
