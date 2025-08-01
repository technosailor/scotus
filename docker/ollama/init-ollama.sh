#!/bin/bash

# Ollama initialization script
# This script runs when the Ollama container starts and downloads required models

echo "🚀 Starting Ollama initialization..."

# Wait for Ollama service to be ready
echo "⏳ Waiting for Ollama service to start..."
while ! curl -s http://localhost:11434/api/version > /dev/null; do
    sleep 2
done

echo "✅ Ollama service is ready"

# Define the model to download
MODEL=${OLLAMA_MODEL:-"llama3.2"}

# Check if model is already installed
echo "📋 Checking for installed models..."
INSTALLED_MODELS=$(curl -s http://localhost:11434/api/tags | jq -r '.models[]?.name' 2>/dev/null || echo "")

if echo "$INSTALLED_MODELS" | grep -q "$MODEL"; then
    echo "✅ Model $MODEL is already installed"
else
    echo "📥 Downloading model: $MODEL"
    echo "⚠️  This may take several minutes depending on model size and internet speed..."
    
    # Pull the model
    curl -X POST http://localhost:11434/api/pull \
        -H "Content-Type: application/json" \
        -d "{\"name\": \"$MODEL\"}" \
        --max-time 1800 \
        --connect-timeout 30
    
    if [ $? -eq 0 ]; then
        echo "✅ Successfully downloaded model: $MODEL"
    else
        echo "❌ Failed to download model: $MODEL"
        echo "💡 You can manually download it later with: docker exec -it supreme-court-ollama ollama pull $MODEL"
    fi
fi

# Test the model with a simple query
echo "🧪 Testing model with sample query..."
TEST_RESPONSE=$(curl -s -X POST http://localhost:11434/api/generate \
    -H "Content-Type: application/json" \
    -d "{
        \"model\": \"$MODEL\",
        \"prompt\": \"Say 'Hello from Ollama' in exactly those words.\",
        \"stream\": false,
        \"options\": {\"temperature\": 0.1, \"num_predict\": 10}
    }" \
    --max-time 60)

if echo "$TEST_RESPONSE" | grep -q "Hello from Ollama"; then
    echo "✅ Model test successful"
else
    echo "⚠️  Model test completed (response may vary)"
fi

echo "🎉 Ollama initialization complete!"
echo "📊 Ready for Supreme Court opinion analysis"
echo ""
echo "Available endpoints:"
echo "  - Health check: http://localhost:11434/api/version"
echo "  - Model list: http://localhost:11434/api/tags"
echo "  - Generate: http://localhost:11434/api/generate"
echo ""
echo "To use from Laravel:"
echo "  php artisan ollama:setup --check-only"
echo "  php artisan opinions:analyze --type=dissent --limit=5"