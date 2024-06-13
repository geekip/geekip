#!/bin/bash

# https://github.com/TurBoTse/Padavan-Build/tree/main

TARGET="${{ inputs.target }}"
INI_FILE="${GITHUB_WORKSPACE}/padavan/config.ini"
CONFIG_FILE="${{ env.source_dir }}/trunk/configs/templates/${TARGET}.config"
SHARED_DEFAULTS_FILE="trunk/user/shared/defaults.h"
CONFIG=()

function set_ip(){
  # 路由器ip
  sed -i 's/192.168.2.1"/10.0.0.1"/' "$SHARED_DEFAULTS_FILE"
  # DHCP开始ip
  sed -i 's/192.168.2.100/10.0.0.11/' "$SHARED_DEFAULTS_FILE"
  # DHCP结束ip
  sed -i 's/192.168.2.244/10.0.0.244/' "$SHARED_DEFAULTS_FILE"
}

function read_ini() {
  local section=$1
  local in_section=0
  while IFS= read -r line; do
    [[ -z "$line" || "$line" =~ ^\;.* || "$line" =~ ^\#.* ]] && continue
    if [[ "$line" =~ ^\[(.*)\]$ ]]; then
      in_section=0
      if [[ "${BASH_REMATCH[1]}" == "$section" ]]; then
        in_section=1
      fi
      continue
    fi
    if [[ $in_section -eq 1 && "$line" =~ ^([^=]+)=(.*)$ ]]; then
      key=$(echo "${BASH_REMATCH[1]}" | xargs)
      value=$(echo "${BASH_REMATCH[2]}" | xargs)
      CONFIG+=("$key=$value")
    fi
  done < "$INI_FILE"
}

function write_config(){
  for item in "${CONFIG[@]}"; do
    key="${item%%=*}"
    val="${item##*=}"
    if grep -q "^#*$key" "$CONFIG_FILE"; then
      sed -i "s/^#*$key=.*/$key=$val/" "$CONFIG_FILE"
    else
      # echo "$key=$val" >> "$CONFIG_FILE"
      echo "No configuration item '${key}'"
    fi
  done
}

set_ip

if [ -f "$INI_FILE" ]; then
  read_ini "common"
  read_ini "$TARGET"
  write_config
else
  echo "No configuration file '${INI_FILE}'"
fi