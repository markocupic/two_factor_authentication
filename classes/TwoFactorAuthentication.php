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
     * @return string
     */
    public static function getBrowserFingerprint()
    {

        $client_ip = \Environment::get('ip');
        $accept = $_SERVER['HTTP_ACCEPT'];
        $useragent = $_SERVER['HTTP_USER_AGENT'];
        $charset = $_SERVER['HTTP_ACCEPT_CHARSET'];
        $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'];
        $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $data = '';
        $data .= $client_ip;
        $data .= $useragent;
        $data .= $accept;
        $data .= $charset;
        $data .= $encoding;
        $data .= $language;

        /* Apply SHA256 hash to the browser fingerprint */
        $hash = hash('sha256', $data);

        return $hash;

    }



    /**
     * @param \FrontendUser $objUser
     */
    public function deleteExpiredLoginSets(\FrontendUser $objUser)
    {
        $expirationTime = $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
        \Database::getInstance()->prepare('DELETE FROM tl_two_factor_authentication WHERE expiresOn<?')->execute(time() - $expirationTime);
    }



    /**
     * @param \FrontendUser $objUser
     */
    public function authenticate(\FrontendUser $objUser)
    {
        if (FE_USER_LOGGED_IN)
        {

            if (self::isLoggedIn())
            {
                // User is logged in by two factor authentication
                // Return everything is ok.
                return;
            }


            $arrGroupsUserBelongsTo = deserialize($objUser->groups);
            if (empty($arrGroupsUserBelongsTo) || !is_array($arrGroupsUserBelongsTo))
            {
                // User is not assigned to any group
                // Return everything is ok.
                return;
            }
            else
            {
                $objPage = static::findFirstActiveWithJumpToTwoFactorByIds($arrGroupsUserBelongsTo);
                if ($objPage === null)
                {
                    // Two factor authentication is not used for this user
                    // Return everything is ok.
                    return;
                }
            }

            // Check if there is not expired datarecord in tl_two_factor_authentication
            // that fits to the current user and his browser fingerprint
            $strIp = \Environment::get('ip');
            $strCookie = 'FE_USER_AUTH';
            $strHash = sha1(session_id() . (!\Config::get('disableIpCheck') ? $strIp : '') . $strCookie);
            $browserFingerprint = static::getBrowserFingerprint();

            // Token is valid for 1 week. After this the user has to renew the token.
            $expirationTime = $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
            $objSet = \Database::getInstance()->prepare("SELECT * FROM tl_two_factor_authentication WHERE pid=? AND browserFingerprint=? AND expiresOn > ? AND activated=?")->limit(1)->execute($objUser->id, $browserFingerprint, time() - $expirationTime, '1');
            if ($objSet->numRows)
            {
                $objTFAM = \TwoFactorAuthenticationModel::findByPk($objSet->id);
                if ($objTFAM !== null)
                {
                    $objTFAM->expiresOn = time() + $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
                    $objTFAM->save();

                    // Update Session
                    \Database::getInstance()->prepare("UPDATE tl_session SET twoFactorAuthenticated=? WHERE hash=?")
                        ->execute('1', $strHash);

                    // Return everything is ok.
                    return;
                }
            }



            // Authentication failed
            // User will be redirected to the two factor authentication form
            // where he can request a login token, that will be sent to his email address
            // If he enters the correct token the login process will succeed
            $strRedirect = \Controller::generateFrontendUrl($objPage->row(), null, null, true);
            // Prevent endless redirect
            if (\Environment::get('indexFreeRequest') != $strRedirect)
            {
                // Redirect to the target page where user can enter his email address and then enter the token
                \Controller::redirect($strRedirect);
            }
        }
    }



    /**
     * Find the first active group with a published jumpToTwoFactor page
     *
     * @param string $arrIds An array of ids of member groups the user belongs to
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
     * @return bool
     */
    public static function isLoggedIn()
    {

        if (!FE_USER_LOGGED_IN)
        {
            return false;
        }

        $objUser = \FrontendUser::getInstance();
        if ($objUser === null)
        {
            return false;
        }

        $strIp = \Environment::get('ip');
        $strCookie = 'FE_USER_AUTH';
        $strHash = sha1(session_id() . (!\Config::get('disableIpCheck') ? $strIp : '') . $strCookie);

        $objSession = \Database::getInstance()->prepare("SELECT * FROM tl_session WHERE hash=? AND twoFactorAuthenticated=?")
            ->execute($strHash, '1');

        // Try to find the session in the database
        if ($objSession->numRows)
        {
            return true;
        }

        return false;

    }

}

