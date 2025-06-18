<?php

return [
    'base_url' => env('CHROMA_BASE_URL', 'http://127.0.0.1:8000'),
    'collection_name' => env('CHROMA_COLLECTION_NAME', 'study_materials'),
    'embedding_service' => env('CHROMA_EMBEDDING_SERVICE', 'sentence_transformers'),
    'local_embedding_url' => env('LOCAL_EMBEDDING_URL', 'http://localhost:8001'),
    'ollama_url' => env('OLLAMA_URL', 'http://localhost:11434'),
];