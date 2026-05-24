#!/bin/bash

# Santa North Pole Security Configuration Script
# Configures Santa for use with Zentral sync server

set -euo pipefail

# Configuration variables
SYNC_BASE_URL="https://santa.starnix.net/public/santa/sync/5lbYEzdA3TgljTrWmb5nbCbdUc6LTB5GZRaJNPhmsSCHQlb74b4gtaUaasCOu3Ti/"
CLIENT_MODE=2
MACHINE_OWNER="cloud"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

# Check if Santa is installed
check_santa_installed() {
    if [[ ! -f "/Applications/Santa.app/Contents/MacOS/santactl" ]]; then
        log_error "Santa application not found. Please install Santa first."
        exit 1
    fi
    
    if [[ ! -L "/usr/local/bin/santactl" ]]; then
        log_error "santactl symlink not found. Santa may not be properly installed."
        exit 1
    fi
    
    log_info "Santa installation detected"
}

# Create managed preferences configuration
create_managed_preferences_config() {
    local config_dir="/Library/Managed Preferences"
    local config_file="${config_dir}/com.northpolesec.santa.plist"
    
    log_info "Creating managed preferences configuration..."
    
    # Ensure directory exists
    mkdir -p "${config_dir}"
    
    # Create the configuration file
    cat > "${config_file}" << EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>ClientMode</key>
    <integer>${CLIENT_MODE}</integer>
    <key>SyncBaseURL</key>
    <string>${SYNC_BASE_URL}</string>
    <key>EnableBundles</key>
    <true/>
    <key>EnableTransitiveRules</key>
    <false/>
    <key>MachineOwner</key>
    <string>${MACHINE_OWNER}</string>
</dict>
</plist>
EOF
    
    # Set proper permissions and make immutable
    chown root:wheel "${config_file}"
    chmod 644 "${config_file}"
    
    # Try to make the file more persistent (may not work on all systems)
    chflags schg "${config_file}" 2>/dev/null || log_warn "Could not set immutable flag on config file"
    
    log_info "Managed preferences configuration created: ${config_file}"
}

# Create traditional configuration (backup)
create_traditional_config() {
    local config_dir="/var/db/santa"
    local config_file="${config_dir}/config.plist"
    
    log_info "Creating traditional configuration (backup)..."
    
    # Ensure directory exists
    mkdir -p "${config_dir}"
    
    # Copy from managed preferences
    cp "/Library/Managed Preferences/com.northpolesec.santa.plist" "${config_file}"
    
    # Set proper permissions
    chown root:wheel "${config_file}"
    chmod 644 "${config_file}"
    
    log_info "Traditional configuration created: ${config_file}"
}

# Check and start Santa services
manage_santa_services() {
    log_info "Managing Santa services..."
    
    local services=(
        "com.northpolesec.santa.bundleservice"
        "com.northpolesec.santa.metricservice"
        "com.northpolesec.santa.syncservice"
    )
    
    for service in "${services[@]}"; do
        log_info "Checking service: ${service}"
        
        # Check if service exists
        if launchctl list | grep -q "${service}"; then
            log_info "Service ${service} is loaded"
            # Restart the service to pick up new configuration
            launchctl kickstart -k "system/${service}" 2>/dev/null || true
        else
            log_warn "Service ${service} not found, attempting to load..."
            # Try to load the service
            if [[ -f "/Library/LaunchDaemons/${service}.plist" ]]; then
                launchctl load "/Library/LaunchDaemons/${service}.plist" 2>/dev/null || true
                launchctl start "${service}" 2>/dev/null || true
            fi
        fi
    done
    
    # Force daemon to reload configuration
    log_info "Reloading Santa daemon configuration..."
    launchctl kill SIGHUP system/ZMCG7MLDV9.com.northpolesec.santa.daemon 2>/dev/null || true
}

# Verify configuration
verify_configuration() {
    log_info "Verifying Santa configuration..."
    
    # Wait a moment for services to restart
    sleep 3
    
    # Check if configuration is readable
    if defaults read "/Library/Managed Preferences/com.northpolesec.santa" >/dev/null 2>&1; then
        log_info "Managed preferences configuration is readable"
    else
        log_warn "Managed preferences configuration may not be readable"
    fi
    
    # Check Santa status
    if /usr/local/bin/santactl status | grep -q "Sync.*Enabled.*Yes"; then
        log_info "Santa sync is enabled"
    else
        log_warn "Santa sync may not be enabled yet"
    fi
    
    # Show current status
    echo ""
    log_info "Current Santa status:"
    /usr/local/bin/santactl status || true
}

# Attempt initial sync
attempt_initial_sync() {
    log_info "Attempting initial sync..."
    
    # Wait a bit longer for services to fully start
    sleep 5
    
    # Try sync as root first, then as regular user
    if sudo -u "$(logname)" /usr/local/bin/santactl sync 2>/dev/null; then
        log_info "Initial sync successful"
    else
        log_warn "Initial sync failed, but this may resolve automatically"
        log_info "Santa will attempt to sync automatically. Check status with: santactl status"
    fi
}

# Create startup script for reboot persistence
create_startup_script() {
    log_info "Creating startup script for reboot persistence..."
    
    local startup_script="/usr/local/bin/santa-config-startup"
    
    cat > "${startup_script}" << 'EOF'
#!/bin/bash

# Santa configuration startup script
# Ensures Santa configuration persists after reboot

CONFIG_FILE="/Library/Managed Preferences/com.northpolesec.santa.plist"
BACKUP_CONFIG="/var/db/santa/config.plist"

# Check if config file exists, recreate if missing
if [[ ! -f "${CONFIG_FILE}" ]] && [[ -f "${BACKUP_CONFIG}" ]]; then
    echo "$(date): Restoring Santa configuration after reboot"
    mkdir -p "$(dirname "${CONFIG_FILE}")"
    cp "${BACKUP_CONFIG}" "${CONFIG_FILE}"
    chown root:wheel "${CONFIG_FILE}"
    chmod 644 "${CONFIG_FILE}"
    
    # Restart Santa services
    launchctl kickstart -k system/com.northpolesec.santa.syncservice 2>/dev/null || true
    launchctl kill SIGHUP system/ZMCG7MLDV9.com.northpolesec.santa.daemon 2>/dev/null || true
fi
EOF

    chmod +x "${startup_script}"
    chown root:wheel "${startup_script}"
    
    # Create LaunchDaemon for startup script
    local launch_daemon="/Library/LaunchDaemons/com.starnix.santa.config.plist"
    
    cat > "${launch_daemon}" << EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.starnix.santa.config</string>
    <key>ProgramArguments</key>
    <array>
        <string>${startup_script}</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <false/>
</dict>
</plist>
EOF

    chown root:wheel "${launch_daemon}"
    chmod 644 "${launch_daemon}"
    
    # Load the launch daemon
    launchctl load "${launch_daemon}" 2>/dev/null || true
    
    log_info "Startup script created and loaded"
}

# Main function
main() {
    log_info "Starting Santa North Pole Security configuration..."
    
    check_root
    check_santa_installed
    create_managed_preferences_config
    create_traditional_config
    create_startup_script
    manage_santa_services
    verify_configuration
    attempt_initial_sync
    
    echo ""
    log_info "Santa configuration completed!"
    log_info "Check sync status with: santactl status"
    log_info "Manually trigger sync with: santactl sync"
    log_info "Run diagnostics with: sudo santactl doctor"
    
    echo ""
    log_info "Configuration persistence:"
    log_info "- Startup script installed to restore config after reboot"
    log_info "- Config backed up to /var/db/santa/config.plist"
    log_info "- If sync fails after reboot, run this script again"
    
    echo ""
    log_info "Santa should now be configured to sync with: ${SYNC_BASE_URL}"
}

# Run main function
main "$@"
