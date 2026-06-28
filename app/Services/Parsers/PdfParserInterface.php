<?php

namespace App\Services\Parsers;

interface PdfParserInterface
{
    /**
     * Returns true if this parser recognizes the PDF content.
     * Receives the pre-extracted text to avoid double-parsing.
     */
    public function supports(string $text): bool;

    /**
     * Parses the PDF and returns:
     *   ['meta' => [...], 'transactions' => [...]]
     *
     * $text is pre-extracted by PdfParserManager.
     */
    public function parse(string $pdfPath, string $text): array;
}
