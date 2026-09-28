#!/usr/bin/env bash
# Lance le scénario dans un site Playground neuf, affiche le rapport, échoue si une vérification échoue.
# Usage : bin/run-test.sh hpos|posts|minimum
set -uo pipefail
bash "$(dirname "$0")/build-zip.sh" > /dev/null || exit 1
cd "$(dirname "$0")/.."

store="${1:-hpos}"
case "$store" in
	hpos)  blueprint="dev/test-hpos.json"; wp_version="7.1.2"; php_version="8.3" ;;
	posts) blueprint="dev/test-legacy.json"; wp_version="7.1.2"; php_version="7.4" ;;
	minimum) blueprint="dev/test-minimum.json"; wp_version="6.6.2"; php_version="7.4" ;;
	*) echo "Usage : $0 hpos|posts|minimum" >&2; exit 2 ;;
esac
report="dev/.test-output-${store/minimum/posts}.txt"
rm -f "$report"

npx wp-playground-cli run-blueprint \
	--wp="$wp_version" --php="$php_version" --blueprint="$blueprint" \
	--mount=dist/jeytech-order-transfer-qr:/wordpress/wp-content/plugins/jeytech-order-transfer-qr \
	--mount=dev:/wordpress/wp-content/otqr-dev \
	--mount=languages:/wordpress/wp-content/otqr-dev-languages
code=$?

if [[ ! -f "$report" ]]; then
	echo "✗ Aucun rapport « $report » (sortie Playground : $code). Rapports présents :" >&2
	ls dev/.test-output-*.txt 2>/dev/null >&2 || echo "  (aucun)" >&2
	exit 1
fi
cat "$report"
if grep -q "^FAIL" "$report"; then
	exit 1
fi
exit 0
