<?php

/**
 * Front end modules
 */
$GLOBALS['FE_MOD']['user']['twoFactorAuthentication'] = 'MCupic\ModuleTwoFactorAuthentication';

/**
 * Hooks
 */
if(TL_MODE === 'FE')
{
    $GLOBALS['TL_HOOKS']['postAuthenticate'][] = array('MCupic\TwoFactorAuthentication', 'authenticate');
    $GLOBALS['TL_HOOKS']['postAuthenticate'][] = array('MCupic\TwoFactorAuthentication', 'deleteExpiredLoginSets');
}

$GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'] = 30*24*60*60;


