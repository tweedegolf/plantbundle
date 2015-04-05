# TweedeGolfPlantBundle
Bundle to work with tweede golf's YGA plant database in different projects.

## Installation
Using [Compose][composer] add the bundle by running `composer require tweedegolf/plantbundle:dev-master` or
add the bundle to your requirements and run `composer install`:

 ```json
 {
     "require": {
         "tweedegolf/plantbundle": "dev-master"
     }
 }
 ```
## Configuration
Set the bundle's elastica_host and elastica_port parameters:

```
tweede_golf_plant:
    elastica_host: 127.0.0.1
    elastica_port: 9200
```

Set the following parameters for the tweede golf plant database to their correct values in your parameters.yml

```
    tweedegolf_plant_driver
    tweedegolf_plant_host
    tweedegolf_plant_name
    tweedegolf_plant_user
    tweedegolf_plant_password
```

And add a dbal connection for Doctrine using these parameters:
```
            tweedegolf_plant:
                driver:   %tweedegolf_plant_driver%
                host:     %tweedegolf_plant_host%
                dbname:   %tweedegolf_plant_name%
                user:     %tweedegolf_plant_user%
                password: %tweedegolf_plant_password%
                charset:  UTF8
```

## Usage

* Use the command `bin/symfony elastica:refresh` that the bundle offers to build / refresh the plant search index
* Use the PlantRetriever as repository for the plant database
* Use the PlantFinder (or extend it) to search in the plant search index

### PlantRetriever and PlantProxy
todo

### PlantFinder
todo