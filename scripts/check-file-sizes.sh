#!/bin/bash
DIRECTORIES=("app/Http" "app/Livewire" "resources/views/livewire")
MAX_LINES=500
IGNORE_FILE=".monolithignore"

echo "🔍 Scanning codebase for file size limits and structural compliance..."
echo "======================================================================"

FOUND_ERRORS=0

for DIR in "${DIRECTORIES[@]}"; do
    if [ -d "$DIR" ]; then
        while read -r FILE; do
            # Skip ignored legacy files
            if [ -f "$IGNORE_FILE" ] && grep -Fxq "$FILE" "$IGNORE_FILE"; then
                continue
            fi

            # Check 1: File Size Limits
            LINE_COUNT=$(wc -l < "$FILE")
            if [ "$LINE_COUNT" -gt "$MAX_LINES" ]; then
                echo "⚠️  [SIZE ERROR] File too long: $FILE ($LINE_COUNT lines)"
                FOUND_ERRORS=$((FOUND_ERRORS + 1))
            fi

            # Check 2: Directory Structure Compliance (HTML Partials Rule)
            # If a view contains typical modal patterns but is NOT inside a 'partials' directory
            if [[ "$FILE" == *.blade.php ]]; then
                if [[ "$FILE" == *modal* || "$FILE" == *dialog* ]]; then
                    if [[ "$FILE" != */partials/* ]]; then
                        echo "⚠️  [STRUCTURE ERROR] Modal component must reside in a 'partials' directory: $FILE"
                        FOUND_ERRORS=$((FOUND_ERRORS + 1))
                    fi
                fi
            fi

        done < <(find "$DIR" -type f \( -name "*.php" -o -name "*.blade.php" \))
    fi
done

echo "======================================================================"
if [ "$FOUND_ERRORS" -eq 0 ]; then
    echo "🎉 Excellent! Codebase conforms perfectly to structural & size guidelines."
    exit 0
else
    echo "💡 Found $FOUND_ERRORS structural/size violations. Please refactor."
    exit 1
fi
