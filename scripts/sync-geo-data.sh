#!/bin/bash

# =============================================================================
# Geo Data Sync Script
# =============================================================================
# This script downloads countries, states, and cities JSON data from the
# dr5hn/countries-states-cities-database repository.
#
# Use this script if you encounter memory issues with the PHP artisan command:
#   php artisan sync:countries-states-json
#
# Usage:
#   ./sync-geo-data.sh [options]
#
# Options:
#   -d, --dir DIR       Storage directory (default: storage/app/remote)
#   -f, --force         Force re-download even if files exist
#   -o, --only FILES    Only download specific files (comma-separated)
#                       Available: countries,countries+states,cities,states,
#                                  regions,subregions,countries+cities,
#                                  states+cities,countries+states+cities
#   -h, --help          Show this help message
#
# Examples:
#   ./sync-geo-data.sh
#   ./sync-geo-data.sh --dir /path/to/storage
#   ./sync-geo-data.sh --only countries,states
#   ./sync-geo-data.sh --force
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default settings
STORAGE_DIR="storage/app/remote"
FORCE_DOWNLOAD=false
ONLY_FILES=""

# Base URL for the repository
BASE_URL="https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json"

# File mappings (key -> remote filename)
declare -A FILES
FILES["countries"]="countries.json"
FILES["countries+states"]="countries%2Bstates.json"
FILES["cities"]="cities.json"
FILES["states"]="states.json"
FILES["regions"]="regions.json"
FILES["subregions"]="subregions.json"
FILES["countries+cities"]="countries%2Bcities.json"
FILES["states+cities"]="states%2Bcities.json"
FILES["countries+states+cities"]="countries%2Bstates%2Bcities.json"

# Local file names (key -> local filename)
declare -A LOCAL_FILES
LOCAL_FILES["countries"]="countries.json"
LOCAL_FILES["countries+states"]="countries+states.json"
LOCAL_FILES["cities"]="cities.json"
LOCAL_FILES["states"]="states.json"
LOCAL_FILES["regions"]="regions.json"
LOCAL_FILES["subregions"]="subregions.json"
LOCAL_FILES["countries+cities"]="countries+cities.json"
LOCAL_FILES["states+cities"]="states+cities.json"
LOCAL_FILES["countries+states+cities"]="countries+states+cities.json"

# =============================================================================
# Helper Functions
# =============================================================================

print_header() {
    echo -e "${BLUE}=============================================${NC}"
    echo -e "${BLUE}  Geo Data Sync Script${NC}"
    echo -e "${BLUE}=============================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✖${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}!${NC} $1"
}

print_info() {
    echo -e "${BLUE}→${NC} $1"
}

show_help() {
    head -40 "$0" | tail -35
    exit 0
}

format_bytes() {
    local bytes=$1
    if [ $bytes -ge 1073741824 ]; then
        echo "$(echo "scale=2; $bytes / 1073741824" | bc) GB"
    elif [ $bytes -ge 1048576 ]; then
        echo "$(echo "scale=2; $bytes / 1048576" | bc) MB"
    elif [ $bytes -ge 1024 ]; then
        echo "$(echo "scale=2; $bytes / 1024" | bc) KB"
    else
        echo "$bytes B"
    fi
}

get_remote_size() {
    local url=$1
    local size=$(curl -sI "$url" 2>/dev/null | grep -i "content-length" | awk '{print $2}' | tr -d '\r')
    echo "${size:-0}"
}

check_url_exists() {
    local url=$1
    local status=$(curl -sI -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)
    [ "$status" = "200" ]
}

# =============================================================================
# Download Functions
# =============================================================================

download_file() {
    local key=$1
    local remote_file=${FILES[$key]}
    local local_file=${LOCAL_FILES[$key]}
    local url="${BASE_URL}/${remote_file}"
    local gz_url="${url}.gz"
    local dest="${STORAGE_DIR}/${local_file}"

    print_info "Checking ${key}..."

    # Check if we should skip
    if [ -f "$dest" ] && [ "$FORCE_DOWNLOAD" = false ]; then
        local local_size=$(stat -f%z "$dest" 2>/dev/null || stat -c%s "$dest" 2>/dev/null || echo "0")
        local remote_size=$(get_remote_size "$url")

        if [ "$local_size" = "$remote_size" ] && [ "$remote_size" != "0" ]; then
            print_success "${key}: Up-to-date ($(format_bytes $local_size))"
            return 0
        fi
    fi

    # Try regular JSON first
    if check_url_exists "$url"; then
        print_info "Downloading ${key}..."
        if curl -# -L -o "$dest" "$url" 2>&1; then
            local size=$(stat -f%z "$dest" 2>/dev/null || stat -c%s "$dest" 2>/dev/null || echo "0")
            print_success "${key}: Downloaded ($(format_bytes $size))"
            return 0
        else
            print_error "${key}: Download failed"
            return 1
        fi
    fi

    # Try gzipped version
    if check_url_exists "$gz_url"; then
        print_warning "${key}: JSON not found, trying gzipped version..."
        local temp_gz="${dest}.gz.tmp"

        print_info "Downloading ${key} (gzipped)..."
        if curl -# -L -o "$temp_gz" "$gz_url" 2>&1; then
            print_info "Decompressing ${key}..."
            if gunzip -c "$temp_gz" > "$dest" 2>/dev/null; then
                rm -f "$temp_gz"
                local size=$(stat -f%z "$dest" 2>/dev/null || stat -c%s "$dest" 2>/dev/null || echo "0")
                print_success "${key}: Downloaded and decompressed ($(format_bytes $size))"
                return 0
            else
                rm -f "$temp_gz"
                print_error "${key}: Decompression failed"
                return 1
            fi
        else
            rm -f "$temp_gz"
            print_error "${key}: Gzipped download failed"
            return 1
        fi
    fi

    print_error "${key}: File not found (tried both .json and .json.gz)"
    return 1
}

# =============================================================================
# Main Script
# =============================================================================

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -d|--dir)
            STORAGE_DIR="$2"
            shift 2
            ;;
        -f|--force)
            FORCE_DOWNLOAD=true
            shift
            ;;
        -o|--only)
            ONLY_FILES="$2"
            shift 2
            ;;
        -h|--help)
            show_help
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Print header
print_header

# Check for curl
if ! command -v curl &> /dev/null; then
    print_error "curl is required but not installed"
    exit 1
fi

# Check for gunzip
if ! command -v gunzip &> /dev/null; then
    print_error "gunzip is required but not installed"
    exit 1
fi

# Create storage directory
if [ ! -d "$STORAGE_DIR" ]; then
    print_info "Creating directory: ${STORAGE_DIR}"
    mkdir -p "$STORAGE_DIR"
fi

echo "Storage directory: ${STORAGE_DIR}"
echo "Force download: ${FORCE_DOWNLOAD}"
echo ""

# Determine which files to download
if [ -n "$ONLY_FILES" ]; then
    IFS=',' read -ra DOWNLOAD_KEYS <<< "$ONLY_FILES"
else
    DOWNLOAD_KEYS=("countries+states" "countries" "cities" "countries+cities" "countries+states+cities" "regions" "states+cities" "states" "subregions")
fi

# Download files
success_count=0
fail_count=0

for key in "${DOWNLOAD_KEYS[@]}"; do
    key=$(echo "$key" | xargs) # Trim whitespace
    if [ -z "${FILES[$key]}" ]; then
        print_warning "Unknown file key: ${key}, skipping..."
        continue
    fi

    if download_file "$key"; then
        ((++success_count))
    else
        ((++fail_count))
    fi
    echo ""
done

# Summary
echo -e "${BLUE}=============================================${NC}"
echo -e "${BLUE}  Summary${NC}"
echo -e "${BLUE}=============================================${NC}"
print_success "Successful: ${success_count}"
if [ $fail_count -gt 0 ]; then
    print_error "Failed: ${fail_count}"
fi

exit $fail_count
