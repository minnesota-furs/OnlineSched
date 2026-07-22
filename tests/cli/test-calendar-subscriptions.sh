#!/usr/bin/env bash

set -euo pipefail

CONTAINER="${ONLINESCHED_CALENDAR_TEST_CONTAINER:-onlinesched-vanilla-cli}"
WP=(docker exec "$CONTAINER" wp --allow-root --path=/var/www/html)
CONTAINER_TEST="/var/www/html/wp-content/plugins/OnlineSched/tests/cli/test-calendar-subscriptions.php"

if [[ "$CONTAINER" != "onlinesched-vanilla-cli" ]]; then
	echo "Refusing unrecognized calendar test container: $CONTAINER" >&2
	exit 1
fi

site_url="$("${WP[@]}" option get siteurl)"
if [[ "$site_url" != "http://localhost:8081" ]]; then
	echo "Refusing unrecognized WordPress site URL: $site_url" >&2
	exit 1
fi

if ! "${WP[@]}" plugin is-active OnlineSched; then
	echo "Refusing to run because OnlineSched is not active on the disposable Vanilla site." >&2
	exit 1
fi

"${WP[@]}" eval-file "$CONTAINER_TEST"
