<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2018 Leo Feyer
 *
 * @package     Markocupic
 * @author      Marko Cupic m.cupic@gmx.ch
 * @link        https://github.com/markocupic
 * @license     GNU/LGLP
 * @copyright   Marko Cupic 2018
 */

/**
 * Front end modules
 */
$GLOBALS['FE_MOD']['user']['twoFactorAuthentication'] = 'Markocupic\ModuleTwoFactorAuthentication';

/**
 * Hooks
 */
if (TL_MODE === 'FE')
{
    $GLOBALS['TL_HOOKS']['postAuthenticate'][] = array('Markocupic\TwoFactorAuthentication', 'authenticate');
    $GLOBALS['TL_HOOKS']['postAuthenticate'][] = array('Markocupic\TwoFactorAuthentication', 'deleteExpiredLoginSets');
}


/**
 * Config
 */

// Set expiration time to 1 month
\Contao\Config::set('twoFactorAuthExpirationTime', 30 * 24 * 60 * 60);


