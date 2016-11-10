<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   TwoFactorAuthentication
 * @author    Marko Cupic m.cupic@gmx.ch
 * @license   GNU/LGLP
 * @copyright Marko Cupic 2016
 */


/**
 * Namespace
 */
namespace MCupic;


/**
 * Class TwoFactorAuthentication
 *
 * @copyright  Marko Cupic 2016
 * @author     Marko Cupic m.cupic@gmx.ch
 * @package    Devtools
 */
class TwoFactorAuthentication extends \System
{

    /**
     * Find the first active group with a published jumpToTwoFactor page
     *
     * @param string $arrIds An array of member group IDs
     *
     * @return \MemberGroupModel|null The model or null if there is no matching member group
     */
    public static function findFirstActiveWithJumpToTwoFactorByIds($arrIds)
    {
        if (!is_array($arrIds) || empty($arrIds))
        {
            return null;
        }


        $time = \Date::floorToMinute();
        $objDatabase = \Database::getInstance();
        $arrIds = array_map('intval', $arrIds);

        $objResult = $objDatabase->prepare("SELECT p.* FROM tl_member_group g LEFT JOIN tl_page p ON g.jumpToTwoFactor=p.id WHERE g.id IN(" . implode(',', $arrIds) . ") AND g.jumpToTwoFactor>0 AND g.redirectToTwoFactor='1' AND g.disable!='1' AND (g.start='' OR g.start<='$time') AND (g.stop='' OR g.stop>'" . ($time + 60) . "') AND p.published='1' AND (p.start='' OR p.start<='$time') AND (p.stop='' OR p.stop>'" . ($time + 60) . "') ORDER BY " . $objDatabase->findInSet('g.id', $arrIds))->limit(1)->execute();

        if ($objResult->numRows < 1)
        {
            return null;
        }

        return \PageModel::findByPk($objResult->id);
    }

    /**
     * @param $strContent
     * @param $strTemplate
     * @return mixed
     */
    public function deleteExpiredLoginSets($strContent, $strTemplate)
    {
        $expirationTime = $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
        \Database::getInstance()->prepare('DELETE FROM tl_two_factor_authentication WHERE expiresOn<?')->execute(time() - $expirationTime);
        return $strContent;
    }


    /**
     * @return bool
     */
    public static function isLoggedIn()
    {

        if (!FE_USER_LOGGED_IN)
        {
            return false;
        }

        $objMember = \FrontendUser::getInstance();
        if ($objMember === null)
        {
            return false;
        }


        $strUa = 'N/A';
        $strIp = '127.0.0.1';

        if (\Environment::get('httpUserAgent'))
        {
            $strUa = \Environment::get('httpUserAgent');
        }
        if (\Environment::get('remoteAddr'))
        {
            $strIp = \Environment::get('ip');
        }
        // token is valid for one week. After the user has to renew the token.
        $expirationTime = $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
        $objSet = \Database::getInstance()->prepare("SELECT * FROM tl_two_factor_authentication WHERE pid=? AND ip=? AND browser=? AND expiresOn > ? AND activated=?")->limit(1)->execute($objMember->id, $strIp, $strUa, time() - $expirationTime, '1');
        if ($objSet->numRows)
        {
            $objTFAM = \TwoFactorAuthenticationModel::findByPk($objSet->id);
            if ($objTFAM !== null)
            {
                $objTFAM->expiresOn = time() + $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
                $objTFAM->save();
            }
            return true;
        }

        return false;

    }

    /**
     * @return bool
     */
    public static function hasValidCode()
    {

        if (!FE_USER_LOGGED_IN)
        {
            return false;
        }

        $objMember = \FrontendUser::getInstance();
        if ($objMember === null)
        {
            return false;
        }

        $strUa = 'N/A';
        $strIp = '127.0.0.1';

        if (\Environment::get('httpUserAgent'))
        {
            $strUa = \Environment::get('httpUserAgent');
        }
        if (\Environment::get('remoteAddr'))
        {
            $strIp = \Environment::get('ip');
        }
        // token is valid for 10 min. After the user has to generate a new token.
        $objSet = \Database::getInstance()->prepare("SELECT * FROM tl_two_factor_authentication WHERE pid=? AND ip=? AND browser=? AND tstamp > ? AND activated=?")->limit(1)->execute($objMember->id, $strIp, $strUa, time() - 600, '');
        if ($objSet->numRows)
        {
            return true;
        }

        return false;

    }


    /**
     * @param $strContent
     * @param $strTemplate
     * @return mixed
     */
    public function parseFrontendTemplate($strContent, $strTemplate)
    {
        if (FE_USER_LOGGED_IN)
        {
            if (!self::isLoggedIn())
            {
                $objMember = \FrontendUser::getInstance();

                if ($objMember !== null)
                {
                    $arrGroups = deserialize($objMember->groups);

                    if (!empty($arrGroups) && is_array($arrGroups))
                    {
                        $objGroupPage = self::findFirstActiveWithJumpToTwoFactorByIds($arrGroups);

                        if ($objGroupPage !== null)
                        {
                            $strRedirect = \Controller::generateFrontendUrl($objGroupPage->row(), null, null, true);
                            if ($_SERVER['REQUEST_URI'] != '/' . $strRedirect && $_SERVER['REDIRECT_URL'] != '/' . $strRedirect)
                            {
                                \Controller::redirect($strRedirect);
                            }
                        }
                    }
                }
            }
        }
        return $strContent;
    }

    /**
     * @param $strEmail
     * @return string
     */
    function anonymizeEmail($strEmail)
    {
        $arrEmail = explode('@', $strEmail);
        $strEnd = substr($arrEmail[0], 2);
        $strStart = substr($arrEmail[0], 0, 2);
        $strEnd = preg_replace("/(.)/", "*", $strEnd);
        if (strlen($arrEmail[0]) < 3)
        {
            return preg_replace("/(.)/", "*", $arrEmail[0]) . '@' . $arrEmail[1];
        }
        else
        {
            return $strStart . $strEnd . '@' . $arrEmail[1];
        }
    }
}

