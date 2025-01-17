# Installation

## Prerequesites
resto installation and deployment is based on docker-compose. It can run on any OS as long as the following software are up and running:

* bash
* Docker engine (i.e. docker)
* Docker compose (i.e. docker-compose)
* PostgreSQL client (i.e. psql)

## Configuration
All configuration options are defined within the [config.env](https://github.com/jjrom/resto/blob/master/config.env) file.

For a local installation, you can leave it untouched. Otherwise, just make your own configuration. It's self explanatory (send me an email if not ;)

Note that each time you change the configuration file, you should undeploy then redeploy the service.

## External Database
resto can use an external PostgreSQL database (version 11+).

The following extensions must be installed on the target database:
 * postgis
 * postgis_topology
 * unaccent
 * uuid-ossp
 * pg_trgm

For instance suppose that the external database is "resto" :

        DATABASE_NAME=resto

        PGPASSWORD=${DATABASE_SUPERUSER_PASSWORD} createdb -X -v ON_ERROR_STOP=1 -h "${DATABASE_HOST}" -p "${DATABASE_PORT}" -U "${DATABASE_SUPERUSER_NAME}" ${DATABASE_NAME}

        PGPASSWORD=${DATABASE_SUPERUSER_PASSWORD} psql -X -v ON_ERROR_STOP=1 -h "${DATABASE_HOST}" -p "${DATABASE_PORT}" -U "${DATABASE_SUPERUSER_NAME}" -d "${DATABASE_NAME}" -f ./build/resto-database/sql/00_resto_extensions.sql

Where DATABASE_SUPERUSER_NAME is a database user with sufficient privileges to install extensions ("postgres" user for instance)

A normal PG user with `create schema` and `insert on spatial_ref_sys` rights is necessary in order for resto to operate. To give a user the suitable rights, run the following sql commands:

        grant create on database <dbname> to <dbuser>;
        grant insert on table spatial_ref_sys to <dbuser>;

By default, resto tables, functions and triggers will be installed in a `resto` schema by running [scripts/installOnExternalDB.sh](https://github.com/jjrom/resto/blob/master/scripts/installOnExternalDB.sh). The default schema can be changed by setting the DATABASE_SCHEMA environment variable in config.env

        ./scripts/installOnExternalDB.sh -e <config file>
        
Note: The `insert on spatial_ref_sys` right can be revoked once the database is installed (first deploy) by running:
    
    revoke insert on table spatial_ref_sys from <dbuser>; 

## Hardware
**[IMPORTANT]** In production mode (see below), the default configuration of the PostgreSQL server is for a 64Go RAM server. Changes this in [configuration](https://github.com/jjrom/resto/blob/master/config.env) file accordingly to your real configuration

## Building and deploying
After reviewing your [configuration](https://github.com/jjrom/resto/blob/master/config.env) file, run one of following command:

(for production)

        ./deploy

(for development)

        ./deploy -e config-dev.env

### Docker volumes
The following permanent docker volumes are created on first deployment:

* **resto_database_data** - contains resto database (i.e. PostgreSQL PGDATA directory)
* **resto_static_content** - contains static files uploaded to the server (e.g. user's avatar pictures)

### Docker network
The docker network **rnet** is created on first deployment. This network is shared by the following images

* jjrom/resto
* jjrom/resto-database
* jjrom/itag (see [iTag github repository](https://github.com/jjrom/itag))

## Production vs development
The development environment differs from the production environment by the following aspects:

* The source code under /app directory is mount within the container
* The Xdebug extension is enabled
* PHP opcache is disabled
* All SQL requests are logged
* The default postgres configuration is set for a small configuration (i.e. 4Go RAM)

# FAQ

## Test the service
Resolve the endpoint provided by the deploy script

        curl http://localhost:5252

If evertyhing runs fine, it should display the **STAC root endpoint content** of the service

## How to undeploy the service ?
Assuming that the application name is "resto" (see deploy "-p" option)

        ./undeploy

## How to check the logs of the running application ?
Assuming that the application name is "resto" (see deploy "-p" option)

        docker-compose -f docker-compose.yml -f docker-compose-restodb.yml logs -f

## How to build the docker images locally
Use docker-compose:

        # This will build the application server image (i.e. jjrom/resto)
        docker-compose -f docker-compose.yml build

        # This will build the database server image (i.e. jjrom/resto-database)
        docker-compose -f docker-compose-restodb.yml build
