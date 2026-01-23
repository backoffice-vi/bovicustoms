<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentTextExtractor
{
    protected ?UnstructuredApiClient $unstructuredApi = null;

    public function __construct(?UnstructuredApiClient $unstructuredApi = null)
    {
        $this->unstructuredApi = $unstructuredApi;
    }

    /**
     * Extract plain text from PDF or Excel. Throws on unsupported types.
     *
     * NOTE: PdfParser can be memory intensive; callers should guard by file size.
     */
    public function extractText(string $fullPath, string $fileType): string
    {
        $fileType = strtolower($fileType);

        return match ($fileType) {
            'pdf' => $this->extractFromPdf($fullPath),
            'xlsx', 'xls' => $this->extractFromExcel($fullPath),
            'txt' => (string) file_get_contents($fullPath),
            default => throw new \InvalidArgumentException("Unsupported file type for text extraction: {$fileType}"),
        };
    }

    protected function extractFromPdf(string $path): string
    {
        set_time_limit(300);

        // Try Unstructured API first (handles OCR, large files better)
        if ($this->unstructuredApi) {
            try {
                $text = $this->unstructuredApi->extractText($path, 'auto');
                if (!empty(trim($text))) {
                    Log::info('PDF extracted via Unstructured API', ['path' => $path]);
                    return $text;
                }
            } catch (\Throwable $e) {
                Log::warning('Unstructured API extraction failed, falling back to PdfParser', [
                    'error' => $e->getMessage(),
                    'path' => $path,
                ]);
            }
        }

        // Fallback to local PdfParser
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            return (string) $pdf->getText();
        } catch (\Throwable $e) {
            Log::warning('PDF text extraction failed', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    protected function extractFromExcel(string $path): string
    {
        $spreadsheet = SpreadsheetFactory::load($path);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $text .= "=== Sheet: {$sheet->getTitle()} ===\n";
            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $value = $cell->getFormattedValue();
                    if ($value !== null && $value !== '') {
                        $rowData[] = $value;
                    }
                }

                if (!empty($rowData)) {
                    $text .= implode(' | ', $rowData) . "\n";
                }
            }
            $text .= "\n";
        }

        return $text;
    }
}

