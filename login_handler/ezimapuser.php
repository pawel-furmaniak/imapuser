<?php

/*

imapuser: imap login handler for eZ publish 

Copyright (C) 2010 Paweł Furmaniak (uszywieloryba@uszywieloryba.pl)

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:
	
The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

include_once( 'kernel/classes/datatypes/ezuser/ezuser.php' );
include_once( 'lib/ezutils/classes/ezini.php' );

class eZImapUser extends eZUser
{
    function eZSuperUser()
    {

    }

    static function &loginUser( $login, $password, $authenticationMatch = false )
    {
        
        #read configuration
        
        $SERVERS = array();
        
        $ini =& eZINI::instance( 'imapuser.ini' );
        $blocks = $ini->groups();
        
        foreach ($blocks as $key => $variables) {
            if ( preg_match( '/SERVER:(?P<server>.*)/', $key, $matches ) )
            {
                $server = $matches['server'];
                $SERVERS[$server] = array();
                $SERVERS[$server] = $variables;
            }
            
        }

        #var_dump($SERVERS);
        
        $IMAP_SERVERS = $ini->variable( 'UserSettings', 'IMAP_SERVERS' );
        $IMAP_PORT = $ini->variable( 'UserSettings', 'IMAP_PORT' );
        $USER_GROUP_ID = $ini->variable( 'UserSettings', 'USER_GROUP_ID' );
        
        $authenticated = false;
        
        #loop over servers and try to authenticate
        foreach( $SERVERS as $server => $params )
        { 
            
            $PORT = $params['PORT'];
            $SSL = $params['SSL'];
            $USER_GROUP_ID = $params['USER_GROUP_ID'];
            $VALIDATE_CERTIFICATE = $params['VALIDATE_CERTIFICATE'];
            
            eZDebug::writeNotice( "Trying to authenticate $login against $server:$PORT", 'eZImapUser::loginUser' );
            
            $flags = '/imap';
            if ( $SSL == 'true' )
            {
                $flags .= '/ssl';
            }
            
            if ( $VALIDATE_CERTIFICATE == 'false' )
            {
                $flags .= '/novalidate-cert';
            }
            
            $identifier = '{'.$server.':'.$PORT.$flags.'}';
            
            #var_dump( $identifier );
            
            $conn = imap_open($identifier, $login, $password, NIL, 0);
            if ($conn == true)
            {
                eZDebug::writeNotice( "$login athenticated using $server:$PORT", 'eZImapUser::loginUser' );
                $authenticated = true;
                break;
            }

        }
        
        if ( $authenticated )
        {
            
            $user = eZUser::fetchByName( $login );
            $createNewUser = ( is_object( $user ) ) ? false : true;
            
            if ( $createNewUser )
            {
                
                #create user
                $ini = eZINI::instance();
                $userClassID = $ini->variable( "UserSettings", "UserClassID" );
                $userCreatorID = $ini->variable( "UserSettings", "UserCreatorID" );
                $defaultSectionID = $ini->variable( "UserSettings", "DefaultSectionID" );
                
                $class = eZContentClass::fetch( $userClassID );
                $contentObject = $class->instantiate( $userCreatorID, $defaultSectionID );
                $contentObject->store();

                $userID = $contentObjectID = $contentObject->attribute( 'id' );

                $version = $contentObject->version( 1 );
                $version->setAttribute( 'modified', time() );
                $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );
                $version->store();

                $user = eZImapUser::create( $userID );
                $user->setAttribute( 'login', $login );
                $user->setAttribute( 'email', $login . '@' . $server );
                
                #set unusable password
                $user->setAttribute( 'password_hash', "" );
                $user->setAttribute( 'password_hash_type', 0 );
                $user->store();
                
                #set group
                $newNodeAssignment = eZNodeAssignment::create( array( 'contentobject_id' => $contentObjectID,
                                                                      'contentobject_version' => 1,
                                                                      'parent_node' => $USER_GROUP_ID,
                                                                      'is_main' => 1 ) );
                $newNodeAssignment->store();
                
                $operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObjectID,
                                                                                         'version' => 1 ) );
                
                #overwrite default name, which is generated based on first name and second name which we don't have here
                $contentObject->setName( $login );
                $contentObject->setAttribute( 'published', time() );
                $contentObject->setAttribute( 'modified', time() );
                $contentObject->store();
            
            }
            
            eZUser::setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );
            
            return $user;
        }
        else
        {
            return false;
        }

        
    }
}

?>
