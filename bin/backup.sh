#!/bin/bash

# Backup script for workspace storage
# Creates a numbered tar archive of the storage directory
# Usage:
#   ./backup.sh            # Create a regular backup, keep the last 6 (run every 30 minutes)
#   ./backup.sh --milestone # Create a milestone backup, keep the last 9 (run at 6,12,18 hours)

# ======= Configuration =======
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STORAGE_DIR="$(realpath "${SCRIPT_DIR}/storage")"
BACKUP_DIR="$(realpath "${SCRIPT_DIR}/storage_backups")"
REGULAR_BACKUPS=6
MILESTONE_BACKUPS=9

# ======= Utility Functions =======
log_info() {
  echo "[INFO] $1" >&2
}

log_error() {
  echo "[ERROR] $1" >&2
}

show_help() {
  echo "Usage: $0 [--milestone]"
  echo "  --milestone    Create a milestone backup instead of a regular backup"
  echo "                 Regular backups keep the last ${REGULAR_BACKUPS}"
  echo "                 Milestone backups keep the last ${MILESTONE_BACKUPS}"
  exit 0
}

# ======= Backup Functions =======
create_backup() {
  local backup_type="$1"
  local prefix="regular"
  local max_backups=${REGULAR_BACKUPS}

  if [ "$backup_type" = "milestone" ]; then
    prefix="milestone"
    max_backups=${MILESTONE_BACKUPS}
  fi

  # Find the next available backup number
  local next_num=1
  local highest_num=$(find "${BACKUP_DIR}" -type f -name "${prefix}_*.tar.gz" | sed -E "s/.*${prefix}_([0-9]+)\.tar\.gz/\1/" | sort -n | tail -n 1)

  if [ -n "$highest_num" ]; then
    next_num=$((highest_num + 1))
  fi

  local backup_file="${BACKUP_DIR}/${prefix}_${next_num}.tar.gz"

  log_info "Creating ${backup_type} backup from $STORAGE_DIR to $backup_file"
  local real_storage_dir="$(realpath "$STORAGE_DIR")"

  tar -czf "$backup_file" -C "$(dirname "$real_storage_dir")" "$(basename "$real_storage_dir")"

  if [ $? -ne 0 ]; then
    log_error "Backup creation failed."
    exit 1
  fi

  log_info "Backup created successfully: $backup_file"

  # Check if it's different from the previous backup
  check_duplicate "$backup_file" "$prefix"

  # Apply retention policy
  apply_retention_policy "$prefix" "$max_backups"
}

check_duplicate() {
  local backup_file="$1"
  local prefix="$2"

  # Find the previous backup (should be highest number - 1)
  local current_num=$(basename "$backup_file" | sed -E "s/${prefix}_([0-9]+)\.tar\.gz/\1/")
  local prev_num=$((current_num - 1))
  local previous_backup="${BACKUP_DIR}/${prefix}_${prev_num}.tar.gz"

  if [ -f "$previous_backup" ]; then
    local current_size=$(stat -c%s "$backup_file")
    local previous_size=$(stat -c%s "$previous_backup")

    if [ "$current_size" -eq "$previous_size" ]; then
      # Compare content with cmp to be extra sure
      if cmp -s "$backup_file" "$previous_backup"; then
        log_info "New backup is identical to previous backup. Removing duplicate and keeping previous."
        rm "$backup_file"
        log_info "Duplicate backup removed."
        return 1  # Indicate duplicate was found
      fi
    fi
  fi

  return 0  # No duplicate or files differ
}

apply_retention_policy() {
  local prefix="$1"
  local max_to_keep="$2"

  log_info "Applying retention policy: keep last ${max_to_keep} ${prefix} backups"

  # Get list of backups sorted by number (oldest first)
  local backups=($(find "${BACKUP_DIR}" -type f -name "${prefix}_*.tar.gz" | sort -V))
  local total_backups=${#backups[@]}

  # If we have more backups than we want to keep, delete the oldest ones
  if [ "$total_backups" -gt "$max_to_keep" ]; then
    local to_delete=$((total_backups - max_to_keep))

    for ((i=0; i<to_delete; i++)); do
      log_info "Removing old backup: ${backups[$i]}"
      rm "${backups[$i]}"
    done

    log_info "Removed ${to_delete} old backup(s), keeping last ${max_to_keep}"
  else
    log_info "Currently have ${total_backups} ${prefix} backup(s), not exceeding limit of ${max_to_keep}"
  fi
}

# ======= Main Execution =======
main() {
  # Process command line arguments
  local backup_type="regular"

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --milestone)
        backup_type="milestone"
        shift
        ;;
      *)
        log_error "Unknown argument: $1"
        show_help
        ;;
    esac
  done

  # Ensure backup directory exists
  mkdir -p "$BACKUP_DIR"

  # Create the backup
  create_backup "$backup_type"

  log_info "Backup process completed"
}

# Run the main function
main "$@"
