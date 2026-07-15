#!/bin/bash

# Define directories to scan
DIRECTORIES=("app/Http" "app/Livewire" "resources/views/livewire")
MAX_LINES=500

echo "🔍 Scanning codebase for monolithic files exceeding $MAX_LINES lines..."
echo "======================================================================"

FOUND_MONOLITHS=0

for DIR in "${DIRECTORIES[@]}"; do
    if [ -d "$DIR" ]; then
        # Find PHP and Blade files and check their line count
        while read -r FILE; do
            LINE_COUNT=$(wc -l < "$FILE")
            if [ "$LINE_COUNT" -gt "$MAX_LINES" ]; then
                echo "⚠️  Bloated File Found: $FILE ($LINE_COUNT lines)"
                FOUND_MONOLITHS=$((FOUND_MONOLITHS + 1))
            fi
        done < <(find "$DIR" -type f \( -name "*.php" -o -name "*.blade.php" \))
    fi
done

echo "======================================================================"
if [ "$FOUND_MONOLITHS" -eq 0 ]; then
    echo "🎉 Excellent! No monolithic files exceeding $MAX_LINES lines were found."
else
    echo "💡 Found $FOUND_MONOLITHS file(s) to refactor using Blade Partials or Service Classes."
fi
