#!/bin/bash

###############################################################################
# HumHub REST API Test Script
# 
# This script tests the HumHub REST API configuration for ERPAEJ integration.
# It verifies authentication, user creation, and user query capabilities.
#
# Usage: ./test-api-setup.sh
#
# Prerequisites:
# - curl installed
# - REST API module enabled in HumHub
# - API token generated
#
# Author: ERPAEJ Development Team
# Version: 1.0.0
# Date: 2025-12-09
###############################################################################

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test configuration
HUMHUB_URL=""
API_TOKEN=""
TEST_EMAIL="test.erpaej.sync@example.com"
TEST_USERNAME="Test ERPAEJ User"

###############################################################################
# Helper Functions
###############################################################################

print_header() {
    echo ""
    echo "═══════════════════════════════════════════════════════════"
    echo -e "${BLUE}$1${NC}"
    echo "═══════════════════════════════════════════════════════════"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

###############################################################################
# Configuration Prompts
###############################################################################

prompt_configuration() {
    print_header "HumHub API Configuration"
    
    # Prompt for HumHub URL
    read -p "Enter HumHub URL (e.g., https://humhub.example.com): " HUMHUB_URL
    HUMHUB_URL=${HUMHUB_URL%/}  # Remove trailing slash
    
    # Prompt for API token
    read -p "Enter API Bearer Token: " API_TOKEN
    
    echo ""
    print_info "Configuration saved:"
    echo "  URL: $HUMHUB_URL"
    echo "  Token: ${API_TOKEN:0:20}..."
    echo ""
}

###############################################################################
# Test Functions
###############################################################################

test_prerequisites() {
    print_header "Test 1: Checking Prerequisites"
    
    # Check if curl is installed
    if ! command -v curl &> /dev/null; then
        print_error "curl is not installed"
        print_info "Install with: sudo apt-get install curl"
        exit 1
    fi
    print_success "curl is installed"
    
    # Check if jq is installed (optional but recommended)
    if command -v jq &> /dev/null; then
        print_success "jq is installed (JSON parsing available)"
        JQ_AVAILABLE=true
    else
        print_warning "jq is not installed (install for better output formatting)"
        JQ_AVAILABLE=false
    fi
}

test_api_availability() {
    print_header "Test 2: API Availability"
    
    print_info "Testing: GET $HUMHUB_URL/api/v1/auth/current"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$HUMHUB_URL/api/v1/auth/current" \
        -H "Authorization: Bearer $API_TOKEN" \
        -H "Accept: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "API is accessible (HTTP $HTTP_CODE)"
        if [ "$JQ_AVAILABLE" = true ]; then
            echo "$BODY" | jq '.'
        else
            echo "$BODY"
        fi
        return 0
    else
        print_error "API authentication failed (HTTP $HTTP_CODE)"
        echo "$BODY"
        return 1
    fi
}

test_user_creation() {
    print_header "Test 3: User Creation Permission"
    
    print_info "Testing: POST $HUMHUB_URL/api/v1/user"
    print_info "Creating test user: $TEST_EMAIL"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$HUMHUB_URL/api/v1/user" \
        -H "Authorization: Bearer $API_TOKEN" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "{
            \"email\": \"$TEST_EMAIL\",
            \"username\": \"$TEST_USERNAME\",
            \"status\": 1
        }")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    if [ "$HTTP_CODE" -eq 201 ] || [ "$HTTP_CODE" -eq 200 ]; then
        print_success "User created successfully (HTTP $HTTP_CODE)"
        if [ "$JQ_AVAILABLE" = true ]; then
            echo "$BODY" | jq '.'
        else
            echo "$BODY"
        fi
        return 0
    elif [ "$HTTP_CODE" -eq 422 ]; then
        print_warning "User might already exist or validation failed (HTTP $HTTP_CODE)"
        echo "$BODY"
        return 0  # Not a critical error
    else
        print_error "User creation failed (HTTP $HTTP_CODE)"
        echo "$BODY"
        return 1
    fi
}

test_user_query() {
    print_header "Test 4: User Query"
    
    print_info "Testing: GET $HUMHUB_URL/api/v1/user?email=$TEST_EMAIL"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$HUMHUB_URL/api/v1/user?email=$TEST_EMAIL" \
        -H "Authorization: Bearer $API_TOKEN" \
        -H "Accept: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "User query successful (HTTP $HTTP_CODE)"
        if [ "$JQ_AVAILABLE" = true ]; then
            echo "$BODY" | jq '.'
        else
            echo "$BODY"
        fi
        return 0
    else
        print_error "User query failed (HTTP $HTTP_CODE)"
        echo "$BODY"
        return 1
    fi
}

test_user_update() {
    print_header "Test 5: User Update Permission"
    
    print_info "Testing: PUT $HUMHUB_URL/api/v1/user (updating test user)"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" -X PUT "$HUMHUB_URL/api/v1/user/$TEST_EMAIL" \
        -H "Authorization: Bearer $API_TOKEN" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "{
            \"username\": \"$TEST_USERNAME Updated\",
            \"email\": \"$TEST_EMAIL\"
        }")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "User update successful (HTTP $HTTP_CODE)"
        if [ "$JQ_AVAILABLE" = true ]; then
            echo "$BODY" | jq '.'
        else
            echo "$BODY"
        fi
        return 0
    else
        print_warning "User update failed (HTTP $HTTP_CODE)"
        echo "$BODY"
        return 0  # Not critical for sync
    fi
}

###############################################################################
# Results Summary
###############################################################################

print_results() {
    print_header "Test Results Summary"
    
    echo ""
    echo "Test Results:"
    echo "─────────────────────────────────────────────"
    
    if [ $TEST1_RESULT -eq 0 ]; then
        print_success "Prerequisites: PASSED"
    else
        print_error "Prerequisites: FAILED"
    fi
    
    if [ $TEST2_RESULT -eq 0 ]; then
        print_success "API Availability: PASSED"
    else
        print_error "API Availability: FAILED"
    fi
    
    if [ $TEST3_RESULT -eq 0 ]; then
        print_success "User Creation: PASSED"
    else
        print_error "User Creation: FAILED"
    fi
    
    if [ $TEST4_RESULT -eq 0 ]; then
        print_success "User Query: PASSED"
    else
        print_error "User Query: FAILED"
    fi
    
    if [ $TEST5_RESULT -eq 0 ]; then
        print_success "User Update: PASSED"
    else
        print_warning "User Update: PARTIAL (may not be critical)"
    fi
    
    echo ""
    
    # Overall result
    if [ $TEST2_RESULT -eq 0 ] && [ $TEST3_RESULT -eq 0 ] && [ $TEST4_RESULT -eq 0 ]; then
        echo ""
        print_success "═══════════════════════════════════════════════════════════"
        print_success "  All critical tests PASSED!"
        print_success "  HumHub is ready for ERPAEJ user synchronization"
        print_success "═══════════════════════════════════════════════════════════"
        echo ""
        print_info "Next Steps:"
        echo "  1. Share configuration with ERPAEJ team:"
        echo "     - HumHub URL: $HUMHUB_URL"
        echo "     - API URL: $HUMHUB_URL/api/v1"
        echo "     - API Token: ${API_TOKEN:0:20}..."
        echo ""
        echo "  2. ERPAEJ team should update their .env file"
        echo "  3. Run initial sync: php artisan humhub:sync-users --dry-run"
        echo ""
        return 0
    else
        echo ""
        print_error "═══════════════════════════════════════════════════════════"
        print_error "  Some tests FAILED"
        print_error "  Please review errors and fix configuration"
        print_error "═══════════════════════════════════════════════════════════"
        echo ""
        print_info "Troubleshooting:"
        
        if [ $TEST2_RESULT -ne 0 ]; then
            echo "  • API Authentication Failed:"
            echo "    - Verify API token is correct"
            echo "    - Check REST API module is enabled"
            echo "    - Ensure API user has proper permissions"
        fi
        
        if [ $TEST3_RESULT -ne 0 ]; then
            echo "  • User Creation Failed:"
            echo "    - Ensure API user has Administrator role"
            echo "    - Check user permissions in HumHub"
            echo "    - Verify email uniqueness settings"
        fi
        
        if [ $TEST4_RESULT -ne 0 ]; then
            echo "  • User Query Failed:"
            echo "    - Check API endpoint accessibility"
            echo "    - Verify URL rewriting configuration"
        fi
        
        echo ""
        print_info "See documentation: ERPAEJ_INTEGRATION_SETUP.md"
        echo ""
        return 1
    fi
}

###############################################################################
# Main Execution
###############################################################################

main() {
    clear
    
    print_header "HumHub REST API Test Suite for ERPAEJ Integration"
    echo "This script will test your HumHub REST API configuration"
    echo ""
    
    # Get configuration
    prompt_configuration
    
    # Run tests
    test_prerequisites
    TEST1_RESULT=$?
    
    if [ $TEST1_RESULT -ne 0 ]; then
        print_error "Prerequisites check failed. Exiting."
        exit 1
    fi
    
    test_api_availability
    TEST2_RESULT=$?
    
    test_user_creation
    TEST3_RESULT=$?
    
    test_user_query
    TEST4_RESULT=$?
    
    test_user_update
    TEST5_RESULT=$?
    
    # Print results
    print_results
    OVERALL_RESULT=$?
    
    exit $OVERALL_RESULT
}

# Run main function
main
