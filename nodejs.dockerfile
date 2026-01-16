FROM docker-registry.wikimedia.org/dev/bookworm-php83-fpm:1.0.0
RUN curl -fsSL https://raw.githubusercontent.com/tj/n/master/bin/n | bash -s lts
