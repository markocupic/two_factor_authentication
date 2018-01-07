<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace MCupic;

/**
 * Front end module "Two Factor Authentication".
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ModuleTwoFactorAuthentication extends \Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_two_factor_authentication';

    /**
     * @var null
     */
    protected $error = null;

    /**
     * @var null
     */
    protected $objUser = null;

    /**
     * @var
     */
    protected $strIp;

    /**
     * @var
     */
    protected $strCookie;

    /**
     * @var
     */
    protected $strHash;

    /**
     * @var null
     */
    protected $case = null;

    /**
     * @return string
     * @throws \Exception
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var \BackendTemplate|object $objTemplate */
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['twoFactorAuthentication'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        global $objPage;


        if (!FE_USER_LOGGED_IN)
        {
            // All ok. Do not show the form
            return '';
        }


        $objMember = \FrontendUser::getInstance();
        if ($objMember === null)
        {
            throw new \Exception("User with ID " . $objMember->id . " doesn't exist.");
        }


        // Set ip
        $this->strIp = \Environment::get('ip');

        // Set strCookie
        $this->strCookie = 'FE_USER_AUTH';

        // Set hash
        $this->strHash = sha1(session_id() . (!\Config::get('disableIpCheck') ? $this->strIp : '') . $this->strCookie);

        // Set the user object
        $this->objUser = $objMember;


        $arrGroups = deserialize($this->objUser->groups);
        if (!empty($arrGroups) && is_array($arrGroups))
        {
            $objGroupPage = TwoFactorAuthentication::findFirstActiveWithJumpToTwoFactorByIds($arrGroups);

            if ($objGroupPage === null)
            {
                // User belongs not to a two factor authentication group
                // All ok. Do not show the form
                return '';
            }
        }


        // Set case
        if (TwoFactorAuthentication::isLoggedIn())
        {
            if(!isset($_SESSION['TFA']))
            {
                // If user lands on this page after he has logged in
                // return empty string
                return '';
            }

            // If user lands on this page just after he has logged in
            // then search for a jumpTo page
            unset($_SESSION['TFA'];
            $this->case = 'caseLoggedIn';

            // Search for a jumpTo-page
            $arrGroups = deserialize($this->objUser->groups);
            if (!empty($arrGroups) && is_array($arrGroups))
            {
                $objGroupPage = \MemberGroupModel::findFirstActiveWithJumpToByIds($arrGroups);
                if ($objGroupPage !== null)
                {
                    if ($objPage->alias != $objGroupPage->alias)
                    {
                        // Everything fine, user has logged in
                        // Jump to or reload
                        $this->jumpToOrReload($objGroupPage->row());
                    }
                }
            }
        }
        elseif ($_SESSION['TFA']['CASE_ENTER_CODE'])
        {
            // Check if there is a valid & not already activated code
            if ($this->hasValidCode())
            {
                $this->case = 'caseEnterCode';
            }
            else
            {
                // No valid token found in the database
                // Enter email address again
                $this->case = 'caseEnterEmail';
                unset($_SESSION['TFA']['CASE_ENTER_CODE']);
                $_SESSION['TFA']['ERROR'] = $GLOBALS['TL_LANG']['TFA']['noCodeFound'];
            }
        }
        else
        {
            $this->case = 'caseEnterEmail';
        }


        // Enter email
        if (\Input::post('FORM_SUBMIT') == 'tl_two_factor_authentification_enter_email')
        {
            // Check whether username and password are set
            if (empty($_POST['email']))
            {
                $_SESSION['TFA']['ERROR'] = $GLOBALS['TL_LANG']['TFA']['enterValidEmail'];
                $this->reload();
            }


            if (trim(strtolower(\Input::post('email'))) == trim(strtolower($this->objUser->email)))
            {

                $_SESSION['TFA']['CASE_ENTER_CODE'] = true;

                $browserFingerprint = TwoFactorAuthentication::getBrowserFingerprint();

                $objModel = new \TwoFactorAuthenticationModel();
                $objModel->pid = $this->objUser->id;
                $objModel->browserFingerprint = $browserFingerprint;
                $verificationEmailToken = rand(111111, 999999);
                $objModel->verification_email_token = md5($verificationEmailToken);
                $objModel->tstamp = time();
                $objModel->expiresOn = time() + $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
                $objModel->save();

                // Send email
                $email = new \Email();
                $email->subject = 'Sicherheitscode für das "' . \Environment::get('host') . '" Konto';
                // Set the admin e-mail as "from" address
                $email->from = $GLOBALS['TL_ADMIN_EMAIL'];
                $email->fromName = 'Administrator';
                $replyTo = '"' . 'Administrator' . '" <' . $GLOBALS['TL_ADMIN_EMAIL'] . '>';
                $email->replyTo($replyTo);
                $objEmailTemplate = new \FrontendTemplate('two_factor_authentication_email_body');
                $objEmailTemplate->host = \Environment::get('host');
                $objEmailTemplate->member = $this->objUser;
                $objEmailTemplate->code = $verificationEmailToken;
                $emailBody = $objEmailTemplate->parse();

                $email->text = \StringUtil::decodeEntities(trim($emailBody));

                $email->sendTo($this->objUser->email);

                $this->reload();
            }
            else
            {
                $_SESSION['TFA']['ERROR'] = $GLOBALS['TL_LANG']['TFA']['enterValidEmail'];
                $this->reload();
            }
        }




        // Enter Code
        if (\Input::post('FORM_SUBMIT') == 'tl_two_factor_authentification_enter_code')
        {
            // Check email token
            if (!empty($_POST['verification_email_token']))
            {
                $browserFingerprint = TwoFactorAuthentication::getBrowserFingerprint();
                $objSet = \Database::getInstance()->prepare('SELECT * FROM tl_two_factor_authentication WHERE pid=? AND browserFingerprint=? AND verification_email_token = ? AND activated = ? AND tstamp > ?')->limit(1)->execute($this->objUser->id, $browserFingerprint, md5(\Input::post('verification_email_token')), '', time() - 600);
                if ($objSet->numRows)
                {
                    $objTFAM = \TwoFactorAuthenticationModel::findByPk($objSet->id);
                    if ($objTFAM !== null)
                    {
                        $objTFAM->activated = '1';
                        $objTFAM->save();

                        // Update Session
                        \Database::getInstance()->prepare("UPDATE tl_session SET twoFactorAuthenticated=? WHERE hash=?")
                            ->execute('1', $this->strHash);

                        unset($_SESSION['TFA']['CASE_ENTER_CODE']);

                        $this->reload();
                    }
                }
                $_SESSION['TFA']['ERROR'] = $GLOBALS['TL_LANG']['TFA']['invalidCode'];
                $this->reload();
            }
        }




        // Unset $_SESSION['TFA']['ERROR'] and store it in $this->errorMsg
        if (isset($_SESSION['TFA']['ERROR']))
        {
            $this->errorMsg = $_SESSION['TFA']['ERROR'];
            unset($_SESSION['TFA']['ERROR']);
        }


        return parent::generate();
    }



    /**
     * Generate the module
     */
    protected function compile()
    {
        if ($this->case == 'caseLoggedIn')
        {
            $this->Template->caseLoggedIn = true;
        }
        elseif ($this->case == 'caseEnterEmail')
        {
            $this->Template->action = ampersand(\Environment::get('indexFreeRequest'));
            if ($this->errorMsg)
            {
                $this->Template->error = $this->errorMsg;
            }
            $this->Template->emailHint = $this->anonymizeEmail($this->objUser->email);
            $this->Template->caseEnterEmail = true;
            $this->Template->formSubmit = 'tl_two_factor_authentification_enter_email';
            $this->Template->labelYourEmailAdress = $GLOBALS['TL_LANG']['TFA']['labelYourEmailAdress'];
            $this->Template->slabel = specialchars($GLOBALS['TL_LANG']['TFA']['slabelEnterEmail']);
        }
        elseif ($this->case == 'caseEnterCode')
        {
            $this->Template->action = ampersand(\Environment::get('indexFreeRequest'));
            if ($this->errorMsg)
            {
                $this->Template->error = $this->errorMsg;
            }
            $this->Template->caseEnterCode = true;
            $this->Template->email = $this->objUser->email;
            $this->Template->labelTwoFactorAuthenticationCode = $GLOBALS['TL_LANG']['TFA']['labelTwoFactorAuthenticationCode'];
            $this->Template->formSubmit = 'tl_two_factor_authentification_enter_code';
            $this->Template->slabel = specialchars($GLOBALS['TL_LANG']['TFA']['slabelEnterCode']);
        }
    }



    /**
     * @param $strEmail
     * @return string
     */
    protected function anonymizeEmail($strEmail)
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



    /**
     * @return bool
     */
    protected function hasValidCode()
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

        $browserFingerprint = TwoFactorAuthentication::getBrowserFingerprint();

        // token is valid for 10 min. After the user has to generate a new token.
        $objSet = \Database::getInstance()->prepare("SELECT * FROM tl_two_factor_authentication WHERE pid=? AND browserFingerprint=? AND tstamp > ? AND activated=?")->limit(1)->execute($objMember->id, $browserFingerprint, time() - 600, '');
        if ($objSet->numRows)
        {
            return true;
        }

        return false;

    }
}
