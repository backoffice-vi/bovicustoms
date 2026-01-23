<?php

namespace App\Providers;

use App\Services\QdrantClient;
use App\Services\EmbeddingService;
use App\Services\VectorClassifier;
use App\Services\ItemClassifier;
use App\Services\DocumentChunker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Qdrant client as singleton
        $this->app->singleton(QdrantClient::class, function ($app) {
            return new QdrantClient();
        });

        // Register Embedding service as singleton
        $this->app->singleton(EmbeddingService::class, function ($app) {
            return new EmbeddingService();
        });

        // Register Vector classifier with dependencies
        $this->app->singleton(VectorClassifier::class, function ($app) {
            return new VectorClassifier(
                $app->make(QdrantClient::class),
                $app->make(EmbeddingService::class)
            );
        });

        // Register Item classifier with optional vector classifier
        $this->app->singleton(ItemClassifier::class, function ($app) {
            $vectorClassifier = null;
            
            // Only inject vector classifier if Qdrant is configured
            if (config('services.qdrant.api_key')) {
                try {
                    $vectorClassifier = $app->make(VectorClassifier::class);
                } catch (\Exception $e) {
                    // Vector classifier not available, continue without it
                }
            }
            
            return new ItemClassifier(
                $app->make(DocumentChunker::class),
                $vectorClassifier
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
