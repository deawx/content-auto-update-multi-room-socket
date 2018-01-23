content-auto-update-multi-room-socket
=====================================

Introduction
------------

A multi user multi room web socket server.

Requirements
------------

This library package requires PHP 5.4 or later.

Installation
------------

### Installing via Composer

The recommended way to install content-auto-update-multi-room-socket is through
[Composer](http://getcomposer.org).

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
```

Next, run the Composer command to install the latest version of content-auto-update-multi-room-socket:

```bash
composer.phar require divshopx/content-auto-update-multi-room-socket
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

Usage
-----

An example is provided in the example/ directory. Start the server with the command:

    php example/server.php

An example HTML client interface is located at example/client.html. You will need to update the notificationUrl variable in 
example/notification.js with the host name (or ip address) of the server you ran the previous command on.
 
    var notificationUrl = 'ws://your-host-name:9911';

Version History
---------------

0.1.3 (20/01/2018)

*   Additional params added.

0.1.2 (16/01/2018)

*   Origin check added.

0.1.1 (04/01/2018)

*   connectedNotification method added as a hook.

0.1.0 (04/01/2018)

*   First public release of content-auto-update-multi-room-socket


Copyright
---------

content-auto-update-multi-room-socket
Copyright (c) 2018 divshopx (support@ds360.biz) 
All rights reserved.
