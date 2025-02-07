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

## Generating OpenAPI/Swagger files

- ```composer run generate-openapi-docs```
- File will be generated in the public folder as openapi.json and openapi.yaml
- When running the application, you could access the bundled Swagger UI by accessing http://127.0.0.1:8080/swaggerui