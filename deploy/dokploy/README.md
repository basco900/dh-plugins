# Deploy the DixcoverHub plugins with Dokploy

The repository contains the custom plugins only. The Compose file keeps the existing WordPress and MySQL services and adds a one-shot `plugin_sync` service. That service copies only the three DixcoverHub plugin directories into the existing `wp_app` volume; it does not replace WordPress core, uploads, themes, or third-party plugins.

## One-time Dokploy setup

Use the existing WordPress Compose resource. Change its source from **Raw** to **GitHub**, select `basco900/dh-plugins` and branch `main`, and set the Compose file path to `./docker-compose.yml`. Keep the existing Dokploy environment values for `DB_NAME`, `DB_PASSWORD`, and `WORDPRESS_DEBUG`.

Enable AutoDeploy for that selected branch. A push to `main` will rebuild the plugin sync image, update the three plugin folders, and leave the persistent WordPress and MySQL volumes in place. Do not create a second Compose resource for this file and do not use **Fresh Volumes**; keeping the same resource preserves its Compose project and existing named volumes.

After the first successful deployment, activate DixcoverHub Core first, then Custom UI and AI Editor in WordPress. Public-site UI features still require their own Studio switches.

The sync image validates all three plugin entry points before changing files and only removes/replaces files inside those three plugin directories. Plugin settings remain in the WordPress database and media remain in `wp-content/uploads`.
