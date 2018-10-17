FROM ubuntu:bionic

RUN apt-get update \
  && apt-get install -y \
        curl \
        mysql-client \
        python \
        unzip \
        duplicity \
        haveged \
        python-boto \
  && apt-get autoremove -y \
  && rm -rf /var/lib/apt/lists/*

# We don't really need AWS CLI since we use Boto but it may be useful.
RUN mkdir -p /tmp/aws \
  && cd /tmp/aws \
  && curl "https://s3.amazonaws.com/aws-cli/awscli-bundle.zip" -o "awscli-bundle.zip" \
  && unzip awscli-bundle.zip \
  && ./awscli-bundle/install -i /usr/local/aws -b /usr/local/bin/aws \
  && cd / \
  && rm -rf /tmp/aws

ENV SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.1.6/supercronic-linux-amd64 \
    SUPERCRONIC=supercronic-linux-amd64 \
    SUPERCRONIC_SHA1SUM=c3b78d342e5413ad39092fd3cfc083a85f5e2b75

RUN curl -fsSLO "$SUPERCRONIC_URL" \
  && echo "${SUPERCRONIC_SHA1SUM}  ${SUPERCRONIC}" | sha1sum -c - \
  && chmod +x "$SUPERCRONIC" \
  && mv "$SUPERCRONIC" "/usr/local/bin/${SUPERCRONIC}" \
  && ln -s "/usr/local/bin/${SUPERCRONIC}" /usr/local/bin/supercronic

ADD crontab /etc/crontab
ADD bin /opt/bin

ENV AWS_ACCESS_KEY_ID=none \
    AWS_SECRET_ACCESS_KEY=none \
    AWS_S3_BACKUP_PATH="" \
    PASSPHRASE="password" \
    BACKUP_NAME_DB="database" \
    BACKUP_NAME_FILES="files"

VOLUME /opt/backup
VOLUME /opt/duplicity

CMD [ "/usr/local/bin/supercronic", "/etc/crontab" ]
