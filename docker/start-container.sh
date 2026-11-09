#!/bin/sh
set -eu

case "${SERVICE_ROLE:-web}" in
    web)
        exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
        ;;
    reverb)
        exec php artisan reverb:start \
            --host="${REVERB_SERVER_HOST:-0.0.0.0}" \
            --port="${REVERB_SERVER_PORT:-8080}"
        ;;
    queue)
        exec php artisan queue:work \
            --queue="${QUEUE_WORKER_QUEUES:-realtime,default}" \
            --sleep="${QUEUE_WORKER_SLEEP:-3}" \
            --tries="${QUEUE_WORKER_TRIES:-3}" \
            --timeout="${QUEUE_WORKER_TIMEOUT:-90}"
        ;;
    *)
        printf 'Unsupported SERVICE_ROLE: %s\n' "$SERVICE_ROLE" >&2
        exit 64
        ;;
esac
