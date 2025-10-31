#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# Initialize PID variable
EMULATOR_PID=""

# Define a cleanup function that will be called on script exit
cleanup() {
  if [ -n "$EMULATOR_PID" ]; then
    echo "Tests finished. Killing emulator (PID $EMULATOR_PID)..."
    # Check if process exists before killing
    if kill -0 $EMULATOR_PID 2>/dev/null; then
      kill $EMULATOR_PID
      wait $EMULATOR_PID 2>/dev/null
    else
      echo "Emulator process (PID $EMULATOR_PID) already stopped."
    fi
  fi
}

# Set the trap: call 'cleanup' on EXIT (normal exit, error, or signal)
trap cleanup EXIT

echo "Starting Datastore emulator..."
gcloud beta emulators datastore start \
  --project=test-project \
  --host-port=localhost:8081 \
  --no-store-on-disk 2> /dev/null &

# Store the Process ID (PID) of the emulator
EMULATOR_PID=$!

echo "Emulator started with PID $EMULATOR_PID. Waiting up to 10s for it to boot..."

ATTEMPTS=0
MAX_ATTEMPTS=20 # 20 * 0.5s = 10 seconds

# Wait for the emulator to be ready by polling its endpoint
until $(curl --output /dev/null --silent --head http://localhost:8081); do
    if [ ${ATTEMPTS} -eq ${MAX_ATTEMPTS} ]; then
        echo "Emulator failed to start after 10 seconds."
        exit 1 # This will trigger the 'trap cleanup' and exit
    fi
    ATTEMPTS=$((ATTEMPTS+1))
    sleep 0.5
done

echo "Emulator is up and running!"

echo "Running PHPUnit tests..."

# Run PHPUnit.
# If it fails, 'set -e' will cause the script to exit, which triggers the 'trap'.
# The exit code of phpunit will be the exit code of the script.
vendor/bin/phpunit --configuration phpunit.xml