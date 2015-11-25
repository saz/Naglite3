Naglite3
========

Nagios/Icinga status monitor for a NOC or operations room.

Inspired by Naglite (http://www.monitoringexchange.org/inventory/Utilities/AddOn-Projects/Frontends/NagLite) 
and Naglite2 (http://laur.ie/blog/2010/03/naglite2-finally-released/).

Written by Steffen Zieger <me@saz.sh>.
Licensed under the GPL.

In case of any problems or bug fixes, feel free to contact me.

Requirements
------------

Naglite3 is only tested with Nagios3, but it should also work with Nagios2.
If you're running Nagios2, please let me know.

[nkadel](https://github.com/nkadel) has reported, that it's also working with Icinga.

- Web server of your choice with PHP support
- PHP 5.2 or newer
- git

Naglite3 must be installed on the same host where Nagios is running, as it
needs to read status.dat from Nagios.

Installation
------------

1. Switch to a directory accessible through your web server (e.g. /var/www/).
2. git clone git://github.com/saz/Naglite3.git
3. Copy config.php.example to config.php if you need to change a setting.
4. Open a browser and point it to your Naglite3 installation.

Customization
-------------

For all possible config options have a look at config.php.example

### CSS

If you want to change colors, create a file called 'custom.css' in the
directory where Naglite3 is placed and add your changes.

If you want to use a per site css, just pass the GET-parameter "css" pointing to a local file.
e.g. http://your-host/Naglite3/?css=my_custom_css

### Refresh interval

You can change the refresh interval (in seconds) through a GET parameter, too.

Example:
http://your-host/Naglite3/?refresh=100
