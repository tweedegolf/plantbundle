# TweedeGolfPlantBundle
Bundle to work with the YGA plant database in different projects.


## Components and design

primary properties, derived properties, refresh command, services

`bin/symfony elastica:refresh`

search index needed with correct name (plant...)

finder only works on elastica, retriever has access to plantproxy to transform finder results
you can extend the default finder