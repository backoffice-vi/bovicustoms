<?php

namespace App\Services;

use App\Models\LawDocument;
use App\Models\CustomsCode;
use App\Models\CustomsCodeHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordFactory;

class LawDocumentProcessor
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model');
        $this->maxTokens = config('services.claude.max_tokens');
    }

    /**
     * Process a law document and extract customs categories
     */
    public function process(LawDocument $document): array
    {
        try {
            $document->markAsProcessing();

            // Extract text from document
            $text = $this->extractText($document);

            if (empty($text)) {
                throw new \Exception('Could not extract text from document');
            }

            // Send to Claude for analysis
            $categories = $this->analyzeWithClaude($text, $document->country_id);

            if (empty($categories)) {
                throw new \Exception('No categories extracted from document');
            }

            // Update customs codes and track history
            $results = $this->updateCustomsCodes($categories, $document);

            $document->markAsCompleted();

            return [
                'success' => true,
                'categories_processed' => count($categories),
                'created' => $results['created'],
                'updated' => $results['updated'],
            ];

        } catch (\Exception $e) {
            Log::error('Law document processing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $document->markAsFailed($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract text content from the document
     */
    protected function extractText(LawDocument $document): string
    {
        $fullPath = $document->getFullPath();

        if (!file_exists($fullPath)) {
            throw new \Exception('Document file not found');
        }

        return match(strtolower($document->file_type)) {
            'pdf' => $this->extractFromPdf($fullPath),
            'docx', 'doc' => $this->extractFromWord($fullPath),
            'txt' => file_get_contents($fullPath),
            'xlsx', 'xls' => $this->extractFromExcel($fullPath),
            default => throw new \Exception("Unsupported file type: {$document->file_type}"),
        };
    }

    /**
     * Extract text from PDF
     */
    protected function extractFromPdf(string $path): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);
        return $pdf->getText();
    }

    /**
     * Extract text from Word document
     */
    protected function extractFromWord(string $path): string
    {
        $phpWord = WordFactory::load($path);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $text .= $childElement->getText() . "\n";
                        }
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Extract text from Excel
     */
    protected function extractFromExcel(string $path): string
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if ($value !== null) {
                        $rowData[] = $value;
                    }
                }
                
                if (!empty($rowData)) {
                    $text .= implode(' | ', $rowData) . "\n";
                }
            }
        }

        return $text;
    }

    /**
     * Analyze document text with Claude API
     */
    protected function analyzeWithClaude(string $text, ?int $countryId): array
    {
        $prompt = $this->buildExtractionPrompt($text);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API request failed: ' . $response->body());
        }

        $result = $response->json();
        $content = $result['content'][0]['text'] ?? '';

        return $this->parseClaudeResponse($content, $countryId);
    }

    /**
     * Build the prompt for category extraction
     */
    protected function buildExtractionPrompt(string $text): string
    {
        // Truncate text if too long (Claude has context limits)
        $maxTextLength = 100000;
        if (strlen($text) > $maxTextLength) {
            $text = substr($text, 0, $maxTextLength) . "\n\n[Document truncated due to length...]";
        }

        return <<<PROMPT
You are analyzing a customs/tariff law document. Extract all tariff categories, codes, and their descriptions.

For each category found, provide:
1. code: The tariff/customs code (e.g., "8703", "0201.10", "HS 8471")
2. description: A clear description of what items fall under this code
3. duty_rate: The duty/tax rate if specified (as a percentage number, e.g., 15 for 15%)

Return your response as a JSON array of objects. Each object should have:
- "code": string (the tariff code)
- "description": string (clear description of the category)
- "duty_rate": number or null (duty rate as percentage)

Example format:
[
    {"code": "8703", "description": "Motor cars and other motor vehicles principally designed for the transport of persons", "duty_rate": 25},
    {"code": "0201.10", "description": "Carcasses and half-carcasses of bovine animals, fresh or chilled", "duty_rate": 12.5}
]

IMPORTANT: Only return the JSON array, no other text or explanation.

Here is the document text to analyze:

{$text}
PROMPT;
    }

    /**
     * Parse Claude's response into structured category data
     */
    protected function parseClaudeResponse(string $content, ?int $countryId): array
    {
        // Find JSON array in response
        $content = trim($content);
        
        // Try to extract JSON if wrapped in markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $categories = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse Claude response as JSON', [
                'error' => json_last_error_msg(),
                'content' => substr($content, 0, 500),
            ]);
            throw new \Exception('Failed to parse AI response: ' . json_last_error_msg());
        }

        if (!is_array($categories)) {
            throw new \Exception('AI response was not an array of categories');
        }

        // Add country_id to each category
        return array_map(function ($cat) use ($countryId) {
            return [
                'code' => $cat['code'] ?? '',
                'description' => $cat['description'] ?? '',
                'duty_rate' => $cat['duty_rate'] ?? null,
                'country_id' => $countryId,
            ];
        }, $categories);
    }

    /**
     * Update customs codes in database and track history
     */
    protected function updateCustomsCodes(array $categories, LawDocument $document): array
    {
        $created = 0;
        $updated = 0;

        foreach ($categories as $category) {
            if (empty($category['code'])) {
                continue;
            }

            // Find existing code
            $query = CustomsCode::where('code', $category['code']);
            if ($category['country_id']) {
                $query->where('country_id', $category['country_id']);
            }
            $existing = $query->first();

            if ($existing) {
                // Track changes
                $this->trackChanges($existing, $category, $document);
                
                // Update existing
                $existing->update([
                    'description' => $category['description'],
                    'duty_rate' => $category['duty_rate'],
                ]);
                $updated++;
            } else {
                // Create new
                $newCode = CustomsCode::create([
                    'code' => $category['code'],
                    'description' => $category['description'],
                    'duty_rate' => $category['duty_rate'],
                    'country_id' => $category['country_id'],
                ]);

                // Log creation in history
                CustomsCodeHistory::logChange(
                    $newCode->id,
                    'created',
                    null,
                    "Code created from law document",
                    $document->id,
                    auth()->id()
                );

                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Track changes to existing customs code
     */
    protected function trackChanges(CustomsCode $existing, array $newData, LawDocument $document): void
    {
        $fields = ['description', 'duty_rate'];

        foreach ($fields as $field) {
            $oldValue = (string) $existing->$field;
            $newValue = (string) ($newData[$field] ?? '');

            if ($oldValue !== $newValue) {
                CustomsCodeHistory::logChange(
                    $existing->id,
                    $field,
                    $oldValue ?: null,
                    $newValue ?: null,
                    $document->id,
                    auth()->id()
                );
            }
        }
    }
}
