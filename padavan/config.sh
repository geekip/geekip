
#!/usr/bin/env bash
set -euo pipefail

TARGET="${TARGET:?TARGET is required}"
SOURCE_DIR="${SOURCE_DIR:-/opt/rt-n56u}"

INI_FILE="${GITHUB_WORKSPACE:?GITHUB_WORKSPACE is required}/padavan/config.ini"
CONFIG_FILE="${SOURCE_DIR}/trunk/configs/templates/${TARGET}.config"
SHARED_DEFAULTS_FILE="${SOURCE_DIR}/trunk/user/shared/defaults.h"

CONFIG=()

set_ip() {
  if [[ ! -f "$SHARED_DEFAULTS_FILE" ]]; then
    echo "Error: defaults.h not found: $SHARED_DEFAULTS_FILE"
    return 1
  fi

  sed -i 's/192.168.2.1"/10.0.0.1"/g' "$SHARED_DEFAULTS_FILE"
  sed -i 's/192.168.2.100/10.0.0.11/g' "$SHARED_DEFAULTS_FILE"
  sed -i 's/192.168.2.244/10.0.0.244/g' "$SHARED_DEFAULTS_FILE"
}

read_ini() {
  local section="$1"
  local in_section=0
  local line key value

  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line%$'\r'}"

    [[ -z "$line" || "$line" =~ ^[[:space:]]*[#\;] ]] && continue

    if [[ "$line" =~ ^\[(.*)\]$ ]]; then
      in_section=0
      if [[ "${BASH_REMATCH[1]}" == "$section" ]]; then
        in_section=1
      fi
      continue
    fi

    if [[ $in_section -eq 1 && "$line" =~ ^([^=]+)=(.*)$ ]]; then
      key="${BASH_REMATCH[1]}"
      value="${BASH_REMATCH[2]}"

      key="$(echo "$key" | xargs)"
      value="$(echo "$value" | xargs)"

      CONFIG+=("$key=$value")
    fi
  done < "$INI_FILE"
}

write_config() {
  local item key val

  for item in "${CONFIG[@]}"; do
    key="${item%%=*}"
    val="${item#*=}"

    if grep -qE "^#?${key}=" "$CONFIG_FILE"; then
      sed -i "s|^#\?${key}=.*|${key}=${val}|" "$CONFIG_FILE"
      echo "Configured: ${key}=${val}"
    else
      echo "Warning: configuration item not found: ${key}"
    fi
  done
}

echo "Target: $TARGET"
echo "Source directory: $SOURCE_DIR"

if [[ ! -f "$CONFIG_FILE" ]]; then
  echo "Error: target config not found: $CONFIG_FILE"
  exit 1
fi

set_ip

if [[ -f "$INI_FILE" ]]; then
  read_ini "common"
  read_ini "$TARGET"
  write_config
else
  echo "Warning: config.ini not found: $INI_FILE"
fi

echo "Target configuration completed."
