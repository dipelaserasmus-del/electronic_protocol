<?php

namespace App\Services\Stamp\Pdf;

/** R4e: a string, its bytes (written back as <hex>) */
final class PdfString
{
    public function __construct(public readonly string $bytes)
    {
    }
}
