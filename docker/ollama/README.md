# Ollama LLM Integration

This directory contains the Docker configuration for running Ollama locally within the Supreme Court data visualization project.

## What is Ollama?

Ollama is a tool for running large language models locally. It provides a simple API for interacting with models like Llama, Mistral, and others without needing external services.

## Setup

### Docker Setup (Recommended)

1. **Build and start the containers:**
   ```bash
   docker-compose build ollama
   docker-compose up -d ollama
   ```

2. **Check if Ollama is running:**
   ```bash
   docker-compose logs ollama
   curl http://localhost:11434/api/version
   ```

3. **Verify model installation:**
   ```bash
   php artisan ollama:setup --check-only
   ```

### Manual Setup

If you prefer to run Ollama outside Docker:

1. **Install Ollama:**
   ```bash
   # macOS
   brew install ollama

   # Linux
   curl -fsSL https://ollama.ai/install.sh | sh
   ```

2. **Start Ollama service:**
   ```bash
   ollama serve
   ```

3. **Download the model:**
   ```bash
   ollama pull llama3.2
   ```

## Usage

### Setup and Testing

```bash
# Check Ollama availability and download models
php artisan ollama:setup

# Test with different model
php artisan ollama:setup --model=mistral

# Just check status (no downloads)
php artisan ollama:setup --check-only
```

### Running Analysis

```bash
# Analyze dissenting opinions
php artisan opinions:analyze --type=dissent --limit=10

# Generate sentiment distribution
php artisan opinions:analyze --sentiment-only

# Thematic analysis on civil rights
php artisan opinions:analyze --thematic-only --theme="civil_rights"

# Full analysis of all opinion types
php artisan opinions:analyze --limit=50
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
OLLAMA_TIMEOUT=120
OLLAMA_TEMPERATURE=0.3
OLLAMA_MAX_TOKENS=1000
```

### Docker Environment

The Docker setup automatically:
- Downloads the specified model on first run
- Configures networking between containers
- Persists model data in a Docker volume
- Includes health checks

## Available Models

Popular models for legal analysis:
- `llama3.2` (default) - Good balance of speed and accuracy
- `llama3.1:8b` - Larger model for better analysis
- `mistral` - Alternative with different strengths
- `codellama` - If analyzing legal code/statutes

## Troubleshooting

### Model Download Issues

If model download fails:
```bash
# Manual download
docker exec -it supreme-court-ollama ollama pull llama3.2

# Check available space
docker system df
```

### Connection Issues

```bash
# Check if Ollama is running
curl http://localhost:11434/api/version

# Check container logs
docker-compose logs ollama

# Restart Ollama
docker-compose restart ollama
```

### Performance Issues

For better performance:
- Use smaller models (`llama3.2` vs `llama3.1:70b`)
- Reduce batch size in analysis commands
- Monitor system resources during analysis

## API Endpoints

When running, Ollama provides these endpoints:

- `GET /api/version` - Service version
- `GET /api/tags` - List installed models
- `POST /api/generate` - Generate text
- `POST /api/pull` - Download model
- `POST /api/push` - Upload model

## Integration with Analysis System

The Laravel application integrates with Ollama through:

1. **LocalLlmService** - Handles HTTP communication
2. **OpinionAnalysisService** - Manages legal analysis workflows
3. **Console Commands** - CLI interface for analysis
4. **Filament Resources** - Web interface for results

## Model Storage

Models are stored in Docker volumes:
- Location: `ollama_data` volume
- Typically 2-8GB per model
- Persists across container restarts

## Security Notes

- Ollama runs locally (no data sent to external services)
- Models and analysis stay within your infrastructure
- API endpoints are only accessible within Docker network
- Consider firewall rules if exposing ports externally