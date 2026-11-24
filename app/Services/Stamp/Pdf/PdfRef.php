<?php

namespace App\Services\Stamp\Pdf;

/** R4e: an indirect reference "num gen R" */
final class PdfRef
{
    public function __construct(public readonly int $num, public readonly int $gen = 0)
    {
    }
}
