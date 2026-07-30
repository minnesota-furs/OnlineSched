#!/usr/bin/env bash

set -euo pipefail

CONTAINER="${ONLINESCHED_EVENT_SAFETY_TEST_CONTAINER:-onlinesched-vanilla-cli}"
WP=(docker exec "$CONTAINER" wp --allow-root --path=/var/www/html)
CONTAINER_TEST="/var/www/html/wp-content/plugins/OnlineSched/tests/cli/test-event-admin-safety.php"
BASE_URL="http://localhost:8081"
START_HINT="cd tests/docker-vanilla && docker compose up -d && ./seed-vanilla.sh"

if [[ "$CONTAINER" != "onlinesched-vanilla-cli" ]]; then
	echo "Refusing unrecognized event-safety test container: $CONTAINER" >&2
	exit 1
fi

if [[ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null || true)" != "true" ]]; then
	echo "The disposable Vanilla WordPress environment is not running (container '$CONTAINER' not found)." >&2
	echo "Start it first:" >&2
	echo "  $START_HINT" >&2
	exit 1
fi

site_url="$("${WP[@]}" option get siteurl)"
if [[ "$site_url" != "$BASE_URL" ]]; then
	echo "Refusing unrecognized WordPress site URL: $site_url" >&2
	exit 1
fi

if ! "${WP[@]}" plugin is-active OnlineSched; then
	echo "Refusing to run because OnlineSched is not active on the disposable Vanilla site." >&2
	exit 1
fi

"${WP[@]}" eval-file "$CONTAINER_TEST"
