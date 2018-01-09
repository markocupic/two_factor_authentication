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


$GLOBALS['TL_DCA']['tl_session']['fields']['twoFactorAuthenticated'] = array
(
    'sql' => "char(1) NOT NULL default ''",
);