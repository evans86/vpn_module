Положите сюда stub.crt и stub.key (или поправьте docker-compose.yml на свои файлы).

Самоподписанный пример:

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout stub.key -out stub.crt -subj "/CN=localhost"
