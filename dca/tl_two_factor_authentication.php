<?php
/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Table tl_log
 */
$GLOBALS['TL_DCA']['tl_two_factor_authentication'] = array
(

// Config
    'config' => array
    (
        'dataContainer' => 'Table',
        'ptable' => 'tl_member',
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary',
                'pid' => 'index'
            )
        )
    ),
    // Fields
    'fields' => array
    (
        'id' => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'pid' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'tstamp' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_log']['tstamp'],
            'filter' => true,
            'sorting' => true,
            'flag' => 6,
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'expiresOn' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_log']['expiresOn'],
            'filter' => true,
            'sorting' => true,
            'flag' => 6,
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'verification_email_token' => array
        (
            'sql' => "varchar(32) NOT NULL default ''"
        ),
        'ip' => array
        (
            'sql' => "varchar(64) NOT NULL default ''"
        ),
        'browser' => array
        (
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'activated' => array
        (
            'sql' => "char(1) NOT NULL default ''"
        )
    )
);
