<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChromaService;
use Illuminate\Support\Facades\Http;

class TestLocalEmbeddings extends Command
{
    protected $signature = 'chroma:test-local';
    protected $description = 'Test local embedding service';

    public function handle()
    {
        $this->info('🔄 Testing local embedding service...');
        
        // Test embedding service directly
        $this->testEmbeddingService();
        
        // Test ChromaDB integration
        $this->testChromaIntegration();
    }
    
    private function testEmbeddingService()
    {
        $this->info('Testing embedding service health...');
        
        try {
            $response = Http::get('http://localhost:8001/health');
            
            if ($response->successful()) {
                $this->info('Embedding service is healthy!');
                $this->info('Model info: ' . json_encode($response->json(), JSON_PRETTY_PRINT));
            } else {
                $this->error('Embedding service health check failed');
                return;
            }
        } catch (\Exception $e) {
            $this->error('Cannot connect to embedding service: ' . $e->getMessage());
            $this->error('Make sure the service is running on http://localhost:8001');
            return;
        }
        
        // Test embedding generation
        $this->info('Testing embedding generation...');
        
        try {
            $response = Http::post('http://localhost:8001/embed', [
                'texts' => ['Hello world', 'This is a test']
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info('Embedding generation successful!');
                $this->info("Generated {$data['count']} embeddings with {$data['dimension']} dimensions");
            } else {
                $this->error('Embedding generation failed');
                $this->error('Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Embedding generation error: ' . $e->getMessage());
        }
    }
    
    private function testChromaIntegration()
    {
        $this->info('Testing ChromaDB integration...');
        
        $chromaService = new ChromaService();
        $chromaService->createDatabase();
        $chromaService->initializeCollection();
        
        // Initialize collection
        if ($chromaService->initializeCollection()) {
            $this->info('ChromaDB collection initialized');
        } else {
            $this->error('Failed to initialize ChromaDB collection');
            return;
        }
        
        // Test documents
        $documents = [
            "Laravel is a PHP web framework for building web applications",
            "Machine learning is a subset of artificial intelligence",
            "Python is a programming language used for data science"
        ];
        
        $metadatas = [
            ['category' => 'web', 'language' => 'php'],
            ['category' => 'ai', 'language' => 'python'],
            ['category' => 'programming', 'language' => 'python']
        ];
        
        // Add documents
        if ($chromaService->addDocuments($documents, $metadatas)) {
            $this->info('Documents added to ChromaDB');
        } else {
            $this->error('Failed to add documents to ChromaDB');
            return;
        }
        
        // Query documents
        $results = $chromaService->queryDocuments("What is a web framework?", 2);
        
        if (!empty($results)) {
            $this->info('Query successful!');
            $this->info('Query results:');
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $this->error('Query failed');
        }
    }
}