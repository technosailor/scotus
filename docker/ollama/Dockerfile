FROM ollama/ollama:latest

# Install required packages
RUN apt-get update && apt-get install -y \
    curl \
    jq \
    && rm -rf /var/lib/apt/lists/*

# Copy initialization script
COPY init-ollama.sh /usr/local/bin/init-ollama.sh
RUN chmod +x /usr/local/bin/init-ollama.sh

# Create startup script that runs both ollama and our init
RUN echo '#!/bin/bash\n\
# Start Ollama in the background\n\
ollama serve &\n\
OLLAMA_PID=$!\n\
\n\
# Wait a moment for the service to start\n\
sleep 10\n\
\n\
# Run our initialization script in background\n\
/usr/local/bin/init-ollama.sh &\n\
\n\
# Wait for Ollama to finish (keep container running)\n\
wait $OLLAMA_PID' > /usr/local/bin/start-ollama.sh

RUN chmod +x /usr/local/bin/start-ollama.sh

# Override entrypoint to use our script
ENTRYPOINT ["/usr/local/bin/start-ollama.sh"]