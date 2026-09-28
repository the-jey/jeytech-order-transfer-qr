#!/usr/bin/env bash
# Verify WordPress language packs and the English fallback on the supported minimum.
set -euo pipefail
cd "$(dirname "$0")/.."
for fixture in pack no-pack; do
	report="dev/results/i18n-smoke-$fixture.txt"
	rm -f "$report"
	blueprint="dev/i18n-smoke.json"
	if [[ "$fixture" == no-pack ]]; then
		blueprint="dev/i18n-smoke-no-pack.json"
	fi
	npx --no-install wp-playground-cli run-blueprint \
		--wp=6.6.2 --php=7.4 --blueprint="$blueprint" \
		--mount=dist/jeytech-order-transfer-qr:/wordpress/wp-content/plugins/jeytech-order-transfer-qr \
		--mount=dev:/wordpress/wp-content/otqr-dev \
		--mount=languages:/wordpress/wp-content/otqr-dev-languages
	[[ -f "$report" ]]
	grep -q '^PASS ' "$report"
	cat "$report"
done
