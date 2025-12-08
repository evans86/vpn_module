#!/bin/bash

# S3 configuration
# Ключи будут подставлены при установке скрипта
S3_ACCESS_KEY="{{S3_ACCESS_KEY}}"
S3_SECRET_KEY="{{S3_SECRET_KEY}}"
S3_BUCKET="{{S3_BUCKET}}"

# Paths
S3_CONFIG="/root/.s3cfg"
UPLOAD_SCRIPT="/root/upload-logs.sh"

# Install required packages
apt update && apt install -y s3cmd

# Configure s3cmd
cat <<EOF > "$S3_CONFIG"
[default]
access_key = $S3_ACCESS_KEY
secret_key = $S3_SECRET_KEY
bucket_location = ru-central1
host_base = storage.yandexcloud.net
host_bucket = %(bucket)s.storage.yandexcloud.net
EOF

# Create daily upload script
cat <<'EOF' > "$UPLOAD_SCRIPT"
#!/bin/bash

# Получаем вчерашнюю дату и IP-адрес сервера
YESTERDAY=$(date -d "yesterday" +"%Y-%m-%d")
IP=$(hostname -I | awk '{print $1}')

# Пути к файлам логов
ACCESS_LOG="/var/lib/marzban/access.log"
ERROR_LOG="/var/lib/marzban/error.log"

# Объединенный и сжатый файл с датой и IP в имени
COMBINED_LOG="/tmp/marzban-combined-${YESTERDAY}.log"
COMBINED_GZ="/var/lib/marzban/marzban-${YESTERDAY}-${IP}.log.gz"

# Путь к S3 бакету (используется переменная из установочного скрипта)
# S3_BUCKET уже определен в начале скрипта

# Проверяем существование обоих логов
if [[ -f "$ACCESS_LOG" || -f "$ERROR_LOG" ]]; then
  # Создаем временный объединенный лог
  echo "[+] Creating combined log file"
  
  # Добавляем метку для access.log
  echo "========== ACCESS LOG ==========" > "$COMBINED_LOG"
  if [[ -f "$ACCESS_LOG" ]]; then
    cat "$ACCESS_LOG" >> "$COMBINED_LOG"
  else
    echo "No access log found" >> "$COMBINED_LOG"
  fi
  
  # Добавляем метку для error.log
  echo -e "\n\n========== ERROR LOG ===========" >> "$COMBINED_LOG"
  if [[ -f "$ERROR_LOG" ]]; then
    cat "$ERROR_LOG" >> "$COMBINED_LOG"
  else
    echo "No error log found" >> "$COMBINED_LOG"
  fi
  
  # Сжимаем объединенный лог
  echo "[+] Compressing: $COMBINED_LOG → $COMBINED_GZ"
  gzip -c "$COMBINED_LOG" > "$COMBINED_GZ"
  
  # Загружаем в S3
  echo "[+] Uploading to S3: $COMBINED_GZ → $S3_BUCKET"
  s3cmd put "$COMBINED_GZ" "$S3_BUCKET/"
  
  # Удаляем оригинальные файлы и временный объединенный файл
  if [[ -f "$ACCESS_LOG" ]]; then
    echo "[+] Removing original: $ACCESS_LOG"
    rm "$ACCESS_LOG"
  fi
  
  if [[ -f "$ERROR_LOG" ]]; then
    echo "[+] Removing original: $ERROR_LOG"
    rm "$ERROR_LOG"
  fi
  
  echo "[+] Removing temporary combined log: $COMBINED_LOG"
  rm "$COMBINED_LOG"
  
  echo "[+] Removing gzipped combined log: $COMBINED_LOG"
  if [[ -f "$COMBINED_GZ" ]]; then
    echo "[+] Removing original: $COMBINED_GZ"
    rm "$COMBINED_GZ"
  fi
  
  # Находим и перезапускаем контейнер marzban
  MARZBAN_CONTAINER=$(docker ps -q --filter "name=marzban")
  if [[ -n "$MARZBAN_CONTAINER" ]]; then
    echo "[+] Restarting marzban container: $MARZBAN_CONTAINER"
    docker restart $MARZBAN_CONTAINER
  else
    echo "[!] No container with name 'marzban' found"
  fi
else
  echo "[!] No log files found to process"
fi

EOF

chmod +x "$UPLOAD_SCRIPT"

# Register cronjob
(crontab -l 2>/dev/null; echo "30 0 * * * $UPLOAD_SCRIPT >> /var/log/s3upload.log 2>&1") | crontab -

echo "[+] Setup complete. Logs will be compressed and uploaded to $S3_BUCKET daily at 00:30."

