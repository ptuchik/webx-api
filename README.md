Client library for WebX Next API.

To use it, you need to fill your username, API key and remote server URL in the `api.php` file and require it in your script... 

## Usage
##### Get IMEI Services
```
<?php

require_once __DIR__.'/api.php';

$services = (new \WebX\Api())->getImeiServices();
```
##### Get Server Services
```
<?php

require_once __DIR__.'/api.php';

$services = (new \WebX\Api())->getServerServices();
```
##### Get File Services
```
<?php

require_once __DIR__.'/api.php';

$services = (new \WebX\Api())->getFileServices();
```
##### Place IMEI Order
```
<?php

require_once __DIR__.'/api.php';

$order = (new \WebX\ImeiOrder())->setServiceId(1)->setDevice('353272079261960')->send());
```
##### Place Server Order
```
<?php

require_once __DIR__.'/api.php';

$order = (new \WebX\ServerOrder())->setServiceId(1)->setQuantity(2)->send());
```
##### Place File Order
```
<?php

require_once __DIR__.'/api.php';

$order = (new \WebX\FileOrder())->setServiceId(1)->setDevice('filename.bcl')->send());
```
##### Get IMEI Order
```
<?php

require_once __DIR__.'/api.php';

$order = (new \WebX\ImeiOrder())->setId(1)->get();
```
##### Get Server Order
```
<?php

require_once __DIR__.'/api.php';

$order = (new \WebX\ServerOrder())->setId(1)->get();
```
##### Get File Order
```
<?php

require_once __DIR__.'/api.php';

$order = (new \WebX\FileOrder())->setId(1)->get();
```

Do not hesitate to contact us at &#104;&#101;&#108;&#108;&#111;&#64;&#119;&#101;&#98;&#120;&#46;&#111;&#110;&#101; in case of any issue or question. 

ⓒ WebX Team. All rights reserved.
