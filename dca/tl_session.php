<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 07.01.2018
 * Time: 17:59
 */

$GLOBALS['TL_DCA']['tl_session']['fields']['twoFactorAuthenticated'] = array
(
    'sql' => "char(1) NOT NULL default ''",
);