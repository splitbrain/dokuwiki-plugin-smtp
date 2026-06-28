#!/bin/bash
#
# Pre-test hook executed by the DokuWiki test workflow before PHPUnit runs.
#
# It starts a Mailpit SMTP server so IntegrationTest can deliver a real mail
# through the plugin and verify it via Mailpit's HTTP API. The connection details
# are exported to the PHPUnit step through $GITHUB_ENV. Setting MAILPIT_HOST is what
# enables the test - it then requires Mailpit to be reachable or fails.
#
# Mailpit is started with an auto-generated self-signed certificate (the "sans:"
# syntax) so it offers STARTTLS. The smtp_allow_insecure test needs this and fails
# (it does not skip) when the server does not speak TLS.
#
set -e

# start Mailpit: SMTP on 1025, HTTP API/web UI on 8025, STARTTLS with a self-signed cert
docker run -d --name mailpit -p 1025:1025 -p 8025:8025 axllent/mailpit:latest \
    --smtp-tls-cert sans:localhost --smtp-tls-key sans:localhost

# wait until Mailpit is ready to accept connections
echo "Waiting for Mailpit to become ready..."
ready=0
for _ in $(seq 1 30); do
    if curl -sf http://127.0.0.1:8025/readyz >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done
if [ "$ready" -ne 1 ]; then
    echo "Mailpit did not become ready in time" >&2
    docker logs mailpit || true
    exit 1
fi
echo "Mailpit is ready"

# expose the connection details to the PHPUnit step
{
    echo "MAILPIT_HOST=127.0.0.1"
    echo "MAILPIT_SMTP_PORT=1025"
    echo "MAILPIT_HTTP_PORT=8025"
} >> "$GITHUB_ENV"
