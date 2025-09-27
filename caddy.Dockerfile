FROM caddy:2-builder AS builder

RUN xcaddy build \
    --with github.com/caddyserver/replace-response \
    --with github.com/pberkel/caddy-storage-redis

FROM caddy:2

COPY --from=builder /usr/bin/caddy /usr/bin/caddy