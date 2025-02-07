# slim4-starter-pack

A slim starter project to allow developing using Slim 4 easier, contains REST API, Query builder and Twig templating engine.

## Background

This project started when I need replacement for CodeIgniter 3 to a more modern development environment while retaining the familiar Model-View-Controller (MVC) structure, with an lightweight enough framework to develop for.

## Initial setup

- ```composer install```
- Copy .env.example to .env
- Make sure the ```storage``` folder is writable

## Starting application

Simply execute ```composer run serve``` to start the development server, by default the application serves on http://127.0.0.1:8080

## Included components

- Slim 4 scaffolding, including PSR-7 and PSR-11 implementation
- Default error handling and response output, including log file output using Monolog
- Database query builder built on the lightweight [pecee-pixie](https://github.com/skipperbent/pecee-pixie), inspired by CI3 & Eloquent
- Database migration and seeder using [phinx](https://phinx.org/)
- Twig templating engine included to help building frontend code, configured by default with View components
- REST API structure, including JWT token generation/validation, route handling and OpenAPI/Swagger annotation for generating documentation
- DotEnv integration, already configured for DB, Redis, Object Storage, and Application default parameters
- Upload helper, with configuration for both filesystem and object storage (AWS S3, DO Spaces, etc.)

## Note on CORS (Cross-Origin Resource Sharing) configuration

CORS is automatically enabled and included on every request to aid in local development when an application is running in localhost or with the PHP development server.
This setup makes the assumption that the webserver or reverse proxy will manage CORS, including preflight requests, when deploying to a server.

## Generating OpenAPI/Swagger files

- ```composer run generate-openapi-docs```
- File will be generated in the public folder as openapi.json and openapi.yaml
- When running the application, you could access the bundled Swagger UI by accessing http://127.0.0.1:8080/swaggerui

## Server deployment

Make sure the webserver is pointing to the public folder by default when you deploy this application, or use the virtualhost setup for NGINX running on Ubuntu Server 24.04 LTS that is provided below.

```nginx
server {
        listen 80;
        server_name     your_domain;

        add_header 'Access-Control-Allow-Origin' '*';
        add_header 'Access-Control-Allow-Methods' 'POST, GET, OPTIONS, DELETE, PUT';
        add_header 'Access-Control-Allow-Headers' 'X-Requested-With, Content-Type, Origin, Authorization, Accept, Client-Security-Token, Accept-Encoding';

        root /var/www/slim4-starter-pack/public/;
        index index.php index.html;

        location / {
                if ($request_method = 'OPTIONS') {
                        add_header 'Access-Control-Allow-Origin' '*';
                        add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS';
                        add_header 'Access-Control-Allow-Headers' 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization';

                        add_header 'Access-Control-Max-Age' 1728000;
                        add_header 'Content-Type' 'text/plain charset=UTF-8';
                        add_header 'Content-Length' 0;
                        return 204;
                }

                try_files $uri $uri/ /index.php$is_args$args;
        }

        location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        }

        error_page 404 /index.php;
}
```