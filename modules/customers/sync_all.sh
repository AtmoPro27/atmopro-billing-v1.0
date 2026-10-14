#!/bin/bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_PATH="$(dirname "$(dirname "$SCRIPT_DIR")")"

echo "Mulai sinkronisasi semua pelanggan..."
echo "Base path: $BASE_PATH"

for i in {1..10}; do
    echo "Batch $i..."
    php "$BASE_PATH/modules/customers/sync_to_mikrotik.php"
    sleep 2
done

echo "Selesai semua batch!"
