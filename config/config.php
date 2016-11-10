<?php

/**
 * Front end modules
 */
$GLOBALS['FE_MOD']['user']['twoFactorAuthentication'] = 'MCupic\ModuleTwoFactorAuthentication';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['parseFrontendTemplate'][] = array('MCupic\TwoFactorAuthentication', 'parseFrontendTemplate');
$GLOBALS['TL_HOOKS']['parseFrontendTemplate'][] = array('MCupic\TwoFactorAuthentication', 'deleteExpiredLoginSets');
$GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'] = 30*24*60*60;

