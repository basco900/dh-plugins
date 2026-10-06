#!/bin/sh
set -eu

site_plugins=/site/wp-content/plugins
source_root=/opt/dh-plugins
plugins="dixcoverhub-core dixcoverhub-custom-ui dixcoverhub-ai-editor"

for plugin in $plugins; do
	if [ ! -s "$source_root/$plugin/$plugin.php" ]; then
		echo "Missing plugin entry point: $source_root/$plugin/$plugin.php" >&2
		exit 1
	fi
done

mkdir -p "$site_plugins"

for plugin in $plugins; do
	source="$source_root/$plugin"
	target="$site_plugins/$plugin"
	mkdir -p "$target"
	rsync -a --delete --delay-updates "$source/" "$target/"
	chown -R 33:33 "$target"
	printf 'Synced %s\n' "$plugin"
done
