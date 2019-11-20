FROM php:7.1-apache

# timezone / date
RUN ln -snf /usr/share/zoneinfo/Europe/Berlin /etc/localtime && echo Europe/Berlin > /etc/timezone

ENV Dependencies "libfreetype6 libjpeg62-turbo unzip git default-mysql-client sudo rsync liblz4-tool bc libzip-dev wget git sudo less nano nvi locales openssh-client iproute2 msmtp libjudy-dev"

# install dependencies and cleanup (needs to be one step, as else it will cache in the laver)
RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
    $Dependencies && \
    apt-get clean && \
    apt-get autoremove -y && \
    rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug && \
    docker-php-ext-enable xdebug

RUN sudo a2enmod rewrite

RUN sed -i "s/ ALL$/ NOPASSWD:ALL/" /etc/sudoers

RUN sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen && \
    dpkg-reconfigure --frontend=noninteractive locales && \
    update-locale LANG=en_US.UTF-8
ENV LANG en_US.UTF-8
ENV LANGUAGE en_US:en
ENV LC_ALL en_US.UTF-8

COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
RUN ln -s /usr/optional_xdebug.ini /usr/local/etc/php/conf.d/xdebug-base.ini


ARG XDEBUG_PORT=9000
RUN	echo xdebug.remote_port=${XDEBUG_PORT} >> /usr/local/etc/php/conf.d/xdebug.ini; \
    echo date.timezone = Europe/Berlin >> /usr/local/etc/php/conf.d/timezone.ini

# Avoid error when www-data does not exist
#RUN sed -i "s/= www-data$/= root/" /usr/local/etc/php-fpm.d/www.conf
#RUN sed -i "s/^User .*\$/User $CONTAINER_USER/g" /usr/local/apache2/conf/httpd.conf

COPY ./xdebug.sh /tmp/xdebug.sh
COPY ./install-composer.sh /tmp/install-composer.sh

#Setup PHP memory profiler
RUN pecl install memprof && docker-php-ext-enable memprof
RUN bash /tmp/install-composer.sh

ARG APACHE_DOCUMENT_ROOT="/var/www/htdocs"
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf


RUN sed -i "s/LogLevel .*$/LogLevel debug/" /etc/apache2/apache2.conf

RUN sed -i "s/^Listen 80$/Listen 8080/" /etc/apache2/ports.conf
RUN sed -ri -e "s/[*]:80/*:8080/g" /etc/apache2/sites-available/*.conf

ARG CONTAINER_GID=1000
ARG CONTAINER_UID=1000
ARG CONTAINER_USER=handBookNotRead

RUN groupadd --gid ${CONTAINER_GID} -o guestgroup && \
    useradd --gid ${CONTAINER_GID} --uid ${CONTAINER_UID} --groups sudo --create-home ${CONTAINER_USER}


USER ${CONTAINER_UID}:${CONTAINER_GID}


