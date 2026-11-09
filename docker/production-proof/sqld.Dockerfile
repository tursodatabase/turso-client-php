# libSQL server for the production proof.
#
# A dedicated file so the topology stays legible; run via compose.yaml,
# which mounts a data volume at /var/lib/sqld (durable across restarts).

FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --system --create-home --shell /usr/sbin/nologin sqld

COPY sqld /usr/local/bin/sqld
COPY sqld-entrypoint.sh /usr/local/bin/sqld-entrypoint.sh

RUN chmod +x /usr/local/bin/sqld /usr/local/bin/sqld-entrypoint.sh \
    && mkdir -p /var/lib/sqld \
    && chown -R sqld:sqld /var/lib/sqld

USER sqld
WORKDIR /var/lib/sqld

ENTRYPOINT ["/usr/local/bin/sqld-entrypoint.sh"]
