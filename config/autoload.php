<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
    'Markocupic',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
    // Modules
    'Markocupic\ModuleTwoFactorAuthentication' => 'system/modules/two_factor_authentication/modules/ModuleTwoFactorAuthentication.php',

    // Classes
    'Markocupic\TwoFactorAuthentication'       => 'system/modules/two_factor_authentication/classes/TwoFactorAuthentication.php',

    // Models
    'Contao\TwoFactorAuthenticationModel'      => 'system/modules/two_factor_authentication/models/TwoFactorAuthenticationModel.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
    'mod_two_factor_authentication'        => 'system/modules/two_factor_authentication/templates',
    'two_factor_authentication_email_body' => 'system/modules/two_factor_authentication/templates',
));
