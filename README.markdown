Naglite3
========

Nagios status monitor for a NOC or operations room.

Inspired by Naglite (http://www.monitoringexchange.org/inventory/Utilities/AddOn-Projects/Frontends/NagLite) 
and Naglite2 (http://laur.ie/blog/2010/03/naglite2-finally-released/).

Written by Steffen Zieger <me@saz.sh>.
Licensed under the GPL.

In case of any problems or bug fixes, feel free to contact me.

Requirements
------------

Naglite3 is only tested with Nagios3, but it should also work with Nagios2.
If you're running Nagios2, please let me know.

- Web server of your choice with PHP support
- PHP 5.2 or newer

Naglite3 must be installed on the same host where Nagios is running, as it
needs to read status.dat from Nagios.

Installation
------------

1. Place all files in the document root of your web server or a directory accessible through your web server.
2. On Debian systems everything should be fine. On other systems you may have to change the path to status.dat.
3. Open a browser and point it to your Naglite3 installation.

Customization
-------------

### CSS

If you want to change colors, create file called 'custom.css' in the
directory where Naglite3 is placed and add your changes.

### Refresh interval

You can change the refresh interval (in seconds) through a GET parameter.

Example:
http://your-host/Naglite3/?refresh=100

### Fortune

To make systems monitoring more fun than it already is, I've added fortune output.
You can enable it by setting $enableFortune to true and setting the correct path to fortune.
