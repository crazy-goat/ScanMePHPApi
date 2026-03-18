#!/bin/bash
DIR="$(cd "$(dirname "$0")" && pwd)"
EXT="$DIR/scanmeqr.so"
LIB="$DIR"

if [ -f "$EXT" ]; then
    export LD_LIBRARY_PATH="$LIB:$LD_LIBRARY_PATH"
    exec php -d extension="$EXT" "$DIR/start.php" "$@"
else
    exec php "$DIR/start.php" "$@"
fi
