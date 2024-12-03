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
    llvm \
    libclang-dev \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update

# Install PHP 8.4 and php8.4-dev
RUN apt-get install -y php8.4 php8.4-cli php8.4-common php8.4-dev

# Create a non-root user and switch to it
RUN useradd -m dockeruser
USER dockeruser

# Install Rust and cross
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y \
    && /home/dockeruser/.cargo/bin/rustup default nightly
ENV PATH="/home/dockeruser/.cargo/bin:${PATH}"
RUN cargo install cross

# Verify installations
RUN php -v
RUN php-config --version
