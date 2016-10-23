<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */




// Palettes, Subpalettes, Selector
$GLOBALS['TL_DCA']['tl_member_group']['subpalettes']['redirectToTwoFactor'] = 'jumpToTwoFactor';
$GLOBALS['TL_DCA']['tl_member_group']['palettes']['__selector__'][] = 'redirectToTwoFactor';
$GLOBALS['TL_DCA']['tl_member_group']['palettes']['default'] = str_replace(',redirect', ',redirectToTwoFactor,redirect',$GLOBALS['TL_DCA']['tl_member_group']['palettes']['default']);

// Fields
$GLOBALS['TL_DCA']['tl_member_group']['fields']['redirectToTwoFactor'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_member_group']['redirectToTwoFactor'],
    'exclude'                 => true,
    'inputType'               => 'checkbox',
    'eval'                    => array('submitOnChange'=>true),
    'sql'                     => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_member_group']['fields']['jumpToTwoFactor'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_member_group']['jumpToTwoFactor'],
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'foreignKey'              => 'tl_page.title',
    'eval'                    => array('mandatory'=>true, 'fieldType'=>'radio', 'tl_class'=>'clr'),
    'sql'                     => "int(10) unsigned NOT NULL default '0'",
    'relation'                => array('type'=>'hasOne', 'load'=>'eager')
);
