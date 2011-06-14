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

Also it is only tested with Nagios3, it should also work with Nagios2.
Feel free to try your luck with Nagios2 and let me know, if it's working or not.

For installation you need a Webserver running PHP 5.2 or newer.

Access to Nagios status.dat is required.
I'm running it on the Nagios server itself.

Installation
------------

1. Place all files in the document root of your web server.
2. Edit index.php and change the path to your status.dat if required. It's also
   possible to set a different refresh interval.
3. Done :-)

Customization
-------------

## CSS

If you want to change colors, you can place a file called 'custom.css' in the
directory where Naglite3 is placed.

## Refresh interval

You can set the refresh interval (in seconds) through a GET parameter:
http://your-host/Naglite3/?refresh=100

## Fortune

To make systems monitoring more fun than it already is, I've added fortune output.
You can enable it by setting $enableFortune to true and setting the correct path to fortune.
