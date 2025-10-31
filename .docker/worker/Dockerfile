FROM ghcr.io/cirruslabs/flutter:stable

# Basics + Node 18
RUN apt-get update && apt-get install -y curl git ca-certificates gnupg && rm -rf /var/lib/apt/lists/* \
 && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
 && apt-get update && apt-get install -y nodejs \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Optionally install worker deps at build-time (also installed on container start if bind-mounted)
COPY build-worker/package*.json build-worker/
RUN [ -f build-worker/package.json ] && ( npm ci --prefix build-worker || npm install --prefix build-worker ) || true

# Environment
ENV WORKER_SECRET=seu-segredo-super-secreto

EXPOSE 9091

# Default command runs the queue poller
CMD ["node","build-worker/queue-poller.js"]

