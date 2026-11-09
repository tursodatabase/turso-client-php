# Production-flavor image for the libsql_php extension proof.
#
# Differences from the repository's dev Dockerfile (../..//Dockerfile):
#   * PHP 8.5 runs as **php-fpm** (not `php -S`), listening on TCP :9000 so an
#     nginx tier can proxy to it — the standard production topology.
#   * The extension is built in **release mode** (the artifact kind you actually
#     ship) and installed the "packaged" way: an .so in the extension dir plus
#     an ini drop-in in `conf.d`, NOT a php.ini with a relative ./target path.
#   * `php8.5-curl` is included so the bundled workload runner can exercise the
#     app through nginx exactly like an external client would.
#
# Build from the repository root (compose.yaml already does it):
#   docker build -f docker/production-proof/Dockerfile.pre .
#
# The build context is the repository root: the extension is compiled from the
# very source tree you are reviewing, not from a released archive.

FROM debian:bookworm-slim AS builder

ENV DEBIAN_FRONTEND=noninteractive

# Build essentials + libclang (bindgen needs it to generate PHP bindings).
RUN apt-get update && apt-get install -y \
    curl \
    build-essential \
    cmake \
    clang \
    libclang-dev \
    pkg-config \
    gnupg \
    ca-certificates \
    lsb-release \
    wget \
    apt-transport-https \
    && rm -rf /var/lib/apt/lists/*

# PHP 8.5 (+ dev headers) from sury — the same source the dev image uses, so
# the ABI matches what the project ships on Debian.
RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" \
       > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y php8.5 php8.5-dev \
    && rm -rf /var/lib/apt/lists/*

# Nightly Rust, same as CONTRIBUTING mandates.
ENV RUSTUP_HOME=/usr/local/rustup \
    CARGO_HOME=/usr/local/cargo \
    PATH=/usr/local/cargo/bin:$PATH
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y \
    && rustup toolchain install nightly \
    && rustup default nightly \
    && rustc --version && cargo --version

# Compile the extension in release mode against PHP 8.5 headers.
ENV PHP=/usr/bin/php8.5 \
    PHP_CONFIG=/usr/bin/php-config8.5
COPY . /build
WORKDIR /build
RUN CARGO_TARGET_DIR=/tmp/target cargo build --release \
    && install -m 0755 /tmp/target/release/liblibsql_php.so \
       /usr/lib/php/20250925/liblibsql_php.so

FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# Runtime: php-fpm + curl (runner uses it), same sury source for ABI honesty.
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    lsb-release \
    ca-certificates \
    wget \
    curl \
    gnupg \
    procps \
    && wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" \
       > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y \
       php8.5-fpm \
       php8.5-cli \
       php8.5-curl \
    && rm -rf /var/lib/apt/lists/*

# Install the compiled extension the packaged way.
COPY --from=builder /usr/lib/php/20250925/liblibsql_php.so /usr/lib/php/20250925/liblibsql_php.so
RUN printf 'extension=liblibsql_php.so\n' \
    > /etc/php/8.5/fpm/conf.d/90-libsql.ini \
    && printf 'extension=liblibsql_php.so\n' \
    > /etc/php/8.5/cli/conf.d/90-libsql.ini

# fpm: listen on TCP so nginx (a different container) can reach it.
RUN sed -i 's|^listen = .*|listen = 9000|' /etc/php/8.5/fpm/pool.d/www.conf

# The production app payload.
WORKDIR /var/www
COPY docker/production-proof/app/ /var/www/
RUN chown -R www-data:www-data /var/www

EXPOSE 9000

# Named volumes arrive root-owned — the packaged app bootstraps its own data
# dir (the honest "volume arrives, container fixes perms" production step),
# then hands off to php-fpm.
CMD ["sh", "-c", "install -d -o www-data -g www-data /var/www/storage && exec /usr/sbin/php-fpm8.5 -F"]
