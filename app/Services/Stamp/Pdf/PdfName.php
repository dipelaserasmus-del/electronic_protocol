<?php

namespace App\Services\Stamp\Pdf;

/** R4e: a name (/Type), decoded (#xx) */
final class PdfName
{
    public function __construct(public readonly string $name)
    {
    }
}
