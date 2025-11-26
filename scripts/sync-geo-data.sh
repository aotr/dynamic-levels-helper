#!/bin/bash

# =============================================================================
# Geo Data Sync Script
# =============================================================================
# This script downloads geographical data files from the countries-states-cities
# database repository. It's designed as an alternative to the PHP artisan command
# for users who experience memory issues with large file downloads.
#
# Usage:
#   ./sync-geo-data.sh [options]
#
# Options:
#   --dir=PATH      Specify the storage directory (default: storage/app/geo-data)
#   --force         Force download even if files exist
#   --only=FILES    Comma-separated list of files to download
#   --help          Show this help message
#
# Examples:
#   ./sync-geo-data.sh
#   ./sync-geo-data.sh --dir=/path/to/storage
#   ./sync-geo-data.sh --force
#   ./sync-geo-data.sh --only=countries,states
#   ./sync-geo-data.sh --only=countries,states,cities --force
# =============================================================================

set -e

# Default configuration
BASE_URL="https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/refs/heads/master/json"
DEFAULT_DIR="storage/app/geo-data"
STORAGE_DIR=""
FORCE=false
ONLY_FILES=""

# Available files
ALL_FILES=(
    "countries"
    "states"
    "cities"
    "regions"
    "subregions"
    "countries+states"
    "countries+cities"
    "countries+states+cities"
    "states+cities"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# =============================================================================
# Helper Functions
# =============================================================================

print_header() {
    echo -e "${BLUE}"
    echo "=============================================="
    echo "  Geo Data Sync Script"
    echo "=============================================="
    echo -e "${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}! $1${NC}"
}

print_info() {
    echo -e "${BLUE}→ $1${NC}"
}

show_help() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --dir=PATH      Specify the storage directory (default: $DEFAULT_DIR)"
    echo "  --force         Force download even if files exist"
    echo "  --only=FILES    Comma-separated list of files to download"
    echo "  --help          Show this help message"
    echo ""
    echo "Available files:"
    for file in "${ALL_FILES[@]}"; do
        echo "  - $file"
    done
    echo ""
    echo "Examples:"
    echo "  $0"
    echo "  $0 --dir=/path/to/storage"
    echo "  $0 --force"
    echo "  $0 --only=countries,states"
    echo "  $0 --only=countries,states,cities --force"
}

# =============================================================================
# Parse Arguments
# =============================================================================

parse_arguments() {
    for arg in "$@"; do
        case $arg in
            --dir=*)
                STORAGE_DIR="${arg#*=}"
                ;;
            --force)
                FORCE=true
                ;;
            --only=*)
                ONLY_FILES="${arg#*=}"
                ;;
            --help)
                show_help
                exit 0
                ;;
            *)
                print_error "Unknown option: $arg"
                show_help
                exit 1
                ;;
        esac
    done
    
    # Set default directory if not specified
    if [ -z "$STORAGE_DIR" ]; then
        # Try to find Laravel's storage directory
        if [ -d "storage/app" ]; then
            STORAGE_DIR="$DEFAULT_DIR"
        elif [ -d "../storage/app" ]; then
            STORAGE_DIR="../$DEFAULT_DIR"
        else
            STORAGE_DIR="$DEFAULT_DIR"
        fi
    fi
}

# =============================================================================
# Download Functions
# =============================================================================

download_file() {
    local filename="$1"
    local json_file="${filename}.json"
    local gz_file="${filename}.json.gz"
    local target_path="${STORAGE_DIR}/${json_file}"
    local temp_gz="${STORAGE_DIR}/${gz_file}"
    
    # Check if file exists and force is not set
    if [ -f "$target_path" ] && [ "$FORCE" = false ]; then
        print_warning "Skipping $json_file (already exists, use --force to overwrite)"
        return 0
    fi
    
    print_info "Downloading $json_file..."
    
    # Try downloading JSON first
    local json_url="${BASE_URL}/${json_file}"
    local http_code
    
    http_code=$(curl -s -w "%{http_code}" -o "$target_path" "$json_url")
    
    if [ "$http_code" = "200" ]; then
        local size=$(du -h "$target_path" | cut -f1)
        print_success "Downloaded $json_file ($size)"
        return 0
    fi
    
    # JSON failed, try gzip
    print_info "JSON not available, trying gzip version..."
    rm -f "$target_path" 2>/dev/null
    
    local gz_url="${BASE_URL}/${gz_file}"
    http_code=$(curl -s -w "%{http_code}" -o "$temp_gz" "$gz_url")
    
    if [ "$http_code" = "200" ]; then
        print_info "Decompressing $gz_file..."
        
        # Decompress using gunzip
        if gunzip -c "$temp_gz" > "$target_path" 2>/dev/null; then
            rm -f "$temp_gz"
            local size=$(du -h "$target_path" | cut -f1)
            print_success "Downloaded and decompressed $json_file ($size)"
            return 0
        else
            print_error "Failed to decompress $gz_file"
            rm -f "$temp_gz" "$target_path" 2>/dev/null
            return 1
        fi
    fi
    
    print_error "Failed to download $json_file (HTTP $http_code)"
    rm -f "$temp_gz" "$target_path" 2>/dev/null
    return 1
}

# =============================================================================
# Main Execution
# =============================================================================

main() {
    print_header
    
    # Parse command line arguments
    parse_arguments "$@"
    
    # Create storage directory
    print_info "Storage directory: $STORAGE_DIR"
    mkdir -p "$STORAGE_DIR"
    
    # Determine which files to download
    local files_to_download=()
    
    if [ -n "$ONLY_FILES" ]; then
        # Parse comma-separated list
        IFS=',' read -ra ADDR <<< "$ONLY_FILES"
        for file in "${ADDR[@]}"; do
            # Trim whitespace
            file=$(echo "$file" | xargs)
            files_to_download+=("$file")
        done
    else
        files_to_download=("${ALL_FILES[@]}")
    fi
    
    echo ""
    print_info "Files to download: ${#files_to_download[@]}"
    echo ""
    
    # Download each file
    local success_count=0
    local fail_count=0
    local skip_count=0
    
    for file in "${files_to_download[@]}"; do
        if download_file "$file"; then
            ((success_count++))
        else
            ((fail_count++))
        fi
    done
    
    # Summary
    echo ""
    echo -e "${BLUE}=============================================="
    echo "  Summary"
    echo -e "==============================================${NC}"
    print_success "Downloaded: $success_count"
    if [ $fail_count -gt 0 ]; then
        print_error "Failed: $fail_count"
    fi
    echo ""
    
    if [ $fail_count -eq 0 ]; then
        print_success "All files synced successfully!"
        exit 0
    else
        print_warning "Some files failed to download"
        exit 1
    fi
}

# Run main function
main "$@"
