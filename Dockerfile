# Dockerfile to create an image with PHP 8.4, PHP 8.4-dev, and Rust
# for the linux/arm64 architecture.

# --- Stage 1: Builder for Rust ---
# This stage installs Rust and its build dependencies.
# Using debian:bookworm-slim as a lightweight base image.
# For arm64, use --platform=linux/arm64 by uncommenting the line below.
# FROM --platform=linux/arm64 debian:bookworm-slim AS builder
# otherwise, use the default image.
FROM debian:bookworm-slim AS builder

# Set environment variables for non-interactive apt operations
ENV DEBIAN_FRONTEND=noninteractive

# Update package lists and install build essentials for Rust,
# including curl for downloading rustup, build-essential for compilers,
# pkg-config and libssl-dev for common Rust dependencies.
RUN apt-get update && apt-get install -y \
    curl \
    build-essential \
    pkg-config \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Set environment variables for Rust installation paths.
# CARGO_HOME is where cargo stores its binaries and registries.
# RUSTUP_HOME is where rustup stores toolchains and other data.
# Add Cargo's bin directory to the PATH.
ENV RUSTUP_HOME=/usr/local/rustup \
    CARGO_HOME=/usr/local/cargo \
    PATH=/usr/local/cargo/bin:$PATH

# Install Rust using rustup.
# Install the nightly toolchain and set it as the default.
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y \
    && rustup toolchain install nightly \
    && rustup default nightly

# Verify Rust installation by checking versions.
RUN rustc --version && cargo --version

# --- Stage 2: Final Image ---
# This stage sets up PHP and copies the Rust installation from the builder stage.
# FROM --platform=linux/arm64 debian:bookworm-slim
# otherwise, use the default image.
FROM debian:bookworm-slim

# Set environment variables for non-interactive apt operations
ENV DEBIAN_FRONTEND=noninteractive

# Update package lists and install necessary packages for adding PHP PPA,
# including apt-transport-https, lsb-release, ca-certificates, wget, and gnupg.
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    lsb-release \
    ca-certificates \
    wget \
    curl \
    git \
    vim \
    nano \
    cmake \
    clang \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# Add Ondrej Sury's PHP PPA (Personal Package Archive).
# This PPA provides up-to-date PHP versions for Debian/Ubuntu.
# First, download and add the GPG key for the repository.
# Then, add the repository URL to the sources list.
RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list

# Update package lists again to include the new PHP repository.
# Install php8.4 and php8.4-dev.
# php8.4-dev includes development files for PHP, useful for compiling extensions.
# Clean up apt caches to reduce image size.
RUN apt-get update && apt-get install -y \
    php8.4 \
    php8.4-dev \
    && rm -rf /var/lib/apt/lists/*

# Copy the Rust installation from the 'builder' stage to the final image.
# This ensures Rust is available without needing its build dependencies.
COPY --from=builder /usr/local/cargo /usr/local/cargo
COPY --from=builder /usr/local/rustup /usr/local/rustup

# Set environment variables for Rust in the final image, mirroring the builder stage.
ENV RUSTUP_HOME=/usr/local/rustup \
    CARGO_HOME=/usr/local/cargo \
    PATH=/usr/local/cargo/bin:$PATH

# Verify PHP and Rust installations in the final image.
RUN php -v && rustc --version && cargo --version

WORKDIR /app

COPY . .

RUN cargo build

EXPOSE 6969

# Start PHP server when container starts
CMD ["php", "-S", "0.0.0.0:6969", "-t", "php-server"]
