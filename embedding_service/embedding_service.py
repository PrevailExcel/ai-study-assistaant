from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import logging
import os
from flask_cors import CORS

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)  # Enable CORS for Laravel requests

# Initialize the model (this will download it on first run)
logger.info("Loading SentenceTransformer model...")
model = SentenceTransformer('all-MiniLM-L6-v2')
logger.info("Model loaded successfully!")

@app.route('/embed', methods=['POST'])
def embed():
    try:
        data = request.json
        if not data:
            return jsonify({'error': 'No JSON data provided'}), 400
        
        texts = data.get('texts', [])
        if not texts:
            return jsonify({'error': 'No texts provided'}), 400
        
        if not isinstance(texts, list):
            return jsonify({'error': 'texts must be a list'}), 400
        
        logger.info(f"Generating embeddings for {len(texts)} texts")
        
        # Generate embeddings
        embeddings = model.encode(texts, convert_to_tensor=False)
        
        # Convert to list format for JSON serialization
        embeddings_list = embeddings.tolist()
        
        logger.info(f"Generated {len(embeddings_list)} embeddings")
        
        return jsonify({
            'embeddings': embeddings_list,
            'count': len(embeddings_list),
            'dimension': len(embeddings_list[0]) if embeddings_list else 0
        })
        
    except Exception as e:
        logger.error(f"Embedding error: {str(e)}")
        return jsonify({'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status': 'healthy',
        'model': 'all-MiniLM-L6-v2',
        'dimension': 384
    })

@app.route('/models', methods=['GET'])
def models():
    return jsonify({
        'current_model': 'all-MiniLM-L6-v2',
        'dimension': 384,
        'max_seq_length': 256
    })

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 8001))
    app.run(host='0.0.0.0', port=port, debug=False)