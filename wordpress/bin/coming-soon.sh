#!/usr/bin/env bash
# Checks, logged out, that coming soon mode is keeping the site closed.
# Usage: bin/coming-soon.sh http://localhost:8886 [post id for ?p=<id>]
# Exits 1 if any URL answers 200, answers anything other than a refusal, or mentions "Hyperion".
set -u
base="${1:?usage: coming-soon.sh <base url>}"
base="${base%/}"
pid="${2:-}"
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

for path in / /rides/hyperion/ /feed/ '/?feed=rss2' /wp-sitemap.xml /wp-sitemap-posts-dp_ride-1.xml /suggest/ /rss.xml /sitemap.xml '/?s=hyperion' /rides/hyperion/embed/ ${pid:+"/?p=$pid"}; do
	check "$path" '^503$' "$base$path"
done
for path in /wp-json/wp/v2/dp_ride '/?rest_route=/wp/v2/dp_ride' "/wp-json/oembed/1.0/embed?url=$base/rides/hyperion/" '/wp-json/wp/v2/search?search=hyperion' /wp-json/wp/v2/pages /wp-json/wp/v2/posts /wp-json/wp/v2/users /wp-json/; do
	check "$path" '^(401|403)$' "$base$path"
done
check "POST admin-post.php?action=dp_suggest" '^503$' -X POST -d 'action=dp_suggest' "$base/wp-admin/admin-post.php"
check "POST admin-ajax.php (nopriv)" '^(503|400|403)$' -X POST -d 'action=heartbeat' "$base/wp-admin/admin-ajax.php"

# Holding page details on /.
hdrs="$(curl -s -D - -o ${TMPDIR:-/tmp}/dp-cs-body.$$ "$base/")"
echo "$hdrs" | grep -qi '^retry-after: 86400' && echo "ok    Retry-After: 86400" || { echo "FAIL  no Retry-After: 86400 on /"; fail=1; }
grep -q 'Coming soon\.' ${TMPDIR:-/tmp}/dp-cs-body.$$ && echo "ok    'Coming soon.' copy on /" || { echo "FAIL  no 'Coming soon.' copy on /"; fail=1; }
rm -f ${TMPDIR:-/tmp}/dp-cs-body.$$

# XML-RPC must not answer anything, pingback included.
xml='<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>'
rpc="$(curl -s -X POST -H 'Content-Type: text/xml' -d "$xml" "$base/xmlrpc.php")"
if echo "$rpc" | grep -qi 'pingback'; then echo "FAIL  xmlrpc lists pingback methods"; fail=1; else echo "ok    xmlrpc lists no pingback methods"; fi

# The way in for the owner must still work.
status="$(curl -s -o /dev/null -w '%{http_code}' "$base/wp-login.php")"
if [ "$status" = "200" ]; then echo "ok    200  /wp-login.php"; else echo "FAIL  $status  /wp-login.php"; fail=1; fi

[ "$fail" = 0 ] && echo "PASS: site is closed" || echo "FAIL: site is leaking"
exit "$fail"
