<?php

namespace App\Services;

use App\Services\Parsers\PdfParserInterface;
use Smalot\PdfParser\Parser;

class PdfParserManager
{
    /** @param PdfParserInterface[] $parsers */
    public function __construct(private array $parsers) {}

    /**
     * Extracts PDF text once, finds the matching parser and delegates.
     * Throws RuntimeException for invalid files or unrecognized banks.
     */
    public function parse(string $pdfPath): array
    {
        try {
            $text = (new Parser())->parseFile($pdfPath)->getText();
        } catch (\Exception) {
            throw new \RuntimeException('O arquivo não é um PDF válido ou está corrompido.');
        }

        if (empty(trim($text))) {
            throw new \RuntimeException('O PDF não contém texto legível. Pode ser um PDF escaneado ou protegido.');
        }

        foreach ($this->parsers as $parser) {
            if ($parser->supports($text)) {
                return $parser->parse($pdfPath, $text);
            }
        }

        throw new \RuntimeException('Banco não reconhecido. Nenhum parser disponível para este PDF.');
    }
}
