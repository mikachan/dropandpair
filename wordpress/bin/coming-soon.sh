#!/usr/bin/env bash
# Checks, logged out, that coming soon mode is keeping the site closed.
# Usage: bin/coming-soon.sh http://localhost:8886
# Exits 1 if any URL answers 200, answers anything other than a refusal, or mentions "Hyperion".
set -u
base="${1:?usage: coming-soon.sh <base url>}"
base="${base%/}"
fail=0

check() { # label, expected status regex, curl args...
	local label="$1" want="$2"; shift 2
	local body status
	body="$(mktemp)"
	status="$(curl -s -o "$body" -w '%{http_code}' "$@")"
	if [ "$status" = "200" ] || ! [[ "$status" =~ $want ]] || grep -qi 'hyperion' "$body"; then
		echo "FAIL  $status  $label"
		fail=1
	else
		echo "ok    $status  $label"
	fi
	rm -f "$body"
}

for path in / /rides/hyperion/ /feed/ /wp-sitemap.xml /suggest/ /rss.xml /sitemap.xml '/?s=hyperion'; do
	check "$path" '^503$' "$base$path"
done
for path in /wp-json/wp/v2/dp_ride '/wp-json/wp/v2/search?search=hyperion' /wp-json/wp/v2/pages /wp-json/wp/v2/posts /wp-json/wp/v2/users /wp-json/; do
	check "$path" '^(401|403)$' "$base$path"
done
check "POST admin-post.php?action=dp_suggest" '^503$' -X POST -d 'action=dp_suggest' "$base/wp-admin/admin-post.php"

# The way in for the owner must still work.
status="$(curl -s -o /dev/null -w '%{http_code}' "$base/wp-login.php")"
if [ "$status" = "200" ]; then echo "ok    200  /wp-login.php"; else echo "FAIL  $status  /wp-login.php"; fail=1; fi

[ "$fail" = 0 ] && echo "PASS: site is closed" || echo "FAIL: site is leaking"
exit "$fail"
