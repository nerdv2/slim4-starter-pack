# slim4-starter-pack

A slim starter project to allow developing using Slim 4 easier, contains REST API, Query builder and Twig templating engine.

## Background

This project started when I need replacement for CodeIgniter 3 to a more modern development environment while retaining the familiar Model-View-Controller (MVC) structure, an lightweight enough framework to develop for.

## Initial setup

- ```composer install```
- Copy .env.example to .env

## Starting application

Simply execute ```composer run serve``` to start the development server.

## Generating OpenAPI/Swagger files

- ```composer run generate-openapi-docs```
- File will be generated in the public folder as openapi.json and openapi.yaml