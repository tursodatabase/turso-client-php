# Use the latest version of Ubuntu
FROM ubuntu:latest

# Avoid prompts from apt
ENV DEBIAN_FRONTEND=noninteractive

# Update and install necessary packages
RUN apt-get update && apt-get install -y \
    software-properties-common \
    curl \
    build-essential \
    pkg-config \
    git \
    cmake \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update

# Install PHP 8.3
RUN apt-get install -y php8.3 php8.3-cli php8.3-common

# Add PHP to PATH
ENV PATH="/usr/bin/php:${PATH}"
ENV PHP_CONFIG="/usr/bin/php/php-config"

# Create a non-root user and switch to it
RUN useradd -m dockeruser
USER dockeruser

# Install Rust and cross
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/home/dockeruser/.cargo/bin:${PATH}"
RUN cargo install cross

# Verify installations
RUN php -v
RUN php-config --version
