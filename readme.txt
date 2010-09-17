
Features
########

    - authenticates against multiple IMAP servers
    - logs in existing eZ publish user (matching based on login) or creates new user

Installation
############
    
    1. Make sure you have imap extension enabled in your php
       If it's Windows, just add 
       extension=php_imap.dll
       to your php.ini and put php_imap.dll (you can find it in php source release) in your php extension directory
    
    2. Copy imapuser directory to <eZpublish>/extension/
    
    2. Enable the extension (in site.ini.append.php or by using the admin interface)

    3. Modify your site.ini.append.php:

    [UserSettings]
    LoginHandler[]=imap

    4. Adjust the settings in /imapuser/settings/imapuser.ini.php

Changelog
#########

    15.09.2010 V. 1.0
    - Initial release, tested on eZ publish 4.3
