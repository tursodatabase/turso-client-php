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

# Install PHP 8.0,8.1,8.3
RUN apt-get install -y php8.0 php8.0-cli php8.0-common php8.0-dev php8.1 php8.1-cli php8.1-common php8.1-dev php8.2 php8.2-cli php8.2-common php8.2-dev php8.3 php8.3-cli php8.3-common php8.3-dev

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
