#!/bin/sh
set -e

# Zorg dat docker compose altijd vanuit deze map draait, ongeacht vanwaar
# dit script wordt aangeroepen, zodat de build-context (de repo-root) klopt.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# --no-cache om te voorkomen dat een gefixte Dockerfile-stap alsnog uit de
# build-cache van een eerdere, mislukte poging wordt hergebruikt.
docker compose build --no-cache
docker compose up "$@"
