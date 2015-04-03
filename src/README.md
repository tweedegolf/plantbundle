# TweedeGolfPlantBundle
Bundle to work with the YGA plant database in different projects.

## Installation
Using [Composer][composer] add the bundle to your requirements:

 ```json
 {
     "require": {
         "tweedegolf/plantbundle": "dev-master"
     }
 }
 ```
## Configuration
Set the elastica_host and elastica_port parameters:

```
tweede_golf_plant:
    elastica_host: 127.0.0.1
    elastica_port: 9200
```

Set the following parameters for the plant database to their correct values in your parameters.yml

```
    dbtool_driver
    dbtool_host
    dbtool_name
    dbtool_user
```

## Usage

* Use the command `bin/symfony elastica:refresh` that the bundle offers to build / refresh the plant search index
* Use the PlantRetriever as repository for the plant database
* Use the PlantFinder (or extend it) to search in the plant search index

### PlantRetriever and PlantProxy
todo

### PlantFinder
todo