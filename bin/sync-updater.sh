#!/bin/sh
# Kopiert lib/github-updater.php in alle Plugins (includes/github-updater.php).
# Nach jeder Änderung an lib/github-updater.php ausführen und die Kopien mit einchecken;
# der Release-Workflow bricht ab, wenn eine Kopie nicht übereinstimmt.
set -e
cd "$(dirname "$0")/.."
for plugin in */; do
	plugin="${plugin%/}"
	if [ -f "$plugin/$plugin.php" ] && grep -q "Update URI:" "$plugin/$plugin.php"; then
		mkdir -p "$plugin/includes"
		cp lib/github-updater.php "$plugin/includes/github-updater.php"
		echo "aktualisiert: $plugin/includes/github-updater.php"
	fi
done
