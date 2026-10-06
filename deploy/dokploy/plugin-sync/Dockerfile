FROM alpine:3.22

RUN apk add --no-cache rsync

WORKDIR /opt/dh-plugins

COPY plugins/dixcoverhub-core/ /opt/dh-plugins/dixcoverhub-core/
COPY plugins/dixcoverhub-custom-ui/ /opt/dh-plugins/dixcoverhub-custom-ui/
COPY plugins/dixcoverhub-ai-editor/ /opt/dh-plugins/dixcoverhub-ai-editor/
COPY deploy/dokploy/plugin-sync/sync-plugins.sh /usr/local/bin/sync-plugins

RUN chmod 0755 /usr/local/bin/sync-plugins

ENTRYPOINT ["/usr/local/bin/sync-plugins"]
