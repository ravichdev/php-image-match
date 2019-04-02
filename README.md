PHP Image Match
=============

PHP Image match is php ported version of [image-match](https://github.com/EdjoLabs/image-match)

Based on the paper [_An image signature for any kind of image_, Wong et
al](http://www.cs.cmu.edu/~hcwong/Pdfs/icip02.ps).

## Quick start
* `git clone git@github.com:ravich11/php-image-match.git`
* `cd php-image-match`
* `composer install`

### Storing Image
```
<?php

require './vendor/autoload.php';

use Elasticsearch\ClientBuilder;
use ImageMatch\Database\ElasticSearchDatabase;

$builder = ClientBuilder::create();
// You have a elasticsearch instance with hostname `elasticsearch` and port 9000
$builder->setHosts(['elasticsearch']);
$client = $builder->build();

$signature_db = new ElasticSearchDatabase($client);
$response = $signature_db->addImage(PATH_TO_THE_IMAGE_FILE);
```

### Searching Image
```
<?php

require './vendor/autoload.php';

use Elasticsearch\ClientBuilder;
use ImageMatch\Database\ElasticSearchDatabase;

$builder = ClientBuilder::create();
// You have a elasticsearch instance with hostname `elasticsearch` and port 9000
$builder->setHosts(['elasticsearch']);
$client = $builder->build();

$signature_db = new ElasticSearchDatabase($client);
$response = $signature_db->searchImage(PATH_TO_THE_IMAGE_FILE);
```