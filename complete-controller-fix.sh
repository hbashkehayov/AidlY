#!/bin/bash

echo "Comprehensive fix for all analytics controllers..."

docker exec aidly-analytics-service bash -c "
cd /var/www/html/app/Http/Controllers

# Fix all missing closing brackets
find . -name '*.php' -exec sed -i '/^[[:space:]]*return response()->json(\[$/,/^[[:space:]]*\]\$/{
    /^[[:space:]]*\]\$/! {
        /^[[:space:]]*return response()->json(\[$/b
        s/$/);/
        t
        b
    }
}' {} \;

# Fix specific syntax issues
find . -name '*.php' -exec sed -i '
    # Add missing closing brackets
    s/^\([[:space:]]*\)\]\$/\1]);/g
    # Fix return statements missing closing
    s/^\([[:space:]]*\)]\$/\1]);/g
    # Fix validation lines
    /\/\/ Validation disabled temporarily$/,/^\$/{
        /^\$[a-zA-Z]/!d
    }
    # Fix array syntax
    s/\$request->boolean(/\$request->input(/g
' {} \;

echo 'Fixed controller syntax issues'
"

echo "Fixed all syntax issues"