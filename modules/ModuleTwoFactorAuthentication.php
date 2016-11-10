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
    protected $objMember = null;

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
            // Redirect to the ROOT-Page
            \Controller::redirect('');
        }


        $objMember = \FrontendUser::getInstance();
        if ($objMember === null)
        {
            throw new \Exception("User with ID " . $objMember->id . " doesn't exist.");
        }

        // Set the member object
        $this->objMember = $objMember;


        $arrGroups = deserialize($this->objMember->groups);
        if (!empty($arrGroups) && is_array($arrGroups))
        {
            $objGroupPage = TwoFactorAuthentication::findFirstActiveWithJumpToTwoFactorByIds($arrGroups);

            if ($objGroupPage !== null)
            {
                $blnRequiresTwoFactorAuthentication = true;
            }
        }

        // User doesn't require two factor authentication
        if ($blnRequiresTwoFactorAuthentication === false)
        {
            // Redirect to the ROOT-Page
            \Controller::redirect('');
        }


        // Set case
        if (TwoFactorAuthentication::isLoggedIn())
        {
            unset($_SESSION['TFA']['CASE_ENTER_CODE']);
            $this->case = 'caseLoggedIn';

            // Search for a jumpTo-page
            $arrGroups = deserialize($this->objMember->groups);
            if (!empty($arrGroups) && is_array($arrGroups))
            {
                $objGroupPage = \MemberGroupModel::findFirstActiveWithJumpToByIds($arrGroups);
                if ($objGroupPage !== null)
                {
                    if ($objPage->alias != $objGroupPage->alias)
                    {
                        $this->jumpToOrReload($objGroupPage->row());
                    }
                }
            }
        }
        elseif ($_SESSION['TFA']['CASE_ENTER_CODE'])
        {
            // Check if there is a valid & not already activated code
            if (TwoFactorAuthentication::hasValidCode())
            {
                $this->case = 'caseEnterCode';
            }
            else
            {
                $this->case = 'caseEnterEmail';
                unset($_SESSION['TFA']['CASE_ENTER_CODE']);
                $_SESSION['TFA']['ERROR'] = $GLOBALS['TL_LANG']['TFA']['noCodeFound'];
            }
        }
        else
        {
            $this->case = 'caseEnterEmail';
        }


        // Enter E-Mail
        if (\Input::post('FORM_SUBMIT') == 'tl_two_factor_authentification_enter_email')
        {
            // Check whether username and password are set
            if (empty($_POST['email']))
            {
                $_SESSION['TFA']['ERROR'] = $GLOBALS['TL_LANG']['TFA']['enterValidEmail'];
                $this->reload();
            }

            if (strtolower(\Input::post('email')) == strtolower($this->objMember->email))
            {

                $_SESSION['TFA']['CASE_ENTER_CODE'] = true;
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
                $objModel = new \TwoFactorAuthenticationModel();
                $objModel->pid = $this->objMember->id;
                $objModel->ip = $strIp;
                $objModel->browser = $strUa;
                $verificationEmailToken = rand(111111, 999999);
                $objModel->verification_email_token = md5($verificationEmailToken);
                $objModel->tstamp = time();
                $objModel->expiresOn = time() + $GLOBALS['CONFIG']['TwoFactorAuthentication']['expirationTime'];
                $objModel->save();

                // Send Email
                $email = new \Email();
                $email->subject = 'Sicherheitscode für das "' . \Environment::get('host') . '" Konto';
                // Set the admin e-mail as "from" address
                $email->from = $GLOBALS['TL_ADMIN_EMAIL'];
                $email->fromName = 'Administrator';
                $replyTo = '"' . 'Administrator' . '" <' . $GLOBALS['TL_ADMIN_EMAIL'] . '>';
                $email->replyTo($replyTo);
                $objEmailTemplate = new \FrontendTemplate('two_factor_authentication_email_body');
                $objEmailTemplate->host = \Environment::get('host');
                $objEmailTemplate->member = $this->objMember;
                $objEmailTemplate->code = $verificationEmailToken;
                $emailBody = $objEmailTemplate->parse();
                //$email->text = \StringUtil::decodeEntities(trim($emailBody));
                $email->text = \String::decodeEntities(trim($emailBody));

                $email->sendTo($this->objMember->email);

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
                $objSet = \Database::getInstance()->prepare('SELECT * FROM tl_two_factor_authentication WHERE pid=? AND verification_email_token = ? AND activated = ? AND tstamp > ?')->limit(1)->execute($this->objMember->id, md5(\Input::post('verification_email_token')), '', time() - 600);
                if ($objSet->numRows)
                {
                    $objTFAM = \TwoFactorAuthenticationModel::findByPk($objSet->id);
                    if ($objTFAM !== null)
                    {
                        $objTFAM->activated = '1';
                        $objTFAM->save();
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
            $this->Template->emailHint = TwoFactorAuthentication::anonymizeEmail($this->objMember->email);
            $this->Template->caseEnterEmail = true;
            $this->Template->formSubmit = 'tl_two_factor_authentification_enter_email';
            $this->Template->labelYourEmailAdress = $GLOBALS['TL_LANG']['TFA']['labelYourEmailAdress'];
            $this->Template->slabel = specialchars($GLOBALS['TL_LANG']['TFA']['slabelEnterEmail']);
        }
        if ($this->case == 'caseEnterCode')
        {
            $this->Template->action = ampersand(\Environment::get('indexFreeRequest'));
            if ($this->errorMsg)
            {
                $this->Template->error = $this->errorMsg;
            }
            $this->Template->caseEnterCode = true;
            $this->Template->email = $this->objMember->email;
            $this->Template->labelTwoFactorAuthenticationCode = $GLOBALS['TL_LANG']['TFA']['labelTwoFactorAuthenticationCode'];
            $this->Template->formSubmit = 'tl_two_factor_authentification_enter_code';
            $this->Template->slabel = specialchars($GLOBALS['TL_LANG']['TFA']['slabelEnterCode']);
        }
    }
}
