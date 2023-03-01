#!/bin/sh
echo "Packing build ..."

# Prepare empty node_modules to prevent errors with copying
mkdir -p /usr/local/build/node_modules

# Store entrypoint into build. Will be used during start process
touch /usr/local/build/.open-runtimes
echo "OPEN_RUNTIMES_ENTRYPOINT=$OPEN_RUNTIMES_ENTRYPOINT" > /usr/local/build/.open-runtimes

# Finish build by preparing tar to use for starting the runtime
tar -C /usr/local/build --exclude code.tar.gz -zcf /mnt/code/code.tar.gz .

echo "Build finished."