<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnstructuredApiClient
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.unstructured.url', 'http://34.48.24.135:8888');
        $this->timeout = config('services.unstructured.timeout', 300);
    }

    /**
     * Check if the Unstructured API is available.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/healthcheck");
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Unstructured API health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Extract structured content from a document file.
     *
     * @param UploadedFile|string $file Either an UploadedFile or a path to the file
     * @param array $options Additional options for the API
     * @return array The extracted elements
     * @throws \Exception on API error
     */
    public function extractFromFile(UploadedFile|string $file, array $options = []): array
    {
        $endpoint = "{$this->baseUrl}/general/v0/general";

        try {
            $http = Http::timeout($this->timeout)
                ->acceptJson();

            // Build the multipart request
            if ($file instanceof UploadedFile) {
                $http = $http->attach(
                    'files',
                    $file->get(),
                    $file->getClientOriginalName()
                );
            } else {
                // It's a file path
                $filename = basename($file);
                $contents = file_get_contents($file);
                $http = $http->attach('files', $contents, $filename);
            }

            // Add optional parameters
            $formData = [];
            
            // Strategy: auto, hi_res, fast, ocr_only
            if (isset($options['strategy'])) {
                $formData['strategy'] = $options['strategy'];
            }

            // Output format
            if (isset($options['output_format'])) {
                $formData['output_format'] = $options['output_format'];
            }

            // Coordinates (for tables/images)
            if (isset($options['coordinates'])) {
                $formData['coordinates'] = $options['coordinates'] ? 'true' : 'false';
            }

            // Include page breaks
            if (isset($options['include_page_breaks'])) {
                $formData['include_page_breaks'] = $options['include_page_breaks'] ? 'true' : 'false';
            }

            $response = $http->post($endpoint, $formData);

            if (!$response->successful()) {
                Log::error('Unstructured API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception("Unstructured API returned status {$response->status()}: {$response->body()}");
            }

            return $response->json() ?? [];

        } catch (\Throwable $e) {
            Log::error('Unstructured API request failed', [
                'error' => $e->getMessage(),
                'file' => $file instanceof UploadedFile ? $file->getClientOriginalName() : $file,
            ]);
            throw $e;
        }
    }

    /**
     * Extract text content from document elements.
     *
     * @param array $elements The elements returned by extractFromFile
     * @return string Combined text content
     */
    public function elementsToText(array $elements): string
    {
        $text = '';
        
        foreach ($elements as $element) {
            $elementText = $element['text'] ?? '';
            $elementType = $element['type'] ?? 'unknown';
            
            if (empty($elementText)) {
                continue;
            }

            // Add appropriate spacing based on element type
            switch ($elementType) {
                case 'Title':
                    $text .= "\n\n=== {$elementText} ===\n\n";
                    break;
                case 'Header':
                    $text .= "\n--- {$elementText} ---\n";
                    break;
                case 'Table':
                    $text .= "\n[TABLE]\n{$elementText}\n[/TABLE]\n";
                    break;
                case 'ListItem':
                    $text .= "• {$elementText}\n";
                    break;
                case 'NarrativeText':
                case 'Text':
                default:
                    $text .= "{$elementText}\n";
                    break;
            }
        }

        return trim($text);
    }

    /**
     * Extract tables from document elements.
     *
     * @param array $elements The elements returned by extractFromFile
     * @return array Array of table data
     */
    public function extractTables(array $elements): array
    {
        $tables = [];
        
        foreach ($elements as $element) {
            if (($element['type'] ?? '') === 'Table') {
                $tables[] = [
                    'text' => $element['text'] ?? '',
                    'metadata' => $element['metadata'] ?? [],
                ];
            }
        }

        return $tables;
    }

    /**
     * Group elements by page number.
     *
     * @param array $elements The elements returned by extractFromFile
     * @return array Elements grouped by page
     */
    public function groupByPage(array $elements): array
    {
        $pages = [];
        
        foreach ($elements as $element) {
            $pageNumber = $element['metadata']['page_number'] ?? 1;
            
            if (!isset($pages[$pageNumber])) {
                $pages[$pageNumber] = [];
            }
            
            $pages[$pageNumber][] = $element;
        }

        ksort($pages);
        return $pages;
    }

    /**
     * Convenience method: Extract and return plain text from a file.
     *
     * @param UploadedFile|string $file
     * @param string $strategy 'auto', 'hi_res', 'fast', or 'ocr_only'
     * @return string
     */
    public function extractText(UploadedFile|string $file, string $strategy = 'auto'): string
    {
        $elements = $this->extractFromFile($file, ['strategy' => $strategy]);
        return $this->elementsToText($elements);
    }
}
