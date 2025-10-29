#!/bin/bash

# API Test Script for OMS Launch Platform
# Usage: ./test_api.sh [base_url]

BASE_URL="${1:-http://localhost:8000}"

echo "OMS Launch Platform - API Test"
echo "==============================="
echo "Base URL: $BASE_URL"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Upload raw HTML content
echo "Test 1: Upload Raw HTML Content"
echo "--------------------------------"

HTML_CONTENT='<html><body><h1>Test Content</h1><input type="text" name="test"><button onclick="window.RecordTest(100)">Submit</button></body></html>'

RESPONSE=$(curl -s -X POST "$BASE_URL/api/upload_content.php" \
  -d "account_id=1" \
  -d "title=Test HTML Content" \
  -d "description=Test content for API testing" \
  -d "upload_type=raw_html" \
  -d "html_content=$HTML_CONTENT")

if echo "$RESPONSE" | grep -q "\"success\":true"; then
    echo -e "${GREEN}✓ PASS${NC}"
    CONTENT_ID=$(echo "$RESPONSE" | grep -o '"content_id":[0-9]*' | grep -o '[0-9]*')
    echo "Content ID: $CONTENT_ID"
else
    echo -e "${RED}✗ FAIL${NC}"
    echo "Response: $RESPONSE"
fi
echo ""

# Test 2: Create launch link
echo "Test 2: Create Launch Link"
echo "---------------------------"

if [ -n "$CONTENT_ID" ]; then
    RESPONSE=$(curl -s -X POST "$BASE_URL/api/create_launch_link.php" \
      -H "Content-Type: application/json" \
      -d "{\"recipient_id\": 1, \"content_id\": $CONTENT_ID}")

    if echo "$RESPONSE" | grep -q "\"success\":true"; then
        echo -e "${GREEN}✓ PASS${NC}"
        LAUNCH_URL=$(echo "$RESPONSE" | grep -o '"launch_url":"[^"]*"' | cut -d'"' -f4)
        TRACKING_ID=$(echo "$RESPONSE" | grep -o '"unique_link_id":"[^"]*"' | cut -d'"' -f4)
        echo "Launch URL: $LAUNCH_URL"
        echo "Tracking ID: $TRACKING_ID"
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo "Response: $RESPONSE"
    fi
else
    echo -e "${YELLOW}⊘ SKIP (no content ID)${NC}"
fi
echo ""

# Test 3: Track view
echo "Test 3: Track View"
echo "------------------"

if [ -n "$TRACKING_ID" ]; then
    RESPONSE=$(curl -s -X POST "$BASE_URL/api/track_view.php" \
      -H "Content-Type: application/json" \
      -d "{\"tracking_link_id\": \"$TRACKING_ID\"}")

    if echo "$RESPONSE" | grep -q "\"success\":true"; then
        echo -e "${GREEN}✓ PASS${NC}"
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo "Response: $RESPONSE"
    fi
else
    echo -e "${YELLOW}⊘ SKIP (no tracking ID)${NC}"
fi
echo ""

# Test 4: Track completion
echo "Test 4: Track Completion"
echo "------------------------"

if [ -n "$TRACKING_ID" ]; then
    RESPONSE=$(curl -s -X POST "$BASE_URL/api/track_completion.php" \
      -H "Content-Type: application/json" \
      -d "{\"tracking_link_id\": \"$TRACKING_ID\", \"score\": 85, \"interactions\": [{\"tag\": \"test_tag\", \"timestamp\": \"2024-01-01T12:00:00Z\"}]}")

    if echo "$RESPONSE" | grep -q "\"success\":true"; then
        echo -e "${GREEN}✓ PASS${NC}"
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo "Response: $RESPONSE"
    fi
else
    echo -e "${YELLOW}⊘ SKIP (no tracking ID)${NC}"
fi
echo ""

echo "==============================="
echo "API Tests Complete"
echo ""

if [ -n "$LAUNCH_URL" ]; then
    echo "You can now visit the launch URL to see the content:"
    echo "$LAUNCH_URL"
fi
