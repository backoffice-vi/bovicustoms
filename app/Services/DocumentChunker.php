<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DocumentChunker
{
    /**
     * Maximum tokens per chunk (default: 95,000 to stay under 100k)
     */
    protected int $maxTokensPerChunk;

    /**
     * Approximate characters per token (Claude average)
     */
    protected const CHARS_PER_TOKEN = 4;

    public function __construct()
    {
        $this->maxTokensPerChunk = (int) config('services.claude.chunk_size', 95000);
    }

    /**
     * Split text into chunks that fit within token limits
     * 
     * @param string $text The text to chunk
     * @param int|null $maxTokens Optional override for max tokens per chunk
     * @return array Array of text chunks
     */
    public function chunkText(string $text, ?int $maxTokens = null): array
    {
        $maxTokens = $maxTokens ?? $this->maxTokensPerChunk;
        $estimatedTokens = $this->estimateTokens($text);

        // If text fits in one chunk, return as-is
        if ($estimatedTokens <= $maxTokens) {
            return [$text];
        }

        Log::info("Chunking document", [
            'total_estimated_tokens' => $estimatedTokens,
            'max_tokens_per_chunk' => $maxTokens,
            'estimated_chunks' => ceil($estimatedTokens / $maxTokens)
        ]);

        return $this->splitIntoChunks($text, $maxTokens);
    }

    /**
     * Estimate token count from text
     * 
     * @param string $text
     * @return int Estimated token count
     */
    public function estimateTokens(string $text): int
    {
        // Use character-based estimation (Claude uses ~4 chars per token)
        return (int) ceil(strlen($text) / self::CHARS_PER_TOKEN);
    }

    /**
     * Split text into chunks by token limit
     * Tries to split on natural boundaries (paragraphs, sentences)
     * 
     * @param string $text
     * @param int $maxTokens
     * @return array
     */
    protected function splitIntoChunks(string $text, int $maxTokens): array
    {
        $chunks = [];
        $maxChars = $maxTokens * self::CHARS_PER_TOKEN;

        // Split by paragraphs first
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraphLength = strlen($paragraph);
            $currentLength = strlen($currentChunk);

            // If adding this paragraph would exceed limit
            if ($currentLength > 0 && ($currentLength + $paragraphLength + 2) > $maxChars) {
                // Save current chunk and start new one
                $chunks[] = trim($currentChunk);
                $currentChunk = $paragraph;
            } 
            // If single paragraph is too large, split it by sentences
            elseif ($paragraphLength > $maxChars) {
                // Save current chunk if any
                if ($currentLength > 0) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                }

                // Split large paragraph by sentences
                $sentenceChunks = $this->splitBySentences($paragraph, $maxChars);
                foreach ($sentenceChunks as $chunk) {
                    $chunks[] = $chunk;
                }
            } 
            // Add paragraph to current chunk
            else {
                if ($currentLength > 0) {
                    $currentChunk .= "\n\n";
                }
                $currentChunk .= $paragraph;
            }
        }

        // Add remaining chunk
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        Log::info("Document chunked successfully", [
            'chunk_count' => count($chunks),
            'chunk_sizes' => array_map(fn($c) => $this->estimateTokens($c), $chunks)
        ]);

        return $chunks;
    }

    /**
     * Split text by sentences when paragraphs are too large
     * 
     * @param string $text
     * @param int $maxChars
     * @return array
     */
    protected function splitBySentences(string $text, int $maxChars): array
    {
        $chunks = [];
        $currentChunk = '';

        // Split by sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);

        foreach ($sentences as $sentence) {
            $sentenceLength = strlen($sentence);
            $currentLength = strlen($currentChunk);

            // If adding sentence would exceed limit
            if ($currentLength > 0 && ($currentLength + $sentenceLength + 1) > $maxChars) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            }
            // If single sentence is too large, force split by chars
            elseif ($sentenceLength > $maxChars) {
                if ($currentLength > 0) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                }
                // Force split by character count
                $charChunks = str_split($sentence, $maxChars);
                foreach ($charChunks as $charChunk) {
                    $chunks[] = $charChunk;
                }
            }
            else {
                if ($currentLength > 0) {
                    $currentChunk .= ' ';
                }
                $currentChunk .= $sentence;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Process text in chunks and merge results
     * 
     * @param string $text
     * @param callable $processor Function to process each chunk
     * @param callable|null $merger Function to merge chunk results
     * @return mixed
     */
    public function processInChunks(string $text, callable $processor, ?callable $merger = null)
    {
        $chunks = $this->chunkText($text);
        $results = [];

        foreach ($chunks as $index => $chunk) {
            Log::info("Processing chunk", ['chunk_number' => $index + 1, 'total_chunks' => count($chunks)]);
            $results[] = $processor($chunk, $index, count($chunks));
        }

        // If merger function provided, use it to combine results
        if ($merger) {
            return $merger($results);
        }

        // Otherwise return array of results
        return $results;
    }

    /**
     * Get recommended chunk overlap size in tokens
     * 
     * @return int
     */
    public function getRecommendedOverlap(): int
    {
        // 5% overlap to maintain context between chunks
        return (int) ($this->maxTokensPerChunk * 0.05);
    }

    /**
     * Chunk text with overlap for better context preservation
     * 
     * @param string $text
     * @param int|null $maxTokens
     * @param int|null $overlapTokens
     * @return array
     */
    public function chunkWithOverlap(string $text, ?int $maxTokens = null, ?int $overlapTokens = null): array
    {
        $maxTokens = $maxTokens ?? $this->maxTokensPerChunk;
        $overlapTokens = $overlapTokens ?? $this->getRecommendedOverlap();
        
        $baseChunks = $this->chunkText($text, $maxTokens - $overlapTokens);
        
        if (count($baseChunks) <= 1) {
            return $baseChunks;
        }

        $chunksWithOverlap = [];
        $overlapChars = $overlapTokens * self::CHARS_PER_TOKEN;

        for ($i = 0; $i < count($baseChunks); $i++) {
            $chunk = $baseChunks[$i];
            
            // Add overlap from previous chunk (except for first chunk)
            if ($i > 0) {
                $previousChunk = $baseChunks[$i - 1];
                $overlap = substr($previousChunk, -$overlapChars);
                $chunk = $overlap . "\n...\n" . $chunk;
            }

            $chunksWithOverlap[] = $chunk;
        }

        return $chunksWithOverlap;
    }
}
