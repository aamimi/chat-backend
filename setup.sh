#!/bin/bash

# Check if src directory exists with Laravel files
if [ ! -d "src" ] || [ ! -f "src/artisan" ]; then
    echo "Laravel project not found. Creating a new Laravel project..."

    # Create a new Laravel project using Composer
    docker run --rm -v $(pwd):/app -w /app composer:latest composer create-project --prefer-dist laravel/laravel src

    echo "New Laravel project created!"
else
    echo "Found existing Laravel project in src directory"
fi

# change ownership of src directory to the current user
sudo chown -R $(whoami):$(whoami) src
# Set proper permissions
chmod -R 775 src/storage src/bootstrap/cache

echo "Configuring Laravel environment..."

# Set up .env file for Laravel
if [ -f "src/.env" ]; then
    # Update database connection details in .env
    sed -i "s/DB_HOST=.*/DB_HOST=mysql/" src/.env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=laravel/" src/.env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=laravel/" src/.env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=secret/" src/.env

    # Set Redis connection if present
    sed -i "s/REDIS_HOST=.*/REDIS_HOST=redis/" src/.env
    sed -i "s/REDIS_PASSWORD=.*/REDIS_PASSWORD=secret/" src/.env
    sed -i "s/CACHE_DRIVER=.*/CACHE_DRIVER=redis/" src/.env
    sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=redis/" src/.env
    sed -i "s/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/" src/.env
fi

echo "Setup complete! You can now run 'docker-compose up -d'"