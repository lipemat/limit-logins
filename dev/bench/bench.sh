#!/usr/bin/env bash
#
# Benchmark blocked login submissions against a local site.
#
# Usage: bash dev/bench/bench.sh <baseline|early-drop-disabled|early-drop>
#
# Environment:
#   BENCH_URL       Site URL. Default `https://starting-point.loc`.
#   BENCH_REQUESTS  Requests per `ab` run. Default `200`.
#   BENCH_AB        ApacheBench binary. Default `abs` when available, otherwise `ab`.
#   BENCH_WP        WP-CLI command. Default `wp`.
#
# Prints a markdown table to stdout and progress to stderr.

set -euo pipefail

readonly BLOCKED_MESSAGE='Too many failed login attempts.'
readonly HEADER_NAME='X-Limit-Logins-Bench'
# Must match `requests/*`; multisite usernames allow only a-z and 0-9.
readonly BENCH_USER='limitloginsbench'
readonly OTHER_USER='limitloginsother'
readonly OTHER_IP='203.0.113.10'
readonly WRONG_APP_PASSWORD='notTheAppPasswordAtAll00'
readonly MU_PLUGIN='limit-logins-bench.php'
readonly LOG_NAME='limit-logins-bench.log'
readonly SCENARIOS=( wp-login-ip wp-login-username xmlrpc rest reference )
readonly CONCURRENCY=( 1 10 )
# Unmeasured requests per client before each run, so every PHP worker is warm.
readonly WARM_UP=5

MODE="${1:-}"
case "$MODE" in
	baseline | early-drop-disabled | early-drop) ;;
	*)
		echo 'Usage: bash dev/bench/bench.sh <baseline|early-drop-disabled|early-drop>' >&2
		exit 1
		;;
esac

log() {
	echo "$*" >&2
}

fail() {
	log "Error: $*"
	exit 1
}

# Convert a Git Bash path to a `C:/...` path native tools understand. No-op outside Windows.
native_path() {
	if command -v cygpath > /dev/null; then
		cygpath -m "$1"
	else
		echo "$1"
	fi
}

URL="${BENCH_URL:-https://starting-point.loc}"
URL="${URL%/}"
REQUESTS="${BENCH_REQUESTS:-200}"
AB="${BENCH_AB:-$(command -v abs || command -v ab || true)}"
read -ra WP <<< "${BENCH_WP:-wp}"
BENCH_DIR="$(native_path "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)")"
MU_DIR=''
LOG=''
IP=''
NETWORK=()
ROWS=()

wp_cli() {
	"${WP[@]}" --url="$URL" "$@"
}

# Print the last line of a WP-CLI command's output, which is checked instead of the unreliable exit code.
wp_value() {
	wp_cli "$@" 2> /dev/null | tail -n 1 | tr -d '\r'
}

delete_bench_user() {
	if [[ "$(wp_value user get "$BENCH_USER" --field=ID)" =~ ^[0-9]+$ ]]; then
		wp_cli user delete "$BENCH_USER" "${NETWORK[@]}" --yes > /dev/null
	fi
}

cleanup() {
	local status=$?
	set +e
	log 'Cleaning up...'
	if [[ -n "$MU_DIR" ]]; then
		rm -f "$MU_DIR/$MU_PLUGIN" "$LOG"
	fi
	wp_cli limit-logins clear-blocks > /dev/null
	delete_bench_user
	exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# Set LABEL, EXPECT_BLOCKED, SEED, TARGET, BODY, TYPE and AUTH for a scenario.
load_scenario() {
	EXPECT_BLOCKED=true
	SEED=()
	TARGET="$URL/wp-login.php"
	BODY="$BENCH_DIR/requests/wp-login.txt"
	TYPE='application/x-www-form-urlencoded'
	AUTH=''
	case "$1" in
		wp-login-ip)
			LABEL='Blocked IP, wp-login'
			SEED=( "$IP" "$OTHER_USER" wp-login )
			;;
		wp-login-username)
			LABEL='Blocked username from another IP, wp-login'
			SEED=( "$OTHER_IP" "$BENCH_USER" wp-login )
			;;
		xmlrpc)
			LABEL='Blocked IP, XML-RPC wp.getUsersBlogs'
			SEED=( "$IP" "$OTHER_USER" xmlrpc )
			TARGET="$URL/xmlrpc.php"
			BODY="$BENCH_DIR/requests/xmlrpc.xml"
			TYPE='text/xml'
			;;
		rest)
			LABEL='Blocked IP, REST application password'
			SEED=( "$IP" "$OTHER_USER" rest-api )
			TARGET="$URL/wp-json/wp/v2/types/post"
			BODY=''
			TYPE=''
			AUTH="$BENCH_USER:$WRONG_APP_PASSWORD"
			;;
		reference)
			LABEL='Unblocked failed login (reference)'
			EXPECT_BLOCKED=false
			;;
	esac
}

# Send one request for the loaded scenario with curl and print the body.
probe() {
	local args=( -sk -H "$HEADER_NAME: $1" -H "Referer: $URL/wp-login.php" )
	if [[ -n "$BODY" ]]; then
		args+=( --data-binary "@$BODY" -H "Content-Type: $TYPE" )
	fi
	if [[ -n "$AUTH" ]]; then
		args+=( -u "$AUTH" )
	fi
	curl "${args[@]}" "$TARGET"
}

# Fail unless the loaded scenario is blocked, or not blocked for the reference.
verify() {
	local body
	body="$(probe "$1")"
	if [[ true == "$EXPECT_BLOCKED" && "$body" != *"$BLOCKED_MESSAGE"* ]]; then
		fail "$LABEL: request was not blocked."
	fi
	if [[ false == "$EXPECT_BLOCKED" && "$body" == *"$BLOCKED_MESSAGE"* ]]; then
		fail "$LABEL: request was blocked."
	fi
}

# Usage: run_ab <scenario> <concurrency> <requests>
run_ab() {
	local args=( -l -n "$3" -c "$2" -H "$HEADER_NAME: $1" -H "Referer: $URL/wp-login.php" )
	if [[ -n "$BODY" ]]; then
		args+=( -p "$BODY" -T "$TYPE" )
	fi
	if [[ -n "$AUTH" ]]; then
		args+=( -A "$AUTH" )
	fi
	"$AB" "${args[@]}" "$TARGET"
}

# Print the value of an `ab` result line such as "Requests per second".
ab_value() {
	awk -v key="$1:" '1 == index( $0, key ) { $0 = substr( $0, length( key ) + 1 ); print $1; exit }' <<< "$2"
}

log_lines() {
	if [[ -f "$LOG" ]]; then
		wc -l < "$LOG"
	else
		echo 0
	fi
}

# Shutdown functions may finish after `ab` has its response.
wait_for_log() {
	local tries=0
	while (( $1 > $(log_lines) && 50 > tries++ )); do
		sleep 0.1
	done
}

# Print "server ms | peak MB | queries" averaged over the logged requests.
summarize_log() {
	awk -F '\t' '{ ms += $1; mem += $2; q += $3; n++ } END { if ( 0 < n ) printf "%.1f | %.1f | %.1f", ms / n, mem / n / 1048576, q / n; else printf "- | - | -" }' "$LOG"
}

run_scenario() {
	local id="$1" concurrency out failed recorded
	load_scenario "$id"
	log "$LABEL"
	wp_cli limit-logins clear-blocks > /dev/null
	if (( 0 < ${#SEED[@]} )); then
		wp_cli eval-file "$BENCH_DIR/seed-block.php" "${SEED[@]}" > /dev/null
	fi
	verify "$id"
	for concurrency in "${CONCURRENCY[@]}"; do
		log "  ab -n $REQUESTS -c $concurrency"
		run_ab "$id" "$concurrency" $(( WARM_UP * concurrency )) > /dev/null
		wait_for_log $(( WARM_UP * concurrency ))
		rm -f "$LOG"
		out="$(run_ab "$id" "$concurrency" "$REQUESTS")"
		wait_for_log "$REQUESTS"
		failed="$(ab_value 'Failed requests' "$out")"
		if [[ 0 != "$failed" ]]; then
			log "  Warning: ab reported $failed failed requests."
		fi
		recorded="$(log_lines)"
		if (( REQUESTS != recorded )); then
			log "  Warning: timed $recorded of $REQUESTS requests."
		fi
		ROWS+=( "| $LABEL | $concurrency | $(ab_value 'Time per request' "$out") | $(ab_value 'Requests per second' "$out") | $(summarize_log) |" )
	done
	if [[ false == "$EXPECT_BLOCKED" ]]; then
		verify "$id"
	fi
	wp_cli limit-logins clear-blocks > /dev/null
}

# Print "branch@sha" of the plugin copy WordPress loads, flagging uncommitted plugin code.
plugin_version() {
	local dir version
	dir="$(native_path "$(wp_value eval 'echo \Lipe\Limit_Logins\LIMIT_LOGINS_PATH;')")"
	version="$(git -C "$dir" rev-parse --abbrev-ref HEAD 2> /dev/null)@$(git -C "$dir" rev-parse --short HEAD 2> /dev/null)" || version="$dir"
	if [[ -n "$(git -C "$dir" status --porcelain -- src limit-logins.php templates 2> /dev/null)" ]]; then
		version+=' with uncommitted plugin changes'
	fi
	echo "$version"
}

command -v curl > /dev/null || fail 'curl is required.'
[[ -n "$AB" ]] || fail 'ApacheBench (ab) is required.'

log "Preparing $URL..."
MU_DIR="$(wp_value eval 'echo WPMU_PLUGIN_DIR;')"
[[ "$MU_DIR" == *mu-plugins ]] || fail "WP-CLI could not load $URL."
MU_DIR="$(native_path "$MU_DIR")"
LOG="$MU_DIR/$LOG_NAME"
if [[ 1 == "$(wp_value eval 'echo (int) is_multisite();')" ]]; then
	NETWORK=( --network )
fi

mkdir -p "$MU_DIR"
cp "$BENCH_DIR/$MU_PLUGIN" "$MU_DIR/$MU_PLUGIN"

delete_bench_user
[[ "$(wp_value user create "$BENCH_USER" "$BENCH_USER@example.com" --role=subscriber --porcelain)" =~ ^[0-9]+$ ]] || fail "Could not create user $BENCH_USER."
# Failed application passwords are only hashed against a user's existing ones.
[[ "$(wp_value user application-password create "$BENCH_USER" bench --porcelain)" =~ ^[A-Za-z0-9]{24}$ ]] || fail "Could not create an application password for $BENCH_USER."
wp_cli limit-logins clear-blocks > /dev/null

# All local requests share one IP, which the timing log reveals.
curl -sk -o /dev/null -H "$HEADER_NAME: probe" "$URL/wp-login.php"
wait_for_log 1
IP="$(awk -F '\t' '1 == NR { print $4 }' "$LOG" 2> /dev/null || true)"
[[ -n "$IP" ]] || fail "No timing recorded. Is $MU_DIR/$MU_PLUGIN loading?"
XDEBUG="$(awk -F '\t' '1 == NR { print $5 }' "$LOG")"
log "Local IP: $IP"
if [[ off != "$XDEBUG" ]]; then
	log "Warning: Xdebug mode is \"$XDEBUG\", which inflates PHP time. Set xdebug.mode=off for comparable results."
fi

for scenario in "${SCENARIOS[@]}"; do
	run_scenario "$scenario"
done

echo "### $MODE"
echo
echo "\`$(plugin_version)\`, n=$REQUESTS, Xdebug $XDEBUG, $(date -u '+%Y-%m-%d %H:%M UTC')"
echo
echo '| Scenario | c | ms/req | req/s | Server ms | Peak MB | Queries |'
echo '| --- | ---: | ---: | ---: | ---: | ---: | ---: |'
printf '%s\n' "${ROWS[@]}"
